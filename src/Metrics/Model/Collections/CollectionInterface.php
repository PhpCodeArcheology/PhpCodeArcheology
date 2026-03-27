<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

/** @extends \IteratorAggregate<array-key, mixed> */
interface CollectionInterface extends \IteratorAggregate
{
    public function set(mixed $value, ?string $key = null): void;

    public function setUnique(mixed $value, ?string $key = null): void;

    public function has(string $key): bool;

    public function get(string $key): mixed;

    public function count(): int;

    /** @return array<array-key, mixed> */
    public function getAsArray(): array;
}
