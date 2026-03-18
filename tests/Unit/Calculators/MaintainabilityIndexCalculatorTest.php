<?php

declare(strict_types=1);

use PhpCodeArch\Calculators\MaintainabilityIndexCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;

beforeEach(function () {
    $this->container = new MetricsContainer();
    $this->controller = new MetricsController($this->container);
    $this->controller->registerMetricTypes();
    $this->controller->createProjectMetricsCollection(['/src']);

    $this->controller->createMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php']
    );

    $this->calculator = new MaintainabilityIndexCalculator($this->controller);
});

it('calculates MI correctly for non-trivial code', function () {
    $this->controller->setMetricValues(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        [
            'volume' => 200.0,
            'cc' => 10,
            'loc' => 50,
            'cloc' => 5,
            'lloc' => 40,
        ]
    );

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php']
    );

    $this->calculator->calculate($collection);

    $mi = $collection->get('maintainabilityIndex')->getValue();
    $miWOC = $collection->get('maintainabilityIndexWithoutComments')->getValue();
    $cW = $collection->get('commentWeight')->getValue();

    // MI should be calculated, not default
    expect($mi)->not->toBe(171)
        ->and($miWOC)->toBeGreaterThan(0)
        ->and($cW)->toBeGreaterThan(0)
        ->and($mi)->toBe($miWOC + $cW);
});

it('returns defaults for empty code (volume=0)', function () {
    $this->controller->setMetricValues(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        ['volume' => 0, 'cc' => 1, 'loc' => 0, 'cloc' => 0, 'lloc' => 0]
    );

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php']
    );

    $this->calculator->calculate($collection);

    expect($collection->get('maintainabilityIndex')->getValue())->toBe(171)
        ->and($collection->get('maintainabilityIndexWithoutComments')->getValue())->toBe(50)
        ->and($collection->get('commentWeight')->getValue())->toBe(0);
});

it('skips project collections', function () {
    $projectCollection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    // Should not throw
    $this->calculator->calculate($projectCollection);

    expect(true)->toBeTrue();
});
