<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\Helper;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;

/**
 * MetricsSplitter
 *
 * Splits files, functions and classes into data arrays.
 */
readonly class MetricsSplitter
{
    public function __construct(private MetricsContainer $metrics, private CliOutput $output)
    {
    }

    public function split(): void
    {
        $files = [];
        $classes = [];
        $functions = [];

        $count = 0;
        $countSum = number_format(count($this->metrics->getAll()));
        foreach ($this->metrics->getAll() as $metric) {
            $this->output->cls();
            $this->output->out(
                "Splitting metric \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$countSum\033[0m... " .
                memory_get_usage() . " bytes of memory"
            );

            ++ $count;

            switch (true) {
                case $metric instanceof FileMetricsCollection:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['name'] = $metric->getName();
                    $files[$data['id']] = $data;
                    break;

                case $metric instanceof FunctionMetricsCollection:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();
                    $functions[$data['id']] = $data;
                    break;

                case $metric instanceof ClassMetricsCollection:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();
                    $data['internal'] = true;

                    $classes[$data['id']] = $data;
                    break;
            }
        }

        $this->output->outNl();

        $count = 0;
        $countSum = number_format(count($files));
        foreach ($files as &$data) {
            $this->output->cls();
            $this->output->out(
                "Setting up file \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$countSum\033[0m... " .
                memory_get_usage() . " bytes of memory"
            );

            ++ $count;

            $path = $data['name'];

            $classesInFile = array_filter($classes, function($class) use ($path) {
                return $path === $class['path'];
            });

            $functionsInFile = array_filter($functions, function($function) use ($path) {
                return $path === $function['path'];
            });

            $data['classes'] = $classesInFile;
            $data['functions'] = $functionsInFile;
        }

        $this->output->outNl();

        $projectMetrics = $this->metrics->get('project');
        $projectMetrics->set('files', $files);
        $projectMetrics->set('classes', $classes);
        $projectMetrics->set('functions', $functions);

        $this->metrics->set('project', $projectMetrics);
    }
}
