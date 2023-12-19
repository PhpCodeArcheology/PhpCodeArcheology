<?php

namespace Marcus\PhpLegacyAnalyzer\Metrics;

trait MetricsTrait
{
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

    public function getAll(): array
    {
        return $this->metrics;
    }
}