<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Application\Service\ClaudeMdGenerator;
use PhpCodeArch\Application\Service\CloverXmlParser;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\FrameworkDetector;
use PhpCodeArch\Application\Service\HistoryService;
use PhpCodeArch\Application\Service\SummaryPrinter;
use PhpCodeArch\Application\Service\TestCoversParser;
use PhpCodeArch\Application\Service\TestCoversParseResult;
use PhpCodeArch\Application\Service\TestDirectoryScanner;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Calculators\CodeDuplicationCalculator;
use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Calculators\DependencyCycleCalculator;
use PhpCodeArch\Calculators\FileCalculator;
use PhpCodeArch\Calculators\HealthScoreCalculator;
use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Calculators\InheritanceDepthCalculator;
use PhpCodeArch\Calculators\LayerViolationCalculator;
use PhpCodeArch\Calculators\LimitsAndAveragesCalculator;
use PhpCodeArch\Calculators\MaintainabilityIndexCalculator;
use PhpCodeArch\Calculators\PackageCohesionCalculator;
use PhpCodeArch\Calculators\ProjectCalculator;
use PhpCodeArch\Calculators\RefactoringPriorityCalculator;
use PhpCodeArch\Calculators\SolidViolationCalculator;
use PhpCodeArch\Calculators\TechnicalDebtCalculator;
use PhpCodeArch\Calculators\TestMappingCalculator;
use PhpCodeArch\Calculators\VariablesCalculator;
use PhpCodeArch\Git\GitAnalyzer;
use PhpCodeArch\Mcp\Command\McpCommand;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\DeadCodePrediction;
use PhpCodeArch\Predictions\DeepInheritancePrediction;
use PhpCodeArch\Predictions\DependencyCyclePrediction;
use PhpCodeArch\Predictions\GodClassPrediction;
use PhpCodeArch\Predictions\HotspotPrediction;
use PhpCodeArch\Predictions\LowTypeCoveragePrediction;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\PredictionService;
use PhpCodeArch\Predictions\SecuritySmellPrediction;
use PhpCodeArch\Predictions\SolidViolationPrediction;
use PhpCodeArch\Predictions\TooComplexPrediction;
use PhpCodeArch\Predictions\TooDependentPrediction;
use PhpCodeArch\Predictions\TooLongPrediction;
use PhpCodeArch\Predictions\TooManyParametersPrediction;
use PhpCodeArch\Predictions\TooMuchHtmlPrediction;
use PhpCodeArch\Predictions\UntestedComplexCodePrediction;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
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
    public const VERSION = '2.7.0';

    /**
     * Version that introduced breaking changes to metric calculations.
     * Users will be prompted to acknowledge before running analysis.
     */
    private const BREAKING_CHANGES_VERSION = '2.7.0';

    /**
     * @param array<int, string> $argv
     *
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     * @throws ReportTypeNotSupported
     */
    public function run(array $argv): int
    {
        try {
            $config = $this->createConfig($argv);
        } catch (VersionDisplayException $e) {
            echo PHP_EOL.'PhpCodeArcheology v'.$e->getMessage();

            return 0;
        }

        $formatter = new CliFormatter(
            $config->get('noColor') ? false : null
        );
        $output = new CliOutput();
        $output->setFormatter($formatter);

        // Dispatch subcommand before analysis
        $command = $config->get('command');
        if (is_string($command)) {
            return $this->dispatchCommand($command, $config, $output, $formatter);
        }

        $memoryLimit = $config->get('memoryLimit') ?? '1G';
        if (is_string($memoryLimit) && preg_match('/^[0-9]+[KMG]?$/i', $memoryLimit)) {
            ini_set('memory_limit', $memoryLimit);
        }

        // Framework detection (runs in all modes)
        $frameworkConfig = $config->get('framework');
        $frameworkDetect = !is_array($frameworkConfig) || ($frameworkConfig['detect'] ?? true);
        if ($frameworkDetect) {
            $detector = new FrameworkDetector();
            $runningDirRaw = $config->get('runningDir');
            $filesRaw = $config->get('files');
            $projectRoot = is_string($runningDirRaw) && '' !== $runningDirRaw
                ? $runningDirRaw
                : (is_array($filesRaw) && is_string($filesRaw[0] ?? null) ? $filesRaw[0] : (getcwd() ?: ''));
            $frameworkResult = $detector->detect($projectRoot);
            $config->set('frameworkDetection', $frameworkResult);

            $testScanner = new TestDirectoryScanner($frameworkResult);
            $testScanResult = $testScanner->scan($projectRoot);
            $config->set('testScanResult', $testScanResult);

            if ([] !== $testScanResult->classBasedTestFiles) {
                $phpParser = (new ParserFactory())->createForHostVersion();
                $coversParser = new TestCoversParser($phpParser);
                $coversResult = $coversParser->parse($testScanResult->classBasedTestFiles);
                $config->set('coversParseResult', $coversResult);
            }

            // Use composer.json directory as the true project root for coverage
            $composerRoot = '' !== $frameworkResult->composerJsonPath
                ? dirname($frameworkResult->composerJsonPath)
                : $projectRoot;

            // Auto-detect Clover XML in common locations
            if (null === $config->get('coverageFile')) {
                $candidates = ['clover.xml', 'coverage/clover.xml', 'build/logs/clover.xml', 'build/coverage/clover.xml'];
                foreach ($candidates as $candidate) {
                    $path = $composerRoot.DIRECTORY_SEPARATOR.$candidate;
                    if (is_file($path)) {
                        $config->set('coverageFile', $path);
                        break;
                    }
                }
            }

            $coverageFile = $config->get('coverageFile');
            if (is_string($coverageFile)) {
                if (is_file($coverageFile)) {
                    $cloverParser = new CloverXmlParser();
                    $coverageData = $cloverParser->parse($coverageFile, $composerRoot);
                    $config->set('coverageData', $coverageData);
                } else {
                    fwrite(STDERR, "Warning: Coverage file not found: {$coverageFile}\n");
                }
            }
        }

        // Breaking changes notice: prompt user before first analysis with new calculations
        if (!$this->isBreakingChangesAcknowledged($config)) {
            $output->outNl($formatter->warning('Important: Metric calculations have changed in v'.self::BREAKING_CHANGES_VERSION.'.'));
            $output->outNl('Several formulas have been corrected (Halstead, Coupling, LCOM, CC, and others).');
            $output->outNl('Analysis results may differ from previous runs.');
            $output->outNl();
            $output->outNl($formatter->dim('Recommendation: Back up your existing reports before continuing.'));
            $output->outNl($formatter->dim('See docs/metrics-formulas.md for details on the updated calculations.'));
            $output->outNl();
            $answer = $output->prompt('Continue with analysis? (y/N)', 'N');
            if ('y' !== strtolower($answer)) {
                $output->outNl('Aborted. Your existing reports remain unchanged.');

                return 0;
            }
            $this->acknowledgeBreakingChanges($config);
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
            if ($frameworkResult instanceof FrameworkDetectionResult
                && $frameworkResult->hasAnyFramework()) {
                $output->outNl('  Frameworks: '.$formatter->info($frameworkResult->getSummary()));
                $output->outNl();
            }

            return 0;
        }

        [$metricsController, $problems] = $this->runAnalysis($config, $output);

        $twigLoader = new FilesystemLoader();
        $isDebug = '1' === getenv('APP_DEBUG');
        $twig = new Environment($twigLoader, options: [
            'debug' => $isDebug,
        ]);
        if ($isDebug) {
            $twig->addExtension(new DebugExtension());
        }

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
        $reportDirVal = $config->get('reportDir');
        $oldIndexFile = (is_string($reportDirVal) ? $reportDirVal : '').DIRECTORY_SEPARATOR.'index.html';
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
        if ($frameworkDetection instanceof FrameworkDetectionResult
            && $frameworkDetection->hasTestFramework()
            && null === $config->get('coverageFile')) {
            $output->outNl();
            $output->outNl('Tip: For precise line-level coverage data, generate a Clover XML report first:');
            if ($frameworkDetection->pestDetected) {
                $output->outNl('  '.$formatter->info('XDEBUG_MODE=coverage vendor/bin/pest --coverage-clover clover.xml'));
            }
            if ($frameworkDetection->phpunitDetected) {
                $output->outNl('  '.$formatter->info('XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover clover.xml'));
            }
            $output->outNl('  Requires Xdebug or PCOV PHP extension.');
            $output->outNl('  The clover.xml will be detected automatically on the next run.');
        }

        return $this->determineExitCode($config, $problems);
    }

    /**
     * @param array<int, string> $argv
     *
     * @throws MultipleConfigFilesException
     * @throws ConfigFileExtensionNotSupportedException
     * @throws VersionDisplayException
     */
    private function createConfig(array $argv): Config
    {
        try {
            $config = (new ArgumentParser())->parse($argv);
        } catch (ParamException $e) {
            echo PHP_EOL."Error: {$e->getMessage()}";
            exit(1);
        }

        $config->set('runningDir', getcwd());

        $configFileFinder = new ConfigFileFinder($config);
        $configFileFinder->checkRunningDir();

        if (!$config->get('reportType')) {
            $config->set('reportType', 'html');
        }

        $runningDirVal = $config->get('runningDir');
        $runningDir = is_string($runningDirVal) ? $runningDirVal : '';
        $reportDirRaw = $config->get('reportDir');
        if (!$reportDirRaw) {
            $reportDir = $runningDir.'/tmp/report';
        } elseif (is_string($reportDirRaw) && !str_starts_with($reportDirRaw, DIRECTORY_SEPARATOR)) {
            // Resolve relative CLI path against runningDir
            $reportDir = $runningDir.DIRECTORY_SEPARATOR.$reportDirRaw;
        } else {
            $reportDir = is_string($reportDirRaw) ? $reportDirRaw : '';
        }
        if (!is_dir($reportDir)) {
            mkdir($reportDir, 0755, true);
        }
        $resolved = realpath($reportDir);
        $config->set('reportDir', false !== $resolved ? $resolved : $reportDir);

        if (!$config->get('packageSize')) {
            $config->set('packageSize', 2);
        }

        // Skip validation for subcommands (they don't need files)
        if (!$config->get('command')) {
            try {
                $config->validate();
            } catch (ConfigException $e) {
                echo PHP_EOL."Error: {$e->getMessage()}";
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
            $output);

        $analyzer->analyze($fileList);
    }

    private function createMetricController(Config $config): MetricsController
    {
        $metricsCollection = new MetricsContainer();
        $metricsController = new MetricsController($metricsCollection);
        $metricsController->registerMetricTypes();
        $filesRaw = $config->get('files');
        $files = is_array($filesRaw) ? array_filter($filesRaw, 'is_string') : [];
        $metricsController->createProjectMetricsCollection(array_values($files));

        return $metricsController;
    }

    private function runCalculators(MetricsController $metricsController, CliOutput $output, Config $config): void
    {
        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($metricsController);

        $frameworkDetectionRaw = $config->get('frameworkDetection');
        $frameworkDetection = $frameworkDetectionRaw instanceof FrameworkDetectionResult ? $frameworkDetectionRaw : null;
        $testScanResultRaw = $config->get('testScanResult');
        $testScanResult = $testScanResultRaw instanceof TestScanResult ? $testScanResultRaw : null;
        $coverageDataRaw = $config->get('coverageData');
        $coverageData = $this->extractCoverageData($coverageDataRaw);
        $coversParseResultRaw = $config->get('coversParseResult');
        $coversParseResult = $coversParseResultRaw instanceof TestCoversParseResult ? $coversParseResultRaw : null;

        // Use composer.json directory as project root for coverage path matching
        $runningDirRaw = $config->get('runningDir');
        $filesRaw = $config->get('files');
        $composerRoot = ($frameworkDetection instanceof FrameworkDetectionResult && '' !== $frameworkDetection->composerJsonPath)
            ? dirname($frameworkDetection->composerJsonPath)
            : (is_string($runningDirRaw) && '' !== $runningDirRaw
                ? $runningDirRaw
                : (is_array($filesRaw) && is_string($filesRaw[0] ?? null) ? $filesRaw[0] : (getcwd() ?: '')));

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

    /** @return array<int, int> */
    private function runPredictors(MetricsController $metricsController, CliOutput $output, Config $config): array
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

    /**
     * Extract and validate coverage data from config, ensuring correct shape.
     *
     * @return array<string, array{linerate: float, statements: int}>|null
     */
    private function extractCoverageData(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $result = [];
        foreach ($raw as $key => $entry) {
            if (!is_string($key) || !is_array($entry)) {
                continue;
            }
            $linerate = isset($entry['linerate']) && is_float($entry['linerate']) ? $entry['linerate'] : (isset($entry['linerate']) && is_numeric($entry['linerate']) ? (float) $entry['linerate'] : 0.0);
            $statements = isset($entry['statements']) && is_int($entry['statements']) ? $entry['statements'] : (isset($entry['statements']) && is_numeric($entry['statements']) ? (int) $entry['statements'] : 0);
            $result[$key] = ['linerate' => $linerate, 'statements' => $statements];
        }

        return [] === $result ? null : $result;
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
        $frameworkResult = $config->get('frameworkDetection');
        if ($frameworkResult instanceof FrameworkDetectionResult) {
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

    /** @param array<int, int> $problems */
    private function determineExitCode(Config $config, array $problems): int
    {
        $failOnRaw = $config->get('failOn');
        $failOn = is_string($failOnRaw) ? $failOnRaw : null;

        // Check YAML config quality gate if no CLI flag
        if (null === $failOn) {
            $qualityGateRaw = $config->get('qualityGate');
            if (is_array($qualityGateRaw)) {
                $maxErrors = $qualityGateRaw['maxErrors'] ?? null;
                $maxWarnings = $qualityGateRaw['maxWarnings'] ?? null;

                if (null !== $maxErrors && $problems[PredictionInterface::ERROR] > $maxErrors) {
                    return 1;
                }
                if (null !== $maxWarnings && $problems[PredictionInterface::WARNING] > $maxWarnings) {
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

    private function isBreakingChangesAcknowledged(Config $config): bool
    {
        $acknowledged = $config->get('acknowledgedVersion');

        return is_string($acknowledged)
            && version_compare($acknowledged, self::BREAKING_CHANGES_VERSION, '>=');
    }

    private function acknowledgeBreakingChanges(Config $config): void
    {
        $runningDir = $config->get('runningDir');
        $runningDir = is_string($runningDir) ? $runningDir : (getcwd() ?: '');

        // Find existing config file or create a new YAML one
        $yamlPath = $runningDir.DIRECTORY_SEPARATOR.'php-codearch-config.yaml';
        $jsonPath = $runningDir.DIRECTORY_SEPARATOR.'.phpcodearch.json';

        if (is_file($jsonPath)) {
            $content = file_get_contents($jsonPath);
            $data = false !== $content ? json_decode($content, true) : [];
            if (!is_array($data)) {
                $data = [];
            }
            $data['acknowledgedVersion'] = self::BREAKING_CHANGES_VERSION;
            file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        } else {
            // Use YAML config (create or update)
            if (is_file($yamlPath)) {
                $content = file_get_contents($yamlPath);
                $yaml = false !== $content ? $content : '';
            } else {
                $yaml = '';
            }

            // Append or replace acknowledgedVersion
            if (str_contains($yaml, 'acknowledgedVersion:')) {
                $yaml = (string) preg_replace(
                    '/acknowledgedVersion:.*/',
                    'acknowledgedVersion: '.self::BREAKING_CHANGES_VERSION,
                    $yaml
                );
            } else {
                $yaml = rtrim($yaml)."\nacknowledgedVersion: ".self::BREAKING_CHANGES_VERSION."\n";
            }

            file_put_contents($yamlPath, $yaml);
        }

        $config->set('acknowledgedVersion', self::BREAKING_CHANGES_VERSION);
    }
}
