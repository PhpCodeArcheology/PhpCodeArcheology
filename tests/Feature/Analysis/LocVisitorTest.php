<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\FileMetrics\FileMetrics;
use PhpCodeArch\Metrics\FunctionMetrics\FunctionMetrics;

require_once __DIR__ . '/test_helpers.php';

$locTests = require __DIR__ . '/fileprovider/test-loc-provider.php';

function getLocVisitors(): array
{
    return [
        IdentifyVisitor::class,
        LocVisitor::class,
    ];
}

it('counts loc, lloc and cloc correctly', function($testFile, $expected) {
    $metrics = getMetricsForVisitors($testFile, getLocVisitors());

    $projectMetrics = $metrics->get('project');

    $loc = $projectMetrics->get('OverallLoc');
    $lloc = $projectMetrics->get('OverallLloc');
    $cloc = $projectMetrics->get('OverallCloc');

    expect($loc)->toBe($expected['loc'])
        ->and($lloc)->toBe($expected['lloc'])
        ->and($cloc)->toBe($expected['cloc']);


    foreach ($metrics->getAll() as $metric) {
        switch (true) {
            case $metric instanceof FileMetrics:
                $fileData = [
                    'loc' => $metric->get('loc'),
                    'lloc' => $metric->get('lloc'),
                    'cloc' => $metric->get('cloc'),
                    'llocOutside' => $metric->get('llocOutside'),
                    'htmlLoc' => $metric->get('htmlLoc'),
                ];

                expect($fileData)->toBe($expected['file']);
                break;

            case $metric instanceof FunctionMetrics:
                $fnName = $metric->getName();

                if (! isset($expected['functions'][$fnName])) {
                    break;
                }

                $functionData = [
                    'loc' => $metric->get('loc'),
                    'lloc' => $metric->get('lloc'),
                    'cloc' => $metric->get('cloc'),
                ];

                expect($functionData)->toBe($expected['functions'][$fnName]);
                break;

            case $metric instanceof ClassMetrics:
                $className = $metric->getName();
                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                if (! isset($expected['classes'][$className])) {
                    break;
                }

                $classData = [
                    'loc' => $metric->get('loc'),
                    'lloc' => $metric->get('lloc'),
                    'cloc' => $metric->get('cloc'),
                ];

                expect($classData)->toBe($expected['classes'][$className]['data']);

                $methods = $metric->get('methods');
                foreach ($methods as $methodMetric) {
                    $methodName = $methodMetric->getName();

                    if (! isset($expected['classes'][$className]['methods'][$methodName])) {
                        continue;
                    }

                    $methodsData = [
                        'loc' => $methodMetric->get('loc'),
                        'lloc' => $methodMetric->get('lloc'),
                        'cloc' => $methodMetric->get('cloc'),
                    ];

                    expect($methodsData)->toBe($expected['classes'][$className]['methods'][$methodName]);
                }
                break;
        }
    }
})->with($locTests);
