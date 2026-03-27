<?php

declare(strict_types=1);

use PhpCodeArch\Analysis\CognitiveComplexityVisitor;
use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

use function Test\Feature\Analysis\getMetricsForVisitors;

$cognitiveTests = array_merge(
    require __DIR__ . '/fileprovider/test-cognitive-provider.php',
    require __DIR__ . '/fileprovider/test-hand-cogc-provider.php',
);

function getCogCVisitors(): array
{
    return [
        IdentifyVisitor::class,
        CyclomaticComplexityVisitor::class,
        CognitiveComplexityVisitor::class,
    ];
}

it('calculates cognitive complexity for functions and classes', function ($testFile, $expects) {
    $metricsController = getMetricsForVisitors($testFile, getCogCVisitors());

    $functionCogC = [];
    $methodCogC = [];
    $fileCogC = null;
    $classCogC = null;

    foreach ($metricsController->getAllCollections() as $collection) {
        $cogC = $collection->get('cognitiveComplexity')?->getValue() ?? null;

        if ($collection instanceof FileMetricsCollection) {
            $fileCogC = $cogC;
        } elseif ($collection instanceof ClassMetricsCollection) {
            $classCogC = $cogC;

            // Get methods via class collection
            $classMethods = $collection->getCollection('methods')->getAsArray();
            foreach ($classMethods as $methodKey => $methodName) {
                $methodMetrics = $metricsController->getMetricCollectionByIdentifierString($methodKey);
                $methodCogC[$methodName] = $methodMetrics->get('cognitiveComplexity')?->getValue() ?? null;
            }
        } elseif ($collection instanceof FunctionMetricsCollection) {
            $name = $collection->get('singleName')?->getValue() ?? $collection->getName();
            $functionCogC[$name] = $cogC;
        }
    }

    // Verify functions
    foreach ($expects['functions'] as $funcName => $expectedCogC) {
        expect($functionCogC[$funcName] ?? null)->toBe($expectedCogC['cogc']);
    }

    // Verify methods
    foreach ($expects['classes'] as $className => $classData) {
        foreach ($classData['methods'] ?? [] as $methodName => $expectedCogC) {
            expect($methodCogC[$methodName] ?? null)->toBe($expectedCogC['cogc']);
        }

        if (isset($classData['cogc'])) {
            expect($classCogC)->toBe($classData['cogc']);
        }
    }

    // Verify file
    expect($fileCogC)->toBe($expects['file']['cogc']);

})->with($cognitiveTests);
