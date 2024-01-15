<?php

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;

trait MetricsCollectionTrait
{
    /**
     * @var MetricValue[]
     */
    private array $metrics = [];

    /**
     * @var CollectionInterface[] $collections
     */
    private array $collections = [];

    /**
     * @param string $key
     * @return MetricValue|null
     */
    public function get(string $key): ?MetricValue
    {
        return $this->has($key) ? $this->metrics[$key] : null;
    }

    /**
     * @param string $key
     * @param MetricValue $value
     * @return void
     */
    public function set(string $key, MetricValue $value): void
    {
        $this->metrics[$key] = $value;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    /**
     * @return MetricValue[]
     */
    public function getAll(): array
    {
        return $this->metrics;
    }

    /**
     * @return string[]
     */
    public function getKeys(): array
    {
        return array_keys($this->metrics);
    }

    /**
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * @param string $key
     * @param CollectionInterface $collection
     * @return void
     */
    public function setCollection(string $key, CollectionInterface $collection): void
    {
        $this->collections[$key] = $collection;
    }

    /**
     * @param string $key
     * @return bool
     */
    public function hasCollection(string $key): bool
    {
        return isset($this->collections[$key]);
    }

    public function getCollection(string $key): ?CollectionInterface
    {
        return $this->hasCollection($key) ? $this->collections[$key] : null;
    }

    private function setMetricValue(string $key, mixed $value): void
    {
        $this->set($key, MetricValue::ofValueAndType($value, $this->usedMetricTypes[$key]));
    }

    private function setMetricValues(MetricsCollectionInterface &$metrics, array $keyValuePairs): void
    {
        foreach ($keyValuePairs as $key => $value) {
            $this->setMetricValue($metrics, $key, $value);
        }
    }

}
