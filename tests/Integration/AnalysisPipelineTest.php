<?php

declare(strict_types=1);

namespace Tests\Integration;

use PhpCodeArch\Application\AnalysisPipeline;
use PhpCodeArch\Application\CliFormatter;
use PhpCodeArch\Application\CliOutput;
use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsRegistryInterface;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\FileMetrics\FileMetricsCollection;
use PhpCodeArch\Metrics\Model\FunctionMetrics\FunctionMetricsCollection;
use PhpCodeArch\Metrics\Model\ProjectMetrics\ProjectMetricsCollection;
use PhpCodeArch\Predictions\PredictionInterface;

/**
 * Integration tests for the full analysis pipeline.
 *
 * Runs the pipeline end-to-end against small PHP fixture files in
 * tests/Integration/testfiles/ and verifies that collections are
 * populated and metrics have sensible values.
 */

/**
 * Run the analysis pipeline once and cache the result for all tests.
 *
 * @return array{MetricsRegistryInterface, array<int, int>}
 */
function runPipeline(): array
{
    static $result = null;

    if (null !== $result) {
        return $result;
    }

    $fixtureDir = __DIR__.'/testfiles';

    $config = new Config();
    $config->set('files', [$fixtureDir]);
    $config->set('packageSize', 2);
    $config->set('git', ['enable' => false]);
    $config->set('runningDir', $fixtureDir);

    $output = new CliOutput();
    $output->setFormatter(new CliFormatter(false));

    $pipeline = new AnalysisPipeline();
    [$registry, , , $problems] = $pipeline->runAnalysis($config, $output);
    $result = [$registry, $problems];

    return $result;
}

// ---------------------------------------------------------------------------
// Smoke test
// ---------------------------------------------------------------------------

it('runs the full analysis pipeline without throwing', function (): void {
    [$metricsController, $problems] = runPipeline();

    expect($metricsController)->toBeInstanceOf(MetricsRegistryInterface::class);
    expect($problems)->toBeArray();
    expect($problems)->toHaveKeys([PredictionInterface::INFO, PredictionInterface::WARNING, PredictionInterface::ERROR]);
});

// ---------------------------------------------------------------------------
// Collection presence
// ---------------------------------------------------------------------------

it('creates one FileMetricsCollection per fixture file', function (): void {
    [$metricsController] = runPipeline();

    $fileCollections = array_filter(
        $metricsController->getAllCollections(),
        fn ($c) => $c instanceof FileMetricsCollection
    );

    // Two fixture files: SimpleClass.php and ComplexFunction.php
    expect($fileCollections)->toHaveCount(2);
});

it('creates a ClassMetricsCollection for the simple class', function (): void {
    [$metricsController] = runPipeline();

    $classCollections = array_filter(
        $metricsController->getAllCollections(),
        fn ($c) => $c instanceof ClassMetricsCollection
    );

    expect($classCollections)->not->toBeEmpty();
});

it('creates a FunctionMetricsCollection for the complex function', function (): void {
    [$metricsController] = runPipeline();

    $functionCollections = array_filter(
        $metricsController->getAllCollections(),
        fn ($c) => $c instanceof FunctionMetricsCollection
    );

    expect($functionCollections)->not->toBeEmpty();
});

it('creates a ProjectMetricsCollection', function (): void {
    [$metricsController] = runPipeline();

    $projectCollections = array_filter(
        $metricsController->getAllCollections(),
        fn ($c) => $c instanceof ProjectMetricsCollection
    );

    expect($projectCollections)->not->toBeEmpty();
});

// ---------------------------------------------------------------------------
// Basic metric values
// ---------------------------------------------------------------------------

it('calculates positive LOC for every file', function (): void {
    [$metricsController] = runPipeline();

    foreach ($metricsController->getAllCollections() as $collection) {
        if (!$collection instanceof FileMetricsCollection) {
            continue;
        }

        $loc = $collection->get(MetricKey::LOC)?->asInt() ?? 0;
        expect($loc)->toBeGreaterThan(0);
    }
});

it('calculates CC >= 1 for every file', function (): void {
    [$metricsController] = runPipeline();

    foreach ($metricsController->getAllCollections() as $collection) {
        if (!$collection instanceof FileMetricsCollection) {
            continue;
        }

        $cc = $collection->get(MetricKey::CC)?->asInt() ?? 0;
        expect($cc)->toBeGreaterThanOrEqual(1);
    }
});

it('calculates a positive maintainability index for every file', function (): void {
    [$metricsController] = runPipeline();

    foreach ($metricsController->getAllCollections() as $collection) {
        if (!$collection instanceof FileMetricsCollection) {
            continue;
        }

        $mi = $collection->get(MetricKey::MAINTAINABILITY_INDEX)?->asFloat() ?? 0.0;
        expect($mi)->toBeGreaterThan(0.0);
    }
});

it('calculates CC >= 1 for the simple class', function (): void {
    [$metricsController] = runPipeline();

    foreach ($metricsController->getAllCollections() as $collection) {
        if (!$collection instanceof ClassMetricsCollection) {
            continue;
        }

        $cc = $collection->get(MetricKey::CC)?->asInt() ?? 0;
        expect($cc)->toBeGreaterThanOrEqual(1);
    }
});

it('detects high cyclomatic complexity for the complex function', function (): void {
    [$metricsController] = runPipeline();

    $maxFunctionCc = 0;

    foreach ($metricsController->getAllCollections() as $collection) {
        if (!$collection instanceof FunctionMetricsCollection) {
            continue;
        }

        $cc = $collection->get(MetricKey::CC)?->asInt() ?? 0;
        $maxFunctionCc = max($maxFunctionCc, $cc);
    }

    // complexDecision() has CC = 12 (> 10)
    expect($maxFunctionCc)->toBeGreaterThan(10);
});

// ---------------------------------------------------------------------------
// Problem detection
// ---------------------------------------------------------------------------

it('detects at least one error-level problem for the complex function', function (): void {
    [, $problems] = runPipeline();

    expect($problems[PredictionInterface::ERROR])->toBeGreaterThan(0);
});

// ---------------------------------------------------------------------------
// Project aggregates
// ---------------------------------------------------------------------------

it('sets overall file count in project metrics', function (): void {
    [$metricsController] = runPipeline();

    $projectCollection = null;
    foreach ($metricsController->getAllCollections() as $collection) {
        if ($collection instanceof ProjectMetricsCollection) {
            $projectCollection = $collection;
            break;
        }
    }

    expect($projectCollection)->not->toBeNull();

    $overallFiles = $projectCollection->get(MetricKey::OVERALL_FILES)?->asInt() ?? 0;
    expect($overallFiles)->toBe(2);
});
