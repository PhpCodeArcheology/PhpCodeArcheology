<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\LcomVisitor;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;

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

    $metricsController = getMetricsForVisitors($testFile, getLcomVisitors());

    foreach ($metricsController->getAllCollections() as $metric) {
        if (! $metric instanceof ClassMetricsCollection) {
            continue;
        }

        if (! isset($expects['classes'][$metric->getName()])) {
            continue;
        }

        expect($metric->get('lcom')->getValue())->toBe($expects['classes'][$metric->getName()]['lcom']);
    }

})->with($lcomTests);
