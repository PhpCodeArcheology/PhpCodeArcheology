<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

use Marcus\PhpLegacyAnalyzer\Calculators\CalculatorService;
use Marcus\PhpLegacyAnalyzer\Calculators\CouplingCalculator;
use Marcus\PhpLegacyAnalyzer\Calculators\FilenameCalculator;
use Marcus\PhpLegacyAnalyzer\Calculators\ProjectCalculator;
use Marcus\PhpLegacyAnalyzer\Calculators\VariablesCalculator;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;
use Marcus\PhpLegacyAnalyzer\Predictions\GodClassPrediction;
use Marcus\PhpLegacyAnalyzer\Predictions\PredictionInterface;
use Marcus\PhpLegacyAnalyzer\Predictions\PredictionService;
use Marcus\PhpLegacyAnalyzer\Predictions\TooComplexPrediction;
use Marcus\PhpLegacyAnalyzer\Predictions\TooLongPrediction;
use Marcus\PhpLegacyAnalyzer\Report\MarkdownReport;
use Marcus\PhpLegacyAnalyzer\Report\MetricsSplitter;
use Marcus\PhpLegacyAnalyzer\Report\ReportData;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Twig\Environment;
use Twig\Extension\DebugExtension;
use Twig\Loader\FilesystemLoader;

final class Application
{
    public function run(array $argv): void
    {
        $config = (new ArgumentParser())->parse($argv);
        $config->set('runningDir', getcwd());

        try {
            $config->validate();
        } catch (ConfigException $e) {
            echo "Fehler: {$e->getMessage()}";
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
            new FilenameCalculator($metrics),
            new VariablesCalculator($metrics),
            new CouplingCalculator($metrics),
            new ProjectCalculator($metrics),
        ], $metrics);
        $calculators->run();

        $predictions = new PredictionService([
            new TooLongPrediction(),
            new GodClassPrediction(),
            new TooComplexPrediction(),
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

        $report = new MarkdownReport($config, $reportData, $twigLoader, $twig);
        $report->generate();
    }
}
