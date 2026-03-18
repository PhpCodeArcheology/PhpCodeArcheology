<?php

declare(strict_types=1);

use PhpCodeArch\Metrics\MetricCollectionFactory;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;

beforeEach(function () {
    $this->container = new MetricsContainer();
    $this->factory = new MetricCollectionFactory($this->container);
});

it('creates project metrics collection', function () {
    $collection = $this->factory->createProject(['/src']);

    expect($collection)->toBeInstanceOf(ProjectMetricsCollection::class);
});

it('creates file metrics collection', function () {
    $collection = $this->factory->create(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php']
    );

    expect($collection)->toBeInstanceOf(FileMetricsCollection::class);
});

it('creates class metrics collection', function () {
    $collection = $this->factory->create(
        MetricCollectionTypeEnum::ClassCollection,
        ['path' => '/src/test.php', 'name' => 'TestClass']
    );

    expect($collection)->toBeInstanceOf(ClassMetricsCollection::class);
});

it('creates function metrics collection', function () {
    $collection = $this->factory->create(
        MetricCollectionTypeEnum::FunctionCollection,
        ['path' => '/src/test.php', 'name' => 'testFunction']
    );

    expect($collection)->toBeInstanceOf(FunctionMetricsCollection::class);
});

it('creates method metrics collection', function () {
    $collection = $this->factory->create(
        MetricCollectionTypeEnum::MethodCollection,
        ['path' => 'TestClass', 'name' => 'testMethod']
    );

    expect($collection)->toBeInstanceOf(FunctionMetricsCollection::class);
});

it('creates package metrics collection', function () {
    $collection = $this->factory->create(
        MetricCollectionTypeEnum::PackageCollection,
        ['name' => 'App\\Models']
    );

    expect($collection)->toBeInstanceOf(PackageMetricsCollection::class);
});

it('stores created collections in the container', function () {
    $this->factory->create(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php']
    );

    expect($this->container->getCount())->toBe(1);
});
