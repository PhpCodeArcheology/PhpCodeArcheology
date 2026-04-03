<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Git\GitAnalyzer;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\PredictionService;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;

final class AnalysisPipeline implements AnalysisPipelineInterface
{
    // NOTE: CalculatorRegistry requires MetricsController at construction (to wire calculators),
    // while MetricsController is created per-run inside runAnalysis()/runQuickAnalysis().
    // Both registries are therefore instantiated inline within each run method rather than
    // via constructor injection. Refactor if registries are redesigned to accept MetricsController
    // per-method instead of per-construction.

    public function __construct(
        private readonly ServiceFactory $factory = new ServiceFactory(),
    ) {
    }

    /**
     * Run full analysis pipeline: parse → git → calculators → predictors → debt.
     *
     * @return array{MetricsController, array<int, int>} [$metricsController, $problems]
     */
    public function runAnalysis(Config $config, CliOutput $output): array
    {
        $fileList = $this->createFileList($config);
        $metricsController = $this->createMetricController($config);
        $this->createAndRunAnalyzer($config, $metricsController, $fileList, $output);

        $gitConfigRaw = $config->get('git');
        $gitConfig = is_array($gitConfigRaw) ? $gitConfigRaw : [];
        if ($gitConfig['enable'] ?? true) {
            $gitAnalyzer = new GitAnalyzer($config, $metricsController, $output);
            $gitAnalyzer->analyze();
        }

        // Store framework detection result in project metrics
        $frameworkResult = $config->getFrameworkDetection();
        if (null !== $frameworkResult) {
            $metricsController->setMetricValues(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                ['detectedFrameworks' => $frameworkResult->getSummary()]
            );
        }

        $calculatorRegistry = $this->factory->createCalculatorRegistry($metricsController);
        $calculatorService = new CalculatorService($calculatorRegistry->getMainCalculators($config), $metricsController, $output);
        $calculatorService->run();

        $predictionRegistry = $this->factory->createPredictionRegistry();
        $predictionService = new PredictionService($predictionRegistry->getPredictions($config), $metricsController, $output);
        $predictionService->predict();
        $problems = $predictionService->getProblemCount();

        $this->setProblems($metricsController, $problems);

        // These calculators must run after predictors (need problem counts)
        $postPredictionService = new CalculatorService($calculatorRegistry->getPostPredictionCalculators(), $metricsController, $output);
        $postPredictionService->run();

        return [$metricsController, $problems];
    }

    public function runQuickAnalysis(Config $config, CliOutput $output): MetricsController
    {
        $fileList = $this->createFileList($config);
        $metricsController = $this->createMetricController($config);
        $this->createAndRunAnalyzer($config, $metricsController, $fileList, $output);

        $calculatorRegistry = $this->factory->createCalculatorRegistry($metricsController);
        $calculatorService = new CalculatorService($calculatorRegistry->getQuickCalculators(), $metricsController, $output);
        $calculatorService->run();

        return $metricsController;
    }

    /** @param array<int, int> $problems */
    private function setProblems(MetricsController $metricsController, array $problems): void
    {
        $metricsController->setMetricValues(
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

    private function createMetricController(Config $config): MetricsController
    {
        $metricsController = $this->factory->createMetricsController();
        $metricsController->registerMetricTypes();
        $files = array_filter($config->getFiles(), 'is_string');
        $metricsController->createProjectMetricsCollection(array_values($files));

        return $metricsController;
    }

    private function createAndRunAnalyzer(Config $config, MetricsController $metricsController, FileList $fileList, CliOutput $output): void
    {
        $phpConfigRaw = $config->get('php');
        $phpConfig = is_array($phpConfigRaw) ? $phpConfigRaw : [];
        $parser = isset($phpConfig['version']) && is_string($phpConfig['version'])
            ? (new ParserFactory())->createForVersion(PhpVersion::fromString($phpConfig['version']))
            : (new ParserFactory())->createForHostVersion();

        $analyzer = new Analyzer(
            $config,
            $parser,
            new NodeTraverser(),
            $metricsController,
            $output
        );

        $analyzer->analyze($fileList);
    }
}
