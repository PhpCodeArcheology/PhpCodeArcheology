<?php

declare(strict_types=1);

use PhpCodeArch\Calculators\Helpers\TarjanSccAlgorithm;

it('finds no cycles in a DAG', function () {
    $tarjan = new TarjanSccAlgorithm();

    $graph = [
        'A' => ['B', 'C'],
        'B' => ['C'],
        'C' => [],
    ];

    $cycles = $tarjan->findCycles($graph);
    expect($cycles)->toBeEmpty();
});

it('finds a simple cycle between two nodes', function () {
    $tarjan = new TarjanSccAlgorithm();

    $graph = [
        'A' => ['B'],
        'B' => ['A'],
    ];

    $cycles = $tarjan->findCycles($graph);
    expect($cycles)->toHaveCount(1)
        ->and($cycles[0])->toHaveCount(2)
        ->and($cycles[0])->toContain('A')
        ->and($cycles[0])->toContain('B');
});

it('finds a cycle of three nodes', function () {
    $tarjan = new TarjanSccAlgorithm();

    $graph = [
        'A' => ['B'],
        'B' => ['C'],
        'C' => ['A'],
    ];

    $cycles = $tarjan->findCycles($graph);
    expect($cycles)->toHaveCount(1)
        ->and($cycles[0])->toHaveCount(3);
});

it('finds multiple independent cycles', function () {
    $tarjan = new TarjanSccAlgorithm();

    $graph = [
        'A' => ['B'],
        'B' => ['A'],
        'C' => ['D'],
        'D' => ['C'],
        'E' => [],
    ];

    $cycles = $tarjan->findCycles($graph);
    expect($cycles)->toHaveCount(2);
});

it('handles a graph with no edges', function () {
    $tarjan = new TarjanSccAlgorithm();

    $graph = [
        'A' => [],
        'B' => [],
        'C' => [],
    ];

    $cycles = $tarjan->findCycles($graph);
    expect($cycles)->toBeEmpty();
});
