<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

require_once __DIR__ . '/test_helpers.php';

$testFunctions = require __DIR__ . '/fileprovider/test-functions-provider.php';
$testClasses = require __DIR__ . '/fileprovider/test-classes-provider.php';
$testMethods = require __DIR__ . '/fileprovider/test-methods-provider.php';

function getIdVisitors(): array
{
    return [
        IdentifyVisitor::class,
    ];
}

it('detects functions correctly', function($testFile, $expected) {
    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $projectMetrics = $metricsController->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $functions = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'functions'
    )->getAsArray();

    expect(count($functions))->toBe($expected['functionCount'])
        ->and($projectMetrics->get('overallFunctionCount')->getValue())->toBe($expected['functionCount'])
        ->and(array_values($functions))->toBe($expected['functionNames']);

    $functionNames = [];
    foreach ($functions as $key => $name) {
        $functionMetrics = $metricsController->getMetricCollectionByIdentifierString($key);
        $functionNames[] = $functionMetrics->getName();
    }

    expect($functionNames)->toBe($expected['functionNames']);

})->with($testFunctions);

it('detects classes correctly', function($testFile, $expected) {
    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $projectMetrics = $metricsController->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $classes = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'classes'
    )->getAsArray();

    $classNamesFromClassesArray = array_map(function($className) {
        return str_starts_with($className, 'anonymous') ? 'anonymous' : $className;
    }, $classes);

    expect(count($classes))->toBe($expected['classCount'])
        ->and($projectMetrics->get('overallClasses')->getValue())->toBe($expected['classCount'])
        ->and(array_values($classNamesFromClassesArray))->toBe($expected['classNames']);

    $classNames = [];
    foreach ($classes as $key => $name) {
        $classMetrics = $metricsController->getMetricCollectionByIdentifierString($key);
        $className = str_starts_with($classMetrics->getName(), 'anonymous') ? 'anonymous' : $classMetrics->getName();
        $classNames[] = $className;
    }

    expect($classNames)->toBe($expected['classNames']);

})->with($testClasses);

it('detects methods correctly', function($testFile, $expected) {
    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $projectMetrics = $metricsController->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $classes = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'classes'
    )->getAsArray();

    $methods = [];
    $methodCountOfAnonymousClass = 0;
    $methodNamesOfAnonymousClass = [];
    foreach ($classes as $key => $className) {
        $classMetrics = $metricsController->getMetricCollectionByIdentifierString($key);
        $classMethods = $classMetrics->getCollection('methods')->getAsArray();
        $methods = array_merge($methods, $classMethods);

        if ($classMetrics->get('anonymous')->getValue()) {
            $methodCountOfAnonymousClass += count($classMethods);
            foreach ($classMethods as $methodName) {
                $methodNamesOfAnonymousClass[] = $methodName;
            }
        }
    }

    $methods = array_values($methods);

    expect($projectMetrics->get('overAllMethodsCount')->getValue())->toBe($expected['methodCount'])
        ->and(count($methods))->toBe($expected['methodCount'])
        ->and($methods)->toBe($expected['methodNames'])
        ->and($projectMetrics->get('overAllPublicMethodsCount')->getValue())->toBe($expected['publicMethods'])
        ->and($projectMetrics->get('overAllPrivateMethodsCount')->getValue())->toBe($expected['privateMethods'])
        ->and($projectMetrics->get('overAllStaticMethodsCount')->getValue())->toBe($expected['staticMethods'])
        ->and(count($classes))->toBe($expected['classCount']);

    if (isset($expected['methodCountAnonymousClass'])) {
        expect($methodCountOfAnonymousClass)->toBe($expected['methodCountAnonymousClass'])
            ->and($methodNamesOfAnonymousClass)->toBe($expected['methodNamesAnonymousClass']);
    }

})->with($testMethods);

it('detects correct class types', function() {
    $testFile = __DIR__ . '/testfiles/class-types.php';

    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $collections = [
        'classes' => [],
        'interfaces' => [],
        'traits' => [],
        'enums' => [],
    ];

    array_walk($collections, function(&$value, $key) use($metricsController) {
        $value = $metricsController->getCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $key
        )->getAsArray();
    });

    expect(count($collections['classes']))->toBe(1)
        ->and(count($collections['interfaces']))->toBe(1)
        ->and(count($collections['traits']))->toBe(1)
        ->and(count($collections['enums']))->toBe(1);
});
