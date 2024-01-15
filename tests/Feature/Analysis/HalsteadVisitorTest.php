<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\HalsteadMetricsVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsCollectionInterface;

require_once __DIR__ . '/test_helpers.php';

$halsteadTests = require __DIR__ . '/fileprovider/test-halstead-provider.php';

function getHalVisitors(): array
{
    return [
        IdentifyVisitor::class,
        HalsteadMetricsVisitor::class,
    ];
}

function getCounts(MetricsCollectionInterface $metrics, array $expected, array $counting): array
{
    $countedInMetrics = array_map(function($key) use($metrics) {
        return $metrics->get($key);
    }, $counting);

    $expectedCounted = array_values($expected);

    return [
        'countedInMetrics' => $countedInMetrics,
        'expectedCounted' => $expectedCounted,
    ];
}

/**
 * Gets the operators and operands of both metrics and expected values
 *
 * Extracted to a function to reduce code duplication, because the data
 * is needed in two it() blocks
 *
 * @param string $testFile
 * @param array $expects
 * @return array Array of metric object, operator and operands counts.
 */
function getOperatorsAndOperands(string $testFile, array $expects): array
{
    $metrics = getMetricsForVisitors($testFile, getHalVisitors());

    $counting = [
        'operators',
        'operands',
        'uniqueOperators',
        'uniqueOperands',
    ];

    $operatorsAndOperands = [];

    foreach ($metrics->getAll() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $operatorsAndOperands[$metrics->getName()] = [
                    'metric' => $metrics,
                    'counts' => getCounts(
                        $metrics,
                        $expects['file']['counted'],
                        $counting
                    ),
                ];
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $fnName = $metrics->getName();

                if (!isset($expects['functions'][$fnName])) {
                    break;
                }

                $operatorsAndOperands[$fnName] = [
                    'metric' => $metrics,
                    'counts' => getCounts(
                        $metrics,
                        $expects['functions'][$fnName]['counted'],
                        $counting
                    ),
                ];
                break;

            case $metrics instanceof ClassMetricsCollection:
                $className = $metrics->getName();
                $className = str_starts_with($className, 'anonymous') ? 'anonymous' : $className;

                if (!isset($expects['classes'][$className])) {
                    break;
                }

                $operatorsAndOperands[$className] = [
                    'metric' => $metrics,
                    'counts' => getCounts(
                        $metrics,
                        $expects['classes'][$className]['counted'],
                        $counting
                    ),
                ];

                $methods = $metrics->get('methods');
                foreach ($methods as $methodMetric) {
                    $methodName = $methodMetric->getName();

                    if (!isset($expects['classes'][$className]['methods'][$methodName])) {
                        break;
                    }

                    $operatorsAndOperands[$className.':'.$methodName] = [
                        'metric' => $methodMetric,
                        'counts' => getCounts(
                            $methodMetric,
                            $expects['classes'][$className]['methods'][$methodName]['counted'],
                            $counting
                        ),
                    ];
                }

                break;
        }
    }

    return $operatorsAndOperands;
}

/**
 * Halstead metrics calculator
 *
 * @param array $operatorsAndOperands
 * @return array
 */
function calculateHalsteadMetrics(array $operatorsAndOperands): array
{
    [$N1, $N2, $n1, $n2] = $operatorsAndOperands;

    $n = $n1 + $n2;
    $N = $N1 + $N2;

    if ($n2 === 0 || $N2 === 0) {
        return [
            'vocabulary' => $n,
            'length' => $N,
            'calcLength' => 0,
            'volume' => 0,
            'difficulty' => 0,
            'effort' => 0,
            'complexityDensity' => 0,
        ];
    }

    $vocabulary = $n;
    $length = $N;
    $calculatedLength = $n * log($n, 2);
    $volume = $N * log($n, 2);
    $difficulty = ($n1 / 2) * ($N2 / $n2);
    $effort = $difficulty * $volume;
    $complexityDensity = $difficulty / ($vocabulary + $length);

    return [
        'vocabulary' => $vocabulary,
        'length' => $length,
        'calcLength' => $calculatedLength,
        'volume' => $volume,
        'difficulty' => $difficulty,
        'effort' => $effort,
        'complexityDensity' => $complexityDensity,
    ];
}

it('counts operators and operands correctly', function($testFile, $expects) {
    $operatorsAndOperands = getOperatorsAndOperands($testFile, $expects);

    foreach ($operatorsAndOperands as $counted) {
        expect($counted['counts']['countedInMetrics'])->toBe($counted['counts']['expectedCounted']);
    }

})->with($halsteadTests);

it('counts calculates halstead values correctly', function($testFile, $expects) {
    $operatorsAndOperands = getOperatorsAndOperands($testFile, $expects);

    foreach ($operatorsAndOperands as $counted) {
        $expectedHalsteadMetrics = calculateHalsteadMetrics($counted['counts']['expectedCounted']);
        $metricKeys = array_keys($expectedHalsteadMetrics);

        $actualHalsteadMetrics = [];
        foreach ($metricKeys as $key) {
            $actualHalsteadMetrics[$key] = $counted['metric']->get($key);
        }

        expect($actualHalsteadMetrics)->toBe($expectedHalsteadMetrics);
    }

})->with($halsteadTests);
