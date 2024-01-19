<?php

declare(strict_types=1);

namespace PhpCodeArch\Report\Helper;

use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Report\Data\ReportDataCollection;
use PhpCodeArch\Report\Data\ReportDataContainer;

/**
 * MetricsSplitter
 *
 * Splits files, functions and classes into data arrays.
 */
readonly class MetricsSplitter
{
    public function __construct(
        private MetricsController    $metricsController,
        private ReportDataContainer $dataContainer,
        private CliOutput            $output)
    {
    }

    public function split(): void
    {
        $fileCollection = new ReportDataCollection();
        $classCollection = new ReportDataCollection();
        $functionCollection = new ReportDataCollection();
        $packageCollection = new ReportDataCollection();

        $count = 0;
        $countSum = number_format(count($this->metricsController->getAllCollections()));
        foreach ($this->metricsController->getAllCollections() as $metric) {
            $this->output->cls();
            $this->output->out(
                "Splitting metric \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$countSum\033[0m... " .
                memory_get_usage() . " bytes of memory"
            );

            ++ $count;

            switch (true) {
                case $metric instanceof PackageMetricsCollection:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['name'] = $metric->getName();

                    $packageCollection->set($data, $data['id']);
                    break;

                case $metric instanceof FileMetricsCollection:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['name'] = $metric->getName();

                    $fileCollection->set($data, $data['id']);
                    break;

                case $metric instanceof FunctionMetricsCollection:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();

                    $functionCollection->set($data, $data['id']);
                    break;

                case $metric instanceof ClassMetricsCollection:
                    $data = $metric->getAll();
                    $data['id'] = (string) $metric->getIdentifier();
                    $data['path'] = $metric->getPath();
                    $data['name'] = $metric->getName();
                    $data['internal'] = true;

                    $classCollection->set($data, $data['id']);
                    break;
            }
        }

        $this->output->outNl();

        $count = 0;
        $countSum = number_format(count($fileCollection));
        foreach ($fileCollection as &$data) {
            $this->output->cls();
            $this->output->out(
                "Setting up file \033[34m" .
                number_format($count + 1) .
                "\033[0m of \033[32m$countSum\033[0m... " .
                memory_get_usage() . " bytes of memory"
            );

            ++ $count;

            $path = $data['name'];

            $classesInFile = array_filter($classCollection->getAll(), function($class) use ($path) {
                return $path === $class['path'];
            });

            $functionsInFile = array_filter($functionCollection->getAll(), function($function) use ($path) {
                return $path === $function['path'];
            });

            $data['classes'] = $classesInFile;
            $data['functions'] = $functionsInFile;
        }

        $this->output->outNl();

        $this->dataContainer->set('files', $fileCollection);
        $this->dataContainer->set('classes', $classCollection);
        $this->dataContainer->set('functions', $functionCollection);
        $this->dataContainer->set('packages', $packageCollection);
    }
}
