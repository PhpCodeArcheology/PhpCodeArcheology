<?php

declare(strict_types=1);

namespace PhpCodeArch\Repository;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

interface RepositoryInterface
{
    public function saveMetricValue(
        ?MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        mixed $value,
        ?string $key
    ): void;

    public function loadMetricValue(
        ?MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        string $key
    ): ?MetricValue;

    public function saveMetricValues(
        ?MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        array $keyValuePairs
    ): void;

    public function loadMetricValues(
        ?MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        array $metricKeys
    ): array;

    public function saveCollection(
        MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        CollectionInterface $collection,
        string $collectionKey
    ): void;

    public function setCollectionDataOrCreateEmptyCollection(
        MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        string $collectionKey,
        ?string $key,
        mixed $value,
        CollectionInterface $collection
    ): void;

    public function createMetricCollection(
        MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
    ): MetricsCollectionInterface;

    public function changeMetricValue(
        MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        string $projectMetricKey,
        string|\Closure $callback
    ): void;

    public function setCollectionData(
        MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        string $collectionKey,
        ?string $key,
        mixed $value
    ): void;

    public function getMetricCollection(
        ?MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
    ): MetricsCollectionInterface;

    public function saveCollectionDataUnique(
        MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        string $collectionKey,
        ?string $key,
        mixed $value
    ): void;

    public function getAllMetricCollections(): array;

    public function loadCollection(
        null|MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        string $collectionKey
    ): ?CollectionInterface;

    public function saveProblem(
        string $identifier,
        string $problemKey,
        ProblemInterface $problem
    ): void;

    public function setMetricTypeToMetricValue(MetricValue &$metricValue): void;

    public function getMetricCollectionsByCollectionKeys(
        MetricCollectionTypeEnum $metricCollectionType,
        null|array|string $identifier,
        string $collectionKey
    ): array;

    public function getMetricsByCollectionTypeAndVisibility(
        MetricCollectionTypeEnum $metricCollectionType,
        int $visibility,
        bool $showEverywhere = true
    ): array;

    public function getListMetricsByCollectionType(MetricCollectionTypeEnum $metricCollectionType): array;

    public function getDetailMetricsByCollectionType(MetricCollectionTypeEnum $metricCollectionType): array;
}
