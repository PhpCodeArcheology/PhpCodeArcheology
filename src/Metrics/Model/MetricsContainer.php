<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;

class MetricsContainer
{
    /**
     * @var MetricsCollectionInterface[]
     */
    private array $metrics = [];

    /**
     * @var CollectionInterface[] $collections
     */
    private array $collections = [];

    /**
     * @param string $key
     * @return MetricsCollectionInterface
     */
    public function get(string $key): MetricsCollectionInterface
    {
        return $this->metrics[$key];
    }

    /**
     * @param string $key
     * @param MetricsCollectionInterface $value
     * @return void
     */
    public function set(string $key, MetricsCollectionInterface $value): void
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
     * @return MetricsCollectionInterface[]
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
     * @param string $key
     * @param CollectionInterface $collection
     * @return void
     */
    public function setCollection(string $key, CollectionInterface $collection): void
    {
        $this->collections[] = $collection;
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
        return $this->has($key) ? $this->collections[$key] : null;
    }


    public function push(MetricsCollectionInterface $metrics): void
    {
        $this->metrics[(string) $metrics->getIdentifier()] = $metrics;
    }

    public function getCount(): int
    {
        return count($this->metrics);
    }
}
