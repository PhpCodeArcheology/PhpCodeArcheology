<?php

declare(strict_types=1);

namespace PhpCodeArch\Calculators\Helpers;

/**
 * Tarjan's Strongly Connected Components algorithm.
 *
 * Finds all strongly connected components in a directed graph in O(V+E).
 * Any SCC with more than one node constitutes a dependency cycle.
 */
class TarjanSccAlgorithm
{
    private int $index = 0;
    /** @var list<string> */
    private array $stack = [];
    /** @var array<string, bool> */
    private array $onStack = [];
    /** @var array<string, int> */
    private array $indices = [];
    /** @var array<string, int> */
    private array $lowLinks = [];
    /** @var list<list<string>> */
    private array $sccs = [];

    /**
     * @param array<string, string[]> $adjacencyList node → [neighbor, ...]
     *
     * @return array<array<string>> Array of SCCs (each SCC is array of node IDs)
     */
    public function findSccs(array $adjacencyList): array
    {
        $this->index = 0;
        $this->stack = [];
        $this->onStack = [];
        $this->indices = [];
        $this->lowLinks = [];
        $this->sccs = [];
        foreach ($adjacencyList as $neighbors) {
            foreach ($neighbors as $neighbor) {
                if (!isset($adjacencyList[$neighbor])) {
                    $adjacencyList[$neighbor] = [];
                }
            }
        }

        foreach (array_keys($adjacencyList) as $node) {
            if (!isset($this->indices[$node])) {
                $this->strongConnect($node, $adjacencyList);
            }
        }

        return $this->sccs;
    }

    /**
     * @return array<array<string>> Only SCCs with more than one node (actual cycles)
     */
    /**
     * @param array<string, string[]> $adjacencyList
     *
     * @return array<array<string>> Only SCCs with more than one node (actual cycles)
     */
    public function findCycles(array $adjacencyList): array
    {
        $sccs = $this->findSccs($adjacencyList);

        return array_values(array_filter($sccs, fn (array $scc): bool => count($scc) > 1));
    }

    /**
     * @param array<string, string[]> $adjacencyList
     */
    private function strongConnect(string $node, array &$adjacencyList): void
    {
        $this->indices[$node] = $this->index;
        $this->lowLinks[$node] = $this->index;
        ++$this->index;
        $this->stack[] = $node;
        $this->onStack[$node] = true;

        foreach ($adjacencyList[$node] ?? [] as $neighbor) {
            if (!isset($this->indices[$neighbor])) {
                $this->strongConnect($neighbor, $adjacencyList);
                $this->lowLinks[$node] = min($this->lowLinks[$node], $this->lowLinks[$neighbor]);
            } elseif ($this->onStack[$neighbor] ?? false) {
                $this->lowLinks[$node] = min($this->lowLinks[$node], $this->indices[$neighbor]);
            }
        }

        // If node is a root of an SCC
        if ($this->lowLinks[$node] === $this->indices[$node]) {
            $scc = [];
            do {
                $w = array_pop($this->stack) ?? '';
                $this->onStack[$w] = false;
                $scc[] = $w;
            } while ($w !== $node);

            $this->sccs[] = $scc;
        }
    }
}
