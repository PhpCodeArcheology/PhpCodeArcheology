<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

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
    $metricsController = getMetricsForVisitors($testFile, getCycloVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $cc = $metrics->get('cc')->getValue();
                expect($cc)->toBe($expected['file']['cc']);
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $cc = $metrics->get('cc')->getValue();
                $functionName = $metrics->getName();

                if (!isset($expected['function'][$functionName])) {
                    break;
                }

                $expectedForFunction = $expected['function'][$functionName];

                expect($cc)->toBe($expectedForFunction['cc']);
                break;

            case $metrics instanceof ClassMetricsCollection:
                $cc = $metrics->get('cc')->getValue();
                $className = $metrics->getName();
                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                $methods = $metrics->getCollection('methods');

                if (!isset($expected['classes'][$className])) {
                    break;
                }

                $expectedForClass = $expected['classes'][$className];
                expect($cc)->toBe($expectedForClass['cc']);

                foreach ($methods as $key => $methodName) {
                    $methodMetric = $metricsController->getMetricCollectionByIdentifierString($key);

                    if (! isset($expected['classes'][$className]['methods'][$methodName])) {
                        continue;
                    }

                    $expectedForMethod = $expected['classes'][$className]['methods'][$methodName];
                    expect($methodMetric->get('cc')->getValue())->toBe($expectedForMethod['cc']);
                }
                break;

        }
    }

})->with($cycloTests);
