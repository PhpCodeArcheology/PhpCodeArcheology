<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\TypeCoverageVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

function getTypeCoverageVisitors(): array
{
    return [
        IdentifyVisitor::class,
        TypeCoverageVisitor::class,
    ];
}

it('calculates high type coverage for a fully typed class', function() {
    $testFile = __DIR__ . '/testfiles/type-coverage-full.php';
    $metricsController = getMetricsForVisitors($testFile, getTypeCoverageVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'FullyTypedClass') {
            $coverage = $metrics->get('typeCoverage')->getValue();
            expect($coverage)->toBe(100.0);
        }
    }
});

it('calculates low type coverage for an untyped class', function() {
    $testFile = __DIR__ . '/testfiles/type-coverage-full.php';
    $metricsController = getMetricsForVisitors($testFile, getTypeCoverageVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'UntypedClass') {
            $coverage = $metrics->get('typeCoverage')->getValue();
            expect($coverage)->toBeLessThan(50.0);
        }
    }
});

it('counts typed vs untyped parameters correctly', function() {
    $testFile = __DIR__ . '/testfiles/type-coverage-full.php';
    $metricsController = getMetricsForVisitors($testFile, getTypeCoverageVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'FullyTypedClass') {
            expect($metrics->get('typedParamCount')->getValue())->toBe(2)
                ->and($metrics->get('totalParamCount')->getValue())->toBe(2);
        }

        if ($metrics->getName() === 'UntypedClass') {
            expect($metrics->get('typedParamCount')->getValue())->toBe(0)
                ->and($metrics->get('totalParamCount')->getValue())->toBe(2);
        }
    }
});

it('counts typed vs untyped return values correctly', function() {
    $testFile = __DIR__ . '/testfiles/type-coverage-full.php';
    $metricsController = getMetricsForVisitors($testFile, getTypeCoverageVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'FullyTypedClass') {
            expect($metrics->get('typedReturnCount')->getValue())->toBe(2)
                ->and($metrics->get('totalReturnCount')->getValue())->toBe(2);
        }

        if ($metrics->getName() === 'UntypedClass') {
            expect($metrics->get('typedReturnCount')->getValue())->toBe(0)
                ->and($metrics->get('totalReturnCount')->getValue())->toBe(2);
        }
    }
});

it('counts typed vs untyped properties correctly', function() {
    $testFile = __DIR__ . '/testfiles/type-coverage-full.php';
    $metricsController = getMetricsForVisitors($testFile, getTypeCoverageVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'FullyTypedClass') {
            expect($metrics->get('typedPropertyCount')->getValue())->toBe(2)
                ->and($metrics->get('totalPropertyCount')->getValue())->toBe(2);
        }

        if ($metrics->getName() === 'UntypedClass') {
            expect($metrics->get('typedPropertyCount')->getValue())->toBe(0)
                ->and($metrics->get('totalPropertyCount')->getValue())->toBe(1);
        }
    }
});

it('handles mixed typed/untyped class correctly', function() {
    $testFile = __DIR__ . '/testfiles/type-coverage-full.php';
    $metricsController = getMetricsForVisitors($testFile, getTypeCoverageVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'MixedTypedClass') {
            $coverage = $metrics->get('typeCoverage')->getValue();
            expect($coverage)->toBeGreaterThan(0.0)
                ->and($coverage)->toBeLessThan(100.0);

            expect($metrics->get('typedParamCount')->getValue())->toBe(1)
                ->and($metrics->get('totalParamCount')->getValue())->toBe(2);

            expect($metrics->get('typedPropertyCount')->getValue())->toBe(1)
                ->and($metrics->get('totalPropertyCount')->getValue())->toBe(2);
        }
    }
});

it('aggregates type coverage at file level', function() {
    $testFile = __DIR__ . '/testfiles/type-coverage-full.php';
    $metricsController = getMetricsForVisitors($testFile, getTypeCoverageVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FileMetricsCollection) {
            continue;
        }

        $coverage = $metrics->get('typeCoverage')->getValue();
        expect($coverage)->toBeGreaterThan(0.0)
            ->and($coverage)->toBeLessThan(101.0);

        // File has mix: some typed, some untyped
        expect($metrics->get('totalParamCount')->getValue())->toBeGreaterThan(0)
            ->and($metrics->get('totalReturnCount')->getValue())->toBeGreaterThan(0);
    }
});
