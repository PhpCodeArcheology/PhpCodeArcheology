<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
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
use PhpCodeArch\Calculators\VariablesCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Enums\BetterDirection;
use PhpCodeArch\Metrics\Model\Enums\MetricValueType;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricType;
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
    const VERSION = '0.3.12';

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

        $historyDate = $this->setHistoryDeltas($metricsController, $config);

        $report = ReportFactory::create(
            $config,
            $dataProviderFactory,
            $historyDate,
            $twigLoader,
            $twig,
            $output
        );
        $report->generate();

        $this->generateHistory($metricsController, $config);

        if ($config->get('generateClaudeMd')) {
            $this->generateClaudeMd($config, $dataProviderFactory, $output);
        }

        $this->printSummary($metricsController, $config, $problems, $output, $formatter);

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
            $config->set('reportDir', realpath($config->get('runningDir') . '/tmp/report'));
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
    private function runPredictors(MetricsController $metricsController, CliOutput $output): array
    {
        $predictions = new PredictionService([
            new TooLongPrediction(),
            new GodClassPrediction(),
            new TooComplexPrediction(),
            new TooDependentPrediction(),
            new TooMuchHtmlPrediction(),
            new LowTypeCoveragePrediction(),
            new DeepInheritancePrediction(),
            new DependencyCyclePrediction(),
            new TooManyParametersPrediction(),
            new DeadCodePrediction(),
            new SecuritySmellPrediction(),
            new SolidViolationPrediction(),
            new HotspotPrediction(),
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

    private function calculateTechnicalDebt(MetricsController $metricsController): void
    {
        $totalDebt = 0;
        $totalLloc = 0;

        foreach ($metricsController->getAllCollections() as $metric) {
            if (!$metric instanceof \PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection
                && !$metric instanceof \PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection) {
                continue;
            }

            $lloc = $metric->get('lloc')?->getValue() ?? 0;
            $debtPoints = 0;

            // Sum weighted problem scores from all metric values
            foreach ($metric->getAll() as $metricValue) {
                foreach ($metricValue->getProblems() as $problem) {
                    $debtPoints += match ($problem->getProblemLevel()) {
                        PredictionInterface::ERROR => 3,
                        PredictionInterface::WARNING => 1,
                        PredictionInterface::INFO => 0.5,
                        default => 0,
                    };
                }
            }

            $debtPerHundredLines = $lloc > 0 ? round($debtPoints / $lloc * 100, 2) : 0;

            $metricsController->setMetricValueByIdentifierString(
                (string) $metric->getIdentifier(),
                'technicalDebtScore',
                $debtPerHundredLines
            );

            $totalDebt += $debtPoints;
            if ($metric instanceof \PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection) {
                $totalLloc += $lloc;
            }
        }

        $overallDebt = $totalLloc > 0 ? round($totalDebt / $totalLloc * 100, 2) : 0;
        $metricsController->setMetricValues(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            ['overallTechnicalDebtScore' => $overallDebt]
        );
    }

    private function generateHistory(MetricsController $metricsController, Config $config): void
    {
        $outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR;
        $historyFile = $outputDir . 'history.jsonl';

        $metricHistory = [
            'date' => (new \DateTimeImmutable())->format('Y-m-d-H-i-s'),
            'data' => [],
        ];

        foreach ($this->getHistoryData($metricsController) as $historyData) {
            if (!isset($metricHistory['data'][$historyData['collectionKey']])) {
                $metricHistory['data'][$historyData['collectionKey']] = [];
            }
            $metricHistory['data'][$historyData['collectionKey']][$historyData['key']] = $historyData['value'];
        }

        // Migrate old history.json → first line of history.jsonl
        $oldHistoryFile = $outputDir . 'history.json';
        if (file_exists($oldHistoryFile) && !file_exists($historyFile)) {
            $oldData = @file_get_contents($oldHistoryFile);
            if ($oldData !== false) {
                file_put_contents($historyFile, trim($oldData) . "\n");
                @unlink($oldHistoryFile);
            }
        }

        // Append current run as new line
        file_put_contents($historyFile, json_encode($metricHistory) . "\n", FILE_APPEND);
    }

    private function getHistoryData(MetricsController $metricsController): \Generator
    {
        foreach ($metricsController->getAllCollections() as $metricCollectionKey => $metricCollection) {
            foreach ($this->getMetricValues($metricCollection) as $metricValue) {
                if ($metricValue->getMetricType()->getVisibility() === MetricVisibility::ShowNowhere) {
                    continue;
                }

                yield [
                    'collectionKey' => $metricCollectionKey,
                    'key' => $metricValue->getMetricTypeKey(),
                    'value' => $metricValue->getValue(),
                ];
            }
        }
    }

    private function getMetricValues(MetricsCollectionInterface $metricCollection): \Generator
    {
        foreach ($metricCollection->getAll() as $metricValue) {
            yield $metricValue;
        }
    }

    private function setHistoryDeltas(MetricsController $metricsController, Config $config): false|\DateTimeImmutable
    {
        $outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR;

        // Support both JSONL (new) and JSON (legacy)
        $historyFile = $outputDir . 'history.jsonl';
        $isJsonl = true;
        if (!file_exists($historyFile)) {
            $historyFile = $outputDir . 'history.json';
            $isJsonl = false;
            if (!file_exists($historyFile)) {
                return false;
            }
        }

        $historyValueTypes = [
            MetricValueType::Int,
            //MetricValueType::Count,
            MetricValueType::Float,
            MetricValueType::Percentage,
        ];

        // Read last entry (last line for JSONL, whole file for JSON)
        if ($isJsonl) {
            $lastLine = $this->getLastLineOfFile($historyFile);
            if ($lastLine === null) {
                return false;
            }
            $historyFileData = json_decode($lastLine);
        } else {
            $rawData = @file_get_contents($historyFile);
            if ($rawData === false) {
                return false;
            }
            $historyFileData = json_decode($rawData);
            unset($rawData);
        }

        if ($historyFileData === null || !isset($historyFileData->date)) {
            return false;
        }

        $historyDate = \DateTimeImmutable::createFromFormat('Y-m-d-H-i-s', $historyFileData->date);
        unset($historyFileData);

        foreach ($this->getHistoryDataFromFile($historyFile, $isJsonl) as $historyData) {
            foreach ($historyData['data'] as $key => $historyValue) {
                $metricValue = $metricsController->getMetricValueByIdentifierString(
                    $historyData['key'],
                    $key
                );

                if (!$metricValue) {
                    continue;
                }

                $metricType = $metricValue->getMetricType();
                $valueType = $metricType->getValueType();

                if ($metricType->getVisibility() === MetricVisibility::ShowNowhere || $metricType->getValueType() === MetricValueType::Storage) {
                    continue;
                }

                $containsColon = is_string($metricValue->getValue())&& str_contains($metricValue->getValue(), ': ');
                $skip = ! in_array($valueType, $historyValueTypes);
                $skip = $skip && !$containsColon;

                if ($skip) {
                    continue;
                }

                $better = $metricType->getBetter();

                $historyValue = $historyValue ?? 0;

                $deltaObject = new Class {
                    public int|float $delta = 0;
                    public string $direction = '';
                    public null|bool $isBetter = null;
                };

                $currentValue = $metricValue->getValue();
                if ($containsColon) {
                    $currentValue = (int) explode(': ', $currentValue)[1];
                    $historyValue = (int) explode(': ', $historyValue)[1];
                }

                $delta = $currentValue - $historyValue;

                $direction = 'sideways';
                $isBetter = null;
                switch (true) {
                    case $better === BetterDirection::Low && $delta < 0:
                        $direction = 'down';
                        $isBetter = true;
                        break;

                    case $better === BetterDirection::Low && $delta > 0:
                        $direction = 'up';
                        $isBetter = false;
                        break;

                    case $better === BetterDirection::High && $delta > 0:
                        $direction = 'up';
                        $isBetter = true;
                        break;

                    case $better === BetterDirection::High && $delta < 0:
                        $direction = 'down';
                        $isBetter = false;
                        break;
                }

                $deltaObject->delta = $delta;
                $deltaObject->isBetter = $isBetter;
                $deltaObject->direction = $direction;

                $metricValue->setDelta($deltaObject);
            }
        }

        return $historyDate;
    }

    private function getHistoryDataFromFile(string $file, bool $isJsonl = false): \Generator
    {
        if ($isJsonl) {
            $lastLine = $this->getLastLineOfFile($file);
            if ($lastLine === null) {
                return;
            }
            $history = json_decode($lastLine);
        } else {
            $jsonData = @file_get_contents($file);
            if ($jsonData === false) {
                return;
            }
            $history = json_decode($jsonData);
        }

        if ($history === null || !isset($history->data)) {
            return;
        }

        foreach ($history->data as $key => $historyData) {
            yield [
                'key' => $key,
                'data' => $historyData,
            ];
        }
    }

    private function getLastLineOfFile(string $file): ?string
    {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || empty($lines)) {
            return null;
        }
        return end($lines);
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

        $problems = $this->runPredictors($metricsController, $output);
        $this->setProblems($metricsController, $problems);
        $this->calculateTechnicalDebt($metricsController);

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

    private function printSummary(MetricsController $metricsController, Config $config, array $problems, CliOutput $output, CliFormatter $formatter): void
    {
        $get = fn(string $key) => $metricsController->getMetricValue(
            MetricCollectionTypeEnum::ProjectCollection, null, $key
        )?->getValue() ?? 0;

        $files = number_format((int) $get('overallFiles'));
        $classes = number_format((int) $get('overallClasses'));
        $lloc = number_format((int) $get('overallLloc'));
        $avgCC = round((float) $get('overallAvgCC'), 2);
        $avgMI = round((float) $get('overallAvgMI'), 1);
        $healthScore = round((float) $get('healthScore'), 1);
        $grade = $get('healthScoreGrade') ?: '?';

        $errors = $problems[PredictionInterface::ERROR] ?? 0;
        $warnings = $problems[PredictionInterface::WARNING] ?? 0;
        $infos = $problems[PredictionInterface::INFO] ?? 0;

        $line = str_repeat("\u{2550}", 50);

        $errStr = $errors > 0 ? $formatter->error(number_format($errors)) : $formatter->success('0');
        $warnStr = $warnings > 0 ? $formatter->warning(number_format($warnings)) : $formatter->success('0');
        $infoStr = number_format($infos);

        $reportDir = $config->get('reportDir') ?? '';
        $reportType = $config->get('reportType') ?? 'html';
        $reportFile = match ($reportType) {
            'html' => $reportDir . '/index.html',
            'json' => $reportDir . '/report.json',
            'sarif' => $reportDir . '/report.sarif.json',
            'ai-summary' => $reportDir . '/ai-summary.md',
            'markdown' => $reportDir . '/index.md',
            default => $reportDir,
        };

        $output->outNl($line);
        $output->outNl(sprintf(
            ' Files: %s  |  Classes: %s  |  LLOC: %s',
            $formatter->info($files),
            $formatter->info($classes),
            $formatter->info($lloc),
        ));
        $output->outNl(sprintf(
            ' Avg CC: %s  |  Avg MI: %s  |  Health: %s (%s)',
            $formatter->info((string) $avgCC),
            $formatter->info((string) $avgMI),
            $formatter->bold($grade),
            $formatter->info((string) $healthScore),
        ));
        $output->outNl(sprintf(
            ' Errors: %s  |  Warnings: %s  |  Info: %s',
            $errStr,
            $warnStr,
            $infoStr,
        ));
        $output->outNl(' Report: ' . $formatter->dim($reportFile));
        $output->outNl($line);
        $output->outNl();
    }

    private function generateClaudeMd(Config $config, DataProviderFactory $dataProviderFactory, CliOutput $output): void
    {
        $output->outWithMemory('Generating CLAUDE.md...');

        $projectData = $dataProviderFactory->getProjectDataProvider()->getTemplateData();
        $problemData = $dataProviderFactory->getProblemDataProvider()->getTemplateData();
        $gitData = $dataProviderFactory->getGitDataProvider()->getTemplateData();

        $metrics = $projectData['elements'] ?? [];
        $lines = [];

        $lines[] = '# Project Architecture (auto-generated by PhpCodeArcheology)';
        $lines[] = '';
        $lines[] = '## Overview';
        $lines[] = '';

        $overviewMetrics = [
            'overallFiles' => 'Files',
            'overallClasses' => 'Classes',
            'overallFunctionCount' => 'Functions',
            'overallMethodsCount' => 'Methods',
            'overallLoc' => 'Lines of Code',
            'overallLloc' => 'Logical Lines of Code',
        ];

        foreach ($overviewMetrics as $key => $label) {
            if (isset($metrics[$key]) && $metrics[$key] instanceof \PhpCodeArch\Metrics\Model\MetricValue) {
                $lines[] = '- ' . $label . ': ' . $metrics[$key]->getValueFormatted();
            }
        }

        $lines[] = '';
        $lines[] = '## Code Quality';
        $lines[] = '';

        $qualityMetrics = [
            'overallAvgCC' => 'Avg Cyclomatic Complexity',
            'overallAvgMI' => 'Avg Maintainability Index',
            'healthScore' => 'Health Score',
            'overallTechnicalDebtScore' => 'Technical Debt Score',
        ];

        foreach ($qualityMetrics as $key => $label) {
            if (isset($metrics[$key]) && $metrics[$key] instanceof \PhpCodeArch\Metrics\Model\MetricValue) {
                $lines[] = '- ' . $label . ': ' . $metrics[$key]->getValueFormatted();
            }
        }

        $lines[] = '';
        $lines[] = '## Package Structure';
        $lines[] = '';

        $packagesData = $dataProviderFactory->getPackagDataProvider()->getTemplateData();
        $packages = $packagesData['packages'] ?? [];
        if (!empty($packages)) {
            foreach (array_slice($packages, 0, 20, true) as $packageKey => $package) {
                $name = method_exists($package, 'getName') ? $package->getName() : $packageKey;
                $lines[] = '- `' . $name . '`';
            }
        } else {
            $lines[] = 'No package data available.';
        }

        $lines[] = '';
        $lines[] = '## Top Problems';
        $lines[] = '';

        $allProblems = [];
        foreach (['fileProblems', 'classProblems', 'functionProblems'] as $key) {
            foreach ($problemData[$key] ?? [] as $entityId => $entityProblems) {
                $data = $entityProblems['data'] ?? null;
                $name = ($data !== null && method_exists($data, 'getName')) ? $data->getName() : $entityId;

                foreach ($entityProblems['problems'] ?? [] as $problem) {
                    $allProblems[] = [
                        'level' => $problem->getProblemLevel(),
                        'entity' => $name,
                        'message' => $problem->getMessage(),
                        'recommendation' => $problem->getRecommendation(),
                    ];
                }
            }
        }

        usort($allProblems, fn($a, $b) => $b['level'] <=> $a['level']);

        foreach (array_slice($allProblems, 0, 15) as $problem) {
            $levelStr = match ($problem['level']) {
                PredictionInterface::ERROR => 'ERROR',
                PredictionInterface::WARNING => 'WARNING',
                default => 'INFO',
            };
            $lines[] = '- [' . $levelStr . '] ' . $problem['entity'] . ': ' . $problem['message'];
            if ($problem['recommendation'] !== '') {
                $lines[] = '  - Recommendation: ' . $problem['recommendation'];
            }
        }

        $lines[] = '';
        $lines[] = '## Recommendations';
        $lines[] = '';

        $errorCount = $metrics['overallErrorCount']?->getValue() ?? 0;
        $warningCount = $metrics['overallWarningCount']?->getValue() ?? 0;

        if ($errorCount > 0) {
            $lines[] = '- Fix ' . $errorCount . ' errors as highest priority';
        }
        if ($warningCount > 0) {
            $lines[] = '- Address ' . $warningCount . ' warnings to improve code quality';
        }

        $hotspots = array_slice($gitData['hotspots'] ?? [], 0, 5);
        if (!empty($hotspots)) {
            $lines[] = '- Refactor these hotspots (high churn + complexity):';
            foreach ($hotspots as $hotspot) {
                $lines[] = '  - `' . $hotspot['name'] . '` (churn:' . $hotspot['churn'] . ' cc:' . $hotspot['cc'] . ')';
            }
        }

        $lines[] = '';

        $claudeMdPath = ($config->get('runningDir') ?? getcwd()) . DIRECTORY_SEPARATOR . 'CLAUDE.md';
        file_put_contents($claudeMdPath, implode("\n", $lines));

        $formatter = $output->getFormatter() ?? new CliFormatter();
        $output->outNl($formatter->success('CLAUDE.md generated at ' . $claudeMdPath));
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
