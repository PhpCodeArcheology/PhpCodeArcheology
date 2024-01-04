<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use Marcus\PhpLegacyAnalyzer\Analysis\GlobalsVisitor;
use Marcus\PhpLegacyAnalyzer\Analysis\IdentifyVisitor;
use Marcus\PhpLegacyAnalyzer\Metrics\ClassMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FileMetrics;
use Marcus\PhpLegacyAnalyzer\Metrics\FunctionMetrics;

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
    $metrics = getMetricsForVisitors($testFile, getGlobalsVisitors());

    foreach ($metrics->getAll() as $metrics) {
        switch (true) {
            case $metrics instanceof FileMetrics:
                $superglobals = $metrics->get('superglobals');
                $superglobalsSum = array_sum($superglobals);

                expect($superglobals)->toBe($expected['file']['superglobals'])
                    ->and($superglobalsSum)->toBe($expected['file']['superglobalsSum']);
                break;

            case $metrics instanceof FunctionMetrics:
                $superglobals = $metrics->get('superglobals');
                $superglobalsSum = array_sum($superglobals);

                if (!isset($expected['functions'][$metrics->getName()])) {
                    break;
                }

                $expectedForFunction = $expected['functions'][$metrics->getName()];
                expect($superglobals)->toBe($expectedForFunction['superglobals'])
                    ->and($superglobalsSum)->toBe($expectedForFunction['superglobalsSum']);

                break;

            case $metrics instanceof ClassMetrics:
                $superglobals = $metrics->get('superglobals');
                $superglobalsSum = array_sum($superglobals);

                if (!isset($expected['classes'][$metrics->getName()])) {
                    break;
                }

                $expectedForClass = $expected['classes'][$metrics->getName()];

                expect($superglobals)->toBe($expectedForClass['superglobals'])
                    ->and($superglobalsSum)->toBe($expectedForClass['superglobalsSum']);

                $methods = $metrics->get('methods');
                foreach ($methods as $method) {
                    $methodName = $method->getName();

                    if (! isset($expected['classes'][$metrics->getName()]['methods'][$methodName])) {
                        continue;
                    }

                    $superglobals = $method->get('superglobals');
                    $superglobalsSum = array_sum($superglobals);

                    $expectedForMethod = $expected['classes'][$metrics->getName()]['methods'][$methodName];

                    expect($superglobals)->toBe($expectedForMethod['superglobals'])
                        ->and($superglobalsSum)->toBe($expectedForMethod['superglobalsSum']);
                }

                break;

        }
    }

})->with($globalsTests);
