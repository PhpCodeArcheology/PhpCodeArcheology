<?php

declare(strict_types=1);

use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\DeadCodePrediction;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\DeadCodeProblem;

function makeDeadCodeController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

/**
 * @param string[] $unusedMethods
 */
function createDeadCodeClass(
    MetricsController $controller,
    string $name,
    int $unusedCount = 0,
    array $unusedMethods = [],
): string {
    $path = '/src/'.str_replace('\\', '/', $name).'.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path]);
    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();

    $controller->setMetricValuesByIdentifierString($id, [
        MetricKey::UNUSED_PRIVATE_METHOD_COUNT => $unusedCount,
        MetricKey::UNUSED_PRIVATE_METHODS => $unusedMethods,
    ]);

    return $id;
}

it('returns 0 when no classes exist', function () {
    $controller = makeDeadCodeController();
    $prediction = new DeadCodePrediction();

    expect($prediction->predict($controller))->toBe(0);
});

it('returns 0 when unusedPrivateMethodCount is 0', function () {
    $controller = makeDeadCodeController();
    createDeadCodeClass($controller, 'App\\CleanService', 0, []);

    $prediction = new DeadCodePrediction();

    expect($prediction->predict($controller))->toBe(0);
});

it('fires when unusedPrivateMethodCount is 1', function () {
    $controller = makeDeadCodeController();
    $classId = createDeadCodeClass($controller, 'App\\DirtyService', 1, ['orphanMethod']);

    $prediction = new DeadCodePrediction();
    $count = $prediction->predict($controller);

    expect($count)->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::UNUSED_PRIVATE_METHOD_COUNT)?->getProblems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBeInstanceOf(DeadCodeProblem::class);
});

it('fires when unusedPrivateMethodCount is greater than 1', function () {
    $controller = makeDeadCodeController();
    $classId = createDeadCodeClass($controller, 'App\\LegacyService', 3, ['oldA', 'oldB', 'oldC']);

    $prediction = new DeadCodePrediction();

    expect($prediction->predict($controller))->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::UNUSED_PRIVATE_METHOD_COUNT)?->getProblems();

    expect($problems)->toHaveCount(1);
});

it('attaches DeadCodeProblem to unusedPrivateMethodCount metric', function () {
    $controller = makeDeadCodeController();
    $classId = createDeadCodeClass($controller, 'App\\LegacyService', 2, ['oldMethod', 'deadHelper']);

    $prediction = new DeadCodePrediction();
    $prediction->predict($controller);

    $metric = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::UNUSED_PRIVATE_METHOD_COUNT);

    expect($metric)->not->toBeNull()
        ->and($metric->getProblems())->toHaveCount(1)
        ->and($metric->getProblems()[0])->toBeInstanceOf(DeadCodeProblem::class)
        ->and($metric->getProblems()[0]->getName())->toBe('Dead code');
});

it('problem message contains unused method names', function () {
    $controller = makeDeadCodeController();
    $classId = createDeadCodeClass($controller, 'App\\OldService', 2, ['doLegacy', 'doObsolete']);

    $prediction = new DeadCodePrediction();
    $prediction->predict($controller);

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::UNUSED_PRIVATE_METHOD_COUNT)?->getProblems()[0] ?? null;

    expect($problem?->getMessage())->toContain('doLegacy')
        ->and($problem?->getMessage())->toContain('doObsolete');
});

it('problem level is INFO', function () {
    $prediction = new DeadCodePrediction();

    expect($prediction->getLevel())->toBe(PredictionInterface::INFO);
});

it('counts multiple classes with dead code independently', function () {
    $controller = makeDeadCodeController();
    createDeadCodeClass($controller, 'App\\ServiceA', 1, ['deadA']);
    createDeadCodeClass($controller, 'App\\ServiceB', 2, ['deadB1', 'deadB2']);
    createDeadCodeClass($controller, 'App\\CleanService', 0, []);

    $prediction = new DeadCodePrediction();

    expect($prediction->predict($controller))->toBe(2);
});

it('does not fire for classes with zero unused methods', function () {
    $controller = makeDeadCodeController();
    createDeadCodeClass($controller, 'App\\PureClass', 0, []);
    createDeadCodeClass($controller, 'App\\AnotherClean', 0, []);

    $prediction = new DeadCodePrediction();

    expect($prediction->predict($controller))->toBe(0);
});

it('skips non-class collections', function () {
    $controller = makeDeadCodeController();

    // FunctionCollection creates a FunctionMetricsCollection, which the prediction skips
    $controller->createMetricCollection(MetricCollectionTypeEnum::FunctionCollection, [
        'name' => 'orphanFunction',
        'path' => '/src/helpers.php',
    ]);
    $funcId = $controller->getMetricCollection(MetricCollectionTypeEnum::FunctionCollection, [
        'name' => 'orphanFunction',
        'path' => '/src/helpers.php',
    ])->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($funcId, MetricKey::UNUSED_PRIVATE_METHOD_COUNT, 5);

    $prediction = new DeadCodePrediction();

    expect($prediction->predict($controller))->toBe(0);
});

it('problem message includes the count of unused methods', function () {
    $controller = makeDeadCodeController();
    $classId = createDeadCodeClass($controller, 'App\\CountService', 2, ['alpha', 'beta']);

    $prediction = new DeadCodePrediction();
    $prediction->predict($controller);

    $problem = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::UNUSED_PRIVATE_METHOD_COUNT)?->getProblems()[0] ?? null;

    expect($problem?->getMessage())->toContain('2');
});
