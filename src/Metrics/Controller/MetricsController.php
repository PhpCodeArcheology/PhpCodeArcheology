<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use Closure;
use Generator;
use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\MetricCollectionFactory;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricTypeRegistry;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricType;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

class MetricsController
{
    private readonly MetricTypeRegistry $typeRegistry;
    private readonly MetricCollectionFactory $collectionFactory;

    public function __construct(private readonly MetricsContainer $metricsContainer)
    {
        $this->typeRegistry = new MetricTypeRegistry();
        $this->collectionFactory = new MetricCollectionFactory($metricsContainer);
    }

    // --- Delegation to MetricTypeRegistry ---

    public function registerMetricTypes(): void
    {
        $this->typeRegistry->register();
    }

    public function getMetricsByCollectionTypeAndVisibility(MetricCollectionTypeEnum $collectionType, MetricVisibility $visibility, bool $showEverywhere = true): array
    {
        return $this->typeRegistry->getByCollectionTypeAndVisibility($collectionType, $visibility, $showEverywhere);
    }

    public function getDetailMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->typeRegistry->getDetailMetrics($collectionType);
    }

    public function getListMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->typeRegistry->getListMetrics($collectionType);
    }

    public function setMetricTypeToMetricValue(MetricValue $metricValue): void
    {
        $this->typeRegistry->applyTypeToValue($metricValue);
    }

    // --- Delegation to MetricCollectionFactory ---

    public function createProjectMetricsCollection(array $files): ProjectMetricsCollection
    {
        return $this->collectionFactory->createProject($files);
    }

    public function createMetricCollection(MetricCollectionTypeEnum $metricsType, array $identifierData): MetricsCollectionInterface
    {
        return $this->collectionFactory->create($metricsType, $identifierData);
    }

    // --- Container Access ---

    public function getContainerCount(): int
    {
        return $this->metricsContainer->getCount();
    }

    public function getAllCollections(): array
    {
        return $this->metricsContainer->getAll();
    }

    // --- MetricValue CRUD ---

    public function getMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key): ?MetricValue
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        return $this->metricsContainer->get($identifierString)->get($key);
    }

    public function setMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, mixed $value, string $key): void
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        $this->setMetricValueByIdentifierString($identifierString, $key, $value);
    }

    public function setMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keyValuePairs): void
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricValueByIdentifierString($identifierString, $key, $value);
        }
    }

    public function getMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keys): array
    {
        $metricValues = [];
        foreach ($keys as $key) {
            $metricValues[$key] = $this->getMetricValue($metricsType, $identifierData, $key);
        }
        return $metricValues;
    }

    public function changeMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key, string|Closure $callback): void
    {
        $value = $this->getMetricValue($metricsType, $identifierData, $key)?->getValue() ?? null;
        $this->setMetricValue($metricsType, $identifierData, call_user_func($callback, $value), $key);
    }

    public function setMetricValueByIdentifierString(string $identifierString, string $key, mixed $value): void
    {
        $this->metricsContainer->get($identifierString)->set($key, MetricValue::ofValueAndTypeKey($value, $key));
    }

    public function setMetricValuesByIdentifierString(string $identifierString, array $keyValuePairs): void
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricValueByIdentifierString($identifierString, $key, $value);
        }
    }

    public function getMetricValueByIdentifierString(string $identifierString, string $key): ?MetricValue
    {
        if (!$this->metricsContainer->has($identifierString)) {
            return null;
        }
        return $this->metricsContainer->get($identifierString)->get($key);
    }

    public function getMetricValuesByIdentifierString(string $identifierString, array $metricKeys): array
    {
        $metricValues = [];
        foreach ($metricKeys as $key) {
            $metricValues[$key] = $this->getMetricValueByIdentifierString($identifierString, $key);
        }
        return $metricValues;
    }

    // --- Collection Access ---

    public function setCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, CollectionInterface $collection, string $key): void
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        $this->metricsContainer->get($identifierString)->setCollection($key, $collection);
    }

    public function getCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey): ?CollectionInterface
    {
        return $this->getMetricCollection($metricsType, $identifierData)->getCollection($collectionKey);
    }

    public function getCollectionByIdentifierString(string $identifierString, string $collectionKey): ?CollectionInterface
    {
        return $this->metricsContainer->get($identifierString)->getCollection($collectionKey);
    }

    public function setCollectionData(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void
    {
        $this->getCollection($metricsType, $identifierData, $collectionKey)->set($value, $key);
    }

    public function setCollectionDataUnique(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void
    {
        $this->getCollection($metricsType, $identifierData, $collectionKey)->setUnique($value, $key);
    }

    public function setCollectionDataOrCreateEmptyCollection(MetricCollectionTypeEnum $metricsType, ?string $identifierData, string $collectionKey, ?string $key, mixed $value, CollectionInterface $collection): void
    {
        $foundCollection = $this->getCollection($metricsType, $identifierData, $collectionKey);

        if ($foundCollection === null) {
            $this->setCollection($metricsType, $identifierData, $collection, $collectionKey);
        }

        $this->setCollectionData($metricsType, $identifierData, $collectionKey, $key, $value);
    }

    public function getMetricCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData): MetricsCollectionInterface
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        return $this->getMetricCollectionByIdentifierString($identifierString);
    }

    public function getMetricCollectionByIdentifierString(string $identifierString): MetricsCollectionInterface
    {
        return $this->metricsContainer->get($identifierString);
    }

    public function getMetricCollectionsByCollectionKeys(MetricCollectionTypeEnum $metricsType, ?array $identifierArray, string $collectionKey): array
    {
        $collectionArray = $this->getCollection($metricsType, $identifierArray, $collectionKey)->getAsArray();
        $keys = array_keys($collectionArray);

        $metrics = [];
        foreach ($this->getMetricsByKeys($keys) as $metric) {
            $metrics[$metric[0]] = $metric[1];
        }

        return $metrics;
    }

    // --- Problems ---

    public function setProblemByIdentifierString(string $identifierString, string $key, ProblemInterface $problem): void
    {
        $this->metricsContainer->get($identifierString)->get($key)?->addProblem($problem);
    }

    // --- Helpers ---

    public static function getIdentifier(MetricCollectionTypeEnum $metricsType, ?array $identifierData): string
    {
        return match ($metricsType) {
            MetricCollectionTypeEnum::ProjectCollection => $metricsType->name,
            MetricCollectionTypeEnum::FileCollection => (string) FileIdentifier::ofPath($identifierData['path']),
            MetricCollectionTypeEnum::ClassCollection, MetricCollectionTypeEnum::FunctionCollection, MetricCollectionTypeEnum::MethodCollection => (string) FunctionAndClassIdentifier::ofNameAndPath(
                $identifierData['name'],
                $identifierData['path']
            ),
            MetricCollectionTypeEnum::PackageCollection => $identifierData['name'],
        };
    }

    private function getMetricsByKeys(array $keys): Generator
    {
        foreach ($this->metricsContainer->getAll() as $key => $metrics) {
            if (!in_array($key, $keys)) {
                continue;
            }
            yield [$key, $metrics];
        }
    }
}
