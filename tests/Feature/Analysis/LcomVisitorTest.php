<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LcomVisitor;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;

require_once __DIR__ . '/test_helpers.php';

$lcomTests = require __DIR__ . '/fileprovider/test-lcom-provider.php';

function getLcomVisitors(): array
{
    return [
        IdentifyVisitor::class,
        LcomVisitor::class,
    ];
}

it('calculates lcom correctly', function($testFile, $expects) {

    $metrics = getMetricsForVisitors($testFile, getLcomVisitors());

    foreach ($metrics->getAll() as $metric) {
        if (! $metric instanceof ClassMetrics) {
            continue;
        }

        if (! isset($expects['classes'][$metric->getName()])) {
            continue;
        }

        expect($metric->get('lcom'))->toBe($expects['classes'][$metric->getName()]['lcom']);
    }

})->with($lcomTests);
