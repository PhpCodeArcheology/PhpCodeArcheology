<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;

trait MetricsCollectionTrait
{
    /**
     * @var array<string, MetricValue>
     */
    private array $metrics = [];

    /**
     * @var CollectionInterface[]
     */
    private array $collections = [];

    public function get(string $key): ?MetricValue
    {
        return $this->has($key) ? $this->metrics[$key] : null;
    }

    public function set(string $key, MetricValue $value): void
    {
        $this->metrics[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    /**
     * @return array<string, MetricValue>
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

    public function getInt(string $key, int $default = 0): int
    {
        return $this->get($key)?->asInt() ?? $default;
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return $this->get($key)?->asFloat() ?? $default;
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return $this->get($key)?->asBool() ?? $default;
    }

    public function getString(string $key, string $default = ''): string
    {
        return $this->get($key)?->asString() ?? $default;
    }

    /**
     * @return array<mixed>
     */
    public function getArray(string $key): array
    {
        return $this->get($key)?->asArray() ?? [];
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setCollection(string $key, CollectionInterface $collection): void
    {
        $this->collections[$key] = $collection;
    }

    public function hasCollection(string $key): bool
    {
        return isset($this->collections[$key]);
    }

    public function getCollection(string $key): ?CollectionInterface
    {
        return $this->hasCollection($key) ? $this->collections[$key] : null;
    }
}
