<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\ImpactAnalysisTool;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\GraphDataProvider;

function makeImpactFactory(array $nodes, array $edges): DataProviderFactory
{
    $provider = Mockery::mock(GraphDataProvider::class);
    $provider->shouldReceive('getGraphData')->andReturn([
        'nodes' => $nodes,
        'edges' => $edges,
    ]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getGraphDataProvider')->andReturn($provider);
    return $factory;
}

function simpleGraph(): array
{
    $nodes = [
        ['id' => 'class:Foo', 'type' => 'class', 'name' => 'Foo'],
        ['id' => 'method:Foo::bar', 'type' => 'method', 'name' => 'bar'],
        ['id' => 'class:Baz', 'type' => 'class', 'name' => 'Baz'],
        ['id' => 'method:Baz::callBar', 'type' => 'method', 'name' => 'callBar'],
    ];
    $edges = [
        ['type' => 'declares', 'source' => 'class:Foo', 'target' => 'method:Foo::bar'],
        ['type' => 'declares', 'source' => 'class:Baz', 'target' => 'method:Baz::callBar'],
        ['type' => 'calls', 'source' => 'method:Baz::callBar', 'target' => 'method:Foo::bar', 'weight' => 1],
    ];
    return [$nodes, $edges];
}

it('returns impact analysis for a known class', function () {
    [$nodes, $edges] = simpleGraph();
    $factory = makeImpactFactory($nodes, $edges);

    $result = (new ImpactAnalysisTool($factory))->getImpactAnalysis('Foo', 'bar');

    expect($result)
        ->toContain('Impact Analysis: Foo::bar')
        ->toContain('Direct Callers (1)')
        ->toContain('Baz::callBar');
});

it('returns "not found" for unknown class', function () {
    [$nodes, $edges] = simpleGraph();
    $factory = makeImpactFactory($nodes, $edges);

    $result = (new ImpactAnalysisTool($factory))->getImpactAnalysis('NonExistent');

    expect($result)->toBe("Class 'NonExistent' not found.");
});

it('respects depth parameter', function () {
    // 3-level chain: A::m1 ← B::m2 ← C::m3
    $nodes = [
        ['id' => 'class:A', 'type' => 'class', 'name' => 'A'],
        ['id' => 'method:A::m1', 'type' => 'method', 'name' => 'm1'],
        ['id' => 'class:B', 'type' => 'class', 'name' => 'B'],
        ['id' => 'method:B::m2', 'type' => 'method', 'name' => 'm2'],
        ['id' => 'class:C', 'type' => 'class', 'name' => 'C'],
        ['id' => 'method:C::m3', 'type' => 'method', 'name' => 'm3'],
    ];
    $edges = [
        ['type' => 'declares', 'source' => 'class:A', 'target' => 'method:A::m1'],
        ['type' => 'declares', 'source' => 'class:B', 'target' => 'method:B::m2'],
        ['type' => 'declares', 'source' => 'class:C', 'target' => 'method:C::m3'],
        ['type' => 'calls', 'source' => 'method:B::m2', 'target' => 'method:A::m1', 'weight' => 1],
        ['type' => 'calls', 'source' => 'method:C::m3', 'target' => 'method:B::m2', 'weight' => 1],
    ];

    $factoryDepth1 = makeImpactFactory($nodes, $edges);
    $resultDepth1 = (new ImpactAnalysisTool($factoryDepth1))->getImpactAnalysis('A', 'm1', depth: 1);

    $factoryDepth2 = makeImpactFactory($nodes, $edges);
    $resultDepth2 = (new ImpactAnalysisTool($factoryDepth2))->getImpactAnalysis('A', 'm1', depth: 2);

    // depth=1: only B::m2 as direct caller, no transitive section
    expect($resultDepth1)
        ->toContain('B::m2')
        ->not->toContain('C::m3');

    // depth=2: B::m2 direct, C::m3 transitive
    expect($resultDepth2)
        ->toContain('B::m2')
        ->toContain('C::m3')
        ->toContain('Transitive Callers');
});

it('shows affected class count in summary', function () {
    // Two callers from different classes
    $nodes = [
        ['id' => 'class:Target', 'type' => 'class', 'name' => 'Target'],
        ['id' => 'method:Target::doWork', 'type' => 'method', 'name' => 'doWork'],
        ['id' => 'class:CallerOne', 'type' => 'class', 'name' => 'CallerOne'],
        ['id' => 'method:CallerOne::run', 'type' => 'method', 'name' => 'run'],
        ['id' => 'class:CallerTwo', 'type' => 'class', 'name' => 'CallerTwo'],
        ['id' => 'method:CallerTwo::exec', 'type' => 'method', 'name' => 'exec'],
    ];
    $edges = [
        ['type' => 'declares', 'source' => 'class:Target', 'target' => 'method:Target::doWork'],
        ['type' => 'declares', 'source' => 'class:CallerOne', 'target' => 'method:CallerOne::run'],
        ['type' => 'declares', 'source' => 'class:CallerTwo', 'target' => 'method:CallerTwo::exec'],
        ['type' => 'calls', 'source' => 'method:CallerOne::run', 'target' => 'method:Target::doWork', 'weight' => 1],
        ['type' => 'calls', 'source' => 'method:CallerTwo::exec', 'target' => 'method:Target::doWork', 'weight' => 1],
    ];
    $factory = makeImpactFactory($nodes, $edges);

    $result = (new ImpactAnalysisTool($factory))->getImpactAnalysis('Target', 'doWork');

    expect($result)->toContain('Classes affected:               2');
});

it('returns error string on exception', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getGraphDataProvider')
        ->andThrow(new \RuntimeException('graph data unavailable'));

    $result = (new ImpactAnalysisTool($factory))->getImpactAnalysis('Foo');

    expect($result)->toBe('An error occurred while performing impact analysis.');
});
