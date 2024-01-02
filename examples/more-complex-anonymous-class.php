<?php

interface TestInterface
{
}

class ToInject
{
    public function getString(): string
    {
        return 'Test';
    }
}

function getClass(ToInject $inject): object {
    return new class ($inject) implements TestInterface
    {
        public function __construct(private readonly ToInject $inject)
        {

        }

        public function getString(): string
        {
            return $this->inject->getString();
        }
    };
}

$inject = new ToInject();
$created = getClass($inject);
echo $created->getString();

