<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\RefactoringTool;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\RefactoringPriorityDataProvider;

function makeRefactoringFactory(array $priorities, array $extra = []): DataProviderFactory
{
    $provider = Mockery::mock(RefactoringPriorityDataProvider::class);
    $provider->shouldReceive('getTemplateData')->andReturn(array_merge([
        'refactoringPriorities'     => $priorities,
        'distribution'              => ['clean' => 5, 'low' => 3, 'medium' => 2, 'high' => 1, 'critical' => 0],
        'totalClasses'              => 11,
        'avgPriority'               => 15.3,
        'maxPriority'               => 80.0,
        'classesNeedingRefactoring' => 6,
    ], $extra));

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getRefactoringPriorityDataProvider')->andReturn($provider);
    return $factory;
}

function makePriority(string $name, float $score, array $drivers = []): array
{
    return [
        'name'                 => $name,
        'score'                => $score,
        'cc'                   => 12,
        'lloc'                 => 200,
        'lcom'                 => 0.8,
        'usedFromOutsideCount' => 5,
        'recommendation'       => 'Extract Method',
        'drivers'              => $drivers,
    ];
}

it('returns a formatted refactoring priority report', function () {
    $factory = makeRefactoringFactory([
        makePriority('GodClass', 90.0, ['too complex', 'too long']),
        makePriority('MediumClass', 40.0),
    ]);

    $result = (new RefactoringTool($factory))->getRefactoringPriorities();

    expect($result)
        ->toContain('Refactoring Priorities')
        ->toContain('GodClass')
        ->toContain('CRITICAL')
        ->toContain('MediumClass')
        ->toContain('Extract Method')
        ->toContain('too complex');
});

it('filters out candidates below min_score', function () {
    $factory = makeRefactoringFactory([
        makePriority('HighClass', 80.0),
        makePriority('LowClass',  10.0),
    ]);

    $result = (new RefactoringTool($factory))->getRefactoringPriorities(min_score: 50.0);

    expect($result)
        ->toContain('HighClass')
        ->not->toContain('LowClass');
});

it('shows "No refactoring candidates found" when list is empty', function () {
    $factory = makeRefactoringFactory([]);

    $result = (new RefactoringTool($factory))->getRefactoringPriorities();

    expect($result)->toContain('No refactoring candidates found');
});

it('respects the limit parameter', function () {
    $priorities = array_map(fn($i) => makePriority("Class{$i}", (float) $i), range(1, 20));
    $factory = makeRefactoringFactory($priorities);

    $result = (new RefactoringTool($factory))->getRefactoringPriorities(limit: 5);

    expect($result)->toContain('Top 20 candidates (showing 5)');
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getRefactoringPriorityDataProvider')
        ->andThrow(new \RuntimeException('unavailable'));

    $result = (new RefactoringTool($factory))->getRefactoringPriorities();

    expect($result)->toBe('Error retrieving refactoring priorities: unavailable');
});
