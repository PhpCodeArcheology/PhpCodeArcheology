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
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricType;
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
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final readonly class Application
{
    const VERSION = '0.3.10';

    /**
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     * @throws ReportTypeNotSupported
     */
    public function run(array $argv): void
    {
        ini_set('memory_limit', '512M');

        $config = $this->createConfig($argv);
        $fileList = $this->createFileList($config);

        $output = new CliOutput();

        $metricsController = $this->createMetricController($config);
        $this->createAndRunAnalyzer($config, $metricsController, $fileList, $output);

        $this->runCalculators($metricsController, $output);

        $problems = $this->runPredictors($metricsController, $output);
        $this->setProblems($metricsController, $problems);

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
            new FileCalculator($metricsController),
            new VariablesCalculator($metricsController),
            new CouplingCalculator($metricsController, $packageIACalculator),
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
    private function runPredictors(MetricsController $metricsController, CliOutput $output): array
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

    private function generateHistory(MetricsController $metricsController, Config $config): void
    {
        $outputDir = $config->get('reportDir') . DIRECTORY_SEPARATOR;

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

        file_put_contents($outputDir . 'history.json', json_encode($metricHistory));
    }

    private function getHistoryData(MetricsController $metricsController): \Generator
    {
        foreach ($metricsController->getAllCollections() as $metricCollectionKey => $metricCollection) {
            foreach ($this->getMetricValues($metricCollection) as $metricValue) {
                if ($metricValue->getMetricType()->getVisibility() === MetricType::SHOW_NOWHERE) {
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
        $historyFile = $outputDir . 'history.json';

        if (!file_exists($historyFile)) {
            return false;
        }

        $historyValueTypes = [
            MetricType::VALUE_INT,
            //MetricType::VALUE_COUNT,
            MetricType::VALUE_FLOAT,
            MetricType::VALUE_PERCENTAGE,
        ];

        $historyFileData = json_decode(file_get_contents($historyFile));
        $historyDate = \DateTimeImmutable::createFromFormat('Y-m-d-H-i-s', $historyFileData->date);
        unset($historyFileData);

        foreach ($this->getHistoryDataFromFile($historyFile) as $historyData) {
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

                if ($metricType->getVisibility() === MetricType::SHOW_NOWHERE || $metricType->getValueType() === MetricType::VALUE_STORAGE) {
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
                    case $better === MetricType::BETTER_LOW && $delta < 0:
                        $direction = 'down';
                        $isBetter = true;
                        break;

                    case $better === MetricType::BETTER_LOW && $delta > 0:
                        $direction = 'up';
                        $isBetter = false;
                        break;

                    case $better === MetricType::BETTER_HIGH && $delta > 0:
                        $direction = 'up';
                        $isBetter = true;
                        break;

                    case $better === MetricType::BETTER_HIGH && $delta < 0:
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

    private function getHistoryDataFromFile($file): \Generator
    {
        $jsonData = file_get_contents($file);
        $history = json_decode($jsonData);

        foreach ($history->data as $key => $historyData) {
            yield [
                'key' => $key,
                'data' => $historyData,
            ];
        }
    }
}
