<?php

declare(strict_types=1);

use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricTypeRegistry;
use PhpCodeArch\Metrics\Model\Enums\MetricVisibility;
use PhpCodeArch\Metrics\Model\MetricValue;

beforeEach(function () {
    $this->registry = new MetricTypeRegistry();
    $this->registry->register();
});

it('registers metric types from data files', function () {
    $fileMetrics = $this->registry->getByCollectionTypeAndVisibility(
        MetricCollectionTypeEnum::FileCollection,
        MetricVisibility::ShowEverywhere
    );

    expect($fileMetrics)->toBeArray()
        ->and(count($fileMetrics))->toBeGreaterThan(0);
});

it('returns detail metrics for a collection type', function () {
    $details = $this->registry->getDetailMetrics(MetricCollectionTypeEnum::ClassCollection);

    expect($details)->toBeArray();
});

it('returns list metrics for a collection type', function () {
    $list = $this->registry->getListMetrics(MetricCollectionTypeEnum::FileCollection);

    expect($list)->toBeArray()
        ->and(count($list))->toBeGreaterThan(0);
});

it('returns empty array for unknown collection type metrics', function () {
    $result = $this->registry->getByCollectionTypeAndVisibility(
        MetricCollectionTypeEnum::PackageCollection,
        MetricVisibility::ShowList
    );

    expect($result)->toBeArray();
});

it('applies type to metric value', function () {
    $metricValue = MetricValue::ofValueAndTypeKey(42, 'cc');
    $this->registry->applyTypeToValue($metricValue);

    expect($metricValue->getMetricType())->not->toBeNull()
        ->and($metricValue->getMetricType()->getKey())->toBe('cc');
});
