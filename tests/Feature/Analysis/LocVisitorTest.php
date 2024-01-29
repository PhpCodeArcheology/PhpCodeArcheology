<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LocVisitor;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

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
    $metricsController = getMetricsForVisitors($testFile, getLocVisitors());

    $projectMetrics = $metricsController->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $loc = $projectMetrics->get('overallLoc')->getValue();
    $lloc = $projectMetrics->get('overallLloc')->getValue();
    $cloc = $projectMetrics->get('overallCloc')->getValue();

    expect($loc)->toBe($expected['loc'])
        ->and($lloc)->toBe($expected['lloc'])
        ->and($cloc)->toBe($expected['cloc']);


    foreach ($metricsController->getAllCollections() as $metric) {
        switch (true) {
            case $metric instanceof FileMetricsCollection:
                $fileData = [
                    'loc' => $metric->get('loc')->getValue(),
                    'lloc' => $metric->get('lloc')->getValue(),
                    'cloc' => $metric->get('cloc')->getValue(),
                    'llocOutside' => $metric->get('llocOutside')->getValue(),
                    'htmlLoc' => $metric->get('htmlLoc')->getValue(),
                ];

                expect($fileData)->toBe($expected['file']);
                break;

            case $metric instanceof FunctionMetricsCollection:
                $fnName = $metric->getName();

                if (! isset($expected['functions'][$fnName])) {
                    break;
                }

                $functionData = [
                    'loc' => $metric->get('loc')->getValue(),
                    'lloc' => $metric->get('lloc')->getValue(),
                    'cloc' => $metric->get('cloc')->getValue(),
                ];

                expect($functionData)->toBe($expected['functions'][$fnName]);
                break;

            case $metric instanceof ClassMetricsCollection:
                $className = $metric->getName();
                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                if (! isset($expected['classes'][$className])) {
                    break;
                }

                $classData = [
                    'loc' => $metric->get('loc')->getValue(),
                    'lloc' => $metric->get('lloc')->getValue(),
                    'cloc' => $metric->get('cloc')->getValue(),
                ];

                expect($classData)->toBe($expected['classes'][$className]['data']);

                $methods = $metric->getCollection('methods');
                foreach ($methods as $key => $methodName) {
                    $methodMetric = $metricsController->getMetricCollectionByIdentifierString($key);

                    if (! isset($expected['classes'][$className]['methods'][$methodName])) {
                        continue;
                    }

                    $methodsData = [
                        'loc' => $methodMetric->get('loc')->getValue(),
                        'lloc' => $methodMetric->get('lloc')->getValue(),
                        'cloc' => $methodMetric->get('cloc')->getValue(),
                    ];

                    expect($methodsData)->toBe($expected['classes'][$className]['methods'][$methodName]);
                }
                break;
        }
    }
})->with($locTests);
