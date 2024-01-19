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
use PhpCodeArch\Report\Data\ReportDataCollection;
use PhpCodeArch\Report\Data\ReportDataContainer;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\Helper\MetricsSplitter;
use PhpCodeArch\Report\ReportFactory;
use PhpCodeArch\Report\ReportTypeNotSupported;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

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

        $problems = $this->runPredictors($this->metricsController, $output);
        $this->setProblems($problems);

        $reportDataContainer = $this->getReportDataContainer($output);

        $twigLoader = new FilesystemLoader();
        $twig = new Environment($twigLoader, [
            'debug' => true,
        ]);
        $twig->addExtension(new DebugExtension());

        $reportData = new DataProviderFactory($this->metricsController, $reportDataContainer);

        $report = ReportFactory::create(
            $config,
            $reportData,
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
     * @param CliOutput $output
     * @return void
     */
    public function runCalculators(CliOutput $output): void
    {
        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($this->metricsController);

        $calculatorService = new CalculatorService([
            new FileCalculator($this->metricsController),
            new VariablesCalculator($this->metricsController),
            new CouplingCalculator($this->metricsController, $packageIACalculator),
            new ProjectCalculator($this->metricsController),
        ], $this->metricsController, $output);

        $calculatorService->run();
    }

    /**
     * @param MetricsController $metricsController
     * @param CliOutput $output
     * @return array
     */
    public function runPredictors(MetricsController $metricsController, CliOutput $output): array
    {
        $predictions = new PredictionService([
            new TooLongPrediction(),
            new GodClassPrediction(),
            new TooComplexPrediction(),
            new TooDependentPrediction(),
            new TooMuchHtmlPrediction(),
        ], $metricsController, $output);
        $predictions->predict();

        return $predictions->getProblemCount();
    }

    /**
     * @param array $problems
     * @return void
     */
    public function setProblems(array $problems): void
    {
        $this->metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            [
                'overallInformationCount' => $problems[PredictionInterface::INFO],
                'overallWarningCount' => $problems[PredictionInterface::WARNING],
                'overallErrorCount' => $problems[PredictionInterface::ERROR],
            ]
        );
    }

    /**
     * @param CliOutput $output
     * @return ReportDataContainer
     */
    public function getReportDataContainer(CliOutput $output): ReportDataContainer
    {
        $reportDataContainer = new ReportDataContainer();
        $splitter = new MetricsSplitter($this->metricsController, $reportDataContainer, $output);
        $splitter->split();

        return $reportDataContainer;
    }
}
