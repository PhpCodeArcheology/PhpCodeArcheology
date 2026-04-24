<?php

declare(strict_types=1);

namespace PharSmoke;

class Counter
{
    private int $value = 0;

    public function increment(): void
    {
        ++$this->value;
    }

    public function value(): int
    {
        return $this->value;
    }
}
