<?php

namespace PhpCodeArch\Metrics;

use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\Manager\MetricValue;

trait MetricsTrait
{
    /**
     * @var MetricsInterface[]|array[]
     */
    private array $metrics = [];

    public function get(string $key): mixed
    {
        return $this->has($key) ? $this->metrics[$key] : null;
    }

    public function set(string $key, mixed $value): void
    {
        $this->metrics[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->metrics[$key]);
    }

    /**
     * @return array[]|FileMetrics[]
     */
    public function getAll(): array
    {
        return $this->metrics;
    }

    public function getKeys(): array
    {
        return array_keys($this->metrics);
    }

    public function getPath(): string
    {
        return $this->path;
    }
}
