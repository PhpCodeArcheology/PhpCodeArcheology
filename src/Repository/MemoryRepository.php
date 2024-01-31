<?php

declare(strict_types=1);

namespace PhpCodeArch\Repository;

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

class MemoryRepository implements RepositoryInterface
{
    public function __construct(private readonly MetricsController $metricsController)
    {}

    public function saveMetricValue(MetricCollectionTypeEnum|null $metricCollectionType, array|string|null $identifier, mixed $value, ?string $key): void
    {
        $this->metricsController->setMetricValueByIdentifierString(
            $this->handleIdentifier($metricCollectionType, $identifier),
            $key,
            $value
        );
    }

    public function saveMetricValues(MetricCollectionTypeEnum|null $metricCollectionType, array|string|null $identifier, array $keyValuePairs): void
    {
        $this->metricsController->setMetricValuesByIdentifierString(
            $this->handleIdentifier($metricCollectionType, $identifier),
            $keyValuePairs
        );
    }

    public function saveCollection(MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, CollectionInterface $collection, string $collectionKey): void
    {
        $this->metricsController->setCollection(
            $metricCollectionType,
            $identifier,
            $collection,
            $collectionKey
        );
    }

    public function createMetricCollection(MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier,): MetricsCollectionInterface
    {
        return $this->metricsController->createMetricCollection(
            $metricCollectionType,
            $identifier,
        );
    }

    private function handleIdentifier(?MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier): ?string
    {
        if (is_string($identifier)) {
            return $identifier;
        }

        return MetricsController::getIdentifier($metricCollectionType, $identifier);
    }

    public function changeMetricValue(MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, string $projectMetricKey, string|\Closure $callback): void
    {
        $this->metricsController->changeMetricValue(
            $metricCollectionType,
            $identifier,
            $projectMetricKey,
            $callback
        );
    }

     public function setCollectionDataOrCreateEmptyCollection(MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, string $collectionKey, ?string $key, mixed $value, CollectionInterface $collection): void
    {
        $this->metricsController->setCollectionDataOrCreateEmptyCollection(
            $metricCollectionType,
            $identifier,
            $collectionKey,
            $key,
            $value,
            $collection
        );
    }

    public function setCollectionData(MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, string $collectionKey, ?string $key, mixed $value): void
    {
        $this->metricsController->setCollectionData(
            $metricCollectionType,
            $identifier,
            $collectionKey,
            $key,
            $value
        );
    }

    public function getMetricCollection(?MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier,): MetricsCollectionInterface
    {
        return $this->metricsController->getMetricCollectionByIdentifierString(
            $this->handleIdentifier($metricCollectionType, $identifier),
            $identifier
        );
    }

    public function saveCollectionDataUnique(MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, string $collectionKey, ?string $key, mixed $value): void
    {
        $this->metricsController->setCollectionDataUnique(
            $metricCollectionType,
            $identifier,
            $collectionKey,
            $key,
            $value
        );
    }

    public function loadMetricValue(?MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, string $key): ?MetricValue
    {
        return $this->metricsController->getMetricValueByIdentifierString(
            $this->handleIdentifier($metricCollectionType, $identifier),
            $key
        );
    }

    public function getAllMetricCollections(): array
    {
        return $this->metricsController->getAllCollections();
    }

    public function loadCollection(MetricCollectionTypeEnum|null $metricCollectionType, array|string|null $identifier, string $collectionKey): ?CollectionInterface
    {
        return $this->metricsController->getCollectionByIdentifierString(
            $this->handleIdentifier($metricCollectionType, $identifier),
            $collectionKey
        );
    }

    public function loadMetricValues(?MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, array $metricKeys): array
    {
        return $this->metricsController->getMetricValuesByIdentifierString(
            $this->handleIdentifier($metricCollectionType, $identifier),
            $metricKeys
        );
    }

    public function saveProblem(string $identifier, string $problemKey, ProblemInterface $problem): void
    {
        $this->metricsController->setProblemByIdentifierString($identifier, $problemKey, $problem);
    }

    public function setMetricTypeToMetricValue(MetricValue &$metricValue): void
    {
        $this->metricsController->setMetricTypeToMetricValue($metricValue);
    }

    public function getMetricCollectionsByCollectionKeys(MetricCollectionTypeEnum $metricCollectionType, array|string|null $identifier, string $collectionKey): array
    {
        return $this->metricsController->getMetricCollectionsByCollectionKeys(
            $metricCollectionType,
            $identifier,
            $collectionKey
        );
    }

    public function getMetricsByCollectionTypeAndVisibility(MetricCollectionTypeEnum $metricCollectionType, int $visibility, bool $showEverywhere = true): array
    {
        return $this->metricsController->getMetricsByCollectionTypeAndVisibility(
            $metricCollectionType,
            $visibility,
            $showEverywhere
        );
    }

    public function getListMetricsByCollectionType(MetricCollectionTypeEnum $metricCollectionType): array
    {
        return $this->metricsController->getListMetricsByCollectionType($metricCollectionType);
    }

    public function getDetailMetricsByCollectionType(MetricCollectionTypeEnum $metricCollectionType): array
    {
        return $this->metricsController->getDetailMetricsByCollectionType($metricCollectionType);
    }
}
