<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use Marcus\PhpLegacyAnalyzer\Analysis\CyclomaticComplexityVisitor;
use Marcus\PhpLegacyAnalyzer\Analysis\IdentifyVisitor;
use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;

require_once __DIR__ . '/test_helpers.php';

$cycloTests = require __DIR__ . '/fileprovider/test-cyclo-provider.php';

function getCycloVisitors(): array
{
    return [
        IdentifyVisitor::class,
        CyclomaticComplexityVisitor::class
    ];
}

it('calculates cyclomatic complexity correctly', function($testFile, $expected) {
    $metrics = getMetricsForVisitors($testFile, getCycloVisitors());

    foreach ($metrics->getAll() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetrics:
                $cc = $metrics->get('cc');
                expect($cc)->toBe($expected['file']['cc']);
                break;

            case $metrics instanceof FunctionMetrics:
                $cc = $metrics->get('cc');
                $functionName = $metrics->getName();

                if (!isset($expected['function'][$functionName])) {
                    break;
                }

                $expectedForFunction = $expected['function'][$functionName];

                expect($cc)->toBe($expectedForFunction['cc']);
                break;

            case $metrics instanceof ClassMetrics:
                $cc = $metrics->get('cc');
                $className = $metrics->getName();
                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                $methods = $metrics->get('methods');

                if (!isset($expected['classes'][$className])) {
                    break;
                }

                $expectedForClass = $expected['classes'][$className];
                expect($cc)->toBe($expectedForClass['cc']);

                foreach ($methods as $methodMetric) {
                    $methodName = $methodMetric->getName();

                    if (! isset($expected['classes'][$className]['methods'][$methodName])) {
                        continue;
                    }

                    $expectedForMethod = $expected['classes'][$className]['methods'][$methodName];
                    expect($methodMetric->get('cc'))->toBe($expectedForMethod['cc']);
                }
                break;

        }
    }

})->with($cycloTests);
