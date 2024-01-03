<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use Marcus\PhpLegacyAnalyzer\Analysis\IdentifyVisitor;
use Marcus\PhpLegacyAnalyzer\Analysis\LocVisitor;
use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;

require_once __DIR__ . '/test_helpers.php';

$locTests = require __DIR__ . '/fileprovider/test-loc-provider.php';

function getLocVisitors(): array
{
    return [
        IdentifyVisitor::class,
        LocVisitor::class,
    ];
}

it('counts loc, lloc and cloc correctly', function($testFile, $expections) {
    $metrics = getMetricsForVisitors($testFile, getLocVisitors());

    $projectMetrics = $metrics->get('project');

    $loc = $projectMetrics->get('OverallLoc');
    $lloc = $projectMetrics->get('OverallLloc');
    $cloc = $projectMetrics->get('OverallCloc');

    $fileData = [];
    $functionData = [];
    $classData = [];

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
                break;

            case $metric instanceof FunctionMetrics:
                $functionData[$metric->getName()] = [
                    'loc' => $metric->get('loc'),
                    'lloc' => $metric->get('lloc'),
                    'cloc' => $metric->get('cloc'),
                ];
                break;

            case $metric instanceof ClassMetrics:
                $classMethods = $metric->get('methods');
                $methods = [];
                foreach ($classMethods as $methodMetric) {
                    $methods[$methodMetric->getName()] = [
                        'loc' => $methodMetric->get('loc'),
                        'lloc' => $methodMetric->get('lloc'),
                        'cloc' => $methodMetric->get('cloc'),
                    ];
                }

                $classData[$metric->getName()] = [
                    'loc' => $metric->get('loc'),
                    'lloc' => $metric->get('lloc'),
                    'cloc' => $metric->get('cloc'),
                    'methods' => $methods,
                ];
                break;
        }
    }

    expect($loc)->toBe($expections['loc'])
        ->and($lloc)->toBe($expections['lloc'])
        ->and($cloc)->toBe($expections['cloc']);

    foreach ($expections['file'] as $key => $value) {
        expect($fileData[$key])->toBe($value);
    }

    foreach ($expections['functions'] as $functionExpectation) {
        $data = $functionData[$functionExpectation['name']];

        expect($data['loc'])->toBe($functionExpectation['loc'])
            ->and($data['lloc'])->toBe($functionExpectation['lloc'])
            ->and($data['cloc'])->toBe($functionExpectation['cloc']);

    }

    foreach ($expections['classes'] as $classExpectation) {
        $data = $classData[$classExpectation['name']];

        expect($data['loc'])->toBe($classExpectation['loc'])
            ->and($data['lloc'])->toBe($classExpectation['lloc'])
            ->and($data['cloc'])->toBe($classExpectation['cloc']);

        foreach ($classExpectation['methods'] as $methodExpectation) {
            $methodData = $classData[$classExpectation['name']]['methods'][$methodExpectation['name']];

            expect($methodData['loc'])->toBe($methodExpectation['loc'])
                ->and($methodData['lloc'])->toBe($methodExpectation['lloc']);
        }
    }

})->with($locTests);
