<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Calculators\FileCalculator;
use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Calculators\LimitsAndAveragesCalculator;
use PhpCodeArch\Calculators\ProjectCalculator;
use PhpCodeArch\Calculators\VariablesCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\GodClassPrediction;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\PredictionService;
use PhpCodeArch\Predictions\TooComplexPrediction;
use PhpCodeArch\Predictions\TooDependentPrediction;
use PhpCodeArch\Predictions\TooLongPrediction;
use PhpCodeArch\Predictions\TooMuchHtmlPrediction;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\ReportFactory;
use PhpCodeArch\Report\ReportTypeNotSupported;
use PhpCodeArch\Repository\MemoryRepository;
use PhpCodeArch\Repository\RepositoryInterface;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final readonly class Application
{
    const string VERSION = '0.3.1';

    /**
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     * @throws ReportTypeNotSupported
     */
    public function run(array $argv): void
    {
        $config = $this->createConfig($argv);
        $fileList = $this->createFileList($config);

        $output = new CliOutput();

        $metricsController = $this->createMetricController($config);
        $repository = $this->setRepository('memory', $metricsController);
        $this->createAndRunAnalyzer($config, $repository, $fileList, $output);

        $this->runCalculators($repository, $output);

        $problems = $this->runPredictors($repository, $output);
        $this->setProblems($repository, $problems);

        $twigLoader = new FilesystemLoader();
        $twig = new Environment($twigLoader, [
            'debug' => true,
        ]);
        $twig->addExtension(new DebugExtension());

        $dataProviderFactory = new DataProviderFactory($repository);

        $report = ReportFactory::create(
            $config,
            $dataProviderFactory,
            $twigLoader,
            $twig,
            $output
        );
        $report->generate();
    }

    /**
     * @throws MultipleConfigFilesException
     * @throws ConfigFileExtensionNotSupportedException
     */
    private function createConfig(array $argv): Config
    {
        try {
            $config = (new ArgumentParser())->parse($argv);
        } catch (ParamException $e) {
            echo PHP_EOL . "Error: {$e->getMessage()}";
            exit;
        }

        $config->set('runningDir', getcwd());

        $configFileFinder = new ConfigFileFinder($config);
        $configFileFinder->checkRunningDir();

        if (! $config->get('reportType')) {
            $config->set('reportType', 'html');
        }

        if (! $config->get('reportDir')) {
            $config->set('reportDir', realpath($config->get('runningDir') . '/tmp/report'));
        }

        if (! $config->get('packageSize')) {
            $config->set('packageSize', 2);
        }

        try {
            $config->validate();
        } catch (ConfigException $e) {
            echo PHP_EOL . "Error: {$e->getMessage()}";
            exit;
        }

        return $config;
    }

    private function createFileList(Config $config): FileList
    {
        $fileList = new FileList($config);
        $fileList->fetch();

        return $fileList;
    }

    private function createAndRunAnalyzer(Config $config, RepositoryInterface $repository, FileList $fileList, CliOutput $output): void
    {
        $analyzer = new Analyzer(
            $config,
            (new ParserFactory())->createForHostVersion(),
            new NodeTraverser(),
            $repository,
            $output);

        $analyzer->analyze($fileList);
    }

    /**
     * @param Config $config
     * @return MetricsController
     */
    private function createMetricController(Config $config): MetricsController
    {
        $metricsCollection = new MetricsContainer();
        $metricsController = new MetricsController($metricsCollection);
        $metricsController->registerMetricTypes();
        $metricsController->createProjectMetricsCollection($config->get('files'));

        return $metricsController;
    }

    /**
     * @param RepositoryInterface $repository
     * @param CliOutput $output
     * @return void
     */
    private function runCalculators(RepositoryInterface $repository, CliOutput $output): void
    {
        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($repository);

        $calculatorService = new CalculatorService([
            new FileCalculator($repository),
            new VariablesCalculator($repository),
            new CouplingCalculator($repository, $packageIACalculator),
            new ProjectCalculator($repository),
            new LimitsAndAveragesCalculator($repository),
        ], $repository, $output);

        $calculatorService->run();
    }

    /**
     * @param RepositoryInterface $repository
     * @param CliOutput $output
     * @return array
     */
    private function runPredictors(RepositoryInterface $repository, CliOutput $output): array
    {
        $predictions = new PredictionService([
            new TooLongPrediction(),
            new GodClassPrediction(),
            new TooComplexPrediction(),
            new TooDependentPrediction(),
            new TooMuchHtmlPrediction(),
        ], $repository, $output);
        $predictions->predict();

        return $predictions->getProblemCount();
    }

    /**
     * @param RepositoryInterface $repository
     * @param array $problems
     * @return void
     */
    private function setProblems(RepositoryInterface $repository, array $problems): void
    {
        $repository->saveMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallInformationCount' => $problems[PredictionInterface::INFO],
                'overallWarningCount' => $problems[PredictionInterface::WARNING],
                'overallErrorCount' => $problems[PredictionInterface::ERROR],
            ]
        );
    }

    private function setRepository(string $type, MetricsController $metricsController): RepositoryInterface
    {
        switch($type) {
            default:
                return new MemoryRepository($metricsController);
        }
    }
}
