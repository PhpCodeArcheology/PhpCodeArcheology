<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;

class MetricsContainer
{
    /**
     * @var array<string, MetricsCollectionInterface>
     */
    private array $metrics = [];

    /**
     * @var CollectionInterface[]
     */
    private array $collections = [];

    public function get(string $key): MetricsCollectionInterface
    {
        return $this->metrics[$key];
    }

    public function set(string $key, MetricsCollectionInterface $value): void
    {
        $this->metrics[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    /**
     * @return array<string, MetricsCollectionInterface>
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

    public function setCollection(string $key, CollectionInterface $collection): void
    {
        $this->collections[] = $collection;
    }

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
