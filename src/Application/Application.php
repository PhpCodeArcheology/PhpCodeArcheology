<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

use Marcus\PhpLegacyAnalyzer\Application\ConfigFile\ConfigFileFinder;
use Marcus\PhpLegacyAnalyzer\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use Marcus\PhpLegacyAnalyzer\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use Marcus\PhpLegacyAnalyzer\Calculators\CalculatorService;
use Marcus\PhpLegacyAnalyzer\Calculators\CouplingCalculator;
use Marcus\PhpLegacyAnalyzer\Calculators\FileCalculator;
use Marcus\PhpLegacyAnalyzer\Calculators\ProjectCalculator;
use Marcus\PhpLegacyAnalyzer\Calculators\VariablesCalculator;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;
use Marcus\PhpLegacyAnalyzer\Predictions\GodClassPrediction;
use Marcus\PhpLegacyAnalyzer\Predictions\PredictionInterface;
use Marcus\PhpLegacyAnalyzer\Predictions\PredictionService;
use Marcus\PhpLegacyAnalyzer\Predictions\TooComplexPrediction;
use Marcus\PhpLegacyAnalyzer\Predictions\TooDependentPrediction;
use Marcus\PhpLegacyAnalyzer\Predictions\TooLongPrediction;
use Marcus\PhpLegacyAnalyzer\Predictions\TooMuchHtmlPrediction;
use Marcus\PhpLegacyAnalyzer\Report\MarkdownReport;
use Marcus\PhpLegacyAnalyzer\Report\MetricsSplitter;
use Marcus\PhpLegacyAnalyzer\Report\ReportData;
use Marcus\PhpLegacyAnalyzer\Report\ReportFactory;
use Marcus\PhpLegacyAnalyzer\Report\ReportTypeNotSupported;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final class Application
{
    const VERSION = '0.0.1';

    /**
     * @throws ConfigFileExtensionNotSupportedException
     * @throws MultipleConfigFilesException
     * @throws ReportTypeNotSupported
     */
    public function run(array $argv): void
    {
        $config = (new ArgumentParser())->parse($argv);
        $config->set('runningDir', getcwd());

        $configFileFinder = new ConfigFileFinder($config);
        $configFileFinder->checkRunningDir();

        try {
            $config->validate();
        } catch (ConfigException $e) {
            echo "Fehler: {$e->getMessage()}";
            exit;
        }

        $fileList = new FileList($config);
        $fileList->fetch();

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser();
        $metrics = new Metrics();

        $projectMetrics = new ProjectMetrics(implode(',', $config->get('files')));
        $metrics->set('project', $projectMetrics);

        $output = new CliOutput();

        $analyzer = new Analyzer($config, $parser, $traverser, $metrics, $output);
        $analyzer->analyze($fileList);

        $calculators = new CalculatorService([
            new FileCalculator($metrics),
            new VariablesCalculator($metrics),
            new CouplingCalculator($metrics),
            new ProjectCalculator($metrics),
        ], $metrics);
        $calculators->run();

        $predictions = new PredictionService([
            new TooLongPrediction(),
            new GodClassPrediction(),
            new TooComplexPrediction(),
            new TooDependentPrediction(),
            new TooMuchHtmlPrediction(),
        ], $metrics);
        $predictions->predict();

        $problems = $predictions->getProblemCount();
        $projectMetrics->set('OverallInformationCount', $problems[PredictionInterface::INFO]);
        $projectMetrics->set('OverallWarningCount', $problems[PredictionInterface::WARNING]);
        $projectMetrics->set('OverallErrorCount', $problems[PredictionInterface::ERROR]);
        $metrics->set('project', $projectMetrics);

        $splitter = new MetricsSplitter($metrics);
        $splitter->split();

        $reportData = new ReportData($metrics);

        $twigLoader = new FilesystemLoader();
        $twig = new Environment($twigLoader, [
            'debug' => true,
        ]);
        $twig->addExtension(new DebugExtension());

        $report = ReportFactory::create($config->get('reportType'), $config, $reportData, $twigLoader, $twig);
        $report->generate();
    }
}
