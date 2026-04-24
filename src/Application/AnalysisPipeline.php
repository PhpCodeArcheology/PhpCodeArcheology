<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Git\GitAnalyzer;
use PhpCodeArch\Metrics\Controller\MetricsReaderInterface;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Controller\MetricsWriterInterface;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\PredictionService;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

final class AnalysisPipeline implements AnalysisPipelineInterface
{
    public function __construct(
        private readonly ServiceFactory $factory = new ServiceFactory(),
    ) {
    }

    /**
     * Run full analysis pipeline: parse → git → calculators → predictors → debt.
     *
     * @return array{MetricsRegistryInterface, MetricsReaderInterface, MetricsWriterInterface, array<int, int>}
     */
    public function runAnalysis(Config $config, CliOutput $output): array
    {
        $fileList = $this->createFileList($config);
        [$registry, $reader, $writer] = $this->bootstrapTriple($config);
        $this->createAndRunAnalyzer($config, $registry, $reader, $writer, $fileList, $output);

        $gitConfigRaw = $config->get('git');
        $gitConfig = is_array($gitConfigRaw) ? $gitConfigRaw : [];
        if ($gitConfig['enable'] ?? true) {
            $gitAnalyzer = new GitAnalyzer($config, $writer, $registry, $output);
            $gitAnalyzer->analyze();
        }

        // Store framework detection result in project metrics
        $frameworkResult = $config->getFrameworkDetection();
        if (null !== $frameworkResult) {
            $writer->setMetricValues(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                ['detectedFrameworks' => $frameworkResult->getSummary()]
            );
        }

        $calculatorRegistry = $this->factory->createCalculatorRegistry($reader, $writer, $registry);
        $calculatorService = new CalculatorService($calculatorRegistry->getMainCalculators($config), $registry, $output);
        $calculatorService->run();

        $predictionRegistry = $this->factory->createPredictionRegistry();
        $predictionService = new PredictionService(
            $predictionRegistry->getPredictions($config, $reader, $writer, $registry),
            $output,
        );
        $predictionService->predict();
        $problems = $predictionService->getProblemCount();

        $this->setProblems($writer, $problems);

        // These calculators must run after predictors (need problem counts)
        $postPredictionService = new CalculatorService($calculatorRegistry->getPostPredictionCalculators(), $registry, $output);
        $postPredictionService->run();

        return [$registry, $reader, $writer, $problems];
    }

    /**
     * @return array{MetricsRegistryInterface, MetricsReaderInterface, MetricsWriterInterface}
     */
    public function runQuickAnalysis(Config $config, CliOutput $output): array
    {
        $fileList = $this->createFileList($config);
        [$registry, $reader, $writer] = $this->bootstrapTriple($config);
        $this->createAndRunAnalyzer($config, $registry, $reader, $writer, $fileList, $output);

        $calculatorRegistry = $this->factory->createCalculatorRegistry($reader, $writer, $registry);
        $calculatorService = new CalculatorService($calculatorRegistry->getQuickCalculators(), $registry, $output);
        $calculatorService->run();

        return [$registry, $reader, $writer];
    }

    /** @param array<int, int> $problems */
    private function setProblems(MetricsWriterInterface $writer, array $problems): void
    {
        $writer->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallInformationCount' => $problems[PredictionInterface::INFO],
                'overallWarningCount' => $problems[PredictionInterface::WARNING],
                'overallErrorCount' => $problems[PredictionInterface::ERROR],
            ]
        );
    }

    private function createFileList(Config $config): FileList
    {
        $fileList = new FileList($config);
        $fileList->fetch();

        return $fileList;
    }

    /**
     * @return array{MetricsRegistryInterface, MetricsReaderInterface, MetricsWriterInterface}
     */
    private function bootstrapTriple(Config $config): array
    {
        [$registry, $reader, $writer] = $this->factory->createMetricsTriple();
        $registry->registerMetricTypes();
        $files = array_filter($config->getFiles(), 'is_string');
        $registry->createProjectMetricsCollection(array_values($files));

        return [$registry, $reader, $writer];
    }

    private function createAndRunAnalyzer(
        Config $config,
        MetricsRegistryInterface $registry,
        MetricsReaderInterface $reader,
        MetricsWriterInterface $writer,
        FileList $fileList,
        CliOutput $output,
    ): void {
        $phpConfigRaw = $config->get('php');
        $phpConfig = is_array($phpConfigRaw) ? $phpConfigRaw : [];
        $parser = isset($phpConfig['version']) && is_string($phpConfig['version'])
            ? (new ParserFactory())->createForVersion(PhpVersion::fromString($phpConfig['version']))
            : (new ParserFactory())->createForHostVersion();

        $analyzer = new Analyzer(
            $config,
            $parser,
            new NodeTraverser(),
            $registry,
            $reader,
            $writer,
            $output
        );

        $analyzer->analyze($fileList);
    }
}
