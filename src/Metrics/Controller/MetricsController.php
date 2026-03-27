<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\MetricCollectionFactory;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricTypeRegistry;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
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

    // --- Delegation to MetricCollectionFactory ---

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

    // --- Container Access ---

    public function getContainerCount(): int
    {
        return $this->metricsContainer->getCount();
    }

    /** @return array<string, MetricsCollectionInterface> */
    public function getAllCollections(): array
    {
        return $this->metricsContainer->getAll();
    }

    // --- MetricValue CRUD ---

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key): ?MetricValue
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);

        return $this->metricsContainer->get($identifierString)->get($key);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, mixed $value, string $key): void
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        $this->setMetricValueByIdentifierString($identifierString, $key, $value);
    }

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     * @param array<string, mixed>                                       $keyValuePairs
     */
    public function setMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keyValuePairs): void
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricValueByIdentifierString($identifierString, $key, $value);
        }
    }

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     * @param string[]                                                   $keys
     *
     * @return array<string, MetricValue|null>
     */
    public function getMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keys): array
    {
        $metricValues = [];
        foreach ($keys as $key) {
            $metricValues[$key] = $this->getMetricValue($metricsType, $identifierData, $key);
        }

        return $metricValues;
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function changeMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key, callable $callback): void
    {
        $value = $this->getMetricValue($metricsType, $identifierData, $key)?->getValue() ?? null;
        $this->setMetricValue($metricsType, $identifierData, $callback($value), $key);
    }

    public function setMetricValueByIdentifierString(string $identifierString, string $key, mixed $value): void
    {
        $this->metricsContainer->get($identifierString)->set($key, MetricValue::ofValueAndTypeKey($value, $key));
    }

    /** @param array<string, mixed> $keyValuePairs */
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

    /**
     * @param string[] $metricKeys
     *
     * @return array<string, MetricValue|null>
     */
    public function getMetricValuesByIdentifierString(string $identifierString, array $metricKeys): array
    {
        $metricValues = [];
        foreach ($metricKeys as $key) {
            $metricValues[$key] = $this->getMetricValueByIdentifierString($identifierString, $key);
        }

        return $metricValues;
    }

    // --- Collection Access ---

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, CollectionInterface $collection, string $key): void
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);
        $this->metricsContainer->get($identifierString)->setCollection($key, $collection);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey): ?CollectionInterface
    {
        return $this->getMetricCollection($metricsType, $identifierData)->getCollection($collectionKey);
    }

    public function getCollectionByIdentifierString(string $identifierString, string $collectionKey): ?CollectionInterface
    {
        return $this->metricsContainer->get($identifierString)->getCollection($collectionKey);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionData(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void
    {
        $collection = $this->getCollection($metricsType, $identifierData, $collectionKey);
        if (null === $collection) {
            return;
        }
        $collection->set($value, $key);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionDataUnique(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void
    {
        $collection = $this->getCollection($metricsType, $identifierData, $collectionKey);
        if (null === $collection) {
            return;
        }
        $collection->setUnique($value, $key);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionDataOrCreateEmptyCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value, CollectionInterface $collection): void
    {
        $foundCollection = $this->getCollection($metricsType, $identifierData, $collectionKey);

        if (!$foundCollection instanceof CollectionInterface) {
            $this->setCollection($metricsType, $identifierData, $collection, $collectionKey);
        }

        $this->setCollectionData($metricsType, $identifierData, $collectionKey, $key, $value);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getMetricCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData): MetricsCollectionInterface
    {
        $identifierString = self::getIdentifier($metricsType, $identifierData);

        return $this->getMetricCollectionByIdentifierString($identifierString);
    }

    public function getMetricCollectionByIdentifierString(string $identifierString): MetricsCollectionInterface
    {
        return $this->metricsContainer->get($identifierString);
    }

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierArray
     *
     * @return array<string, MetricsCollectionInterface>
     */
    public function getMetricCollectionsByCollectionKeys(MetricCollectionTypeEnum $metricsType, ?array $identifierArray, string $collectionKey): array
    {
        $collection = $this->getCollection($metricsType, $identifierArray, $collectionKey);
        if (null === $collection) {
            return [];
        }
        $collectionArray = $collection->getAsArray();
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

    /**
     * @param string[] $keys
     *
     * @return \Generator<int, array{string, MetricsCollectionInterface}>
     */
    private function getMetricsByKeys(array $keys): \Generator
    {
        foreach ($this->metricsContainer->getAll() as $key => $metrics) {
            if (!in_array($key, $keys)) {
                continue;
            }
            yield [$key, $metrics];
        }
    }
}
