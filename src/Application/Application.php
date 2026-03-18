<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Calculators\DependencyCycleCalculator;
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
    public function run(array $argv): void
    {
        $config = $this->createConfig($argv);

        $memoryLimit = $config->get('memoryLimit') ?? '1G';
        ini_set('memory_limit', $memoryLimit);
        $fileList = $this->createFileList($config);

        $output = new CliOutput();

        $metricsController = $this->createMetricController($config);
        $this->createAndRunAnalyzer($config, $metricsController, $fileList, $output);

        $this->runCalculators($metricsController, $output);

        $problems = $this->runPredictors($metricsController, $output);
        $this->setProblems($metricsController, $problems);
        $this->calculateTechnicalDebt($metricsController);

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

        try {
            $config->validate();
        } catch (ConfigException $e) {
            echo PHP_EOL . "Error: {$e->getMessage()}";
            exit(1);
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
}
