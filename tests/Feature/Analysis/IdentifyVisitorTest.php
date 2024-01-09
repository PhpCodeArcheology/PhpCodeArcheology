<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;

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
    $metrics = getMetricsForVisitors($testFile, getIdVisitors());

    $projectMetrics = $metrics->get('project');

    $functions = $metrics->get('functions');

    expect(count($functions))->toBe($expected['functionCount'])
        ->and($projectMetrics->get('OverallFunctions'))->toBe($expected['functionCount'])
        ->and(array_values($functions))->toBe($expected['functionNames']);

    $functionNames = [];
    foreach ($functions as $key => $name) {
        $functionNames[] = $metrics->get($key)->getName();
    }

    expect($functionNames)->toBe($expected['functionNames']);

})->with($testFunctions);

it('detects classes correctly', function($testFile, $expected) {
    $metrics = getMetricsForVisitors($testFile, getIdVisitors());

    $projectMetrics = $metrics->get('project');

    $classes = $metrics->get('classes');
    $classNamesFromClassesArray = array_map(function($className) {
        return str_starts_with($className, 'anonymous') ? 'anonymous' : $className;
    }, $classes);

    expect(count($classes))->toBe($expected['classCount'])
        ->and($projectMetrics->get('OverallClasses'))->toBe($expected['classCount'])
        ->and(array_values($classNamesFromClassesArray))->toBe($expected['classNames']);

    $classNames = [];
    foreach ($classes as $key => $name) {
        $className = str_starts_with($metrics->get($key)->getName(), 'anonymous') ? 'anonymous' : $metrics->get($key)->getName();
        $classNames[] = $className;
    }

    expect($classNames)->toBe($expected['classNames']);

})->with($testClasses);

it('detects methods correctly', function($testFile, $expected) {
    $metrics = getMetricsForVisitors($testFile, getIdVisitors());

    $projectMetrics = $metrics->get('project');

    $classes = $metrics->get('classes');

    $methods = [];
    $methodCountOfAnonymousClass = 0;
    $methodNamesOfAnonymousClass = [];
    foreach ($classes as $key => $className) {
        $classMetrics = $metrics->get($key);
        $classMethods = $classMetrics->get('methods');
        $methods = array_merge($methods, $classMethods);

        if ($classMetrics->get('anonymous')) {
            $methodCountOfAnonymousClass += count($classMethods);
            foreach ($classMethods as $methodMetric) {
                $methodNamesOfAnonymousClass[] = $methodMetric->getName();
            }
        }
    }

    $methodNames = array_map(function($method) {
        return $method->getName();
    }, array_values($methods));

    expect($projectMetrics->get('OverallMethods'))->toBe($expected['methodCount'])
        ->and(count($methods))->toBe($expected['methodCount'])
        ->and($methodNames)->toBe($expected['methodNames'])
        ->and($projectMetrics->get('OverallPublicMethods'))->toBe($expected['publicMethods'])
        ->and($projectMetrics->get('OverallPrivateMethods'))->toBe($expected['privateMethods'])
        ->and($projectMetrics->get('OverallStaticMethods'))->toBe($expected['staticMethods'])
        ->and(count($classes))->toBe($expected['classCount']);

    if (isset($expected['methodCountAnonymousClass'])) {
        expect($methodCountOfAnonymousClass)->toBe($expected['methodCountAnonymousClass'])
            ->and($methodNamesOfAnonymousClass)->toBe($expected['methodNamesAnonymousClass']);
    }

})->with($testMethods);

it('detects correct class types', function() {
    $testFile = __DIR__ . '/testfiles/class-types.php';

    $metrics = getMetricsForVisitors($testFile, getIdVisitors());

    expect(count($metrics->get('classes')))->toBe(1)
        ->and(count($metrics->get('interfaces')))->toBe(1)
        ->and(count($metrics->get('traits')))->toBe(1)
        ->and(count($metrics->get('enums')))->toBe(1);
});
