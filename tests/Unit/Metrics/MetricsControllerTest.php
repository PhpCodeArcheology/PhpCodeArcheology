<?php

declare(strict_types=1);

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
});

it('sets and gets metric values', function () {
    $this->controller->setMetricValue(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        42,
        'cc'
    );

    $value = $this->controller->getMetricValue(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        'cc'
    );

    expect($value)->not->toBeNull()
        ->and($value->getValue())->toBe(42);
});

it('sets multiple metric values at once', function () {
    $this->controller->setMetricValues(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        ['cc' => 10, 'loc' => 100, 'lloc' => 80]
    );

    $values = $this->controller->getMetricValues(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        ['cc', 'loc', 'lloc']
    );

    expect($values['cc']->getValue())->toBe(10)
        ->and($values['loc']->getValue())->toBe(100)
        ->and($values['lloc']->getValue())->toBe(80);
});

it('changes metric values with callback', function () {
    $this->controller->setMetricValue(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        5,
        'cc'
    );

    $this->controller->changeMetricValue(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        'cc',
        fn($v) => $v + 3
    );

    $value = $this->controller->getMetricValue(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php'],
        'cc'
    );

    expect($value->getValue())->toBe(8);
});

it('returns null for non-existent metric value by identifier', function () {
    $value = $this->controller->getMetricValueByIdentifierString('nonexistent', 'cc');

    expect($value)->toBeNull();
});

it('counts all collections', function () {
    expect($this->controller->getContainerCount())->toBe(2);
});

it('gets all collections', function () {
    $all = $this->controller->getAllCollections();

    expect($all)->toBeArray()
        ->and(count($all))->toBe(2);
});
