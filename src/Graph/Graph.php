<?php

declare(strict_types=1);

namespace Marcus\PhpLegacyAnalyzer\Graph;

class Graph
{
    private array $nodes = [];

    private array $edges = [];

    private array $edgesMap = [];

    public function insert(Node $node): void
    {
        if ($this->has($node->getKey())) {
            return;
        }

        $this->nodes[$node->getKey()] = $node;
    }

    public function has(string $key): bool
    {
        return isset($this->nodes[$key]);
    }

    public function __toString(): string
    {
        $string = '';
        foreach ($this->nodes as $node) {
            $string .= sprintf("%s;\n", $node->getKey());
        }
        foreach ($this->getEdges() as $edge) {
            $string .= sprintf("%s;\n", $edge);
        }
        return $string;
    }

    public function get(string $key): ?Node
    {
        return $this->has($key) ? $this->nodes[$key] : null;
    }

    public function addEdge(Node $from, Node $to): void
    {
        $key = $from->getUniqueId() . '->' . $to->getUniqueId();

        if (isset($this->edgesMap[$key])) {
            return;
        }

        $this->edgesMap[$key] = true;

        $edge = new Edge($from, $to);

        $from->addEdge($edge);
        $to->addEdge($edge);

        $this->edges[] = $edge;
    }

    private function getEdges(): array
    {
        return $this->edges;
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }
}
