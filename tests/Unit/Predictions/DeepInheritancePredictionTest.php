<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\DeepInheritancePrediction;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\DeepInheritanceProblem;

function makeDeepInheritanceController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

function createClassWithDit(
    MetricsController $controller,
    string $name,
    int $dit,
): string {
    $path = '/src/'.str_replace('\\', '/', $name).'.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path]);
    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($id, MetricKey::DIT, $dit);

    return $id;
}

it('returns 0 when no classes exist', function () {
    $controller = makeDeepInheritanceController();
    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('returns 0 when dit is 0', function () {
    $controller = makeDeepInheritanceController();
    createClassWithDit($controller, 'App\\RootClass', 0);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('returns 0 when dit is 3 (below default warning threshold of 4)', function () {
    $controller = makeDeepInheritanceController();
    createClassWithDit($controller, 'App\\ShallowChild', 3);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('fires WARNING when dit is exactly 4 (at default warning threshold)', function () {
    $controller = makeDeepInheritanceController();
    $classId = createClassWithDit($controller, 'App\\DeepChild', 4);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());
    $count = $prediction->predict();

    expect($count)->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::DIT)?->getProblems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBeInstanceOf(DeepInheritanceProblem::class)
        ->and($problems[0]->getProblemLevel())->toBe(PredictionInterface::WARNING);
});

it('fires WARNING when dit is 5 (between warning and error thresholds)', function () {
    $controller = makeDeepInheritanceController();
    $classId = createClassWithDit($controller, 'App\\DeeperChild', 5);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::DIT)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::WARNING);
});

it('fires ERROR when dit is exactly 6 (at default error threshold)', function () {
    $controller = makeDeepInheritanceController();
    $classId = createClassWithDit($controller, 'App\\VeryDeepChild', 6);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::DIT)?->getProblems()[0] ?? null;

    expect($problem)->not->toBeNull()
        ->and($problem->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('fires ERROR when dit is greater than 6', function () {
    $controller = makeDeepInheritanceController();
    $classId = createClassWithDit($controller, 'App\\ExtremeDescent', 10);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::DIT)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('attaches DeepInheritanceProblem to DIT metric', function () {
    $controller = makeDeepInheritanceController();
    $classId = createClassWithDit($controller, 'App\\DeepService', 4);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $metric = $controller->getMetricCollectionByIdentifierString($classId)->get(MetricKey::DIT);

    expect($metric)->not->toBeNull()
        ->and($metric->getProblems())->toHaveCount(1)
        ->and($metric->getProblems()[0])->toBeInstanceOf(DeepInheritanceProblem::class)
        ->and($metric->getProblems()[0]->getName())->toBe('Deep inheritance hierarchy');
});

it('problem message contains the DIT value', function () {
    $controller = makeDeepInheritanceController();
    $classId = createClassWithDit($controller, 'App\\NestedClass', 5);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::DIT)?->getProblems()[0] ?? null;

    expect($problem?->getMessage())->toContain('5');
});

it('problem level returned by getLevel() is WARNING', function () {
    $controller = makeDeepInheritanceController();
    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());

    expect($prediction->getLevel())->toBe(PredictionInterface::WARNING);
});

it('counts multiple deep inheritance classes independently', function () {
    $controller = makeDeepInheritanceController();
    createClassWithDit($controller, 'App\\ClassA', 4);
    createClassWithDit($controller, 'App\\ClassB', 7);
    createClassWithDit($controller, 'App\\ClassC', 2);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(2);
});

it('uses configurable warning threshold', function () {
    $controller = makeDeepInheritanceController();
    // dit=3, default warning=4 → no fire; custom warning=3 → fires
    createClassWithDit($controller, 'App\\SlightlyDeep', 3);

    $config = new Config();
    $config->set('thresholds', ['deepInheritance' => ['warning' => 3, 'error' => 6]]);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(1);
});

it('uses configurable error threshold', function () {
    $controller = makeDeepInheritanceController();
    // dit=5, default error=6 → WARNING; custom error=4 → ERROR
    $classId = createClassWithDit($controller, 'App\\ModeratelyDeep', 5);

    $config = new Config();
    $config->set('thresholds', ['deepInheritance' => ['warning' => 4, 'error' => 4]]);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, $config);
    $prediction->predict();

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::DIT)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('skips non-class collections', function () {
    $controller = makeDeepInheritanceController();

    // FunctionCollection creates FunctionMetricsCollection, not ClassMetricsCollection
    $controller->createMetricCollection(MetricCollectionTypeEnum::FunctionCollection, [
        'name' => 'helperFunction',
        'path' => '/src/helpers.php',
    ]);
    $funcId = $controller->getMetricCollection(MetricCollectionTypeEnum::FunctionCollection, [
        'name' => 'helperFunction',
        'path' => '/src/helpers.php',
    ])->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($funcId, MetricKey::DIT, 10);

    $prediction = new DeepInheritancePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});
