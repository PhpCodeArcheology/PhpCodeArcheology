<?php

declare(strict_types=1);

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\ClassMetrics\ClassMetricsCollection;
use PhpCodeArch\Metrics\Model\MetricValue;
use PhpCodeArch\Report\DataProvider\TestsDataProvider;

function testsMv(mixed $value, string $key = 'dummy'): MetricValue
{
    return MetricValue::ofValueAndTypeKey($value, $key);
}

/**
 * Build a MetricsController mock pre-loaded with project-level stats.
 * Pass $statsOverrides to replace any default value (use null to make a key return null).
 */
function makeTestsMc(array $collections = [], array $statsOverrides = []): Mockery\MockInterface
{
    $defaults = [
        'overallTestFileCount' => 5,
        'overallProductionFileCount' => 20,
        'overallTestRatio' => 25.0,
        'overallTestedClassCount' => 3,
        'overallUntestedClassCount' => 7,
        'overallTestedClassRatio' => 30.0,
        'overallFunctionBasedTestFileCount' => 0,
        'detectedTestFrameworks' => 'Pest',
        'overallCoveragePercent' => null,
        'overallSourceExcludedClassCount' => 0,
    ];
    $stats = array_merge($defaults, $statsOverrides);

    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();

    // Required by ReportDataProviderTrait constructor
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(testsMv('/project', 'commonPath'));

    foreach ($stats as $key => $value) {
        if (null === $value) {
            $mc->shouldReceive('getMetricValue')
                ->with(MetricCollectionTypeEnum::ProjectCollection, null, $key)
                ->andReturn(null);
        } else {
            $mc->shouldReceive('getMetricValue')
                ->with(MetricCollectionTypeEnum::ProjectCollection, null, $key)
                ->andReturn(testsMv($value, $key));
        }
    }

    $mc->shouldReceive('getAllCollections')->andReturn($collections);

    return $mc;
}

// ── Stats ─────────────────────────────────────────────────────────────────────

it('gathers test stats from project metrics', function () {
    $mc = makeTestsMc([], [
        'overallTestFileCount' => 8,
        'overallProductionFileCount' => 40,
        'overallTestRatio' => 20.0,
        'overallTestedClassCount' => 5,
        'overallUntestedClassCount' => 15,
        'overallTestedClassRatio' => 25.0,
        'detectedTestFrameworks' => 'PHPUnit',
    ]);
    $provider = new TestsDataProvider($mc);
    $stats = $provider->getTemplateData()['stats'];

    expect($stats['testFileCount'])->toBe(8)
        ->and($stats['productionFileCount'])->toBe(40)
        ->and($stats['testRatio'])->toBe(20.0)
        ->and($stats['testedClassCount'])->toBe(5)
        ->and($stats['untestedClassCount'])->toBe(15)
        ->and($stats['testedClassRatio'])->toBe(25.0)
        ->and($stats['detectedTestFrameworks'])->toBe('PHPUnit');
});

// ── Coverage gaps ─────────────────────────────────────────────────────────────

it('returns top 20 untested complex classes sorted by CC descending', function () {
    // Create 30 classes without tests; CC values 1–30. The complexity threshold
    // (8) filters out CC 1–7, leaving 23 candidates; the top 20 of those are shown.
    $classes = array_map(function ($i) {
        $c = new ClassMetricsCollection("/src/Class{$i}.php", "Class{$i}");
        $c->set('cc', testsMv($i, 'cc'));
        $c->set('lloc', testsMv(50, 'lloc'));
        $c->set('singleName', testsMv("Class{$i}", 'singleName'));
        $c->set('fullName', testsMv("Ns\\Class{$i}", 'fullName'));
        $c->set('refactoringPriority', testsMv((float) $i, 'refactoringPriority'));
        $c->set('hasTest', testsMv(false, 'hasTest'));

        return $c;
    }, range(1, 30));

    $provider = new TestsDataProvider(makeTestsMc($classes));
    $gaps = $provider->getTemplateData()['coverageGaps'];

    expect($gaps)->toHaveCount(20);
    // Sorted descending: first gap is CC=30, last is CC=11 (top 20 of CC 8..30)
    expect($gaps[0]['cc'])->toBe(30);
    expect($gaps[19]['cc'])->toBe(11);
});

it('filters out classes below the complexity threshold', function () {
    // Create classes with CC 1, 4, 7, 8, 15 — only cc >= 8 should appear
    $classes = array_map(function ($cc) {
        $c = new ClassMetricsCollection("/src/Class{$cc}.php", "Class{$cc}");
        $c->set('cc', testsMv($cc, 'cc'));
        $c->set('lloc', testsMv(50, 'lloc'));
        $c->set('singleName', testsMv("Class{$cc}", 'singleName'));
        $c->set('fullName', testsMv("Ns\\Class{$cc}", 'fullName'));
        $c->set('refactoringPriority', testsMv((float) $cc, 'refactoringPriority'));
        $c->set('hasTest', testsMv(false, 'hasTest'));

        return $c;
    }, [1, 4, 7, 8, 15]);

    $provider = new TestsDataProvider(makeTestsMc($classes));
    $gaps = $provider->getTemplateData()['coverageGaps'];

    // CC < 8 are dropped; CC 8 and 15 remain
    expect($gaps)->toHaveCount(2)
        ->and(array_column($gaps, 'cc'))->toBe([15, 8]);
});

it('filters out trivial exceptions from the gaps list', function () {
    // Typical exception class: pure pass-through, CC=1, LLOC=3
    $exception = new ClassMetricsCollection('/src/Exception/InvalidFoo.php', 'InvalidFooException');
    $exception->set('cc', testsMv(1, 'cc'));
    $exception->set('lloc', testsMv(3, 'lloc'));
    $exception->set('singleName', testsMv('InvalidFooException', 'singleName'));
    $exception->set('fullName', testsMv('App\\Exception\\InvalidFooException', 'fullName'));
    $exception->set('refactoringPriority', testsMv(1.0, 'refactoringPriority'));
    $exception->set('hasTest', testsMv(false, 'hasTest'));

    // Real complex class
    $service = new ClassMetricsCollection('/src/Services/Complex.php', 'ComplexService');
    $service->set('cc', testsMv(15, 'cc'));
    $service->set('lloc', testsMv(100, 'lloc'));
    $service->set('singleName', testsMv('ComplexService', 'singleName'));
    $service->set('fullName', testsMv('App\\Services\\ComplexService', 'fullName'));
    $service->set('refactoringPriority', testsMv(20.0, 'refactoringPriority'));
    $service->set('hasTest', testsMv(false, 'hasTest'));

    $provider = new TestsDataProvider(makeTestsMc([$exception, $service]));
    $gaps = $provider->getTemplateData()['coverageGaps'];

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]['name'])->toBe('ComplexService');
});

it('excludes interfaces/traits/enums from coverage gaps', function () {
    $interface = new ClassMetricsCollection('/src/IFoo.php', 'IFoo');
    $interface->set('interface', testsMv(true, 'interface'));
    $interface->set('hasTest', testsMv(false, 'hasTest'));
    $interface->set('cc', testsMv(5, 'cc'));

    $trait = new ClassMetricsCollection('/src/MyTrait.php', 'MyTrait');
    $trait->set('trait', testsMv(true, 'trait'));
    $trait->set('hasTest', testsMv(false, 'hasTest'));
    $trait->set('cc', testsMv(3, 'cc'));

    $enum = new ClassMetricsCollection('/src/MyEnum.php', 'MyEnum');
    $enum->set('enum', testsMv(true, 'enum'));
    $enum->set('hasTest', testsMv(false, 'hasTest'));
    $enum->set('cc', testsMv(2, 'cc'));

    $provider = new TestsDataProvider(makeTestsMc([$interface, $trait, $enum]));
    $gaps = $provider->getTemplateData()['coverageGaps'];

    expect($gaps)->toBeEmpty();
});

it('returns empty coverageGaps when all classes have tests', function () {
    $classes = array_map(function ($i) {
        $c = new ClassMetricsCollection("/src/Class{$i}.php", "Class{$i}");
        $c->set('hasTest', testsMv(true, 'hasTest'));
        $c->set('cc', testsMv($i, 'cc'));

        return $c;
    }, range(1, 5));

    $provider = new TestsDataProvider(makeTestsMc($classes));
    $gaps = $provider->getTemplateData()['coverageGaps'];

    expect($gaps)->toBeEmpty();
});

// ── <source> scope exclusion ──────────────────────────────────────────────────

it('excludes source-excluded classes from coverage gaps', function () {
    $included = new ClassMetricsCollection('/src/Services/UserService.php', 'UserService');
    $included->set('hasTest', testsMv(false, 'hasTest'));
    $included->set('cc', testsMv(12, 'cc'));
    $included->set('lloc', testsMv(50, 'lloc'));
    $included->set('singleName', testsMv('UserService', 'singleName'));
    $included->set('fullName', testsMv('App\\Services\\UserService', 'fullName'));
    $included->set('refactoringPriority', testsMv(10.0, 'refactoringPriority'));

    $excluded = new ClassMetricsCollection('/src/DataFixtures/UserFixtures.php', 'UserFixtures');
    $excluded->set('hasTest', testsMv(false, 'hasTest'));
    $excluded->set('cc', testsMv(20, 'cc'));
    $excluded->set('lloc', testsMv(100, 'lloc'));
    $excluded->set('singleName', testsMv('UserFixtures', 'singleName'));
    $excluded->set('fullName', testsMv('App\\DataFixtures\\UserFixtures', 'fullName'));
    $excluded->set('refactoringPriority', testsMv(15.0, 'refactoringPriority'));
    $excluded->set('excludedByPhpunitSource', testsMv(true, 'excludedByPhpunitSource'));

    $provider = new TestsDataProvider(makeTestsMc([$included, $excluded]));
    $gaps = $provider->getTemplateData()['coverageGaps'];

    expect($gaps)->toHaveCount(1)
        ->and($gaps[0]['name'])->toBe('UserService');
});

it('exposes sourceExcludedClassCount in stats', function () {
    $mc = makeTestsMc([], ['overallSourceExcludedClassCount' => 6]);
    $provider = new TestsDataProvider($mc);
    $stats = $provider->getTemplateData()['stats'];

    expect($stats['sourceExcludedClassCount'])->toBe(6);
});

it('defaults sourceExcludedClassCount to 0 when the metric is absent', function () {
    $mc = makeTestsMc([], ['overallSourceExcludedClassCount' => null]);
    $provider = new TestsDataProvider($mc);
    $stats = $provider->getTemplateData()['stats'];

    expect($stats['sourceExcludedClassCount'])->toBe(0);
});
