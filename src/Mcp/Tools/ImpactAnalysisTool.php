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

            // Build lookup structures
            /** @var array<string, array<string, mixed>> $nodeById */
            $nodeById = [];
            foreach ($nodes as $node) {
                $id = $this->str($node['id'] ?? null);
                if ('' !== $id) {
                    $nodeById[$id] = $node;
                }
            }

            // Build declares map: methodId → classId
            /** @var array<string, string> $methodToClass */
            $methodToClass = [];
            foreach ($edges as $edge) {
                if ('declares' === $this->str($edge['type'] ?? null)) {
                    $target = $this->str($edge['target'] ?? null);
                    $source = $this->str($edge['source'] ?? null);
                    if ('' !== $target && '' !== $source) {
                        $methodToClass[$target] = $source;
                    }
                }
            }

            // Build reverse calls index: target → [{source, weight}]
            /** @var array<string, list<array{source: string, weight: int}>> $reverseCallIndex */
            $reverseCallIndex = [];
            // Build forward calls index: source → [{target, weight}]
            /** @var array<string, list<array{target: string, weight: int}>> $forwardCallIndex */
            $forwardCallIndex = [];
            foreach ($edges as $edge) {
                if ('calls' === $this->str($edge['type'] ?? null)) {
                    $edgeSource = $this->str($edge['source'] ?? null);
                    $edgeTarget = $this->str($edge['target'] ?? null);
                    $edgeWeight = is_int($edge['weight'] ?? null) ? (int) $edge['weight'] : 1;
                    if ('' !== $edgeSource && '' !== $edgeTarget) {
                        $reverseCallIndex[$edgeTarget][] = [
                            'source' => $edgeSource,
                            'weight' => $edgeWeight,
                        ];
                        $forwardCallIndex[$edgeSource][] = [
                            'target' => $edgeTarget,
                            'weight' => $edgeWeight,
                        ];
                    }
                }
            }

            // Find matching class node
            /** @var array<string, mixed>|null $classNode */
            $classNode = null;
            foreach ($nodes as $node) {
                if ('class' !== $this->str($node['type'] ?? null)) {
                    continue;
                }
                $nodeName = $this->str($node['name'] ?? null);
                if (
                    0 === strcasecmp($nodeName, $class_name)
                    || false !== stripos($nodeName, $class_name)
                ) {
                    $classNode = $node;
                    break;
                }
            }

            if (null === $classNode) {
                return "Class '{$class_name}' not found.";
            }

            $classNodeId = $this->str($classNode['id'] ?? null);
            $classNodeName = $this->str($classNode['name'] ?? null);

            // Find target method(s)
            /** @var list<string> $targetMethodIds */
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

            if ([] === $targetMethodIds) {
                if ('' !== $method_name) {
                    return "Method '{$method_name}' not found in class '{$classNodeName}'.";
                }

                return "No methods found in class '{$classNodeName}'.";
            }

            $lines = [];

            foreach ($targetMethodIds as $methodId) {
                $methodNode = $nodeById[$methodId] ?? null;
                if (null === $methodNode) {
                    continue;
                }
                $methodNodeName = $this->str($methodNode['name'] ?? null);
                $methodLabel = $classNodeName.'::'.$methodNodeName;

                $lines[] = "# Impact Analysis: {$methodLabel}";
                $lines[] = '';

                // Forward calls (what does this method call?)
                $forwardCalls = $forwardCallIndex[$methodId] ?? [];
                if ([] !== $forwardCalls) {
                    $lines[] = '## Calls ('.count($forwardCalls).')';
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
                }

                // BFS for callers (reverse direction)
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

                    // Continue BFS
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

                // Direct callers
                $directCallers = $callersByDepth[1] ?? [];
                $lines[] = '## Direct Callers ('.count($directCallers).')';
                if ([] === $directCallers) {
                    $lines[] = '  (none — this method is not called by other classes)';
                } else {
                    foreach ($directCallers as $caller) {
                        $weight = $caller['weight'] > 1 ? " ({$caller['weight']} call-sites)" : '';
                        $lines[] = "  - {$caller['label']}{$weight}";
                    }
                }
                $lines[] = '';

                // Transitive callers
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

                // Summary
                $allCallers = array_merge($directCallers, $transitiveCallers);
                $affectedClasses = [];
                foreach ($allCallers as $caller) {
                    $className = explode('::', $caller['label'])[0];
                    $affectedClasses[$className] = true;
                }

                $lines[] = '## Summary';
                $lines[] = '  Methods directly affected:      '.count($directCallers);
                $lines[] = '  Methods transitively affected:  '.(count($directCallers) + count($transitiveCallers));
                $lines[] = '  Classes affected:               '.count($affectedClasses);
                $lines[] = '';
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'An error occurred while performing impact analysis.';
        }
    }

    private function str(mixed $value): string
    {
        return is_scalar($value) ? strval($value) : '';
    }
}
