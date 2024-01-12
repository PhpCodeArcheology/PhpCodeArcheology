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
use PhpCodeArch\Metrics\Manager\MetricCategory;
use PhpCodeArch\Metrics\Manager\MetricsManager;
use PhpCodeArch\Metrics\Manager\MetricType;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\ProjectMetrics\ProjectMetrics;
use PhpCodeArch\Predictions\GodClassPrediction;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\PredictionService;
use PhpCodeArch\Predictions\TooComplexPrediction;
use PhpCodeArch\Predictions\TooDependentPrediction;
use PhpCodeArch\Predictions\TooLongPrediction;
use PhpCodeArch\Predictions\TooMuchHtmlPrediction;
use PhpCodeArch\Report\Data\DataProviderFactory;
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

    private MetricsManager $metricsManager;

    /**
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     * @throws ReportTypeNotSupported
     */
    public function run(array $argv): void
    {
        $this->metricsManager = new MetricsManager();

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

        $this->loadMetricTypes();

        $fileList = new FileList($config);
        $fileList->fetch();

        $metrics = new Metrics();

        $projectMetrics = new ProjectMetrics(implode(',', $config->get('files')));
        $metrics->set('project', $projectMetrics);

        $output = new CliOutput();

        $analyzer = new Analyzer(
            $config,
            (new ParserFactory())->createForNewestSupportedVersion(),
            new NodeTraverser(),
            $metrics,
            $this->metricsManager,
            $output);

        $analyzer->analyze($fileList);

        $packageIACalculator = new PackageInstabilityAbstractnessCalculator($metrics);

        $calculatorService = new CalculatorService([
            new FileCalculator($metrics, []),
            new VariablesCalculator($metrics, [
                'superglobalsUsed',
                'distinctSuperglobalsUsed',
                'variablesUsed',
                'distinctVariablesUsed',
                'constantsUsed',
                'distinctConstantsUsed',
                'superglobalMetric',
            ]),
            new CouplingCalculator($metrics, [
                'uses',
                'usesCount',
                'usedBy',
                'usedByCount',
                'usesInProject',
                'usesInProjectCount',
                'usesForInstabilityCount',
            ], $packageIACalculator),
            new ProjectCalculator($metrics, []),
        ], $metrics, $this->metricsManager, $output);

        $calculatorService->run();

        $predictions = new PredictionService([
            new TooLongPrediction(),
            new GodClassPrediction(),
            new TooComplexPrediction(),
            new TooDependentPrediction(),
            new TooMuchHtmlPrediction(),
        ], $metrics, $output);
        $predictions->predict();

        $problems = $predictions->getProblemCount();
        $projectMetrics->set('OverallInformationCount', $problems[PredictionInterface::INFO]);
        $projectMetrics->set('OverallWarningCount', $problems[PredictionInterface::WARNING]);
        $projectMetrics->set('OverallErrorCount', $problems[PredictionInterface::ERROR]);
        $metrics->set('project', $projectMetrics);

        $splitter = new MetricsSplitter($metrics, $output);
        $splitter->split();

        $reportData = new DataProviderFactory($metrics, $this->metricsManager);

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
    }

    private function loadMetricTypes(): void
    {
        $metricTypes = require __DIR__ . '/../../data/metric-types.php';

        $categoryMap = [];

        foreach ($metricTypes as $metricTypeArray) {
            $categories = array_pop($metricTypeArray);

            $metricType = MetricType::fromArray($metricTypeArray);

            foreach ($categories as $categoryName) {
                if (! isset($categoryMap[$categoryName])) {
                    $categoryMap[$categoryName] = MetricCategory::ofName($categoryName);
                }

                $metricCategory = $categoryMap[$categoryName];
                $this->metricsManager->addMetricType($metricType, $metricCategory);
            }
        }
    }
}
