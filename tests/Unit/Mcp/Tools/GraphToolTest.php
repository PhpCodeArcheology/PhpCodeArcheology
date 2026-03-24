<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\GraphTool;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\GraphDataProvider;

function makeGraphFactory(array $graphData): DataProviderFactory
{
    $graphProvider = Mockery::mock(GraphDataProvider::class);
    $graphProvider->shouldReceive('getGraphData')->andReturn($graphData);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getGraphDataProvider')->andReturn($graphProvider);
    return $factory;
}

function sampleGraphData(): array
{
    return [
        'nodes' => [
            ['id' => 'class:1',       'type' => 'class',   'name' => 'FooClass'],
            ['id' => 'class:2',       'type' => 'class',   'name' => 'BarClass'],
            ['id' => 'package:MyPkg', 'type' => 'package', 'name' => 'MyPkg'],
        ],
        'edges' => [
            ['source' => 'class:1', 'target' => 'class:2',       'type' => 'depends_on'],
            ['source' => 'class:1', 'target' => 'package:MyPkg', 'type' => 'belongs_to'],
        ],
        'clusters' => [
            ['id' => 'package:MyPkg', 'name' => 'MyPkg', 'nodeIds' => ['class:1']],
        ],
        'cycles' => [],
    ];
}

it('returns a summary when summary_only=true', function () {
    $factory = makeGraphFactory(sampleGraphData());

    $result = (new GraphTool($factory))->getGraph(summary_only: true);

    expect($result)
        ->toContain('Knowledge Graph Summary')
        ->toContain('Nodes:    3')
        ->toContain('Edges:    2')
        ->toContain('Clusters: 1')
        ->toContain('Cycles:   0')
        ->toContain('Node Types')
        ->toContain('Edge Types')
        ->toContain('class: 2')
        ->toContain('depends_on: 1');
});

it('returns JSON output when summary_only=false', function () {
    $factory = makeGraphFactory(sampleGraphData());

    $result = (new GraphTool($factory))->getGraph(summary_only: false);

    $decoded = json_decode($result, true);

    expect($decoded)->toBeArray()
        ->and($decoded)->toHaveKeys(['nodes', 'edges', 'clusters', 'cycles'])
        ->and($decoded['nodes'])->toHaveCount(3)
        ->and($decoded['edges'])->toHaveCount(2);
});

it('returns empty graph JSON for an empty dataset', function () {
    $factory = makeGraphFactory(['nodes' => [], 'edges' => [], 'clusters' => [], 'cycles' => []]);

    $result = (new GraphTool($factory))->getGraph(summary_only: false);

    $decoded = json_decode($result, true);

    expect($decoded['nodes'])->toBeEmpty()
        ->and($decoded['edges'])->toBeEmpty();
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getGraphDataProvider')
        ->andThrow(new \RuntimeException('graph error'));

    $result = (new GraphTool($factory))->getGraph();

    expect($result)->toBe('Error retrieving graph: graph error');
});
