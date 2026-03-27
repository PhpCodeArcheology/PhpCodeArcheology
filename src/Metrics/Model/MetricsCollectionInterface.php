<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model;

use PhpCodeArch\Metrics\Identity\IdentifierInterface;
use PhpCodeArch\Metrics\Model\Collections\CollectionInterface;

interface MetricsCollectionInterface
{
    public function getIdentifier(): IdentifierInterface;

    public function get(string $key): ?MetricValue;

    public function set(string $key, MetricValue $value): void;

    public function has(string $key): bool;

    public function getInt(string $key, int $default = 0): int;

    public function getFloat(string $key, float $default = 0.0): float;

    public function getBool(string $key, bool $default = false): bool;

    public function getString(string $key, string $default = ''): string;

    /**
     * @return array<mixed>
     */
    public function getArray(string $key): array;

    /**
     * @return array<string, MetricValue>
     */
    public function getAll(): array;

    /**
     * @return string[]
     */
    public function getKeys(): array;

    public function setCollection(string $key, CollectionInterface $collection): void;

    public function hasCollection(string $key): bool;

    public function getCollection(string $key): ?CollectionInterface;

    public function getPath(): string;

    public function getName(): string;
}
