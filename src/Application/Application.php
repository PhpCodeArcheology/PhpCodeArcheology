<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Application\Service\ClaudeMdGenerator;
use PhpCodeArch\Application\Service\CloverXmlParser;
use PhpCodeArch\Application\Service\FrameworkDetector;
use PhpCodeArch\Application\Service\TestCoversParser;
use PhpCodeArch\Application\Service\HistoryService;
use PhpCodeArch\Application\Service\TestDirectoryScanner;
use PhpCodeArch\Application\Service\SummaryPrinter;
use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Calculators\CodeDuplicationCalculator;
use PhpCodeArch\Calculators\DependencyCycleCalculator;
use PhpCodeArch\Calculators\LayerViolationCalculator;
use PhpCodeArch\Calculators\PackageCohesionCalculator;
use PhpCodeArch\Calculators\SolidViolationCalculator;
use PhpCodeArch\Calculators\FileCalculator;
use PhpCodeArch\Calculators\TestMappingCalculator;
use PhpCodeArch\Calculators\HealthScoreCalculator;
use PhpCodeArch\Calculators\RefactoringPriorityCalculator;
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
use PhpCodeArch\Predictions\UntestedComplexCodePrediction;
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
use PhpCodeArch\Mcp\Command\McpCommand;
use PhpCodeArch\Report\ReportFactory;
use PhpCodeArch\Report\ReportTypeNotSupported;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final readonly class Application
{
    const VERSION = '2.6.1';

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

        // Framework detection (runs in all modes)
        $frameworkConfig = $config->get('framework') ?? [];
        if ($frameworkConfig['detect'] ?? true) {
            $detector = new FrameworkDetector();
            $projectRoot = $config->get('runningDir') ?: ($config->get('files')[0] ?? getcwd());
            $frameworkResult = $detector->detect($projectRoot);
            $config->set('frameworkDetection', $frameworkResult);

            $testScanner = new TestDirectoryScanner($frameworkResult);
            $testScanResult = $testScanner->scan($projectRoot);
            $config->set('testScanResult', $testScanResult);

            if (!empty($testScanResult->classBasedTestFiles)) {
                $phpParser = (new ParserFactory())->createForHostVersion();
                $coversParser = new TestCoversParser($phpParser);
                $coversResult = $coversParser->parse($testScanResult->classBasedTestFiles);
                $config->set('coversParseResult', $coversResult);
            }

            // Use composer.json directory as the true project root for coverage
            $composerRoot = $frameworkResult->composerJsonPath !== ''
                ? dirname($frameworkResult->composerJsonPath)
                : $projectRoot;

            // Auto-detect Clover XML in common locations
            if ($config->get('coverageFile') === null) {
                $candidates = ['clover.xml', 'coverage/clover.xml', 'build/logs/clover.xml', 'build/coverage/clover.xml'];
                foreach ($candidates as $candidate) {
                    $path = $composerRoot . DIRECTORY_SEPARATOR . $candidate;
                    if (is_file($path)) {
                        $config->set('coverageFile', $path);
                        break;
                    }
                }
            }

            $coverageFile = $config->get('coverageFile');
            if ($coverageFile !== null) {
                if (is_file($coverageFile)) {
                    $cloverParser = new CloverXmlParser();
                    $coverageData = $cloverParser->parse($coverageFile, $composerRoot);
                    $config->set('coverageData', $coverageData);
                } else {
                    fwrite(STDERR, "Warning: Coverage file not found: {$coverageFile}\n");
                }
            }
        }

        // Quick mode: reduced analysis, no report
        if ($config->get('quickMode')) {
            $fileList = $this->createFileList($config);
            $metricsController = $this->createMetricController($config);
            $this->createAndRunAnalyzer($config, $metricsController, $fileList, $output);
            $this->runQuickCalculators($metricsController, $output);
            $quickOutput = new QuickOutput($metricsController, $output, $formatter);
            $quickOutput->render();

            $frameworkResult = $config->get('frameworkDetection');
            if ($frameworkResult instanceof \PhpCodeArch\Application\Service\FrameworkDetectionResult
                && $frameworkResult->hasAnyFramework()) {
                $output->outNl('  Frameworks: ' . $formatter->info($frameworkResult->getSummary()));
                $output->outNl();
            }

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

        $reports = ReportFactory::createMultiple(
            $config,
            $dataProviderFactory,
            $historyDate,
            $twigLoader,
            $twig,
            $output
        );

        foreach ($reports as $report) {
            $report->generate();
        }

        // Migration hint for users upgrading from pre-v1.6.0
        $oldIndexFile = $config->get('reportDir') . DIRECTORY_SEPARATOR . 'index.html';
        if (file_exists($oldIndexFile)) {
            $output->outNl();
            $output->outNl('Note: Since v1.6.0, reports are generated in subdirectories (e.g., html/, json/).');
            $output->outNl('Old report files in the root directory can be safely removed.');
            $output->outNl();
        }

        $historyService->writeHistory($metricsController, $config);

        if ($config->get('generateClaudeMd')) {
            (new ClaudeMdGenerator())->generate($config, $dataProviderFactory, $output);
        }

        (new SummaryPrinter())->print($metricsController, $config, $problems, $output, $formatter);

        $frameworkDetection = $config->get('frameworkDetection');
        if ($frameworkDetection instanceof \PhpCodeArch\Application\Service\FrameworkDetectionResult
            && $frameworkDetection->hasTestFramework()
            && $config->get('coverageFile') === null) {
            $output->outNl();
            $output->outNl('Tip: For precise line-level coverage data, generate a Clover XML report first:');
            if ($frameworkDetection->pestDetected) {
                $output->outNl('  ' . $formatter->info('XDEBUG_MODE=coverage vendor/bin/pest --coverage-clover clover.xml'));
            }
            if ($frameworkDetection->phpunitDetected) {
                $output->outNl('  ' . $formatter->info('XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover clover.xml'));
            }
            $output->outNl('  Requires Xdebug or PCOV PHP extension.');
            $output->outNl('  The clover.xml will be detected automatically on the next run.');
        }

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

        $reportDir = $config->get('reportDir');
        if (!$reportDir) {
            $reportDir = $config->get('runningDir') . '/tmp/report';
        } elseif (!str_starts_with($reportDir, DIRECTORY_SEPARATOR)) {
            // Resolve relative CLI path against runningDir
            $reportDir = $config->get('runningDir') . DIRECTORY_SEPARATOR . $reportDir;
        }
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        $resolved = realpath($reportDir);
        $config->set('reportDir', $resolved !== false ? $resolved : $reportDir);

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
        $phpConfig = $config->get('php') ?? [];
        $parser = isset($phpConfig['version'])
            ? (new ParserFactory())->createForVersion(PhpVersion::fromString($phpConfig['version']))
            : (new ParserFactory())->createForHostVersion();

        $analyzer = new Analyzer(
            $config,
            $parser,
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
    private function runCalculators(MetricsController $metricsController, CliOutput $output, ?Config $config = null): void
    {
        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($metricsController);

        $frameworkDetection = $config?->get('frameworkDetection');
        $testScanResult = $config?->get('testScanResult');
        $coverageData = $config?->get('coverageData');
        $coversParseResult = $config?->get('coversParseResult');

        // Use composer.json directory as project root for coverage path matching
        $composerRoot = ($frameworkDetection instanceof \PhpCodeArch\Application\Service\FrameworkDetectionResult && $frameworkDetection->composerJsonPath !== '')
            ? dirname($frameworkDetection->composerJsonPath)
            : ($config?->get('runningDir') ?: ($config?->get('files')[0] ?? getcwd()));

        $calculatorService = new CalculatorService([
            new MaintainabilityIndexCalculator($metricsController),
            new FileCalculator($metricsController),
            new TestMappingCalculator($metricsController, $frameworkDetection, $testScanResult, $coverageData, $composerRoot, $coversParseResult),
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
            new GodClassPrediction($config),
            new TooComplexPrediction($config),
            new TooDependentPrediction($config),
            new TooMuchHtmlPrediction($config),
            new LowTypeCoveragePrediction($config),
            new DeepInheritancePrediction($config),
            new DependencyCyclePrediction($config),
            new TooManyParametersPrediction($config),
            new DeadCodePrediction(),
            new SecuritySmellPrediction(),
            new SolidViolationPrediction(),
            new UntestedComplexCodePrediction($config),
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
            'mcp' => (new McpCommand($this))->execute($config, $output, $formatter),
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

        // Store framework detection result in project metrics
        $frameworkResult = $config->get('frameworkDetection');
        if ($frameworkResult instanceof \PhpCodeArch\Application\Service\FrameworkDetectionResult) {
            $metricsController->setMetricValues(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                ['detectedFrameworks' => $frameworkResult->getSummary()]
            );
        }

        $this->runCalculators($metricsController, $output, $config);

        $problems = $this->runPredictors($metricsController, $output, $config);
        $this->setProblems($metricsController, $problems);

        // These calculators must run after predictors (need problem counts)
        $postPredictionService = new CalculatorService([
            new TechnicalDebtCalculator($metricsController),
            new HealthScoreCalculator($metricsController),
            new RefactoringPriorityCalculator($metricsController),
        ], $metricsController, $output);
        $postPredictionService->run();

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
