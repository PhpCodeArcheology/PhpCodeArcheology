<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\ProblemsTool;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\ProblemInterface;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\ProblemDataProvider;

function makeProblem(int $level, string $message): ProblemInterface
{
    $p = Mockery::mock(ProblemInterface::class);
    $p->shouldReceive('getProblemLevel')->andReturn($level);
    $p->shouldReceive('getMessage')->andReturn($message);
    return $p;
}

function makeProblemsFactory(array $fileProblems = [], array $classProblems = [], array $functionProblems = []): DataProviderFactory
{
    $provider = Mockery::mock(ProblemDataProvider::class);
    $provider->shouldReceive('getTemplateData')->andReturn([
        'fileProblems'     => $fileProblems,
        'classProblems'    => $classProblems,
        'functionProblems' => $functionProblems,
    ]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProblemDataProvider')->andReturn($provider);
    return $factory;
}

it('returns all problems when no filters are applied', function () {
    $factory = makeProblemsFactory(
        fileProblems: [
            '/src/Foo.php' => ['problems' => [makeProblem(PredictionInterface::ERROR, 'Too complex')]],
        ],
        classProblems: [
            'FooClass' => ['problems' => [makeProblem(PredictionInterface::WARNING, 'High coupling')]],
        ],
    );

    $result = (new ProblemsTool($factory))->getProblems();

    expect($result)
        ->toContain('2 total')
        ->toContain('[error]')
        ->toContain('[warning]')
        ->toContain('Too complex')
        ->toContain('High coupling');
});

it('filters problems by severity', function () {
    $factory = makeProblemsFactory(
        fileProblems: [
            '/src/Foo.php' => ['problems' => [
                makeProblem(PredictionInterface::ERROR,   'Critical issue'),
                makeProblem(PredictionInterface::WARNING, 'Minor issue'),
            ]],
        ],
    );

    $result = (new ProblemsTool($factory))->getProblems(severity: 'error');

    expect($result)
        ->toContain('Critical issue')
        ->not->toContain('Minor issue');
});

it('filters problems by type keyword', function () {
    $factory = makeProblemsFactory(
        classProblems: [
            'MyClass' => ['problems' => [
                makeProblem(PredictionInterface::WARNING, 'High cyclomatic complexity'),
                makeProblem(PredictionInterface::WARNING, 'Too many parameters'),
            ]],
        ],
    );

    $result = (new ProblemsTool($factory))->getProblems(type: 'cyclomatic');

    expect($result)
        ->toContain('cyclomatic')
        ->not->toContain('parameters');
});

it('respects the limit parameter', function () {
    $problems = array_map(
        fn($i) => makeProblem(PredictionInterface::WARNING, "Problem {$i}"),
        range(1, 10)
    );
    $factory = makeProblemsFactory(
        fileProblems: ['/src/Big.php' => ['problems' => $problems]],
    );

    $result = (new ProblemsTool($factory))->getProblems(limit: 3);

    expect($result)->toContain('10 total, showing 3');
});

it('shows "No problems found" when there are no problems', function () {
    $factory = makeProblemsFactory();

    $result = (new ProblemsTool($factory))->getProblems();

    expect($result)->toContain('No problems found');
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProblemDataProvider')
        ->andThrow(new \RuntimeException('db error'));

    $result = (new ProblemsTool($factory))->getProblems();

    expect($result)->toBe('An error occurred while retrieving problems.');
});
