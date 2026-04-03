<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\GetTestCoverageTool;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\TestsDataProvider;

function makeTestCoverageFactory(array $stats = [], array $coverageGaps = []): DataProviderFactory
{
    $provider = Mockery::mock(TestsDataProvider::class);
    $provider->shouldReceive('getTemplateData')->andReturn([
        'stats' => $stats,
        'coverageGaps' => $coverageGaps,
    ]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getTestsDataProvider')->andReturn($provider);

    return $factory;
}

it('returns a formatted test coverage summary', function () {
    $factory = makeTestCoverageFactory([
        'testFileCount' => 5,
        'productionFileCount' => 20,
        'testRatio' => 33.3,
        'testedClassCount' => 3,
        'untestedClassCount' => 7,
        'testedClassRatio' => 30.0,
        'functionBasedTestFileCount' => 0,
        'detectedTestFrameworks' => 'Pest',
        'overallCoveragePercent' => null,
    ], []);

    $result = (new GetTestCoverageTool($factory))->getTestCoverage();

    expect($result)
        ->toContain('Test Coverage Summary')
        ->toContain('Test Frameworks: Pest')
        ->toContain('Tested Classes: 3 / 10');
});

it('respects the limit parameter', function () {
    $gaps = array_map(
        fn ($i) => [
            'name' => "Class{$i}",
            'fullName' => "Ns\\Class{$i}",
            'cc' => 10 - $i,
            'lloc' => 100,
            'refactoringPriority' => 5.0,
        ],
        range(1, 10)
    );
    $factory = makeTestCoverageFactory([
        'testFileCount' => 2,
        'productionFileCount' => 12,
        'testRatio' => 16.7,
        'testedClassCount' => 2,
        'untestedClassCount' => 10,
        'testedClassRatio' => 16.7,
        'functionBasedTestFileCount' => 0,
        'detectedTestFrameworks' => 'PHPUnit',
        'overallCoveragePercent' => null,
    ], $gaps);

    $result = (new GetTestCoverageTool($factory))->getTestCoverage(limit: 3);

    expect($result)->toContain('Top 3 Untested Complex Classes');
});

it('returns "no test infrastructure detected" when testFileCount=0 and no framework', function () {
    $factory = makeTestCoverageFactory([
        'testFileCount' => 0,
        'detectedTestFrameworks' => '',
    ], []);

    $result = (new GetTestCoverageTool($factory))->getTestCoverage();

    expect($result)->toContain('No test infrastructure detected');
});

it('shows line coverage when available (overallCoveragePercent)', function () {
    $factory = makeTestCoverageFactory([
        'testFileCount' => 10,
        'productionFileCount' => 30,
        'testRatio' => 33.3,
        'testedClassCount' => 8,
        'untestedClassCount' => 2,
        'testedClassRatio' => 80.0,
        'functionBasedTestFileCount' => 0,
        'detectedTestFrameworks' => 'Pest',
        'overallCoveragePercent' => 87.5,
    ], []);

    $result = (new GetTestCoverageTool($factory))->getTestCoverage();

    expect($result)->toContain('Line Coverage: 87.5% (from Clover XML)');
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getTestsDataProvider')
        ->andThrow(new RuntimeException('provider failed'));

    $result = (new GetTestCoverageTool($factory))->getTestCoverage();

    expect($result)->toBe('An error occurred while retrieving test coverage.');
});
