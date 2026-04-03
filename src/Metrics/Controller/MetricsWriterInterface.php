<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

interface MetricsWriterInterface
{
    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, mixed $value, string $key): void;

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     * @param array<string, mixed>                                       $keyValuePairs
     */
    public function setMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keyValuePairs): void;

    public function setMetricValueByIdentifierString(string $identifierString, string $key, mixed $value): void;

    /** @param array<string, mixed> $keyValuePairs */
    public function setMetricValuesByIdentifierString(string $identifierString, array $keyValuePairs): void;

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function changeMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key, callable $callback): void;

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, CollectionInterface $collection, string $key): void;

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionData(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void;

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionDataUnique(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void;

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionDataOrCreateEmptyCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value, CollectionInterface $collection): void;

    public function setProblemByIdentifierString(string $identifierString, string $key, ProblemInterface $problem): void;

    /** @param array{path?: string, name?: string, files?: string[]} $identifierData */
    public function createMetricCollection(MetricCollectionTypeEnum $metricsType, array $identifierData): MetricsCollectionInterface;

    /** @param string[] $files */
    public function createProjectMetricsCollection(array $files): ProjectMetricsCollection;

    public function registerMetricTypes(): void;

    public function setMetricTypeToMetricValue(MetricValue $metricValue): void;
}
