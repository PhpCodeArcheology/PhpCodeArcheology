<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Controller;

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Predictions\Problems\ProblemInterface;

class MetricsWriter implements MetricsWriterInterface
{
    public function __construct(
        private readonly MetricsContainer $metricsContainer,
    ) {
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, mixed $value, string $key): void
    {
        $identifierString = MetricsRegistry::getIdentifier($metricsType, $identifierData);
        $this->setMetricValueByIdentifierString($identifierString, $key, $value);
    }

    /**
     * @param array{path?: string, name?: string, files?: string[]}|null $identifierData
     * @param array<string, mixed>                                       $keyValuePairs
     */
    public function setMetricValues(MetricCollectionTypeEnum $metricsType, ?array $identifierData, array $keyValuePairs): void
    {
        $identifierString = MetricsRegistry::getIdentifier($metricsType, $identifierData);
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricValueByIdentifierString($identifierString, $key, $value);
        }
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

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function changeMetricValue(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $key, callable $callback): void
    {
        $identifierString = MetricsRegistry::getIdentifier($metricsType, $identifierData);
        $currentValue = $this->metricsContainer->has($identifierString)
            ? $this->metricsContainer->get($identifierString)->get($key)?->getValue() ?? null
            : null;
        $this->setMetricValueByIdentifierString($identifierString, $key, $callback($currentValue));
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    public function setCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, CollectionInterface $collection, string $key): void
    {
        $identifierString = MetricsRegistry::getIdentifier($metricsType, $identifierData);
        $this->metricsContainer->get($identifierString)->setCollection($key, $collection);
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

    public function setProblemByIdentifierString(string $identifierString, string $key, ProblemInterface $problem): void
    {
        $this->metricsContainer->get($identifierString)->get($key)?->addProblem($problem);
    }

    /** @param array{path?: string, name?: string, files?: string[]}|null $identifierData */
    private function getCollection(MetricCollectionTypeEnum $metricsType, ?array $identifierData, string $collectionKey): ?CollectionInterface
    {
        $identifierString = MetricsRegistry::getIdentifier($metricsType, $identifierData);

        return $this->metricsContainer->get($identifierString)->getCollection($collectionKey);
    }
}
