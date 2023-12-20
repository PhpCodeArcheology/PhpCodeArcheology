<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Application;

use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;
use Marcus\PhpLegacyAnalyzer\Metrics\ProjectMetrics;
use Marcus\PhpLegacyAnalyzer\Report\MarkdownReport;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

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

        $report = new MarkdownReport($config, $metrics);
        $report->generate();
    }
}
