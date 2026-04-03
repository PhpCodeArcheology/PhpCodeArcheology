<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class ImpactAnalysisTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getImpactAnalysis(string $class_name, string $method_name = '', int $depth = 2): string
    {
        try {
            $graphData = $this->factory->getGraphDataProvider()->getGraphData();
            $nodes = $graphData['nodes'];
            $edges = $graphData['edges'];

            $nodeById = $this->buildNodeIndex($nodes);
            $methodToClass = $this->buildMethodToClassMap($edges);
            [$forwardCallIndex, $reverseCallIndex] = $this->buildCallIndexes($edges);

            $classNode = $this->findClassNode($nodes, $class_name);
            if (null === $classNode) {
                return "Class '{$class_name}' not found.";
            }

            $classNodeId = $this->str($classNode['id'] ?? null);
            $classNodeName = $this->str($classNode['name'] ?? null);

            $targetMethodIds = $this->findTargetMethodIds($edges, $classNodeId, $method_name, $nodeById);

            if ([] === $targetMethodIds) {
                if ('' !== $method_name) {
                    return "Method '{$method_name}' not found in class '{$classNodeName}'.";
                }

                return "No methods found in class '{$classNodeName}'.";
            }

            $lines = [];
            foreach ($targetMethodIds as $methodId) {
                $methodLines = $this->formatMethodAnalysis(
                    $methodId, $nodeById, $classNodeName,
                    $forwardCallIndex, $reverseCallIndex, $methodToClass, $depth
                );
                $lines = array_merge($lines, $methodLines);
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while performing impact analysis.';
        }
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return array<string, array<string, mixed>>
     */
    private function buildNodeIndex(array $nodes): array
    {
        $nodeById = [];
        foreach ($nodes as $node) {
            $id = $this->str($node['id'] ?? null);
            if ('' !== $id) {
                $nodeById[$id] = $node;
            }
        }

        return $nodeById;
    }

    /**
     * @param list<array<string, mixed>> $edges
     *
     * @return array<string, string>
     */
    private function buildMethodToClassMap(array $edges): array
    {
        $methodToClass = [];
        foreach ($edges as $edge) {
            if ('declares' !== $this->str($edge['type'] ?? null)) {
                continue;
            }
            $target = $this->str($edge['target'] ?? null);
            $source = $this->str($edge['source'] ?? null);
            if ('' !== $target && '' !== $source) {
                $methodToClass[$target] = $source;
            }
        }

        return $methodToClass;
    }

    /**
     * @param list<array<string, mixed>> $edges
     *
     * @return array{
     *   0: array<string, list<array{target: string, weight: int}>>,
     *   1: array<string, list<array{source: string, weight: int}>>
     * }
     */
    private function buildCallIndexes(array $edges): array
    {
        /** @var array<string, list<array{target: string, weight: int}>> $forwardCallIndex */
        $forwardCallIndex = [];
        /** @var array<string, list<array{source: string, weight: int}>> $reverseCallIndex */
        $reverseCallIndex = [];

        foreach ($edges as $edge) {
            if ('calls' !== $this->str($edge['type'] ?? null)) {
                continue;
            }
            $edgeSource = $this->str($edge['source'] ?? null);
            $edgeTarget = $this->str($edge['target'] ?? null);
            $edgeWeight = is_int($edge['weight'] ?? null) ? (int) $edge['weight'] : 1;
            if ('' !== $edgeSource && '' !== $edgeTarget) {
                $reverseCallIndex[$edgeTarget][] = ['source' => $edgeSource, 'weight' => $edgeWeight];
                $forwardCallIndex[$edgeSource][] = ['target' => $edgeTarget, 'weight' => $edgeWeight];
            }
        }

        return [$forwardCallIndex, $reverseCallIndex];
    }

    /**
     * @param list<array<string, mixed>> $nodes
     *
     * @return array<string, mixed>|null
     */
    private function findClassNode(array $nodes, string $class_name): ?array
    {
        foreach ($nodes as $node) {
            if ('class' !== $this->str($node['type'] ?? null)) {
                continue;
            }
            $nodeName = $this->str($node['name'] ?? null);
            if (0 === strcasecmp($nodeName, $class_name) || false !== stripos($nodeName, $class_name)) {
                return $node;
            }
        }

        return null;
    }

    /**
     * @param list<array<string, mixed>>          $edges
     * @param array<string, array<string, mixed>> $nodeById
     *
     * @return list<string>
     */
    private function findTargetMethodIds(array $edges, string $classNodeId, string $method_name, array $nodeById): array
    {
        $targetMethodIds = [];
        foreach ($edges as $edge) {
            $edgeType = $this->str($edge['type'] ?? null);
            $edgeSource = $this->str($edge['source'] ?? null);
            $edgeTarget = $this->str($edge['target'] ?? null);

            if ('declares' !== $edgeType || $edgeSource !== $classNodeId) {
                continue;
            }

            $methodNode = '' !== $edgeTarget ? ($nodeById[$edgeTarget] ?? null) : null;
            if (null === $methodNode) {
                continue;
            }

            $methodNodeName = $this->str($methodNode['name'] ?? null);
            if ('' === $method_name || 0 === strcasecmp($methodNodeName, $method_name)) {
                $targetMethodIds[] = $edgeTarget;
            }
        }

        return $targetMethodIds;
    }

    /**
     * @param array<string, list<array{source: string, weight: int}>> $reverseCallIndex
     * @param array<string, array<string, mixed>>                     $nodeById
     * @param array<string, string>                                   $methodToClass
     *
     * @return array<int, list<array{label: string, weight: int, path: list<string>}>>
     */
    private function bfsCallers(
        string $methodId,
        string $methodLabel,
        array $reverseCallIndex,
        array $nodeById,
        array $methodToClass,
        int $depth,
    ): array {
        /** @var array<string, true> $visited */
        $visited = [];
        /** @var list<array{id: string, weight: int, depth: int, path: list<string>}> $queue */
        $queue = [];
        /** @var array<int, list<array{label: string, weight: int, path: list<string>}>> $callersByDepth */
        $callersByDepth = [];

        foreach ($reverseCallIndex[$methodId] ?? [] as $caller) {
            $queue[] = ['id' => $caller['source'], 'weight' => $caller['weight'], 'depth' => 1, 'path' => [$methodLabel]];
        }

        while ([] !== $queue) {
            $current = array_shift($queue);
            $currentId = $current['id'];
            $currentDepth = $current['depth'];

            if ($currentDepth > $depth || isset($visited[$currentId])) {
                continue;
            }
            $visited[$currentId] = true;

            $callerNode = $nodeById[$currentId] ?? null;
            if (null === $callerNode) {
                continue;
            }

            $callerClassId = $methodToClass[$currentId] ?? null;
            $callerClassName = null !== $callerClassId
                ? $this->str($nodeById[$callerClassId]['name'] ?? '?')
                : '?';
            $callerNodeName = $this->str($callerNode['name'] ?? null);

            $callersByDepth[$currentDepth][] = [
                'label' => "{$callerClassName}::{$callerNodeName}",
                'weight' => $current['weight'],
                'path' => $current['path'],
            ];

            foreach ($reverseCallIndex[$currentId] ?? [] as $nextCaller) {
                if (!isset($visited[$nextCaller['source']])) {
                    $queue[] = [
                        'id' => $nextCaller['source'],
                        'weight' => $nextCaller['weight'],
                        'depth' => $currentDepth + 1,
                        'path' => array_merge($current['path'], ["{$callerClassName}::{$callerNodeName}"]),
                    ];
                }
            }
        }

        return $callersByDepth;
    }

    /**
     * @param array<string, array<string, mixed>>                     $nodeById
     * @param array<string, list<array{target: string, weight: int}>> $forwardCallIndex
     * @param array<string, list<array{source: string, weight: int}>> $reverseCallIndex
     * @param array<string, string>                                   $methodToClass
     *
     * @return list<string>
     */
    private function formatMethodAnalysis(
        string $methodId,
        array $nodeById,
        string $classNodeName,
        array $forwardCallIndex,
        array $reverseCallIndex,
        array $methodToClass,
        int $depth,
    ): array {
        $methodNode = $nodeById[$methodId] ?? null;
        if (null === $methodNode) {
            return [];
        }

        $methodNodeName = $this->str($methodNode['name'] ?? null);
        $methodLabel = $classNodeName.'::'.$methodNodeName;

        $lines = ["# Impact Analysis: {$methodLabel}", ''];
        $lines = array_merge($lines, $this->formatForwardCalls($forwardCallIndex[$methodId] ?? [], $nodeById, $methodToClass));

        $callersByDepth = $this->bfsCallers($methodId, $methodLabel, $reverseCallIndex, $nodeById, $methodToClass, $depth);
        $lines = array_merge($lines, $this->formatCallerSections($callersByDepth, $depth));

        return $lines;
    }

    /**
     * @param list<array{target: string, weight: int}> $forwardCalls
     * @param array<string, array<string, mixed>>      $nodeById
     * @param array<string, string>                    $methodToClass
     *
     * @return list<string>
     */
    private function formatForwardCalls(array $forwardCalls, array $nodeById, array $methodToClass): array
    {
        if ([] === $forwardCalls) {
            return [];
        }

        $lines = ['## Calls ('.count($forwardCalls).')'];
        foreach ($forwardCalls as $call) {
            $targetNode = $nodeById[$call['target']] ?? null;
            if (null === $targetNode) {
                continue;
            }
            $targetClassId = $methodToClass[$call['target']] ?? null;
            $targetClassName = null !== $targetClassId
                ? $this->str($nodeById[$targetClassId]['name'] ?? '?')
                : '?';
            $targetNodeName = $this->str($targetNode['name'] ?? null);
            $weight = $call['weight'] > 1 ? " ({$call['weight']} call-sites)" : '';
            $lines[] = "  - {$targetClassName}::{$targetNodeName}{$weight}";
        }
        $lines[] = '';

        return $lines;
    }

    /**
     * @param array<int, list<array{label: string, weight: int, path: list<string>}>> $callersByDepth
     *
     * @return list<string>
     */
    private function formatCallerSections(array $callersByDepth, int $depth): array
    {
        $directCallers = $callersByDepth[1] ?? [];
        $lines = ['## Direct Callers ('.count($directCallers).')'];

        if ([] === $directCallers) {
            $lines[] = '  (none — this method is not called by other classes)';
        } else {
            foreach ($directCallers as $caller) {
                $weight = $caller['weight'] > 1 ? " ({$caller['weight']} call-sites)" : '';
                $lines[] = "  - {$caller['label']}{$weight}";
            }
        }
        $lines[] = '';

        $transitiveCallers = [];
        for ($d = 2; $d <= $depth; ++$d) {
            foreach ($callersByDepth[$d] ?? [] as $caller) {
                $transitiveCallers[] = $caller;
            }
        }

        if ([] !== $transitiveCallers) {
            $lines[] = '## Transitive Callers (depth '.$depth.', +'.count($transitiveCallers).')';
            foreach ($transitiveCallers as $caller) {
                $chain = implode(' → ', array_reverse($caller['path']));
                $lines[] = "  - {$caller['label']} → {$chain}";
            }
            $lines[] = '';
        }

        $lines = array_merge($lines, $this->formatSummary($directCallers, $transitiveCallers));

        return $lines;
    }

    /**
     * @param list<array{label: string, weight: int, path: list<string>}> $directCallers
     * @param list<array{label: string, weight: int, path: list<string>}> $transitiveCallers
     *
     * @return list<string>
     */
    private function formatSummary(array $directCallers, array $transitiveCallers): array
    {
        $allCallers = array_merge($directCallers, $transitiveCallers);
        $affectedClasses = [];
        foreach ($allCallers as $caller) {
            $className = explode('::', $caller['label'])[0];
            $affectedClasses[$className] = true;
        }

        return [
            '## Summary',
            '  Methods directly affected:      '.count($directCallers),
            '  Methods transitively affected:  '.(count($directCallers) + count($transitiveCallers)),
            '  Classes affected:               '.count($affectedClasses),
            '',
        ];
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? strval($value) : '';
    }
}
