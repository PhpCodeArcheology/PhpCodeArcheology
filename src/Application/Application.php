<?php

declare(strict_types=1);

namespace PhpCodeArch\Application;

use PhpCodeArch\Application\ConfigFile\ConfigFileFinder;
use PhpCodeArch\Application\ConfigFile\Exceptions\ConfigFileExtensionNotSupportedException;
use PhpCodeArch\Application\ConfigFile\Exceptions\MultipleConfigFilesException;
use PhpCodeArch\Calculators\CalculatorService;
use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Calculators\FileCalculator;
use PhpCodeArch\Calculators\ProjectCalculator;
use PhpCodeArch\Calculators\VariablesCalculator;
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
        ], $metrics, $output);
        $calculators->run();

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

        $reportData = new DataProviderFactory($metrics);

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
}
