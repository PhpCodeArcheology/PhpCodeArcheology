<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\SecuritySmellVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

function getSecurityVisitors(): array
{
    return [
        IdentifyVisitor::class,
        SecuritySmellVisitor::class,
    ];
}

it('detects dangerous function calls (exec, system, shell_exec)', function() {
    $testFile = __DIR__ . '/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'dangerousFunc') {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(3);
        }
    }
});

it('detects weak hash function calls (md5, sha1)', function() {
    $testFile = __DIR__ . '/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'weakHashFunc') {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(1);
        }
    }
});

it('detects unsafe unserialize calls', function() {
    $testFile = __DIR__ . '/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'unsafeUnserialize') {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(1);
        }
    }
});

it('detects SQL string concatenation', function() {
    $testFile = __DIR__ . '/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'sqlConcatFunc') {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(1);
        }
    }
});

it('does not flag safe code', function() {
    $testFile = __DIR__ . '/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'safeFunc') {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(0);
        }
    }
});

it('counts security smells per class method', function() {
    $testFile = __DIR__ . '/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    $dangerousClassSmellCount = null;
    $safeClassSmellCount = null;

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ($metrics->getName() === 'DangerousClass') {
            $dangerousClassSmellCount = $metrics->get('securitySmellCount')->getValue();
        }

        if ($metrics->getName() === 'SafeClass') {
            $safeClassSmellCount = $metrics->get('securitySmellCount')->getValue();
        }
    }

    expect($dangerousClassSmellCount)->toBe(2)
        ->and($safeClassSmellCount)->toBe(0);
});

it('aggregates security smell count at file level', function() {
    $testFile = __DIR__ . '/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FileMetricsCollection) {
            continue;
        }

        // dangerousFunc: 3, weakHashFunc: 1, unsafeUnserialize: 1, sqlConcatFunc: 1, DangerousClass methods: 2
        expect($metrics->get('securitySmellCount')->getValue())->toBe(8);
    }
});
