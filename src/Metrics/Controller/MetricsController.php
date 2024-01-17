<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\Identity\FileIdentifier;
use PhpCodeArch\Metrics\Identity\FunctionAndClassIdentifier;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsFactory;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricType;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

class MetricsController
{
    /**
     * @var array<string, array<MetricType>>
     */
    private array $metricTypes = [];

    /**
     * @var MetricType[]
     */
    private array $metricTypeMap = [];

    /**
     * @param MetricsContainer $metricsContainer
     */
    public function __construct(private readonly MetricsContainer $metricsContainer)
    {
    }

    public function getContainerCount(): int
    {
        return $this->metricsContainer->getCount();
    }

    public function getAllCollections(): array
    {
        return $this->metricsContainer->getAll();
    }

    /**
     * @return void
     */
    public function registerMetricTypes(): void
    {
        $metricTypes = require __DIR__ . '/../../../data/metric-types.php';

        foreach ($metricTypes as $metricTypeArray) {
            $collections = array_pop($metricTypeArray);
            $metricType = MetricType::fromArray($metricTypeArray);

            foreach ($collections as $collection) {
                $this->addMetricType($metricType, $collection);
            }
        }
    }

    /**
     * @param MetricType $metricType
     * @param MetricCollectionTypeEnum $collectionType
     * @return void
     */
    private function addMetricType(MetricType $metricType, MetricCollectionTypeEnum $collectionType): void
    {
        if (! isset($this->metricTypes[$collectionType->name])) {
            $this->metricTypes[$collectionType->name] = [];
        }

        if (in_array($metricType, $this->metricTypes[$collectionType->name])) {
            return;
        }

        $this->metricTypes[$collectionType->name][] = $metricType;

        if (in_array($metricType, $this->metricTypeMap)) {
            return;
        }

        $this->metricTypeMap[$metricType->getKey()] = $metricType;
    }

    /**
     * @param MetricCollectionTypeEnum $collectionType
     * @return array|MetricType[]
     */
    public function getMetricTypesOfCollection(MetricCollectionTypeEnum $collectionType): array
    {
        if (! isset($this->metricTypes[$collectionType->name])) {
            return [];
        }

        return $this->metricTypes[$collectionType->name];
    }

    /**
     * @param MetricCollectionTypeEnum $collectionType
     * @param int $visibility
     * @return array
     */
    public function getMetricsByCollectionTypeAndVisibility(MetricCollectionTypeEnum $collectionType, int $visibility): array
    {
        if (! isset($this->metricTypes[$collectionType->name])) {
            return [];
        }

        return array_filter($this->metricTypes[$collectionType->name], function($metricType) use ($visibility) {
           return $metricType->getVisibility() === $visibility || $metricType->getVisibility() === MetricType::SHOW_EVERYWHERE;
        });
    }

    /**
     * @param MetricCollectionTypeEnum $collectionType
     * @return array
     */
    public function getDetailMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->getMetricsByCollectionTypeAndVisibility($collectionType, MetricType::SHOW_IN_DETAILS);
    }

    /**
     * @param MetricCollectionTypeEnum $collectionType
     * @return array
     */
    public function getListMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->getMetricsByCollectionTypeAndVisibility($collectionType, MetricType::SHOW_IN_LIST);
    }

    /**
     * @param string $key
     * @return MetricType|null
     */
    public function getMetricTypeByKey(string $key): ?MetricType
    {
        if (! isset($this->metricTypeMap[$key])) {
            return null;
        }

        return $this->metricTypeMap[$key];
    }

    /**
     * @param array $keys
     * @return array
     */
    public function getMetricTypesByKeys(array $keys): array
    {
        $types = [];

        foreach ($keys as $key) {
            $types[$key] = $this->getMetricTypeByKey($key);
        }

        return $types;
    }

    /**
     * @param array $files
     */
    public function createProjectMetricsCollection(array $files): ProjectMetricsCollection
    {
        $projectMetrics = new ProjectMetricsCollection(implode(',', $files));
        $this->metricsContainer->set(
            MetricCollectionTypeEnum::ProjectCollection->name,
            $projectMetrics
        );

        return $projectMetrics;
    }

    public function createMetricCollection(
        MetricCollectionTypeEnum $metricsType,
        array $identifierData): MetricsCollectionInterface
    {
        return match ($metricsType) {
            MetricCollectionTypeEnum::ProjectCollection => $this->createProjectMetricsCollection($identifierData['files']),
            MetricCollectionTypeEnum::FileCollection => $this->createFileMetricsCollection($identifierData['path']),
            MetricCollectionTypeEnum::ClassCollection => $this->createClassMetricsCollection($identifierData['path'], $identifierData['name']),
            MetricCollectionTypeEnum::PackageCollection => $this->createPackageMetricsCollection($identifierData['name']),
            MetricCollectionTypeEnum::MethodCollection, MetricCollectionTypeEnum::FunctionCollection => $this->createFunctionMetricsCollection($identifierData['path'], $identifierData['name']),
        };
    }

    public function getMetricValue(
        MetricCollectionTypeEnum $metricsType,
        ?array $identifierData,
        string $key): ?MetricValue
    {
        $identifierString = $this->getIdentifier($metricsType, $identifierData);

        return $this->metricsContainer->get($identifierString)->get($key);
    }

    public function setMetricValue(
        MetricCollectionTypeEnum $metricsType,
        ?array $identifierData,
        mixed $value,
        string $key): void
    {
        $identifierString = $this->getIdentifier($metricsType, $identifierData);
        $this->setMetricDataWithIdentifierString($identifierString, $key, $value);
    }

    public function setCollection(
        MetricCollectionTypeEnum $metricsType,
        ?array $identifierData,
        CollectionInterface $collection,
        string $key): void
    {
        $identifierString = $this->getIdentifier($metricsType, $identifierData);

        $this->metricsContainer->get($identifierString)->setCollection($key, $collection);
    }

    public function changeMetricValue(
        MetricCollectionTypeEnum $metricsType,
        ?array $identifierData,
        string $key,
        string|\Closure $callback
    ): void
    {
        $value = $this->getMetricValue($metricsType, $identifierData, $key)?->getValue() ?? null;
        $this->setMetricValue($metricsType, $identifierData, call_user_func($callback, $value), $key);
    }

    private function setMetricDataWithIdentifierString(string $identifierString, string $key, mixed $value): void
    {
        $this->metricsContainer->get($identifierString)->set(
            $key,
            MetricValue::ofValueAndType(
                $value,
                $this->metricTypeMap[$key]
            )
        );
    }

    public function setMetricValues(
        MetricCollectionTypeEnum $metricsType,
        ?array $identifierData,
        array $keyValuePairs): void
    {
        $identifierString = $this->getIdentifier($metricsType, $identifierData);

        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricDataWithIdentifierString($identifierString, $key, $value);
        }
    }

    public function pushMetricsCollection(MetricsCollectionInterface $fileMetrics): void
    {
        $this->metricsContainer->push($fileMetrics);
    }

    public function getFunctionMetrics(mixed $namespacedName, string $path): FunctionMetricsCollection
    {
        return FunctionMetricsFactory::createFromMetricsByNameAndPath(
            $this->metricsContainer,
            $namespacedName,
            $path
        );
    }

    private function getIdentifier(MetricCollectionTypeEnum $metricsType, ?array $identifierData): string
    {
        return match ($metricsType) {
            MetricCollectionTypeEnum::ProjectCollection => $metricsType->name,
            MetricCollectionTypeEnum::FileCollection => (string)FileIdentifier::ofPath($identifierData['path']),
            MetricCollectionTypeEnum::ClassCollection, MetricCollectionTypeEnum::FunctionCollection, MetricCollectionTypeEnum::MethodCollection => (string)FunctionAndClassIdentifier::ofNameAndPath(
                $identifierData['name'],
                $identifierData['path']
            ),
            MetricCollectionTypeEnum::PackageCollection => $identifierData['name'],
            default => 'unidentified',
        };
    }

    private function createFunctionMetricsCollection(string $path, string $name): FunctionMetricsCollection
    {
        $functionMetrics = new FunctionMetricsCollection($path, $name);
        $this->metricsContainer->set(
            (string) $functionMetrics->getIdentifier(),
            $functionMetrics
        );

        return $functionMetrics;
    }

    private function createFileMetricsCollection(string $path): FileMetricsCollection
    {
        $fileMetrics = new FileMetricsCollection($path);
        $this->metricsContainer->set(
            (string) $fileMetrics->getIdentifier(),
            $fileMetrics
        );

        return $fileMetrics;
    }

    public function getMetricCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData): MetricsCollectionInterface
    {
        $identifierString = $this->getIdentifier($metricsType, $identifierData);

        return $this->getMetricCollectionByIdentifierString($identifierString);
    }

    public function getMetricCollectionByIdentifierString(string $identifierString): MetricsCollectionInterface
    {
        return $this->metricsContainer->get($identifierString);
    }

    private function createClassMetricsCollection(mixed $path, string $name): ClassMetricsCollection
    {
        $classMetrics = new ClassMetricsCollection($path, $name);
        $this->metricsContainer->set(
            (string) $classMetrics->getIdentifier(),
            $classMetrics
        );

        return $classMetrics;
    }

    public function setCollectionData(
        MetricCollectionTypeEnum $metricsType,
        ?array $identifierData,
        string $collectionKey,
        ?string $key,
        mixed $value): void
    {
        $this->getCollection($metricsType, $identifierData, $collectionKey)->set($value, $key);
    }

    public function setCollectionDataUnique(
        MetricCollectionTypeEnum $metricsType,
        ?array $identifierData,
        string $collectionKey,
        ?string $key,
        mixed $value): void
    {
        $this->getCollection($metricsType, $identifierData, $collectionKey)->setUnique($value, $key);
    }

    public function setCollectionDataOrCreateEmptyCollection(
        MetricCollectionTypeEnum $metricsType,
        ?string $identifierData,
        string $collectionKey,
        ?string $key,
        mixed $value,
        CollectionInterface $collection): void
    {
        $foundCollection = $this->getCollection($metricsType, $identifierData, $collectionKey);

        if ($foundCollection === null) {
            $this->setCollection($metricsType, $identifierData, $collection, $collectionKey);
        }

        $this->setCollectionData($metricsType, $identifierData, $collectionKey, $key, $value);
    }


    private function createPackageMetricsCollection(string $name): PackageMetricsCollection
    {
        $packageMetrics = new PackageMetricsCollection($name);
        $this->metricsContainer->set(
            (string) $packageMetrics->getIdentifier(),
            $packageMetrics
        );

        return $packageMetrics;
    }

    public function getCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey): ?CollectionInterface
    {
        return $this->getMetricCollection($metricsType, $identifierData)->getCollection($collectionKey);
    }

    public function getMetricValues(MetricCollectionTypeEnum $metricsType, array $identifierData, array $keys): object
    {
        $metricValues = [];

        foreach ($keys as $key) {
            $metricValues[$key] = $this->getMetricValue($metricsType, $identifierData, $key);
        }

        return (object) $metricValues;
    }

    public function setMetricValuesByIdentifierString(string $identifierString, array $keyValuePairs): void
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricDataWithIdentifierString($identifierString, $key, $value);
        }
    }

    public function getMetricValueByIdentifierString(string $identifierString, string $key): ?MetricValue
    {
        return $this->metricsContainer->get($identifierString)->get($key);
    }

    public function getCollectionByIdentifierString(string $identifierString, string $collectionKey): ?CollectionInterface
    {
        return $this->metricsContainer->get($identifierString)->getCollection($collectionKey);
    }

    /**
     * @param string $identifierString
     * @param array $metricKeys
     * @return MetricValue[]|null[]
     */
    public function getMetricValuesByIdentifierString(string $identifierString, array $metricKeys): array
    {
        $metricValues = [];

        foreach ($metricKeys as $key) {
            $metricValues[$key] = $this->getMetricValueByIdentifierString($identifierString, $key);
        }

        return $metricValues;
    }
}
