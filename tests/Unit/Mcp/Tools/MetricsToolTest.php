<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\MetricsTool;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

function metricsMv(mixed $value): MetricValue
{
    return MetricValue::ofValueAndTypeKey($value, 'dummy');
}

function makeMetricsTool(array $collections): MetricsTool
{
    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getAllCollections')->andReturn($collections);

    $factory = Mockery::mock(DataProviderFactory::class)->shouldIgnoreMissing();

    return new MetricsTool($factory, $mc);
}

it('returns metrics for a found class entity', function () {
    $class = new ClassMetricsCollection('/src/FooClass.php', 'FooClass');
    $class->set('singleName', metricsMv('FooClass'));
    $class->set('cc', metricsMv(5));
    $class->set('lloc', metricsMv(120));

    $result = makeMetricsTool([$class])->getMetrics('FooClass');

    expect($result)
        ->toContain('# Metrics: FooClass (class)')
        ->toContain('cc:')
        ->toContain('5')
        ->toContain('lloc:')
        ->toContain('120');
});

it('returns metrics for a file entity', function () {
    $file = new FileMetricsCollection('/src/helpers.php');
    $file->set('loc', metricsMv(80));

    $result = makeMetricsTool([$file])->getMetrics('helpers');

    expect($result)
        ->toContain('# Metrics: /src/helpers.php (file)')
        ->toContain('loc:');
});

it('filters by entity type', function () {
    $class = new ClassMetricsCollection('/src/FooClass.php', 'FooClass');
    $class->set('singleName', metricsMv('FooClass'));

    $result = makeMetricsTool([$class])->getMetrics('FooClass', type: 'file');

    expect($result)->toContain("No entity found matching 'FooClass' (type: file)");
});

it('returns "no entity found" when nothing matches', function () {
    $result = makeMetricsTool([])->getMetrics('NonExistent');

    expect($result)->toBe("No entity found matching 'NonExistent'.");
});

it('handles collections with no metrics gracefully', function () {
    $class = new ClassMetricsCollection('/src/Empty.php', 'EmptyClass');

    $result = makeMetricsTool([$class])->getMetrics('Empty');

    expect($result)->toContain('(no metrics available)');
});

it('formats boolean metric values as true/false', function () {
    $class = new ClassMetricsCollection('/src/Foo.php', 'Foo');
    $class->set('singleName', metricsMv('Foo'));
    $class->set('isAbstract', metricsMv(true));

    $result = makeMetricsTool([$class])->getMetrics('Foo');

    expect($result)->toContain('true');
});

it('returns an error string when an exception is thrown', function () {
    $mc = Mockery::mock(MetricsController::class);
    $mc->shouldReceive('getAllCollections')->andThrow(new \RuntimeException('controller error'));

    $factory = Mockery::mock(DataProviderFactory::class)->shouldIgnoreMissing();

    $result = (new MetricsTool($factory, $mc))->getMetrics('Any');

    expect($result)->toBe('Error retrieving metrics: controller error');
});
