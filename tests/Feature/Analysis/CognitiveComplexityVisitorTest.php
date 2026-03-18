<?php

declare(strict_types=1);

use PhpCodeArch\Analysis\CognitiveComplexityVisitor;
use PhpCodeArch\Analysis\CyclomaticComplexityVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

use function Test\Feature\Analysis\getMetrics;

function getCogCVisitors(): array
{
    return [
        IdentifyVisitor::class,
        CyclomaticComplexityVisitor::class,
        CognitiveComplexityVisitor::class,
    ];
}

it('calculates cognitive complexity for functions and classes', function () {
    $testFile = __DIR__ . '/testfiles/cognitive-complexity-1.php';
    $metricsController = getMetrics($testFile, getCogCVisitors());

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

    // Functions
    expect($functionCogC['simpleFunction'])->toBe(1)
        ->and($functionCogC['nestedFunction'])->toBe(3)
        ->and($functionCogC['booleanSequence'])->toBe(2)
        ->and($functionCogC['mixedBoolean'])->toBe(3)
        ->and($functionCogC['loopWithNesting'])->toBe(4);

    // Methods
    expect($methodCogC['deeplyNested'])->toBe(6)
        ->and($methodCogC['simpleSwitch'])->toBe(1)
        ->and($methodCogC['tryCatch'])->toBe(3);

    // Class = sum of methods: 6 + 1 + 3 = 10
    expect($classCogC)->toBe(10);

    // File = sum of all: 1 + 3 + 2 + 3 + 4 + 10 = 23
    expect($fileCogC)->toBe(23);
});
