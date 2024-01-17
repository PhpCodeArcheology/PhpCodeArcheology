<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

trait CollectionTrait
{
    public function __construct(private array $items = [])
    {}

    public function set(mixed $value, ?string $key = null): void
    {
        if ($key === null) {
            $this->items[] = $value;
            return;
        }

        $this->items[$key] = $value;
    }

    public function setUnique(mixed $value, ?string $key = null): void
    {
        if (in_array($value, $this->items)) {
            return;
        }

        if ($key === null) {
            $this->items[] = $value;
            return;
        }

        $this->items[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->items[$key]);
    }

    public function get(string $key): mixed
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->items[$key];
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this);
    }
}
