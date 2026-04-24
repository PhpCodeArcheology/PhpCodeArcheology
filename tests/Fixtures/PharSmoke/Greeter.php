<?php

declare(strict_types=1);

namespace PharSmoke;

/**
 * Minimal clean class used as smoke-test fixture for the phar build.
 * Kept deliberately simple so no problem detector triggers on it.
 */
class Greeter
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function greet(): string
    {
        return 'Hello, '.$this->name.'!';
    }

    public function name(): string
    {
        return $this->name;
    }
}
