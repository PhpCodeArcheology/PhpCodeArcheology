<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\RuntimeComplexityVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

function getRuntimeComplexityVisitors(): array
{
    return [
        IdentifyVisitor::class,
        RuntimeComplexityVisitor::class,
    ];
}

it('reports O(1) for functions with no loops', function() {
    $testFile = __DIR__ . '/testfiles/runtime-complexity.php';
    $metricsController = getMetricsForVisitors($testFile, getRuntimeComplexityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'noLoops') {
            expect($metrics->get('estimatedRuntimeComplexity')->getValue())->toBe('O(1)');
        }
    }
});

it('reports O(n) for functions with a single loop', function() {
    $testFile = __DIR__ . '/testfiles/runtime-complexity.php';
    $metricsController = getMetricsForVisitors($testFile, getRuntimeComplexityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'singleLoop') {
            expect($metrics->get('estimatedRuntimeComplexity')->getValue())->toBe('O(n)');
        }
    }
});

it('reports O(n²) for functions with nested loops', function() {
    $testFile = __DIR__ . '/testfiles/runtime-complexity.php';
    $metricsController = getMetricsForVisitors($testFile, getRuntimeComplexityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'nestedLoops') {
            expect($metrics->get('estimatedRuntimeComplexity')->getValue())->toBe('O(n²)');
        }
    }
});

it('reports O(n³+) for functions with triple nested loops', function() {
    $testFile = __DIR__ . '/testfiles/runtime-complexity.php';
    $metricsController = getMetricsForVisitors($testFile, getRuntimeComplexityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'tripleNestedLoops') {
            expect($metrics->get('estimatedRuntimeComplexity')->getValue())->toBe('O(n³+)');
        }
    }
});

it('reports correct complexity for class methods', function() {
    $testFile = __DIR__ . '/testfiles/runtime-complexity.php';
    $metricsController = getMetricsForVisitors($testFile, getRuntimeComplexityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() !== 'LoopClass') {
            continue;
        }

        $methods = $metrics->getCollection('methods');
        foreach ($methods as $key => $methodName) {
            $methodMetric = $metricsController->getMetricCollectionByIdentifierString($key);
            $complexity = $methodMetric->get('estimatedRuntimeComplexity')->getValue();

            match ($methodName) {
                'noLoop'        => expect($complexity)->toBe('O(1)'),
                'withLoop'      => expect($complexity)->toBe('O(n)'),
                'withNestedLoop' => expect($complexity)->toBe('O(n²)'),
                default         => null,
            };
        }
    }
});

it('reports the maximum loop depth at file level', function() {
    $testFile = __DIR__ . '/testfiles/runtime-complexity.php';
    $metricsController = getMetricsForVisitors($testFile, getRuntimeComplexityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FileMetricsCollection) {
            continue;
        }

        // File contains tripleNestedLoops, so max depth is 3 => O(n³+)
        expect($metrics->get('estimatedRuntimeComplexity')->getValue())->toBe('O(n³+)');
    }
});
