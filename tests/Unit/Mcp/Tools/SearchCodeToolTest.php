<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\SearchCodeTool;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

function searchMv(mixed $value): MetricValue
{
    return MetricValue::ofValueAndTypeKey($value, 'dummy');
}

function makeSearchTool(array $collections): SearchCodeTool
{
    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getAllCollections')->andReturn($collections);

    $factory = Mockery::mock(DataProviderFactory::class)->shouldIgnoreMissing();

    return new SearchCodeTool($mc);
}

it('finds a class by name', function () {
    $class = new ClassMetricsCollection('/src/UserService.php', 'UserService');
    $class->set('singleName', searchMv('UserService'));
    $class->set('cc', searchMv(5));
    $class->set('lloc', searchMv(150));

    $result = makeSearchTool([$class])->searchCode('UserService');

    expect($result)
        ->toContain("Search Results: 'UserService'")
        ->toContain('UserService')
        ->toContain('class');
});

it('finds a file by partial name match', function () {
    $file = new FileMetricsCollection('/src/bootstrap.php');

    $result = makeSearchTool([$file])->searchCode('bootstrap');

    expect($result)->toContain('file');
});

it('finds a function by singleName', function () {
    $fn = new FunctionMetricsCollection('/src/helpers.php', 'formatDate');
    $fn->set('singleName', searchMv('formatDate'));

    $result = makeSearchTool([$fn])->searchCode('format');

    expect($result)->toContain('function');
});

it('returns "no results found" when nothing matches', function () {
    $class = new ClassMetricsCollection('/src/FooClass.php', 'FooClass');
    $class->set('singleName', searchMv('FooClass'));

    $result = makeSearchTool([$class])->searchCode('Totally_Nonexistent_XYZ');

    expect($result)->toContain("No results found for 'Totally_Nonexistent_XYZ'");
});

it('filters by entity_type=class', function () {
    $class = new ClassMetricsCollection('/src/Handler.php', 'Handler');
    $class->set('singleName', searchMv('Handler'));

    $file = new FileMetricsCollection('/src/handler_bootstrap.php');

    $result = makeSearchTool([$class, $file])->searchCode('handler', entity_type: 'class');

    expect($result)
        ->toContain('class')
        ->not->toContain('file');
});

it('filters by entity_type=file', function () {
    $class = new ClassMetricsCollection('/src/Parser.php', 'Parser');
    $class->set('singleName', searchMv('Parser'));

    $file = new FileMetricsCollection('/src/parser_helpers.php');

    $result = makeSearchTool([$class, $file])->searchCode('parser', entity_type: 'file');

    expect($result)->toContain('file');
    expect($result)->not->toContain('class');
});

it('includes type hint in "no results" message when entity_type is set', function () {
    $result = makeSearchTool([])->searchCode('anything', entity_type: 'class');

    expect($result)->toContain('(type: class)');
});

it('respects the limit parameter', function () {
    $classes = array_map(
        fn($i) => (function () use ($i) {
            $c = new ClassMetricsCollection("/src/Service{$i}.php", "Service{$i}");
            $c->set('singleName', searchMv("Service{$i}"));
            return $c;
        })(),
        range(1, 10)
    );

    $result = makeSearchTool($classes)->searchCode('Service', limit: 3);

    expect($result)->toContain('Total: 10 | Showing: 3');
});

it('returns an error string when the query is empty', function () {
    $result = makeSearchTool([])->searchCode('');

    expect($result)->toBe('Error: query must not be empty.');
});

it('returns an error string when an exception is thrown', function () {
    $mc = Mockery::mock(MetricsController::class);
    $mc->shouldReceive('getAllCollections')->andThrow(new \RuntimeException('mc error'));

    $factory = Mockery::mock(DataProviderFactory::class)->shouldIgnoreMissing();

    $result = (new SearchCodeTool($mc))->searchCode('anything');

    expect($result)->toBe('An error occurred while searching code.');
});
