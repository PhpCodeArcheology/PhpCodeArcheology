<?php

declare(strict_types=1);

namespace PhpCodeArch\Metrics\Model\Collections;

interface CollectionInterface
{
    public function set(mixed $value, ?string $key = null): void;
    public function has(string $key): bool;
    public function get(string $key): mixed;
    public function count(): int;
}
