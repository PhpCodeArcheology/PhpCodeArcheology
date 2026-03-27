<?php

declare(strict_types=1);

namespace PhpCodeArch\Mcp\Tools;

use PhpCodeArch\Report\DataProvider\DataProviderFactory;

class GraphTool
{
    public function __construct(
        private readonly DataProviderFactory $factory,
    ) {
    }

    public function getGraph(bool $summary_only = false): string
    {
        try {
            $provider = $this->factory->getGraphDataProvider();
            $graphData = $provider->getGraphData();

            $nodeCount = count($graphData['nodes']);
            $edgeCount = count($graphData['edges']);
            $clusterCount = count($graphData['clusters']);
            $cycleCount = count($graphData['cycles']);

            if ($summary_only) {
                $nodeTypes = [];
                foreach ($graphData['nodes'] as $node) {
                    $t = is_string($node['type'] ?? null) ? $node['type'] : 'unknown';
                    $nodeTypes[$t] = ($nodeTypes[$t] ?? 0) + 1;
                }

                $edgeTypes = [];
                foreach ($graphData['edges'] as $edge) {
                    $t = is_string($edge['type'] ?? null) ? $edge['type'] : 'unknown';
                    $edgeTypes[$t] = ($edgeTypes[$t] ?? 0) + 1;
                }

                $lines = [
                    '# Knowledge Graph Summary',
                    '',
                    "Nodes:    {$nodeCount}",
                    "Edges:    {$edgeCount}",
                    "Clusters: {$clusterCount}",
                    "Cycles:   {$cycleCount}",
                    '',
                    '## Node Types',
                ];

                arsort($nodeTypes);
                foreach ($nodeTypes as $nodeType => $count) {
                    $lines[] = "  {$nodeType}: {$count}";
                }

                $lines[] = '';
                $lines[] = '## Edge Types';

                arsort($edgeTypes);
                foreach ($edgeTypes as $edgeType => $count) {
                    $lines[] = "  {$edgeType}: {$count}";
                }

                return implode("\n", $lines);
            }

            return json_encode($graphData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}';
        } catch (\Throwable $e) {
            return 'An error occurred while retrieving the graph.';
        }
    }
}
