<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\HealthScoreTool;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;
use PhpCodeArch\Report\DataProvider\ProjectDataProvider;

// Helper to create a MetricValue without needing a full MetricType
$mv = fn(mixed $value): MetricValue => MetricValue::ofValueAndTypeKey($value, 'dummy');

it('returns a formatted health score report', function () use ($mv) {
    $elements = [
        'healthScore'                => $mv(85),
        'healthScoreGrade'           => $mv('B'),
        'overallTechnicalDebtScore'  => $mv(12.5),
        'overallErrorCount'          => $mv(3),
        'overallWarningCount'        => $mv(10),
        'overallInformationCount'    => $mv(5),
        'overallFiles'               => $mv(42),
        'overallClasses'             => $mv(30),
        'overallFunctions'           => $mv(15),
        'overallMethods'             => $mv(120),
        'overallLloc'                => $mv(5000),
        'overallAvgCC'               => $mv(2.5),
        'overallAvgMI'               => $mv(75.0),
    ];

    $projectProvider = Mockery::mock(ProjectDataProvider::class);
    $projectProvider->shouldReceive('getTemplateData')->andReturn(['elements' => $elements]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProjectDataProvider')->andReturn($projectProvider);

    $result = (new HealthScoreTool($factory))->getHealthScore();

    expect($result)
        ->toContain('85/100')
        ->toContain('Grade: B')
        ->toContain('Errors:   3')
        ->toContain('Warnings: 10')
        ->toContain('Info:     5')
        ->toContain('Files:     42')
        ->toContain('Classes:   30')
        ->toContain('LLOC:      5000');
});

it('returns report with zero values when MetricValues are null', function () use ($mv) {
    // Elements present but MetricValues set to null-ish — tool defaults to 0 via ?? 0
    $projectProvider = Mockery::mock(ProjectDataProvider::class);
    $projectProvider->shouldReceive('getTemplateData')->andReturn([
        'elements' => [
            'healthScore'               => $mv(null),
            'healthScoreGrade'          => $mv(null),
            'overallTechnicalDebtScore' => $mv(null),
            'overallErrorCount'         => $mv(0),
            'overallWarningCount'       => $mv(0),
            'overallInformationCount'   => $mv(0),
            'overallFiles'              => $mv(0),
            'overallClasses'            => $mv(0),
            'overallFunctions'          => $mv(0),
            'overallMethods'            => $mv(0),
            'overallLloc'               => $mv(0),
            'overallAvgCC'              => $mv(0),
            'overallAvgMI'              => $mv(0),
        ],
    ]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProjectDataProvider')->andReturn($projectProvider);

    $result = (new HealthScoreTool($factory))->getHealthScore();

    expect($result)->toContain('Code Health Report');
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getProjectDataProvider')
        ->andThrow(new \RuntimeException('connection failed'));

    $result = (new HealthScoreTool($factory))->getHealthScore();

    expect($result)->toBe('An error occurred while retrieving the health score.');
});
