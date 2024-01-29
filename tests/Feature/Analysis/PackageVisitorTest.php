<?php

declare(strict_types=1);

namespace Test\Feature\Analysis;

use PhpCodeArch\Analysis\IdentifyVisitor;
use PhpCodeArch\Analysis\PackageVisitor;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\PackageMetrics\PackageMetricsCollection;

require_once __DIR__ . '/test_helpers.php';

$packageTests = require __DIR__ . '/fileprovider/test-package-provider.php';

function getPackageVisitors(): array
{
    return [
        IdentifyVisitor::class,
        PackageVisitor::class,
    ];
}

it('detects file namespaces and packages correctly', function($testFiles, $expects) {
    $metricsController = getMetricsForMultipleFilesAndVisitors($testFiles, getPackageVisitors());

    $packages = $metricsController->getCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        'packages'
    )->getAsArray();

    expect($packages)->toBeArray()
        ->and(in_array('_global', $packages))->toBeTrue()
        ->and($packages)->toBe($expects['foundPackages']);

    foreach ($metricsController->getAllCollections() as $metric) {
        if (! $metric instanceof FileMetricsCollection) {
            continue;
        }

        $file = $metric->getName();
        $expectedNamespace = $expects['fileNamespaces'][$file];

        expect($metric->get('namespace')->getValue())->toBeString()
            ->and($metric->get('namespace')->getValue())->toBe($expectedNamespace);
    }
})->with($packageTests);

it('assigns elements to the correct package', function($testFiles, $expects) {
    $metricsController = getMetricsForMultipleFilesAndVisitors($testFiles, getPackageVisitors());

    $packageMetricCount = 0;
    foreach ($metricsController->getAllCollections() as $metric) {
        if (!$metric instanceof PackageMetricsCollection) {
            continue;
        }

        $metricExpects = $expects['packageMetrics'][$metric->getName()];

        $functionCount = count($metric->getCollection('functions')->getAsArray());
        $classCount = count($metric->getCollection('classes')->getAsArray());
        $fileCount = count($metric->getCollection('files')->getAsArray());

        expect($fileCount)->toBe($metricExpects['fileCount'])
            ->and($functionCount)->toBe($metricExpects['functionCount'])
            ->and($classCount)->toBe($metricExpects['classCount']);

        ++ $packageMetricCount;
    }

    expect($packageMetricCount)->toBe(count($expects['packageMetrics']));

})->with($packageTests);
