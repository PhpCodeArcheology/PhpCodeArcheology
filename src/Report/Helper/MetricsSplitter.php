<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\Helper;

use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\ProgressBar;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;

/**
 * MetricsSplitter.
 *
 * Splits files, functions and classes into data arrays.
 */
readonly class MetricsSplitter
{
    public function __construct(
        private MetricsRegistryInterface $registry,
        private CliOutput $output)
    {
    }

    /**
     * @return array{files: array<string, array<string, mixed>>, classes: array<string, array<string, mixed>>, functions: array<string, array<string, mixed>>, packages: array<string, array<string, mixed>>}
     */
    public function split(): array
    {
        /** @var array<string, array<string, mixed>> $fileCollection */
        $fileCollection = [];
        /** @var array<string, array<string, mixed>> $classCollection */
        $classCollection = [];
        /** @var array<string, array<string, mixed>> $functionCollection */
        $functionCollection = [];
        /** @var array<string, array<string, mixed>> $packageCollection */
        $packageCollection = [];

        $formatter = $this->output->getFormatter() ?? new CliFormatter();
        $allCollections = $this->registry->getAllCollections();
        $progressBar = new ProgressBar($this->output, $formatter, count($allCollections), 'Splitting');

        foreach ($allCollections as $metric) {
            $progressBar->advance();

            $id = (string) $metric->getIdentifier();

            switch (true) {
                case $metric instanceof PackageMetricsCollection:
                    $data = $this->metricValuesToArray($metric->getAll());
                    $data['id'] = $id;
                    $data['name'] = $metric->getName();
                    $packageCollection[$id] = $data;
                    break;

                case $metric instanceof FileMetricsCollection:
                    $data = $this->metricValuesToArray($metric->getAll());
                    $data['id'] = $id;
                    $data['name'] = $metric->getName();
                    $fileCollection[$id] = $data;
                    break;

                case $metric instanceof FunctionMetricsCollection:
                    $data = $this->metricValuesToArray($metric->getAll());
                    $data['id'] = $id;
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();
                    $functionCollection[$id] = $data;
                    break;

                case $metric instanceof ClassMetricsCollection:
                    $data = $this->metricValuesToArray($metric->getAll());
                    $data['id'] = $id;
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();
                    $data['internal'] = true;
                    $data['classUses'] = $metric->getCollection('usedClasses')?->getAsArray() ?? [];
                    $classCollection[$id] = $data;
                    break;
            }
        }

        $progressBar->finish();

        $fileProgressBar = new ProgressBar($this->output, $formatter, count($fileCollection), 'Setting up files');

        foreach ($fileCollection as $id => &$data) {
            $fileProgressBar->advance();

            $path = is_string($data['name'] ?? null) ? $data['name'] : '';

            $data['classes'] = array_filter(
                $classCollection,
                fn (array $class): bool => $path === ($class['path'] ?? null)
            );

            $data['functions'] = array_filter(
                $functionCollection,
                fn (array $function): bool => $path === ($function['path'] ?? null)
            );
        }
        unset($data);

        $fileProgressBar->finish();

        return [
            'files' => $fileCollection,
            'classes' => $classCollection,
            'functions' => $functionCollection,
            'packages' => $packageCollection,
        ];
    }

    /**
     * @param array<string, MetricValue> $metricValues
     *
     * @return array<string, mixed>
     */
    private function metricValuesToArray(array $metricValues): array
    {
        $result = [];
        foreach ($metricValues as $key => $metricValue) {
            $result[$key] = $metricValue->getValue();
        }

        return $result;
    }
}
