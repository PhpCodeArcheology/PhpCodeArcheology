<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Graph;

class Edge
{
    public function __construct(private Node $from, private Node $to)
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
