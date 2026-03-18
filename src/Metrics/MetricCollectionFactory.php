<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics;

use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class MetricCollectionFactory
{
    public function __construct(private readonly MetricsContainer $metricsContainer)
    {
    }

    public function create(MetricCollectionTypeEnum $metricsType, array $identifierData): MetricsCollectionInterface
    {
        return match ($metricsType) {
            MetricCollectionTypeEnum::ProjectCollection => $this->createProject($identifierData['files']),
            MetricCollectionTypeEnum::FileCollection => $this->createFile($identifierData['path']),
            MetricCollectionTypeEnum::ClassCollection => $this->createClass($identifierData['path'], $identifierData['name']),
            MetricCollectionTypeEnum::PackageCollection => $this->createPackage($identifierData['name']),
            MetricCollectionTypeEnum::MethodCollection, MetricCollectionTypeEnum::FunctionCollection => $this->createFunction($identifierData['path'], $identifierData['name']),
        };
    }

    public function createProject(array $files): ProjectMetricsCollection
    {
        $projectMetrics = new ProjectMetricsCollection(implode(',', $files));
        $this->metricsContainer->set(
            MetricCollectionTypeEnum::ProjectCollection->name,
            $projectMetrics
        );

        return $projectMetrics;
    }

    private function createFile(string $path): FileMetricsCollection
    {
        $fileMetrics = new FileMetricsCollection($path);
        $this->metricsContainer->set(
            (string) $fileMetrics->getIdentifier(),
            $fileMetrics
        );

        return $fileMetrics;
    }

    private function createClass(mixed $path, string $name): ClassMetricsCollection
    {
        $classMetrics = new ClassMetricsCollection($path, $name);
        $this->metricsContainer->set(
            (string) $classMetrics->getIdentifier(),
            $classMetrics
        );

        return $classMetrics;
    }

    private function createFunction(string $path, string $name): FunctionMetricsCollection
    {
        $functionMetrics = new FunctionMetricsCollection($path, $name);
        $this->metricsContainer->set(
            (string) $functionMetrics->getIdentifier(),
            $functionMetrics
        );

        return $functionMetrics;
    }

    private function createPackage(string $name): PackageMetricsCollection
    {
        $packageMetrics = new PackageMetricsCollection($name);
        $this->metricsContainer->set(
            (string) $packageMetrics->getIdentifier(),
            $packageMetrics
        );

        return $packageMetrics;
    }
}
