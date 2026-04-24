<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricType;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

interface MetricsRegistryInterface
{
    public function registerMetricTypes(): void;

    /** @param string[] $files */
    public function createProjectMetricsCollection(array $files): ProjectMetricsCollection;

    /** @param array{path?: string, name?: string, files?: string[]} $identifierData */
    public function createMetricCollection(MetricCollectionTypeEnum $metricsType, array $identifierData): MetricsCollectionInterface;

    /** @return array<string, MetricsCollectionInterface> */
    public function getAllCollections(): array;

    public function getContainerCount(): int;

    /** @return MetricType[] */
    public function getMetricsByCollectionTypeAndVisibility(MetricCollectionTypeEnum $collectionType, MetricVisibility $visibility, bool $showEverywhere = true): array;

    /** @return MetricType[] */
    public function getDetailMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array;

    /** @return MetricType[] */
    public function getListMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array;

    public function setMetricTypeToMetricValue(MetricValue $metricValue): void;
}
