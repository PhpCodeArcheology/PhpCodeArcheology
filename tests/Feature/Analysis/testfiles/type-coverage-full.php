<?php

class FullyTypedClass
{
    private int $count;
    private string $name;

    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    public function getName(): string
    {
        return $this->name;
    }
}

class UntypedClass
{
    public $value;

    public function compute($a, $b)
    {
        return $a + $b;
    }

    public function getValue()
    {
        return $this->value;
    }
}

class MixedTypedClass
{
    public int $typed;
    public $untyped;

    public function typedMethod(int $a): string
    {
        return (string) $a;
    }

    public function untypedMethod($a)
    {
        return $a;
    }
}
