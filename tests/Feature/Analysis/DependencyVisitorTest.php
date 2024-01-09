<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\DependencyVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;

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
    $metrics = getMetricsForVisitors($testFile, getDepVisitors());

    foreach ($metrics->getAll() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetrics:
                $dependencies = $metrics->get('dependencies');

                expect(count($dependencies))->toBe($expects['file']['dependencyCount'])
                    ->and($dependencies)->toBe($expects['file']['dependencies']);
                break;

            case $metrics instanceof FunctionMetrics:
                $dependencies = $metrics->get('dependencies');
                $fnName = $metrics->getName();

                if (! isset($expects['functions'][$fnName])) {
                    break;
                }

                $fnExpects = $expects['functions'][$fnName];

                expect(count($dependencies))->toBe($fnExpects['dependencyCount']);
                break;

            case $metrics instanceof ClassMetrics:
                $dependencies = $metrics->get('dependencies');
                $className = $metrics->getName();

                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                if (! isset($expects['classes'][$className])) {
                    break;
                }

                $classExpects = $expects['classes'][$className];

                expect(count($dependencies))->toBe($classExpects['dependencyCount'])
                    ->and($dependencies)->toBe($classExpects['dependencies'])
                    ->and($metrics->get('interfaces'))->toBe($classExpects['interfaces'])
                    ->and($metrics->get('extends'))->toBe($classExpects['extends']);

                $methods = $metrics->get('methods');

                foreach ($methods as $methodMetric) {
                    $methodName = $methodMetric->getName();

                    if (! isset($classExpects['methods'][$methodName])) {
                        continue;
                    }

                    $methodExpects = $classExpects['methods'][$methodName];

                    $dependencies = $methodMetric->get('dependencies');

                    expect(count($dependencies))->toBe($methodExpects['dependencyCount'])
                        ->and($dependencies)->toBe($methodExpects['dependencies']);
                }

                break;
        }
    }

})->with($dependencyTests);
