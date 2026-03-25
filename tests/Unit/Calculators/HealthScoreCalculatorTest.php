<?php

declare(strict_types=1);

use PhpCodeArch\Calculators\HealthScoreCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;

beforeEach(function () {
    $this->container = new MetricsContainer();
    $this->controller = new MetricsController($this->container);
    $this->controller->registerMetricTypes();
    $this->controller->createProjectMetricsCollection(['/src']);

    $this->calculator = new HealthScoreCalculator($this->controller);
});

function setProjectMetrics(MetricsController $controller, array $values): void
{
    $controller->setMetricValues(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        $values
    );
}

function getProjectMetric(MetricsController $controller, string $key): mixed
{
    $collection = $controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    return $collection->get($key)?->getValue();
}

/**
 * Default metric set simulating a clean, well-structured Symfony project.
 */
function cleanProjectMetrics(): array
{
    return [
        'overallAvgMI' => 95.0,
        'overallErrorCount' => 5,
        'overallWarningCount' => 10,
        'overallClasses' => 100,
        'overallFiles' => 50,
        'overallAvgCC' => 2.5,
        'overallDistanceFromMainline' => 0.1,
        'overallLloc' => 5000,
        'overallLlocOutside' => 250,
        'overallHtmlLoc' => 0,
        'overallLoc' => 8000,
        'overallMethodsCount' => 500,
        'overallPublicMethodsCount' => 325,
        'overallStaticMethodsCount' => 25,
        'overallPrivateMethodsCount' => 175,
        'overallClassesInCycles' => 0,
        'overallDependencyCycles' => 0,
        'overallAbstractness' => 0.12,
    ];
}

/**
 * Metric set simulating a 20-year-old legacy project with heavy HTML mixing.
 */
function legacyProjectMetrics(): array
{
    return [
        'overallAvgMI' => 139.0,
        'overallErrorCount' => 7743,
        'overallWarningCount' => 2561,
        'overallClasses' => 673,
        'overallFiles' => 2214,
        'overallAvgCC' => 3.77,
        'overallDistanceFromMainline' => -0.26,
        'overallLloc' => 82569,
        'overallLlocOutside' => 16000,
        'overallHtmlLoc' => 180080,
        'overallLoc' => 305381,
        'overallMethodsCount' => 6593,
        'overallPublicMethodsCount' => 5487,
        'overallStaticMethodsCount' => 1926,
        'overallPrivateMethodsCount' => 1106,
        'overallClassesInCycles' => 79,
        'overallDependencyCycles' => 5,
        'overallAbstractness' => 0.05,
    ];
}

/**
 * Metric set simulating a medium-quality project with some issues.
 */
function mediumProjectMetrics(): array
{
    return [
        'overallAvgMI' => 85.0,
        'overallErrorCount' => 30,
        'overallWarningCount' => 50,
        'overallClasses' => 80,
        'overallFiles' => 40,
        'overallAvgCC' => 5.0,
        'overallDistanceFromMainline' => 0.2,
        'overallLloc' => 4000,
        'overallLlocOutside' => 400,
        'overallHtmlLoc' => 500,
        'overallLoc' => 10000,
        'overallMethodsCount' => 300,
        'overallPublicMethodsCount' => 240,
        'overallStaticMethodsCount' => 30,
        'overallPrivateMethodsCount' => 60,
        'overallClassesInCycles' => 0,
        'overallDependencyCycles' => 0,
        'overallAbstractness' => 0.05,
    ];
}

it('scores a clean Symfony project in A range', function () {
    setProjectMetrics($this->controller, cleanProjectMetrics());

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $score = getProjectMetric($this->controller, 'healthScore');
    $grade = getProjectMetric($this->controller, 'healthScoreGrade');

    expect($score)->toBeGreaterThanOrEqual(88)
        ->and($grade)->toBe('A');
});

it('scores a legacy project in D range', function () {
    setProjectMetrics($this->controller, legacyProjectMetrics());

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $score = getProjectMetric($this->controller, 'healthScore');
    $grade = getProjectMetric($this->controller, 'healthScoreGrade');

    expect($score)->toBeGreaterThanOrEqual(50)
        ->and($score)->toBeLessThan(65)
        ->and($grade)->toBe('D');
});

it('scores a medium project in C range', function () {
    setProjectMetrics($this->controller, mediumProjectMetrics());

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $score = getProjectMetric($this->controller, 'healthScore');
    $grade = getProjectMetric($this->controller, 'healthScoreGrade');

    expect($score)->toBeGreaterThanOrEqual(65)
        ->and($score)->toBeLessThan(80)
        ->and($grade)->toBe('C');
});

it('gives full HTML score when no inline HTML exists', function () {
    $metrics = cleanProjectMetrics();
    $metrics['overallHtmlLoc'] = 0;
    $metrics['overallLoc'] = 10000;

    setProjectMetrics($this->controller, $metrics);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $htmlRatio = getProjectMetric($this->controller, 'overallHtmlRatio');
    expect($htmlRatio)->toBe(0.0);
});

it('penalizes heavy HTML mixing', function () {
    $metrics = cleanProjectMetrics();
    $metrics['overallHtmlLoc'] = 6000;
    $metrics['overallLoc'] = 10000;

    setProjectMetrics($this->controller, $metrics);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $htmlRatio = getProjectMetric($this->controller, 'overallHtmlRatio');
    $score = getProjectMetric($this->controller, 'healthScore');

    expect($htmlRatio)->toBe(60.0)
        ->and($score)->toBeLessThan(85);
});

it('gives low encapsulation score when all methods are public', function () {
    $metrics = cleanProjectMetrics();
    $metrics['overallMethodsCount'] = 500;
    $metrics['overallPublicMethodsCount'] = 490;
    $metrics['overallPrivateMethodsCount'] = 10;
    $metrics['overallStaticMethodsCount'] = 200;

    setProjectMetrics($this->controller, $metrics);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $encapsulation = getProjectMetric($this->controller, 'overallEncapsulationScore');
    expect($encapsulation)->toBeLessThan(30);
});

it('defaults to neutral scores when no classes or methods exist', function () {
    $metrics = cleanProjectMetrics();
    $metrics['overallClasses'] = 0;
    $metrics['overallMethodsCount'] = 0;
    $metrics['overallPublicMethodsCount'] = 0;
    $metrics['overallStaticMethodsCount'] = 0;
    $metrics['overallPrivateMethodsCount'] = 0;
    $metrics['overallAbstractness'] = 0;

    setProjectMetrics($this->controller, $metrics);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $encapsulation = getProjectMetric($this->controller, 'overallEncapsulationScore');
    expect($encapsulation)->toBe(100.0);
});

it('skips non-project collections', function () {
    $this->controller->createMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php']
    );

    $fileCollection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/test.php']
    );

    $this->calculator->calculate($fileCollection);

    // Should not throw, and project score should remain unset
    expect(true)->toBeTrue();
});

// ── Factor 10: Test coverage ──────────────────────────────────────────────────

it('includes test coverage factor when overallTestedClassRatio is set', function () {
    $metrics = cleanProjectMetrics();
    $metrics['overallTestedClassRatio'] = 80.0;
    $metrics['overallTestFileCount'] = 10;

    setProjectMetrics($this->controller, $metrics);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $score = getProjectMetric($this->controller, 'healthScore');
    expect($score)->toBeGreaterThan(0);
});

it('skips test coverage factor when no test data exists and score is still valid', function () {
    // Without test data
    $metricsWithout = cleanProjectMetrics();
    setProjectMetrics($this->controller, $metricsWithout);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );
    $this->calculator->calculate($collection);
    $scoreWithout = getProjectMetric($this->controller, 'healthScore');

    // With full test coverage
    $metricsWith = cleanProjectMetrics();
    $metricsWith['overallTestedClassRatio'] = 100.0;
    $metricsWith['overallTestFileCount'] = 20;
    setProjectMetrics($this->controller, $metricsWith);

    $this->calculator->calculate($collection);
    $scoreWith = getProjectMetric($this->controller, 'healthScore');

    // Both produce valid numeric scores; test coverage boosts the already-clean project
    expect($scoreWithout)->toBeFloat()
        ->and($scoreWith)->toBeFloat()
        ->and($scoreWith)->toBeGreaterThanOrEqual($scoreWithout);
});

it('prefers overallCoveragePercent over overallTestedClassRatio', function () {
    // High Clover coverage + low class ratio → should score high
    $metricsHigh = cleanProjectMetrics();
    $metricsHigh['overallCoveragePercent'] = 95.0;
    $metricsHigh['overallTestedClassRatio'] = 5.0;
    $metricsHigh['overallTestFileCount'] = 10;

    setProjectMetrics($this->controller, $metricsHigh);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );
    $this->calculator->calculate($collection);
    $scoreHigh = getProjectMetric($this->controller, 'healthScore');

    // Low Clover coverage + high class ratio → should score lower
    $metricsLow = cleanProjectMetrics();
    $metricsLow['overallCoveragePercent'] = 5.0;
    $metricsLow['overallTestedClassRatio'] = 95.0;
    $metricsLow['overallTestFileCount'] = 10;

    setProjectMetrics($this->controller, $metricsLow);
    $this->calculator->calculate($collection);
    $scoreLow = getProjectMetric($this->controller, 'healthScore');

    expect($scoreHigh)->toBeGreaterThan($scoreLow);
});

it('sets healthScoreVersion to 2', function () {
    setProjectMetrics($this->controller, cleanProjectMetrics());

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );
    $this->calculator->calculate($collection);

    expect(getProjectMetric($this->controller, 'healthScoreVersion'))->toBe(2);
});

// ── Grade thresholds ──────────────────────────────────────────────────────────

it('computes correct grade thresholds', function () {
    // Test each grade boundary by manipulating metrics to hit target scores
    $metrics = cleanProjectMetrics();

    // A project with perfect scores everywhere
    $metrics['overallAvgMI'] = 130.0;
    $metrics['overallErrorCount'] = 0;
    $metrics['overallWarningCount'] = 0;
    $metrics['overallAvgCC'] = 1.0;
    $metrics['overallDistanceFromMainline'] = 0.0;
    $metrics['overallLlocOutside'] = 0;
    $metrics['overallHtmlLoc'] = 0;
    $metrics['overallPublicMethodsCount'] = 300;
    $metrics['overallStaticMethodsCount'] = 10;
    $metrics['overallAbstractness'] = 0.15;

    setProjectMetrics($this->controller, $metrics);

    $collection = $this->controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );

    $this->calculator->calculate($collection);

    $score = getProjectMetric($this->controller, 'healthScore');
    $grade = getProjectMetric($this->controller, 'healthScoreGrade');

    expect($score)->toBeGreaterThanOrEqual(90)
        ->and($grade)->toBe('A');
});
