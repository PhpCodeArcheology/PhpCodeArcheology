<?php

declare(strict_types=1);

namespace PhpCodeArch\Graph;

class Node
{
    private array $edges = [];

    private bool $visited = false;

    public function __construct(private readonly string $key)
    {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getUniqueId(): string
    {
        return spl_object_hash($this);
    }

    public function addEdge(Edge $edge): void
    {
        $this->edges[] = $edge;
    }

    public function isVisited(): bool
    {
        return $this->visited;
    }

    public function visit(): void
    {
        $this->visited = true;
    }

    public function getAdjacents(): array
    {
        $adjacents = [];
        foreach ($this->edges as $edge) {
            if ($edge->getFrom()->getKey() != $this->getKey()) {
                $adjacents[$edge->getFrom()->getKey()] = $edge->getFrom();
            }
            if ($edge->getTo()->getKey() != $this->getKey()) {
                $adjacents[$edge->getTo()->getKey()] = $edge->getTo();
            }
        }
        return $adjacents;
    }
}
