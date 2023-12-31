<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Report;

use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\Metrics;

/**
 * MetricsSplitter
 *
 * Splits files, functions and classes into data arrays.
 */
readonly class MetricsSplitter
{
    public function __construct(private Metrics $metrics)
    {
    }

    public function split(): void
    {
        $files = [];
        $classes = [];
        $functions = [];

        foreach ($this->metrics->getAll() as $metric) {
            switch (true) {
                case $metric instanceof FileMetrics:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['name'] = $metric->getName();
                    $files[$data['id']] = $data;
                    break;

                case $metric instanceof FunctionMetrics:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();
                    $functions[$data['id']] = $data;
                    break;

                case $metric instanceof ClassMetrics:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();
                    $data['internal'] = true;

                    $classes[$data['id']] = $data;
                    break;
            }
        }

        foreach ($files as &$data) {
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

        $projectMetrics = $this->metrics->get('project');
        $projectMetrics->set('files', $files);
        $projectMetrics->set('classes', $classes);
        $projectMetrics->set('functions', $functions);

        $this->metrics->set('project', $projectMetrics);
    }
}
