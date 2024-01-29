<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\HalsteadMetricsVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Analysis\MaintainabilityIndexVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

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

function getHalstead(MetricsCollectionInterface $metric): array
{
    return [
        'mi' => $metric->get('maintainabilityIndex')->getValue(),
        'miWOC' => $metric->get('maintainabilityIndexWithoutComments')->getValue(),
        'cW' => $metric->get('commentWeight')->getValue(),
    ];
}

it('calculates maintainability index correctly', function($testFile, $expects) {
    $metricsController = getMetricsForVisitors($testFile, getMaintainabilityVisitors());

    foreach ($metricsController->getAllCollections() as $metric) {
        switch (true) {
            case $metric instanceof FileMetricsCollection:
                $halstead = getHalstead($metric);
                expect($halstead)->toBe($expects['file']['halstead']);
                break;

            case $metric instanceof FunctionMetricsCollection:
                $halstead = getHalstead($metric);

                $fnName = $metric->getName();

                if (! isset($expects['functions'][$fnName])) {
                    break;
                }

                expect($halstead)->toBe($expects['functions'][$fnName]['halstead']);
                break;

            case $metric instanceof ClassMetricsCollection:
                $halstead = getHalstead($metric);

                $className = $metric->getName();
                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                expect($halstead)->toBe($expects['classes'][$className]['halstead']);

                if (! isset($expects['classes'][$className])) {
                    break;
                }

                $methods = $metric->getCollection('methods');
                foreach ($methods as $key => $methodName) {
                    $methodMetric = $metricsController->getMetricCollectionByIdentifierString($key);

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
