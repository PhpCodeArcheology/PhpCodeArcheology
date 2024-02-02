<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\GlobalsVisitor;
use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

$globalsTests = require __DIR__ . '/fileprovider/test-globals-provider.php';

function getGlobalsVisitors(): array
{
    return [
        IdentifyVisitor::class,
        GlobalsVisitor::class,
    ];
}

it('counts globals correctly', function($testFile, $expected) {
    $metricsController = getMetricsForVisitors($testFile, getGlobalsVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $superglobals = $metrics->get('superglobals')->getValue();
                $superglobalsSum = array_sum($superglobals);

                expect($superglobals)->toBe($expected['file']['superglobals'])
                    ->and($superglobalsSum)->toBe($expected['file']['superglobalsSum']);
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $superglobals = $metrics->get('superglobals')->getValue();
                $superglobalsSum = array_sum($superglobals);

                if (!isset($expected['functions'][$metrics->getName()])) {
                    break;
                }

                $expectedForFunction = $expected['functions'][$metrics->getName()];
                expect($superglobals)->toBe($expectedForFunction['superglobals'])
                    ->and($superglobalsSum)->toBe($expectedForFunction['superglobalsSum']);

                break;

            case $metrics instanceof ClassMetricsCollection:
                $superglobals = $metrics->get('superglobals')->getValue();
                $superglobalsSum = array_sum($superglobals);

                if (!isset($expected['classes'][$metrics->getName()])) {
                    break;
                }

                $expectedForClass = $expected['classes'][$metrics->getName()];

                expect($superglobals)->toBe($expectedForClass['superglobals'])
                    ->and($superglobalsSum)->toBe($expectedForClass['superglobalsSum']);

                $methods = $metrics->getCollection('methods');
                foreach ($methods as $key => $methodName) {
                    $methodMetric = $metricsController->getMetricCollectionByIdentifierString($key);

                    if (! isset($expected['classes'][$metrics->getName()]['methods'][$methodName])) {
                        continue;
                    }

                    $superglobals = $methodMetric->get('superglobals')->getValue();
                    $superglobalsSum = array_sum($superglobals);

                    $expectedForMethod = $expected['classes'][$metrics->getName()]['methods'][$methodName];

                    expect($superglobals)->toBe($expectedForMethod['superglobals'])
                        ->and($superglobalsSum)->toBe($expectedForMethod['superglobalsSum']);
                }

                break;

        }
    }

})->with($globalsTests);

it('counts constants correctly', function($testFile, $expected) {
    $metricsController = getMetricsForVisitors($testFile, getGlobalsVisitors());

    foreach ($metricsController->getAllCollections() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetricsCollection:
                $constants = $metrics->get('constants')->getValue();
                $constantsSum = array_sum($constants);

                expect($constants)->toBe($expected['file']['constants'])
                    ->and($constantsSum)->toBe($expected['file']['constantsSum']);
                break;

            case $metrics instanceof FunctionMetricsCollection:
                $constants = $metrics->get('constants')->getValue();
                $constantsSum = array_sum($constants);

                if (!isset($expected['functions'][$metrics->getName()])) {
                    break;
                }

                $expectedForFunction = $expected['functions'][$metrics->getName()];
                expect($constants)->toBe($expectedForFunction['constants'])
                    ->and($constantsSum)->toBe($expectedForFunction['constantsSum']);
                break;

            case $metrics instanceof ClassMetricsCollection:
                $constants = $metrics->get('constants')->getValue();
                $constantsSum = array_sum($constants);

                if (!isset($expected['classes'][$metrics->getName()])) {
                    break;
                }

                $expectedForClass = $expected['classes'][$metrics->getName()];

                expect($constants)->toBe($expectedForClass['constants'])
                    ->and($constantsSum)->toBe($expectedForClass['constantsSum']);

                $methods = $metrics->getCollection('methods');
                foreach ($methods as $key => $methodName) {
                    $methodMetric = $metricsController->getMetricCollectionByIdentifierString($key);

                    if (! isset($expected['classes'][$metrics->getName()]['methods'][$methodName])) {
                        continue;
                    }

                    $constants = $methodMetric->get('constants')->getValue();
                    $constantsSum = array_sum($constants);

                    $expectedForMethod = $expected['classes'][$metrics->getName()]['methods'][$methodName];

                    expect($constants)->toBe($expectedForMethod['constants'])
                        ->and($constantsSum)->toBe($expectedForMethod['constantsSum']);
                }

                break;

        }
    }

})->with($globalsTests);
