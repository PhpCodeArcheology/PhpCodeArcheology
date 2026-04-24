<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\MetricCollectionFactory;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricTypeRegistry;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricType;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class MetricsRegistry implements MetricsRegistryInterface
{
    private readonly MetricTypeRegistry $typeRegistry;
    private readonly MetricCollectionFactory $collectionFactory;

    public function __construct(private readonly MetricsContainer $metricsContainer)
    {
        $this->typeRegistry = new MetricTypeRegistry();
        $this->collectionFactory = new MetricCollectionFactory($metricsContainer);
    }

    public function registerMetricTypes(): void
    {
        $this->typeRegistry->register();
    }

    /** @param string[] $files */
    public function createProjectMetricsCollection(array $files): ProjectMetricsCollection
    {
        return $this->collectionFactory->createProject($files);
    }

    /** @param array{path?: string, name?: string, files?: string[]} $identifierData */
    public function createMetricCollection(MetricCollectionTypeEnum $metricsType, array $identifierData): MetricsCollectionInterface
    {
        return $this->collectionFactory->create($metricsType, $identifierData);
    }

    /** @return array<string, MetricsCollectionInterface> */
    public function getAllCollections(): array
    {
        return $this->metricsContainer->getAll();
    }

    public function getContainerCount(): int
    {
        return $this->metricsContainer->getCount();
    }

    /** @return MetricType[] */
    public function getMetricsByCollectionTypeAndVisibility(MetricCollectionTypeEnum $collectionType, MetricVisibility $visibility, bool $showEverywhere = true): array
    {
        return $this->typeRegistry->getByCollectionTypeAndVisibility($collectionType, $visibility, $showEverywhere);
    }

    /** @return MetricType[] */
    public function getDetailMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->typeRegistry->getDetailMetrics($collectionType);
    }

    /** @return MetricType[] */
    public function getListMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->typeRegistry->getListMetrics($collectionType);
    }

    public function setMetricTypeToMetricValue(MetricValue $metricValue): void
    {
        $this->typeRegistry->applyTypeToValue($metricValue);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public static function getIdentifier(MetricCollectionTypeEnum $metricsType, ?array $identifierData): string
    {
        return match ($metricsType) {
            MetricCollectionTypeEnum::ProjectCollection => $metricsType->name,
            MetricCollectionTypeEnum::FileCollection => (string) FileIdentifier::ofPath($identifierData['path'] ?? ''),
            MetricCollectionTypeEnum::ClassCollection, MetricCollectionTypeEnum::FunctionCollection, MetricCollectionTypeEnum::MethodCollection => (string) FunctionAndClassIdentifier::ofNameAndPath(
                $identifierData['name'] ?? '',
                $identifierData['path'] ?? ''
            ),
            MetricCollectionTypeEnum::PackageCollection => $identifierData['name'] ?? '',
        };
    }
}
