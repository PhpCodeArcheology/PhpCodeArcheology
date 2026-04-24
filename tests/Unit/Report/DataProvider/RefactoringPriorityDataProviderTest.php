<?php

declare(strict_types=1);

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\RefactoringPriorityDataProvider;

// Helper ──────────────────────────────────────────────────────────────────────

function makeRpMc(array $allCollections = []): Mockery\MockInterface
{
    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(MetricValue::ofValueAndTypeKey('/project', 'commonPath'));
    $mc->shouldReceive('getAllCollections')->andReturn($allCollections);

    return $mc;
}

function makeRpMcWithProjectMetrics(
    array $allCollections,
    float $avg = 0,
    float $max = 0,
    int $needing = 0,
): Mockery\MockInterface {
    $mc = makeRpMc($allCollections);
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'overallAvgRefactoringPriority')
        ->andReturn(MetricValue::ofValueAndTypeKey($avg, 'overallAvgRefactoringPriority'));
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'overallMaxRefactoringPriority')
        ->andReturn(MetricValue::ofValueAndTypeKey($max, 'overallMaxRefactoringPriority'));
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'overallClassesNeedingRefactoring')
        ->andReturn(MetricValue::ofValueAndTypeKey($needing, 'overallClassesNeedingRefactoring'));

    return $mc;
}

function rpMv(mixed $value, string $key = 'dummy'): MetricValue
{
    return MetricValue::ofValueAndTypeKey($value, $key);
}

function makeRpClassCollection(
    string $name,
    float $score = 0.0,
    bool $isInterface = false,
    bool $isTrait = false,
    bool $isEnum = false,
    int $cc = 1,
    int $lcom = 0,
    int $lloc = 20,
    int $usedFromOutside = 0,
    string $recommendation = '',
    array $drivers = [],
): PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection {
    $priority = $score;
    $col = new PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection('/src/Foo.php', $name);
    $col->set('refactoringPriority', rpMv($priority, 'refactoringPriority'));
    $col->set('singleName', rpMv($name, 'singleName'));
    $col->set('fullName', rpMv($name, 'fullName'));
    $col->set('refactoringPriorityRecommendation', rpMv($recommendation, 'refactoringPriorityRecommendation'));
    $col->set('refactoringPriorityDrivers', rpMv($drivers, 'refactoringPriorityDrivers'));
    $col->set('cc', rpMv($cc, 'cc'));
    $col->set('lcom', rpMv($lcom, 'lcom'));
    $col->set('lloc', rpMv($lloc, 'lloc'));
    $col->set('usedFromOutsideCount', rpMv($usedFromOutside, 'usedFromOutsideCount'));
    $col->set('interface', rpMv($isInterface, 'interface'));
    $col->set('trait', rpMv($isTrait, 'trait'));
    $col->set('enum', rpMv($isEnum, 'enum'));

    return $col;
}

// ── Empty data ────────────────────────────────────────────────────────────────

it('returns empty priorities list when no classes exist', function () {
    $mc = makeRpMcWithProjectMetrics([]);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    expect($data['refactoringPriorities'])->toBeEmpty()
        ->and($data['totalClasses'])->toBe(0);
});

it('returns all-zero distribution when no classes exist', function () {
    $mc = makeRpMcWithProjectMetrics([]);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    expect($data['distribution'])->toBe([
        'clean' => 0,
        'low' => 0,
        'medium' => 0,
        'high' => 0,
        'critical' => 0,
    ]);
});

// ── Priority list sorted by score ────────────────────────────────────────────

it('returns priorities sorted descending by score', function () {
    $low = makeRpClassCollection('LowClass', score: 15.0);
    $high = makeRpClassCollection('HighClass', score: 80.0);
    $medium = makeRpClassCollection('MedClass', score: 40.0);

    $mc = makeRpMcWithProjectMetrics([$low, $high, $medium], avg: 45.0, max: 80.0, needing: 3);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    $names = array_column($data['refactoringPriorities'], 'name');
    expect($names)->toBe(['HighClass', 'MedClass', 'LowClass']);
});

it('excludes classes with priority <= 0 from the priorities list', function () {
    $clean = makeRpClassCollection('CleanClass', score: 0.0);
    $pending = makeRpClassCollection('BadClass', score: 55.0);

    $mc = makeRpMcWithProjectMetrics([$clean, $pending], avg: 27.5, max: 55.0, needing: 1);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    expect($data['refactoringPriorities'])->toHaveCount(1)
        ->and($data['refactoringPriorities'][0]['name'])->toBe('BadClass');
});

// ── Distribution buckets ──────────────────────────────────────────────────────

it('calculates distribution buckets correctly', function () {
    $collections = [
        makeRpClassCollection('Clean1', score: 0.0),   // clean
        makeRpClassCollection('Clean2', score: -1.0),  // clean (negative → ≤ 0)
        makeRpClassCollection('LowPrio', score: 10.0),  // low (1–25)
        makeRpClassCollection('MedPrio', score: 35.0),  // medium (26–50)
        makeRpClassCollection('HighPrio', score: 60.0),  // high (51–75)
        makeRpClassCollection('Critical', score: 90.0),  // critical (>75)
    ];

    $mc = makeRpMcWithProjectMetrics($collections);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $dist = $provider->getTemplateData()['distribution'];

    expect($dist['clean'])->toBe(2)
        ->and($dist['low'])->toBe(1)
        ->and($dist['medium'])->toBe(1)
        ->and($dist['high'])->toBe(1)
        ->and($dist['critical'])->toBe(1);
});

it('excludes interfaces from distribution count', function () {
    $realClass = makeRpClassCollection('RealClass', score: 0.0, isInterface: false);
    $iface = makeRpClassCollection('MyInterface', score: 0.0, isInterface: true);

    $mc = makeRpMcWithProjectMetrics([$realClass, $iface]);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    expect($data['totalClasses'])->toBe(1);
});

it('excludes traits from distribution count', function () {
    $realClass = makeRpClassCollection('RealClass', score: 0.0);
    $trait = makeRpClassCollection('MyTrait', score: 0.0, isTrait: true);

    $mc = makeRpMcWithProjectMetrics([$realClass, $trait]);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    expect($data['totalClasses'])->toBe(1);
});

it('excludes enums from distribution count', function () {
    $realClass = makeRpClassCollection('RealClass', score: 0.0);
    $enum = makeRpClassCollection('MyEnum', score: 0.0, isEnum: true);

    $mc = makeRpMcWithProjectMetrics([$realClass, $enum]);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    expect($data['totalClasses'])->toBe(1);
});

// ── Project-level aggregates ─────────────────────────────────────────────────

it('exposes project-level avgPriority, maxPriority, and classesNeedingRefactoring', function () {
    $mc = makeRpMcWithProjectMetrics([], avg: 12.5, max: 75.0, needing: 3);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $data = $provider->getTemplateData();

    expect($data['avgPriority'])->toBe(12.5)
        ->and($data['maxPriority'])->toBe(75.0)
        ->and($data['classesNeedingRefactoring'])->toBe(3);
});

// ── Priority entry structure ──────────────────────────────────────────────────

it('priority entry contains expected keys', function () {
    $class = makeRpClassCollection(
        name: 'HotClass',
        score: 70.0,
        cc: 25,
        lcom: 3,
        lloc: 300,
        usedFromOutside: 8,
        recommendation: 'Refactor this',
        drivers: ['complexity'],
    );

    $mc = makeRpMcWithProjectMetrics([$class], avg: 70.0, max: 70.0, needing: 1);
    $provider = new RefactoringPriorityDataProvider($mc, $mc);
    $entry = $provider->getTemplateData()['refactoringPriorities'][0];

    expect($entry)->toHaveKeys(['id', 'name', 'fullName', 'score', 'recommendation', 'drivers', 'cc', 'lcom', 'lloc', 'usedFromOutsideCount'])
        ->and($entry['name'])->toBe('HotClass')
        ->and($entry['score'])->toBe(70.0)
        ->and($entry['cc'])->toBe(25)
        ->and($entry['recommendation'])->toBe('Refactor this')
        ->and($entry['drivers'])->toContain('complexity');
});
