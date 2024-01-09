<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\HalsteadMetricsVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Analysis\MaintainabilityIndexVisitor;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;
use PhpCodeArch\Metrics\MetricsInterface;

require_once __DIR__ . '/test_helpers.php';

$maintainabilityTests = require __DIR__ . '/fileprovider/test-maintain-provider.php';

function getMaintainabilityVisitors(): array
{
    return [
        IdentifyVisitor::class,
        LocVisitor::class,
        CyclomaticComplexityVisitor::class,
        HalsteadMetricsVisitor::class,
        MaintainabilityIndexVisitor::class,
    ];
}

function getHalstead(MetricsInterface $metric): array
{
    return [
        'mi' => $metric->get('maintainabilityIndex'),
        'miWOC' => $metric->get('maintainabilityIndexWithoutComments'),
        'cW' => $metric->get('commentWeight'),
    ];
}

it('calculates maintainability index correctly', function($testFile, $expects) {
    $metrics = getMetricsForVisitors($testFile, getMaintainabilityVisitors());

    foreach ($metrics->getAll() as $metric) {
        switch (true) {
            case $metric instanceof FileMetrics:
                $halstead = getHalstead($metric);
                expect($halstead)->toBe($expects['file']['halstead']);
                break;

            case $metric instanceof FunctionMetrics:
                $halstead = getHalstead($metric);

                $fnName = $metric->getName();

                if (! isset($expects['functions'][$fnName])) {
                    break;
                }

                expect($halstead)->toBe($expects['functions'][$fnName]['halstead']);
                break;

            case $metric instanceof ClassMetrics:
                $halstead = getHalstead($metric);

                $className = $metric->getName();
                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                expect($halstead)->toBe($expects['classes'][$className]['halstead']);

                if (! isset($expects['classes'][$className])) {
                    break;
                }

                $methods = $metric->get('methods');
                foreach ($methods as $methodMetric) {
                    $methodName = $methodMetric->getName();
                    if (! isset($expects['classes'][$className]['methods'][$methodName])) {
                        continue;
                    }

                    $halstead = getHalstead($methodMetric);
                    $methodExpected = $expects['classes'][$className]['methods'][$methodName];
                    expect($halstead)->toBe($methodExpected['halstead']);
                }
                break;
        }
    }

})->with($maintainabilityTests);
