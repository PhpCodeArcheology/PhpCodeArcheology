<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;

require_once __DIR__.'/test_helpers.php';

$testFunctions = require __DIR__.'/fileprovider/test-functions-provider.php';
$testClasses = require __DIR__.'/fileprovider/test-classes-provider.php';
$testMethods = require __DIR__.'/fileprovider/test-methods-provider.php';

function getIdVisitors(): array
{
    return [
        IdentifyVisitor::class,
    ];
}

it('detects functions correctly', function ($testFile, $expected) {
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

it('detects classes correctly', function ($testFile, $expected) {
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

    $classNamesFromClassesArray = array_map(function ($className) {
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

it('detects methods correctly', function ($testFile, $expected) {
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

    expect($projectMetrics->get('overallMethodsCount')->getValue())->toBe($expected['methodCount'])
        ->and(count($methods))->toBe($expected['methodCount'])
        ->and($methods)->toBe($expected['methodNames'])
        ->and($projectMetrics->get('overallPublicMethodsCount')->getValue())->toBe($expected['publicMethods'])
        ->and($projectMetrics->get('overallPrivateMethodsCount')->getValue())->toBe($expected['privateMethods'])
        ->and($projectMetrics->get('overallStaticMethodsCount')->getValue())->toBe($expected['staticMethods'])
        ->and(count($classes))->toBe($expected['classCount']);

    if (isset($expected['methodCountAnonymousClass'])) {
        expect($methodCountOfAnonymousClass)->toBe($expected['methodCountAnonymousClass'])
            ->and($methodNamesOfAnonymousClass)->toBe($expected['methodNamesAnonymousClass']);
    }
})->with($testMethods);

it('detects correct class types', function () {
    $testFile = __DIR__.'/testfiles/class-types.php';

    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $collections = [
        'classes' => [],
        'interfaces' => [],
        'traits' => [],
        'enums' => [],
    ];

    array_walk($collections, function (&$value, $key) use ($metricsController) {
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

it('extracts correct namespace when class name appears in namespace', function () {
    $testFile = __DIR__.'/testfiles/fqn-namespace-collision.php';
    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $classes = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'classes'
    )->getAsArray();

    // Hand-calculated: FQN = App\Account\EmployeeReport\Model\Account
    // Short name = Account
    // Expected namespace = App\Account\EmployeeReport\Model
    foreach ($classes as $key => $name) {
        $classMetrics = $metricsController->getMetricCollectionByIdentifierString($key);
        expect($classMetrics->get('singleName')->getValue())->toBe('Account')
            ->and($classMetrics->get('namespace')->getValue())->toBe('App\Account\EmployeeReport\Model');
    }
});

it('extracts correct namespace when function name appears in namespace', function () {
    $testFile = __DIR__.'/testfiles/fqn-function-namespace-collision.php';
    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $functions = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'functions'
    )->getAsArray();

    // Hand-calculated: FQN = App\Helper\Helper\helper
    // Short name = helper
    // Expected namespace = App\Helper\Helper
    foreach ($functions as $key => $name) {
        $fnMetrics = $metricsController->getMetricCollectionByIdentifierString($key);
        expect($fnMetrics->get('singleName')->getValue())->toBe('helper')
            ->and($fnMetrics->get('namespace')->getValue())->toBe('App\Helper\Helper');
    }
});

it('sets fullName metric on namespaced class collections', function () {
    $testFile = __DIR__.'/testfiles/ANamespacedClass.php';
    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $classes = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'classes'
    )->getAsArray();

    $found = false;
    foreach ($classes as $key => $name) {
        $classMetrics = $metricsController->getMetricCollectionByIdentifierString($key);
        if ('ANamespacedClass' === $classMetrics->get('singleName')->getValue()) {
            // Hand-calculated: FQN = Testfile\ANamespacedClass, namespace = Testfile
            expect($classMetrics->get('fullName')->getValue())->toBe('Testfile\\ANamespacedClass')
                ->and($classMetrics->get('namespace')->getValue())->toBe('Testfile');
            $found = true;
        }
    }

    expect($found)->toBeTrue();
});

it('sets fullName metric on global class collections', function () {
    $testFile = __DIR__.'/testfiles/AClass.php';
    $metricsController = getMetricsForVisitors($testFile, getIdVisitors());

    $classes = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'classes'
    )->getAsArray();

    $found = false;
    foreach ($classes as $key => $name) {
        $classMetrics = $metricsController->getMetricCollectionByIdentifierString($key);
        if ('AClass' === $classMetrics->get('singleName')->getValue()) {
            // Hand-calculated: FQN = AClass (no namespace), namespace = ''
            expect($classMetrics->get('fullName')->getValue())->toBe('AClass')
                ->and($classMetrics->get('namespace')->getValue())->toBe('');
            $found = true;
        }
    }

    expect($found)->toBeTrue();
});
