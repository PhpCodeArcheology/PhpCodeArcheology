<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\SecuritySmellVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__.'/test_helpers.php';

function getSecurityVisitors(): array
{
    return [
        IdentifyVisitor::class,
        SecuritySmellVisitor::class,
    ];
}

it('detects dangerous function calls (exec, system, shell_exec)', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('dangerousFunc' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(3);
        }
    }
});

it('detects weak hash function calls (md5, sha1)', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('weakHashFunc' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(1);
        }
    }
});

it('detects unsafe unserialize calls', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('unsafeUnserialize' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(1);
        }
    }
});

it('detects SQL string concatenation', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('sqlConcatFunc' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(1);
        }
    }
});

it('does not flag safe code', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('safeFunc' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(0);
        }
    }
});

it('counts security smells per class method', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    $dangerousClassSmellCount = null;
    $safeClassSmellCount = null;

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof ClassMetricsCollection) {
            continue;
        }

        if ('DangerousClass' === $metrics->getName()) {
            $dangerousClassSmellCount = $metrics->get('securitySmellCount')->getValue();
        }

        if ('SafeClass' === $metrics->getName()) {
            $safeClassSmellCount = $metrics->get('securitySmellCount')->getValue();
        }
    }

    expect($dangerousClassSmellCount)->toBe(2)
        ->and($safeClassSmellCount)->toBe(0);
});

it('aggregates security smell count at file level', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FileMetricsCollection) {
            continue;
        }

        // dangerousFunc: 3, weakHashFunc: 1, unsafeUnserialize: 1, sqlConcatFunc: 1,
        // DangerousClass methods: 2, sqlConcatWithMixedSafeAndUnsafe: 1
        expect($metrics->get('securitySmellCount')->getValue())->toBe(9);
    }
});

it('does not flag SQL concat when all dynamic operands are class constants', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('sqlConcatWithClassConstants' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(0);
        }
    }
});

it('does not flag SQL concat when all dynamic operands are global constants', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('sqlConcatWithGlobalConstant' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(0);
        }
    }
});

it('flags SQL concat when at least one operand is unsafe', function () {
    $testFile = __DIR__.'/testfiles/security-smells.php';
    $metricsController = getMetricsForVisitors($testFile, getSecurityVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        if (!$metrics instanceof FunctionMetricsCollection) {
            continue;
        }

        if ('sqlConcatWithMixedSafeAndUnsafe' === $metrics->getName()) {
            expect($metrics->get('securitySmellCount')->getValue())->toBe(1);
        }
    }
});
