<?php

declare(strict_types=1);

use PhpCodeArch\Mcp\Tools\ClassListTool;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\ClassDataProvider;
use PhpCodeArch\Report\DataProvider\DataProviderFactory;

function classListMv(mixed $value): MetricValue
{
    return MetricValue::ofValueAndTypeKey($value, 'dummy');
}

function makeClassCollection(string $name, array $values): ClassMetricsCollection
{
    $col = new ClassMetricsCollection('/src/' . $name . '.php', $name);
    foreach ($values as $key => $val) {
        $col->set($key, classListMv($val));
    }
    return $col;
}

function makeClassListFactory(array $classes): DataProviderFactory
{
    $provider = Mockery::mock(ClassDataProvider::class);
    $provider->shouldReceive('getTemplateData')->andReturn(['classes' => $classes]);

    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getClassDataProvider')->andReturn($provider);
    return $factory;
}

it('returns a formatted class list sorted by refactoring_priority by default', function () {
    $factory = makeClassListFactory([
        makeClassCollection('Alpha', ['singleName' => 'Alpha', 'fullName' => 'App\\Alpha', 'cc' => 5,  'lloc' => 100, 'maintainabilityIndex' => 70.0, 'refactoringPriority' => 30, 'usedFromOutsideCount' => 2]),
        makeClassCollection('Zeta',  ['singleName' => 'Zeta',  'fullName' => 'App\\Zeta',  'cc' => 20, 'lloc' => 400, 'maintainabilityIndex' => 30.0, 'refactoringPriority' => 80, 'usedFromOutsideCount' => 5]),
    ]);

    $result = (new ClassListTool($factory))->getClassList();

    expect($result)
        ->toContain('Class List')
        ->toContain('Zeta')
        ->toContain('Alpha');

    // Zeta has higher priority so it should appear first in the output
    expect(strpos($result, 'Zeta'))->toBeLessThan(strpos($result, 'Alpha'));
});

it('sorts by cc descending when sort_by=cc', function () {
    $factory = makeClassListFactory([
        makeClassCollection('SimpleClass', ['singleName' => 'SimpleClass', 'fullName' => 'App\\SimpleClass', 'cc' => 2,  'lloc' => 50,  'maintainabilityIndex' => 80.0, 'refactoringPriority' => 5, 'usedFromOutsideCount' => 0]),
        makeClassCollection('ComplexClass', ['singleName' => 'ComplexClass', 'fullName' => 'App\\ComplexClass', 'cc' => 25, 'lloc' => 500, 'maintainabilityIndex' => 30.0, 'refactoringPriority' => 5, 'usedFromOutsideCount' => 0]),
    ]);

    $result = (new ClassListTool($factory))->getClassList(sort_by: 'cc');

    // ComplexClass has higher cc so it should appear first
    expect($result)
        ->toContain('sorted by: cc')
        ->toContain('ComplexClass')
        ->toContain('SimpleClass');

    expect(strpos($result, 'ComplexClass'))->toBeLessThan(strpos($result, 'SimpleClass'));
});

it('filters classes by name substring', function () {
    $factory = makeClassListFactory([
        makeClassCollection('UserController', ['singleName' => 'UserController', 'fullName' => 'App\\UserController', 'cc' => 5, 'lloc' => 100, 'maintainabilityIndex' => 70.0, 'refactoringPriority' => 10, 'usedFromOutsideCount' => 1]),
        makeClassCollection('OrderService',   ['singleName' => 'OrderService',   'fullName' => 'App\\OrderService',   'cc' => 3, 'lloc' =>  80, 'maintainabilityIndex' => 75.0, 'refactoringPriority' =>  5, 'usedFromOutsideCount' => 0]),
    ]);

    $result = (new ClassListTool($factory))->getClassList(filter: 'User');

    expect($result)
        ->toContain('UserController')
        ->not->toContain('OrderService');
});

it('returns "No class data available" when classes are empty', function () {
    $factory = makeClassListFactory([]);

    $result = (new ClassListTool($factory))->getClassList();

    expect($result)->toBe('No class data available.');
});

it('respects the limit parameter', function () {
    $classes = array_map(
        fn($i) => makeClassCollection("Class{$i}", ['singleName' => "Class{$i}", 'fullName' => "App\\Class{$i}", 'cc' => $i, 'lloc' => $i * 10, 'maintainabilityIndex' => 50.0, 'refactoringPriority' => $i, 'usedFromOutsideCount' => 0]),
        range(1, 10)
    );
    $factory = makeClassListFactory($classes);

    $result = (new ClassListTool($factory))->getClassList(limit: 3);

    expect($result)->toContain('Total: 10 classes | Showing: 3');
});

it('returns an error string when an exception is thrown', function () {
    $factory = Mockery::mock(DataProviderFactory::class);
    $factory->shouldReceive('getClassDataProvider')
        ->andThrow(new \RuntimeException('provider error'));

    $result = (new ClassListTool($factory))->getClassList();

    expect($result)->toBe('Error retrieving class list: provider error');
});
