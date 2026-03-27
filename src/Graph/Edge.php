<?php

declare(strict_types=1);

namespace PhpCodeArch\Graph;

class Edge implements \Stringable
{
    public function __construct(private readonly Node $from, private readonly Node $to)
    {
    }

    public function getFrom(): Node
    {
        return $this->from;
    }

    public function getTo(): Node
    {
        return $this->to;
    }

    public function __toString(): string
    {
        return sprintf('%s -> %s', $this->from->getKey(), $this->to->getKey());
    }
}
