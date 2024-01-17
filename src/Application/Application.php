<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Calculators\FileCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Report\ReportTypeNotSupported;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

final readonly class Application
{
    const VERSION = '0.0.1';

    private MetricsController $metricsController;

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

        $this->createMetricController($config);
        $this->createAndRunAnalyzer($config, $fileList, $output);
        $this->runCalculators($output);
        /*

        $predictions = new PredictionService([
            new TooLongPrediction(),
            new GodClassPrediction(),
            new TooComplexPrediction(),
            new TooDependentPrediction(),
            new TooMuchHtmlPrediction(),
        ], $metricsCollection, $output);
        $predictions->predict();

        $problems = $predictions->getProblemCount();
        $projectMetrics->set('OverallInformationCount', $problems[PredictionInterface::INFO]);
        $projectMetrics->set('OverallWarningCount', $problems[PredictionInterface::WARNING]);
        $projectMetrics->set('OverallErrorCount', $problems[PredictionInterface::ERROR]);
        $metricsCollection->set('project', $projectMetrics);

        $splitter = new MetricsSplitter($metricsCollection, $output);
        $splitter->split();

        $reportData = new DataProviderFactory($metricsCollection, $this->metricsManager);

        $twigLoader = new FilesystemLoader();
        $twig = new Environment($twigLoader, [
            'debug' => true,
        ]);
        $twig->addExtension(new DebugExtension());

        $report = ReportFactory::create(
            $config->get('reportType'),
            $config,
            $reportData,
            $twigLoader,
            $twig,
            $output
        );
        $report->generate();
        */
    }

    /**
     * @throws MultipleConfigFilesException
     * @throws ConfigFileExtensionNotSupportedException
     */
    private function createConfig(array $argv): Config
    {
        $config = (new ArgumentParser())->parse($argv);
        $config->set('runningDir', getcwd());

        $configFileFinder = new ConfigFileFinder($config);
        $configFileFinder->checkRunningDir();

        if (! $config->get('reportType')) {
            $config->set('reportType', 'html');
        }

        if (! $config->get('reportDir')) {
            $config->set('reportDir', realpath($config->get('runningDir') . '/tmp/report'));
        }

        try {
            $config->validate();
        } catch (ConfigException $e) {
            echo "Fehler: {$e->getMessage()}";
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

    private function createAndRunAnalyzer(Config $config, FileList $fileList, CliOutput $output): void
    {
        $analyzer = new Analyzer(
            $config,
            (new ParserFactory())->createForNewestSupportedVersion(),
            new NodeTraverser(),
            $this->metricsController,
            $output);

        $analyzer->analyze($fileList);
    }

    /**
     * @param Config $config
     * @return void
     */
    public function createMetricController(Config $config): void
    {
        $metricsCollection = new MetricsContainer();
        $this->metricsController = new MetricsController($metricsCollection);
        $this->metricsController->registerMetricTypes();
        $this->metricsController->createProjectMetricsCollection($config->get('files'));
    }

    /**
     * @param $metricsCollection
     * @param CliOutput $output
     * @return void
     */
    public function runCalculators(CliOutput $output): void
    {
        //$packageIACalculator = new PackageInstabilityAbstractnessCalculator($metricsCollection);

        $calculatorService = new CalculatorService([
            new FileCalculator($this->metricsController),
            /*
            new VariablesCalculator($metricsCollection, [
                'superglobalsUsed',
                'distinctSuperglobalsUsed',
                'variablesUsed',
                'distinctVariablesUsed',
                'constantsUsed',
                'distinctConstantsUsed',
                'superglobalMetric',
            ]),
            new CouplingCalculator($metricsCollection, [
                'uses',
                'usesCount',
                'usedBy',
                'usedByCount',
                'usesInProject',
                'usesInProjectCount',
                'usesForInstabilityCount',
            ], $packageIACalculator),
            new ProjectCalculator($metricsCollection, []),
            */
        ], $this->metricsController, $output);

        $calculatorService->run();
    }
}
