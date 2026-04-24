<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricType;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

/**
 * Thin facade that implements all three narrow interfaces at once.
 *
 * @deprecated Production code uses MetricsRegistry, MetricsReader and
 * MetricsWriter directly (via ServiceFactory::createMetricsTriple). This
 * facade is retained as a test convenience that lets tests instantiate a
 * single object and polymorphically pass it wherever any of the three
 * interfaces is expected. A future release will migrate the tests onto the
 * trio and remove this class.
 */
class MetricsController implements MetricsRegistryInterface, MetricsReaderInterface, MetricsWriterInterface
{
    private readonly MetricsRegistry $registry;
    private readonly MetricsReader $reader;
    private readonly MetricsWriter $writer;

    public function __construct(MetricsContainer $metricsContainer)
    {
        $this->registry = new MetricsRegistry($metricsContainer);
        $this->reader = new MetricsReader($metricsContainer);
        $this->writer = new MetricsWriter($metricsContainer);
    }

    // --- Registry delegation ---

    public function registerMetricTypes(): void
    {
        $this->registry->registerMetricTypes();
    }

    /** @param string[] $files */
    public function createProjectMetricsCollection(array $files): ProjectMetricsCollection
    {
        return $this->registry->createProjectMetricsCollection($files);
    }

    /** @param array{path?: string, name?: string, files?: string[]} $identifierData */
    public function createMetricCollection(MetricCollectionTypeEnum $metricsType, array $identifierData): MetricsCollectionInterface
    {
        return $this->registry->createMetricCollection($metricsType, $identifierData);
    }

    /** @return array<string, MetricsCollectionInterface> */
    public function getAllCollections(): array
    {
        return $this->registry->getAllCollections();
    }

    public function getContainerCount(): int
    {
        return $this->registry->getContainerCount();
    }

    /** @return MetricType[] */
    public function getMetricsByCollectionTypeAndVisibility(MetricCollectionTypeEnum $collectionType, MetricVisibility $visibility, bool $showEverywhere = true): array
    {
        return $this->registry->getMetricsByCollectionTypeAndVisibility($collectionType, $visibility, $showEverywhere);
    }

    /** @return MetricType[] */
    public function getDetailMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->registry->getDetailMetricsByCollectionType($collectionType);
    }

    /** @return MetricType[] */
    public function getListMetricsByCollectionType(MetricCollectionTypeEnum $collectionType): array
    {
        return $this->registry->getListMetricsByCollectionType($collectionType);
    }

    public function setMetricTypeToMetricValue(MetricValue $metricValue): void
    {
        $this->registry->setMetricTypeToMetricValue($metricValue);
    }

    /**
     * @deprecated use MetricsRegistry::getIdentifier() directly
     *
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     */
    public static function getIdentifier(MetricCollectionTypeEnum $metricsType, ?array $identifierData): string
    {
        return MetricsRegistry::getIdentifier($metricsType, $identifierData);
    }

    // --- Reader delegation ---

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key): ?MetricValue
    {
        return $this->reader->getMetricValue($metricsType, $identifierData, $key);
    }

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     * @param string[]                                                   $keys
     *
     * @return array<string, MetricValue|null>
     */
    public function getMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keys): array
    {
        return $this->reader->getMetricValues($metricsType, $identifierData, $keys);
    }

    public function getMetricValueByIdentifierString(string $identifierString, string $key): ?MetricValue
    {
        return $this->reader->getMetricValueByIdentifierString($identifierString, $key);
    }

    /**
     * @param string[] $metricKeys
     *
     * @return array<string, MetricValue|null>
     */
    public function getMetricValuesByIdentifierString(string $identifierString, array $metricKeys): array
    {
        return $this->reader->getMetricValuesByIdentifierString($identifierString, $metricKeys);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey): ?CollectionInterface
    {
        return $this->reader->getCollection($metricsType, $identifierData, $collectionKey);
    }

    public function getCollectionByIdentifierString(string $identifierString, string $collectionKey): ?CollectionInterface
    {
        return $this->reader->getCollectionByIdentifierString($identifierString, $collectionKey);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getMetricCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData): MetricsCollectionInterface
    {
        return $this->reader->getMetricCollection($metricsType, $identifierData);
    }

    public function getMetricCollectionByIdentifierString(string $identifierString): MetricsCollectionInterface
    {
        return $this->reader->getMetricCollectionByIdentifierString($identifierString);
    }

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierArray
     *
     * @return array<string, MetricsCollectionInterface>
     */
    public function getMetricCollectionsByCollectionKeys(MetricCollectionTypeEnum $metricsType, ?array $identifierArray, string $collectionKey): array
    {
        return $this->reader->getMetricCollectionsByCollectionKeys($metricsType, $identifierArray, $collectionKey);
    }

    // --- Writer delegation ---

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, mixed $value, string $key): void
    {
        $this->writer->setMetricValue($metricsType, $identifierData, $value, $key);
    }

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     * @param array<string, mixed>                                       $keyValuePairs
     */
    public function setMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keyValuePairs): void
    {
        $this->writer->setMetricValues($metricsType, $identifierData, $keyValuePairs);
    }

    public function setMetricValueByIdentifierString(string $identifierString, string $key, mixed $value): void
    {
        $this->writer->setMetricValueByIdentifierString($identifierString, $key, $value);
    }

    /** @param array<string, mixed> $keyValuePairs */
    public function setMetricValuesByIdentifierString(string $identifierString, array $keyValuePairs): void
    {
        $this->writer->setMetricValuesByIdentifierString($identifierString, $keyValuePairs);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function changeMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key, callable $callback): void
    {
        $this->writer->changeMetricValue($metricsType, $identifierData, $key, $callback);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, CollectionInterface $collection, string $key): void
    {
        $this->writer->setCollection($metricsType, $identifierData, $collection, $key);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionData(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void
    {
        $this->writer->setCollectionData($metricsType, $identifierData, $collectionKey, $key, $value);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionDataUnique(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value): void
    {
        $this->writer->setCollectionDataUnique($metricsType, $identifierData, $collectionKey, $key, $value);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollectionDataOrCreateEmptyCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey, ?string $key, mixed $value, CollectionInterface $collection): void
    {
        $this->writer->setCollectionDataOrCreateEmptyCollection($metricsType, $identifierData, $collectionKey, $key, $value, $collection);
    }

    public function setProblemByIdentifierString(string $identifierString, string $key, ProblemInterface $problem): void
    {
        $this->writer->setProblemByIdentifierString($identifierString, $key, $problem);
    }

    // --- Internal access for shared-container wiring ---

    public function getRegistry(): MetricsRegistry
    {
        return $this->registry;
    }

    public function getReader(): MetricsReader
    {
        return $this->reader;
    }

    public function getWriter(): MetricsWriter
    {
        return $this->writer;
    }
}
