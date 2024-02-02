<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\DependencyVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

$dependencyTests = require __DIR__ . '/fileprovider/test-dep-provider.php';

function getDepVisitors(): array
{
    return [
        IdentifyVisitor::class,
        DependencyVisitor::class,
    ];
}

it('detects the dependencies correctly', function($testFile, $expects) {
    $metricsController = getMetricsForVisitors($testFile, getDepVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $dependencies = $metrics->getCollection('dependencies')->getAsArray();

                expect(count($dependencies))->toBe($expects['file']['dependencyCount'])
                    ->and($dependencies)->toBe($expects['file']['dependencies']);
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $dependencies = $metrics->getCollection('dependencies')->getAsArray();
                $fnName = $metrics->getName();

                if (! isset($expects['functions'][$fnName])) {
                    break;
                }

                $fnExpects = $expects['functions'][$fnName];

                expect(count($dependencies))->toBe($fnExpects['dependencyCount']);
                break;

            case $metrics instanceof ClassMetricsCollection:
                $dependencies = $metrics->getCollection('dependencies')->getAsArray();
                $interfaces = $metrics->getCollection('interfaces')->getAsArray();
                $extends = $metrics->getCollection('extends')->getAsArray();
                $traits = $metrics->getCollection('traits')->getAsArray();
                $className = $metrics->getName();

                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                if (! isset($expects['classes'][$className])) {
                    break;
                }

                $classExpects = $expects['classes'][$className];

                expect(count($dependencies))->toBe($classExpects['dependencyCount'])
                    ->and($dependencies)->toBe($classExpects['dependencies'])
                    ->and($interfaces)->toBe($classExpects['interfaces'])
                    ->and($traits)->toBe($classExpects['traits'])
                    ->and($extends)->toBe($classExpects['extends']);

                $methods = $metrics->getCollection('methods');

                foreach ($methods as $key => $methodName) {
                    $methodMetric = $metricsController->getMetricCollectionByIdentifierString($key);

                    if (! isset($classExpects['methods'][$methodName])) {
                        continue;
                    }

                    $methodExpects = $classExpects['methods'][$methodName];

                    $dependencies = $methodMetric->getCollection('dependencies')->getAsArray();

                    expect(count($dependencies))->toBe($methodExpects['dependencyCount'])
                        ->and($dependencies)->toBe($methodExpects['dependencies']);
                }

                break;
        }
    }

})->with($dependencyTests);
