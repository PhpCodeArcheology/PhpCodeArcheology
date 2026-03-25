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
function makeTestsMc(array $collections = [], array $statsOverrides = []): \Mockery\MockInterface
{
    $defaults = [
        'overallTestFileCount'             => 5,
        'overallProductionFileCount'       => 20,
        'overallTestRatio'                 => 25.0,
        'overallTestedClassCount'          => 3,
        'overallUntestedClassCount'        => 7,
        'overallTestedClassRatio'          => 30.0,
        'overallFunctionBasedTestFileCount' => 0,
        'detectedTestFrameworks'           => 'Pest',
        'overallCoveragePercent'           => null,
    ];
    $stats = array_merge($defaults, $statsOverrides);

    $mc = Mockery::mock(MetricsController::class)->shouldIgnoreMissing();

    // Required by ReportDataProviderTrait constructor
    $mc->shouldReceive('getMetricValue')
        ->with(MetricCollectionTypeEnum::ProjectCollection, null, 'commonPath')
        ->andReturn(testsMv('/project', 'commonPath'));

    foreach ($stats as $key => $value) {
        if ($value === null) {
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
    $mc       = makeTestsMc([], [
        'overallTestFileCount'       => 8,
        'overallProductionFileCount' => 40,
        'overallTestRatio'           => 20.0,
        'overallTestedClassCount'    => 5,
        'overallUntestedClassCount'  => 15,
        'overallTestedClassRatio'    => 25.0,
        'detectedTestFrameworks'     => 'PHPUnit',
    ]);
    $provider = new TestsDataProvider($mc);
    $stats    = $provider->getTemplateData()['stats'];

    expect($stats['testFileCount'])->toBe(8)
        ->and($stats['productionFileCount'])->toBe(40)
        ->and($stats['testRatio'])->toBe(20.0)
        ->and($stats['testedClassCount'])->toBe(5)
        ->and($stats['untestedClassCount'])->toBe(15)
        ->and($stats['testedClassRatio'])->toBe(25.0)
        ->and($stats['detectedTestFrameworks'])->toBe('PHPUnit');
});

// ── Coverage gaps ─────────────────────────────────────────────────────────────

it('returns top 20 untested classes sorted by CC descending', function () {
    // Create 25 classes without tests; CC values 1–25
    $classes = array_map(function ($i) {
        $c = new ClassMetricsCollection("/src/Class{$i}.php", "Class{$i}");
        $c->set('cc', testsMv($i, 'cc'));
        $c->set('lloc', testsMv(50, 'lloc'));
        $c->set('singleName', testsMv("Class{$i}", 'singleName'));
        $c->set('fullName', testsMv("Ns\\Class{$i}", 'fullName'));
        $c->set('refactoringPriority', testsMv((float) $i, 'refactoringPriority'));
        $c->set('hasTest', testsMv(false, 'hasTest'));
        return $c;
    }, range(1, 25));

    $provider = new TestsDataProvider(makeTestsMc($classes));
    $gaps     = $provider->getTemplateData()['coverageGaps'];

    expect($gaps)->toHaveCount(20);
    // Sorted descending: first gap is CC=25, last is CC=6
    expect($gaps[0]['cc'])->toBe(25);
    expect($gaps[19]['cc'])->toBe(6);
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
    $gaps     = $provider->getTemplateData()['coverageGaps'];

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
    $gaps     = $provider->getTemplateData()['coverageGaps'];

    expect($gaps)->toBeEmpty();
});
