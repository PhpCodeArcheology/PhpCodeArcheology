<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\DependencyCyclePrediction;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\DependencyCycleProblem;

function makeCycleController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

/**
 * Creates a class in a dependency cycle.
 *
 * @param string[] $cycleClasses
 */
function createCycleClass(
    MetricsController $controller,
    string $name,
    bool $inCycle,
    array $cycleClasses = [],
    int $cycleLength = 0,
): string {
    $path = '/src/'.str_replace('\\', '/', $name).'.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path]);

    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();

    $controller->setMetricValuesByIdentifierString($id, [
        MetricKey::IN_DEPENDENCY_CYCLE => $inCycle,
        MetricKey::DEPENDENCY_CYCLE_CLASSES => $cycleClasses,
        MetricKey::DEPENDENCY_CYCLE_LENGTH => $cycleLength,
    ]);

    return $id;
}

function makeDoctrineConfig(): Config
{
    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(doctrineDetected: true));

    return $config;
}

// --- Basic fire/no-fire tests ---

it('returns 0 when no classes exist', function () {
    $controller = makeCycleController();
    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('returns 0 when class is not in a cycle', function () {
    $controller = makeCycleController();
    createCycleClass($controller, 'App\\Service', false);

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('fires ERROR when class is in a cycle', function () {
    $controller = makeCycleController();
    $classId = createCycleClass(
        $controller,
        'App\\ServiceA',
        true,
        ['App\\ServiceA', 'App\\ServiceB'],
        2
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());
    $count = $prediction->predict();

    expect($count)->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBeInstanceOf(DependencyCycleProblem::class)
        ->and($problems[0]->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('problem message contains cycle length and class preview', function () {
    $controller = makeCycleController();
    $classId = createCycleClass(
        $controller,
        'App\\ServiceA',
        true,
        ['App\\ServiceA', 'App\\ServiceB'],
        2
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem)->not->toBeNull()
        ->and($problem->getMessage())->toContain('cycle length: 2')
        ->and($problem->getMessage())->toContain('App\\ServiceA');
});

it('problem level is ERROR for getLevel()', function () {
    $controller = makeCycleController();
    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());

    expect($prediction->getLevel())->toBe(PredictionInterface::ERROR);
});

it('counts multiple cycle classes independently', function () {
    $controller = makeCycleController();
    createCycleClass($controller, 'App\\A', true, ['App\\A', 'App\\B', 'App\\C'], 3);
    createCycleClass($controller, 'App\\B', true, ['App\\A', 'App\\B', 'App\\C'], 3);
    createCycleClass($controller, 'App\\C', false);

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(2);
});

// --- Cycle preview truncation ---

it('truncates cycle preview to first 5 classes', function () {
    $controller = makeCycleController();
    $cycleClasses = [
        'App\\ClassA',
        'App\\ClassB',
        'App\\ClassC',
        'App\\ClassD',
        'App\\ClassE',
        'App\\ClassF',
        'App\\ClassG',
    ];
    $classId = createCycleClass($controller, 'App\\ClassA', true, $cycleClasses, 7);

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem)->not->toBeNull()
        ->and($problem->getMessage())->toContain('App\\ClassE')
        ->and($problem->getMessage())->not->toContain('App\\ClassF')
        ->and($problem->getMessage())->not->toContain('App\\ClassG');
});

// --- Doctrine Entity/Repository pattern ---

it('downgrades to INFO for Doctrine Entity/Repository cycle', function () {
    $controller = makeCycleController();
    // cycleLength=2, one Repository, one non-Repository → Doctrine ORM pattern
    $classId = createCycleClass(
        $controller,
        'App\\Entity\\User',
        true,
        ['App\\Entity\\User', 'App\\Repository\\UserRepository'],
        2
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, makeDoctrineConfig());
    $count = $prediction->predict();

    expect($count)->toBe(1);

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::INFO)
        ->and($problem?->getMessage())->toContain('Doctrine Entity/Repository cycle');
});

it('does not downgrade Entity/Repository cycle when cycle length is not 2', function () {
    $controller = makeCycleController();
    // 3-class cycle with a Repository → isDoctrineEntityRepoCycle returns false (length != 2)
    $classId = createCycleClass(
        $controller,
        'App\\Entity\\User',
        true,
        ['App\\Entity\\User', 'App\\Repository\\UserRepository', 'App\\Service\\UserService'],
        3
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, makeDoctrineConfig());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('does not downgrade Entity/Repository cycle when Doctrine is not detected', function () {
    $controller = makeCycleController();
    $classId = createCycleClass(
        $controller,
        'App\\Entity\\User',
        true,
        ['App\\Entity\\User', 'App\\Repository\\UserRepository'],
        2
    );

    // No Doctrine in config → isDoctrineDetected() returns false
    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

// --- Doctrine Entity relationship cycle ---

it('downgrades to INFO for Doctrine Entity relationship cycle', function () {
    $controller = makeCycleController();
    // All classes have 'Entity' as a namespace segment → entity relationship cycle
    $classId = createCycleClass(
        $controller,
        'App\\Entity\\User',
        true,
        ['App\\Entity\\User', 'App\\Entity\\Order', 'App\\Entity\\Product'],
        3
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, makeDoctrineConfig());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::INFO)
        ->and($problem?->getMessage())->toContain('Doctrine Entity relationship cycle');
});

it('downgrades to INFO for Doctrine Model namespace cycle', function () {
    $controller = makeCycleController();
    // All classes have 'Model' as a namespace segment
    $classId = createCycleClass(
        $controller,
        'App\\Model\\Invoice',
        true,
        ['App\\Model\\Invoice', 'App\\Model\\LineItem'],
        2
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, makeDoctrineConfig());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    // isDoctrineEntityRepoCycle fires first (length=2, both are Model/* — no Repository) → false
    // isDoctrineEntityCycle fires: both have 'Model' namespace → INFO
    expect($problem?->getProblemLevel())->toBe(PredictionInterface::INFO);
});

it('does not downgrade Entity cycle when one class is not entity-like', function () {
    $controller = makeCycleController();
    // One class is a plain Service — isDoctrineEntityCycle returns false
    $classId = createCycleClass(
        $controller,
        'App\\Entity\\User',
        true,
        ['App\\Entity\\User', 'App\\Service\\UserService'],
        2
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, makeDoctrineConfig());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    // isDoctrineEntityRepoCycle: UserService is not *Repository → hasRepository=false → false
    // isDoctrineEntityCycle: UserService has no Entity/Model/Document segment → false
    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('does not downgrade Entity cycle when Doctrine is not detected', function () {
    $controller = makeCycleController();
    $classId = createCycleClass(
        $controller,
        'App\\Entity\\User',
        true,
        ['App\\Entity\\User', 'App\\Entity\\Order'],
        2
    );

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

// --- Framework adjustments disabled ---

it('does not downgrade when doctrineCycles adjustment is disabled', function () {
    $controller = makeCycleController();
    $classId = createCycleClass(
        $controller,
        'App\\Entity\\User',
        true,
        ['App\\Entity\\User', 'App\\Repository\\UserRepository'],
        2
    );

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(doctrineDetected: true));
    $config->set('framework', ['adjustments' => ['doctrineCycles' => false, 'entityCycles' => false]]);

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, $config);
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('problem name is Circular dependency', function () {
    $controller = makeCycleController();
    $classId = createCycleClass($controller, 'App\\Foo', true, ['App\\Foo', 'App\\Bar'], 2);

    $prediction = new DependencyCyclePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::IN_DEPENDENCY_CYCLE)?->getProblems()[0] ?? null;

    expect($problem?->getName())->toBe('Circular dependency');
});
