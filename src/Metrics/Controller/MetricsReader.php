<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricValue;

class MetricsReader implements MetricsReaderInterface
{
    public function __construct(
        private readonly MetricsContainer $metricsContainer,
    ) {
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function getMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key): ?MetricValue
    {
        $identifierString = MetricsRegistry::getIdentifier($metricsType, $identifierData);

        return $this->metricsContainer->get($identifierString)->get($key);
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
    public function getMetricCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData): MetricsCollectionInterface
    {
        $identifierString = MetricsRegistry::getIdentifier($metricsType, $identifierData);

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
