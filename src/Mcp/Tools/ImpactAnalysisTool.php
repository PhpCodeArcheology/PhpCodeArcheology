<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class ImpactAnalysisTool
{
    public function __construct(
        private readonly DataProviderFactory $factory
    ) {
    }

    public function getImpactAnalysis(string $class_name, string $method_name = '', int $depth = 2): string
    {
        try {
            $graphData = $this->factory->getGraphDataProvider()->getGraphData();
            $nodes = $graphData['nodes'];
            $edges = $graphData['edges'];

            // Build lookup structures
            $nodeById = [];
            foreach ($nodes as $node) {
                $nodeById[$node['id']] = $node;
            }

            // Build declares map: methodId → classId
            $methodToClass = [];
            foreach ($edges as $edge) {
                if ($edge['type'] === 'declares') {
                    $methodToClass[$edge['target']] = $edge['source'];
                }
            }

            // Build reverse calls index: target → [{source, weight}]
            $reverseCallIndex = [];
            // Build forward calls index: source → [{target, weight}]
            $forwardCallIndex = [];
            foreach ($edges as $edge) {
                if ($edge['type'] === 'calls') {
                    $reverseCallIndex[$edge['target']][] = [
                        'source' => $edge['source'],
                        'weight' => $edge['weight'],
                    ];
                    $forwardCallIndex[$edge['source']][] = [
                        'target' => $edge['target'],
                        'weight' => $edge['weight'],
                    ];
                }
            }

            // Find matching class node
            $classNode = null;
            foreach ($nodes as $node) {
                if ($node['type'] !== 'class') {
                    continue;
                }
                if (
                    strcasecmp($node['name'], $class_name) === 0
                    || stripos($node['name'], $class_name) !== false
                ) {
                    $classNode = $node;
                    break;
                }
            }

            if ($classNode === null) {
                return "Class '{$class_name}' not found.";
            }

            // Find target method(s)
            $targetMethodIds = [];
            foreach ($edges as $edge) {
                if ($edge['type'] === 'declares' && $edge['source'] === $classNode['id']) {
                    $methodNode = $nodeById[$edge['target']] ?? null;
                    if ($methodNode === null) {
                        continue;
                    }

                    if ($method_name === '' || strcasecmp($methodNode['name'], $method_name) === 0) {
                        $targetMethodIds[] = $edge['target'];
                    }
                }
            }

            if (empty($targetMethodIds)) {
                if ($method_name !== '') {
                    return "Method '{$method_name}' not found in class '{$classNode['name']}'.";
                }
                return "No methods found in class '{$classNode['name']}'.";
            }

            $lines = [];

            foreach ($targetMethodIds as $methodId) {
                $methodNode = $nodeById[$methodId];
                $methodLabel = $classNode['name'] . '::' . $methodNode['name'];

                $lines[] = "# Impact Analysis: {$methodLabel}";
                $lines[] = '';

                // Forward calls (what does this method call?)
                $forwardCalls = $forwardCallIndex[$methodId] ?? [];
                if (!empty($forwardCalls)) {
                    $lines[] = '## Calls (' . count($forwardCalls) . ')';
                    foreach ($forwardCalls as $call) {
                        $targetNode = $nodeById[$call['target']] ?? null;
                        if ($targetNode === null) {
                            continue;
                        }
                        $targetClassId = $methodToClass[$call['target']] ?? null;
                        $targetClassName = $targetClassId !== null
                            ? ($nodeById[$targetClassId]['name'] ?? '?')
                            : '?';
                        $weight = $call['weight'] > 1 ? " ({$call['weight']} call-sites)" : '';
                        $lines[] = "  - {$targetClassName}::{$targetNode['name']}{$weight}";
                    }
                    $lines[] = '';
                }

                // BFS for callers (reverse direction)
                $visited = [];
                $queue = [];
                $callersByDepth = [];

                foreach ($reverseCallIndex[$methodId] ?? [] as $caller) {
                    $queue[] = ['id' => $caller['source'], 'weight' => $caller['weight'], 'depth' => 1, 'path' => [$methodLabel]];
                }

                while (!empty($queue)) {
                    $current = array_shift($queue);
                    $currentId = $current['id'];
                    $currentDepth = $current['depth'];

                    if ($currentDepth > $depth || isset($visited[$currentId])) {
                        continue;
                    }
                    $visited[$currentId] = true;

                    $callerNode = $nodeById[$currentId] ?? null;
                    if ($callerNode === null) {
                        continue;
                    }

                    $callerClassId = $methodToClass[$currentId] ?? null;
                    $callerClassName = $callerClassId !== null
                        ? ($nodeById[$callerClassId]['name'] ?? '?')
                        : '?';

                    $callersByDepth[$currentDepth][] = [
                        'label' => "{$callerClassName}::{$callerNode['name']}",
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
                                'path' => array_merge($current['path'], ["{$callerClassName}::{$callerNode['name']}"]),
                            ];
                        }
                    }
                }

                // Direct callers
                $directCallers = $callersByDepth[1] ?? [];
                $lines[] = '## Direct Callers (' . count($directCallers) . ')';
                if (empty($directCallers)) {
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
                for ($d = 2; $d <= $depth; $d++) {
                    foreach ($callersByDepth[$d] ?? [] as $caller) {
                        $transitiveCallers[] = $caller;
                    }
                }

                if (!empty($transitiveCallers)) {
                    $lines[] = '## Transitive Callers (depth ' . $depth . ', +' . count($transitiveCallers) . ')';
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
                $lines[] = '  Methods directly affected:      ' . count($directCallers);
                $lines[] = '  Methods transitively affected:  ' . (count($directCallers) + count($transitiveCallers));
                $lines[] = '  Classes affected:               ' . count($affectedClasses);
                $lines[] = '';
            }

            if (empty($lines)) {
                return "No call data available. Method call tracking may be disabled (graph.methodCalls: false).";
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            return 'Error performing impact analysis: ' . $e->getMessage();
        }
    }
}
