<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Application\Service\ClaudeMdGenerator;
use PhpCodeArch\Application\Service\HistoryService;
use PhpCodeArch\Application\Service\SummaryPrinter;
use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Calculators\CodeDuplicationCalculator;
use PhpCodeArch\Calculators\DependencyCycleCalculator;
use PhpCodeArch\Calculators\LayerViolationCalculator;
use PhpCodeArch\Calculators\PackageCohesionCalculator;
use PhpCodeArch\Calculators\SolidViolationCalculator;
use PhpCodeArch\Calculators\FileCalculator;
use PhpCodeArch\Calculators\HealthScoreCalculator;
use PhpCodeArch\Calculators\InheritanceDepthCalculator;
use PhpCodeArch\Calculators\MaintainabilityIndexCalculator;
use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Calculators\LimitsAndAveragesCalculator;
use PhpCodeArch\Calculators\ProjectCalculator;
use PhpCodeArch\Calculators\TechnicalDebtCalculator;
use PhpCodeArch\Calculators\VariablesCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\GodClassPrediction;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\PredictionService;
use PhpCodeArch\Predictions\DeadCodePrediction;
use PhpCodeArch\Predictions\HotspotPrediction;
use PhpCodeArch\Predictions\DeepInheritancePrediction;
use PhpCodeArch\Predictions\DependencyCyclePrediction;
use PhpCodeArch\Predictions\LowTypeCoveragePrediction;
use PhpCodeArch\Predictions\SecuritySmellPrediction;
use PhpCodeArch\Predictions\SolidViolationPrediction;
use PhpCodeArch\Predictions\TooManyParametersPrediction;
use PhpCodeArch\Predictions\TooComplexPrediction;
use PhpCodeArch\Predictions\TooDependentPrediction;
use PhpCodeArch\Predictions\TooLongPrediction;
use PhpCodeArch\Predictions\TooMuchHtmlPrediction;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\ReportFactory;
use PhpCodeArch\Report\ReportTypeNotSupported;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final readonly class Application
{
    const VERSION = '1.0.1';

    /**
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     * @throws ReportTypeNotSupported
     */
    public function run(array $argv): int
    {
        $config = $this->createConfig($argv);

        $formatter = new CliFormatter(
            $config->get('noColor') ? false : null
        );
        $output = new CliOutput();
        $output->setFormatter($formatter);

        // Dispatch subcommand before analysis
        $command = $config->get('command');
        if ($command !== null) {
            return $this->dispatchCommand($command, $config, $output, $formatter);
        }

        $memoryLimit = $config->get('memoryLimit') ?? '1G';
        ini_set('memory_limit', $memoryLimit);

        // Quick mode: reduced analysis, no report
        if ($config->get('quickMode')) {
            $fileList = $this->createFileList($config);
            $metricsController = $this->createMetricController($config);
            $this->createAndRunAnalyzer($config, $metricsController, $fileList, $output);
            $this->runQuickCalculators($metricsController, $output);
            $quickOutput = new QuickOutput($metricsController, $output, $formatter);
            $quickOutput->render();
            return 0;
        }

        [$metricsController, $problems] = $this->runAnalysis($config, $output);

        $twigLoader = new FilesystemLoader();
        $twig = new Environment($twigLoader, options: [
            'debug' => true,
        ]);
        $twig->addExtension(new DebugExtension());

        $dataProviderFactory = new DataProviderFactory($metricsController);

        $historyService = new HistoryService();
        $historyDate = $historyService->setDeltas($metricsController, $config);

        $report = ReportFactory::create(
            $config,
            $dataProviderFactory,
            $historyDate,
            $twigLoader,
            $twig,
            $output
        );
        $report->generate();

        $historyService->writeHistory($metricsController, $config);

        if ($config->get('generateClaudeMd')) {
            (new ClaudeMdGenerator())->generate($config, $dataProviderFactory, $output);
        }

        (new SummaryPrinter())->print($metricsController, $config, $problems, $output, $formatter);

        return $this->determineExitCode($config, $problems);
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
            exit(1);
        }

        $config->set('runningDir', getcwd());

        $configFileFinder = new ConfigFileFinder($config);
        $configFileFinder->checkRunningDir();

        if (! $config->get('reportType')) {
            $config->set('reportType', 'html');
        }

        if (! $config->get('reportDir')) {
            $reportDir = $config->get('runningDir') . '/tmp/report';
            if (!is_dir($reportDir)) {
                mkdir($reportDir, 0755, true);
            }
            $config->set('reportDir', $reportDir);
        }

        if (! $config->get('packageSize')) {
            $config->set('packageSize', 2);
        }

        // Skip validation for subcommands (they don't need files)
        if (!$config->get('command')) {
            try {
                $config->validate();
            } catch (ConfigException $e) {
                echo PHP_EOL . "Error: {$e->getMessage()}";
                exit(1);
            }
        }

        return $config;
    }

    private function createFileList(Config $config): FileList
    {
        $fileList = new FileList($config);
        $fileList->fetch();

        return $fileList;
    }

    private function createAndRunAnalyzer(Config $config, MetricsController $metricsController, FileList $fileList, CliOutput $output): void
    {
        $analyzer = new Analyzer(
            $config,
            (new ParserFactory())->createForHostVersion(),
            new NodeTraverser(),
            $metricsController,
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
     * @param MetricsController $metricsController
     * @param CliOutput $output
     * @return void
     */
    private function runCalculators(MetricsController $metricsController, CliOutput $output): void
    {
        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($metricsController);

        $calculatorService = new CalculatorService([
            new MaintainabilityIndexCalculator($metricsController),
            new FileCalculator($metricsController),
            new VariablesCalculator($metricsController),
            new CouplingCalculator($metricsController, $packageIACalculator),
            new InheritanceDepthCalculator($metricsController),
            new DependencyCycleCalculator($metricsController),
            new SolidViolationCalculator($metricsController),
            new LayerViolationCalculator($metricsController),
            new PackageCohesionCalculator($metricsController),
            new CodeDuplicationCalculator($metricsController),
            new ProjectCalculator($metricsController),
            new LimitsAndAveragesCalculator($metricsController),
            new HealthScoreCalculator($metricsController),
        ], $metricsController, $output);

        $calculatorService->run();
    }

    /**
     * @param MetricsController $metricsController
     * @param CliOutput $output
     * @return array
     */
    private function runPredictors(MetricsController $metricsController, CliOutput $output, ?Config $config = null): array
    {
        $predictions = new PredictionService([
            new TooLongPrediction($config),
            new GodClassPrediction(),
            new TooComplexPrediction($config),
            new TooDependentPrediction($config),
            new TooMuchHtmlPrediction($config),
            new LowTypeCoveragePrediction($config),
            new DeepInheritancePrediction($config),
            new DependencyCyclePrediction(),
            new TooManyParametersPrediction($config),
            new DeadCodePrediction(),
            new SecuritySmellPrediction(),
            new SolidViolationPrediction(),
            new HotspotPrediction($config),
        ], $metricsController, $output);
        $predictions->predict();

        return $predictions->getProblemCount();
    }

    /**
     * @param MetricsController $metricsController
     * @param array $problems
     * @return void
     */
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

    private function dispatchCommand(string $command, Config $config, CliOutput $output, CliFormatter $formatter): int
    {
        return match ($command) {
            'init' => (new Command\InitCommand())->execute($config, $output, $formatter),
            'compare' => (new Command\CompareCommand())->execute($config, $output, $formatter),
            'baseline' => (new Command\BaselineCommand($this))->execute($config, $output, $formatter),
            default => throw new ParamException("Unknown command: $command"),
        };
    }

    /**
     * Run full analysis pipeline: parse → git → calculators → predictors → debt.
     * @return array{MetricsController, array} [$metricsController, $problems]
     */
    public function runAnalysis(Config $config, CliOutput $output): array
    {
        $fileList = $this->createFileList($config);
        $metricsController = $this->createMetricController($config);
        $this->createAndRunAnalyzer($config, $metricsController, $fileList, $output);

        $gitConfig = $config->get('git') ?? ['enable' => true];
        if ($gitConfig['enable'] ?? true) {
            $gitAnalyzer = new \PhpCodeArch\Git\GitAnalyzer($config, $metricsController, $output);
            $gitAnalyzer->analyze();
        }

        $this->runCalculators($metricsController, $output);

        $problems = $this->runPredictors($metricsController, $output, $config);
        $this->setProblems($metricsController, $problems);

        // Technical debt must run after predictors (needs problem data)
        $debtCalculatorService = new CalculatorService([
            new TechnicalDebtCalculator($metricsController),
        ], $metricsController, $output);
        $debtCalculatorService->run();

        return [$metricsController, $problems];
    }

    private function runQuickCalculators(MetricsController $metricsController, CliOutput $output): void
    {
        $calculatorService = new CalculatorService([
            new MaintainabilityIndexCalculator($metricsController),
            new FileCalculator($metricsController),
            new ProjectCalculator($metricsController),
            new LimitsAndAveragesCalculator($metricsController),
        ], $metricsController, $output);

        $calculatorService->run();
    }

    private function determineExitCode(Config $config, array $problems): int
    {
        $failOn = $config->get('failOn');

        // Check YAML config quality gate if no CLI flag
        if ($failOn === null) {
            $qualityGate = $config->get('qualityGate');
            if ($qualityGate !== null) {
                $maxErrors = $qualityGate['maxErrors'] ?? null;
                $maxWarnings = $qualityGate['maxWarnings'] ?? null;

                if ($maxErrors !== null && $problems[PredictionInterface::ERROR] > $maxErrors) {
                    return 1;
                }
                if ($maxWarnings !== null && $problems[PredictionInterface::WARNING] > $maxWarnings) {
                    return 1;
                }

                return 0;
            }

            return 0;
        }

        return match ($failOn) {
            'error' => $problems[PredictionInterface::ERROR] > 0 ? 1 : 0,
            'warning' => ($problems[PredictionInterface::ERROR] > 0 || $problems[PredictionInterface::WARNING] > 0) ? 1 : 0,
            default => 0,
        };
    }
}
