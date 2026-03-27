<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\DependenciesTool;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\ClassCouplingDataProvider;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

function depMv(mixed $value): MetricValue
{
    return MetricValue::ofValueAndTypeKey($value, 'dummy');
}

function makeDepsFactory(array $classes): DataProviderFactory
{
    $provider = Mockery::mock(ClassCouplingDataProvider::class);
    $provider->shouldReceive('getTemplateData')->andReturn(['classes' => $classes]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getClassCouplingDataProvider')->andReturn($provider);
    return $factory;
}

function makeCouplingClass(string $name, array $usesInProject = [], array $usedBy = []): ClassMetricsCollection
{
    $col = new ClassMetricsCollection('/src/' . $name . '.php', $name);
    $col->set('singleName',    depMv($name));
    $col->set('usesCount',     depMv(count($usesInProject)));
    $col->set('usedByCount',   depMv(count($usedBy)));
    $col->set('instability',   depMv(0.6));
    $col->set('usesInProject', depMv($usesInProject));
    $col->set('usedBy',        depMv($usedBy));
    return $col;
}

it('returns dependency details for a found class', function () {
    $factory = makeDepsFactory([
        makeCouplingClass('OrderService', usesInProject: ['UserRepository', 'PaymentGateway'], usedBy: ['OrderController']),
    ]);

    $result = (new DependenciesTool($factory))->getDependencies('OrderService');

    expect($result)
        ->toContain('# Dependencies: OrderService')
        ->toContain('UserRepository')
        ->toContain('PaymentGateway')
        ->toContain('OrderController')
        ->toContain('Outgoing dependencies (uses):   2')
        ->toContain('Incoming dependencies (usedBy): 1');
});

it('shows only outgoing dependencies when direction=outgoing', function () {
    $factory = makeDepsFactory([
        makeCouplingClass('FooService', usesInProject: ['BarRepo'], usedBy: ['FooController']),
    ]);

    $result = (new DependenciesTool($factory))->getDependencies('FooService', direction: 'outgoing');

    expect($result)
        ->toContain('Outgoing Dependencies')
        ->toContain('BarRepo')
        ->not->toContain('Incoming Dependencies')
        ->not->toContain('FooController');
});

it('shows only incoming dependencies when direction=incoming', function () {
    $factory = makeDepsFactory([
        makeCouplingClass('FooService', usesInProject: ['BarRepo'], usedBy: ['FooController']),
    ]);

    $result = (new DependenciesTool($factory))->getDependencies('FooService', direction: 'incoming');

    expect($result)
        ->toContain('Incoming Dependencies')
        ->toContain('FooController')
        ->not->toContain('Outgoing Dependencies')
        ->not->toContain('BarRepo');
});

it('does a case-insensitive partial name match', function () {
    $factory = makeDepsFactory([
        makeCouplingClass('UserRepository'),
    ]);

    $result = (new DependenciesTool($factory))->getDependencies('userrepo');

    expect($result)->toContain('# Dependencies: UserRepository');
});

it('returns "not found" when class does not exist', function () {
    $factory = makeDepsFactory([
        makeCouplingClass('OrderService'),
    ]);

    $result = (new DependenciesTool($factory))->getDependencies('NonExistent');

    expect($result)->toBe("Class 'NonExistent' not found.");
});

it('shows "(none)" when there are no dependencies', function () {
    $factory = makeDepsFactory([
        makeCouplingClass('Isolated'),
    ]);

    $result = (new DependenciesTool($factory))->getDependencies('Isolated');

    expect($result)->toContain('(none)');
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getClassCouplingDataProvider')
        ->andThrow(new \RuntimeException('coupling error'));

    $result = (new DependenciesTool($factory))->getDependencies('Any');

    expect($result)->toBe('An error occurred while retrieving dependencies.');
});
