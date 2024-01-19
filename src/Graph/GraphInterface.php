<?php

declare(strict_types=1);

namespace PhpCodeArch\Graph;

interface GraphInterface
{
    public function insert(Node $node): void;
    public function has(string $key): bool;
    public function __toString(): string;
    public function get(string $key): ?Node;
    public function addEdge(Node $from, Node $to): void;
    public function getNodes(): array;
}
