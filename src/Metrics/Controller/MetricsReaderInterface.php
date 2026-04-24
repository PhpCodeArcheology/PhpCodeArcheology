<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;

interface MetricsReaderInterface
{
    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key): ?MetricValue;

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     * @param string[]                                                   $keys
     *
     * @return array<string, MetricValue|null>
     */
    public function getMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keys): array;

    public function getMetricValueByIdentifierString(string $identifierString, string $key): ?MetricValue;

    /**
     * @param string[] $metricKeys
     *
     * @return array<string, MetricValue|null>
     */
    public function getMetricValuesByIdentifierString(string $identifierString, array $metricKeys): array;

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey): ?CollectionInterface;

    public function getCollectionByIdentifierString(string $identifierString, string $collectionKey): ?CollectionInterface;

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getMetricCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData): MetricsCollectionInterface;

    public function getMetricCollectionByIdentifierString(string $identifierString): MetricsCollectionInterface;

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierArray
     *
     * @return array<string, MetricsCollectionInterface>
     */
    public function getMetricCollectionsByCollectionKeys(MetricCollectionTypeEnum $metricsType, ?array $identifierArray, string $collectionKey): array;
}
