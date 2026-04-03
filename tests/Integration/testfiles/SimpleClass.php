<?php

declare(strict_types=1);

namespace Integration\Fixture;

/**
 * A simple, clean class used as integration test fixture.
 * Expected: low CC (1), positive LOC, healthy MI.
 */
class SimpleClass
{
    private string $name;
    private int $value;

    public function __construct(string $name, int $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function greet(): string
    {
        return 'Hello, '.$this->name.'!';
    }

    public function doubled(): int
    {
        return $this->value * 2;
    }
}
