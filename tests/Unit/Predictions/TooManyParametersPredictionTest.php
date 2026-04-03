<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\Problems\TooManyParametersProblem;
use PhpCodeArch\Predictions\TooManyParametersPrediction;

function makeTooManyParamsController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

function createFunctionWithParams(
    MetricsController $controller,
    string $name,
    int $paramCount,
    string $path = '/src/functions.php',
): string {
    $controller->createMetricCollection(MetricCollectionTypeEnum::FunctionCollection, ['name' => $name, 'path' => $path]);
    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::FunctionCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($id, MetricKey::PARAMETER_COUNT, $paramCount);

    return $id;
}

it('returns 0 when no collections exist', function () {
    $controller = makeTooManyParamsController();
    $prediction = new TooManyParametersPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('returns 0 when paramCount is at default warning threshold (4)', function () {
    $controller = makeTooManyParamsController();
    createFunctionWithParams($controller, 'myFunc', 4);

    $prediction = new TooManyParametersPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('returns 0 when paramCount is below threshold', function () {
    $controller = makeTooManyParamsController();
    createFunctionWithParams($controller, 'simpleFunc', 2);

    $prediction = new TooManyParametersPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('fires WARNING when paramCount is 5 (just above default warning threshold)', function () {
    $controller = makeTooManyParamsController();
    $funcId = createFunctionWithParams($controller, 'heavyFunc', 5);

    $prediction = new TooManyParametersPrediction(new Config());
    $count = $prediction->predict($controller);

    expect($count)->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($funcId)
        ->get(MetricKey::PARAMETER_COUNT)?->getProblems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBeInstanceOf(TooManyParametersProblem::class)
        ->and($problems[0]->getProblemLevel())->toBe(PredictionInterface::WARNING);
});

it('fires WARNING when paramCount is exactly 7 (at error threshold boundary)', function () {
    $controller = makeTooManyParamsController();
    $funcId = createFunctionWithParams($controller, 'borderlineFunc', 7);

    $prediction = new TooManyParametersPrediction(new Config());
    $prediction->predict($controller);

    $problem = $controller->getMetricCollectionByIdentifierString($funcId)
        ->get(MetricKey::PARAMETER_COUNT)?->getProblems()[0] ?? null;

    // paramCount=7 is NOT > threshold(error,7), so level should be WARNING
    expect($problem?->getProblemLevel())->toBe(PredictionInterface::WARNING);
});

it('fires ERROR when paramCount exceeds error threshold (> 7)', function () {
    $controller = makeTooManyParamsController();
    $funcId = createFunctionWithParams($controller, 'massiveFunc', 8);

    $prediction = new TooManyParametersPrediction(new Config());
    $prediction->predict($controller);

    $problem = $controller->getMetricCollectionByIdentifierString($funcId)
        ->get(MetricKey::PARAMETER_COUNT)?->getProblems()[0] ?? null;

    expect($problem)->not->toBeNull()
        ->and($problem->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('attaches TooManyParametersProblem to parameterCount metric', function () {
    $controller = makeTooManyParamsController();
    $funcId = createFunctionWithParams($controller, 'bloatedFunc', 6);

    $prediction = new TooManyParametersPrediction(new Config());
    $prediction->predict($controller);

    $metric = $controller->getMetricCollectionByIdentifierString($funcId)
        ->get(MetricKey::PARAMETER_COUNT);

    expect($metric)->not->toBeNull()
        ->and($metric->getProblems())->toHaveCount(1)
        ->and($metric->getProblems()[0])->toBeInstanceOf(TooManyParametersProblem::class)
        ->and($metric->getProblems()[0]->getName())->toBe('Too many parameters');
});

it('problem message contains the parameter count', function () {
    $controller = makeTooManyParamsController();
    $funcId = createFunctionWithParams($controller, 'verboseFunc', 9);

    $prediction = new TooManyParametersPrediction(new Config());
    $prediction->predict($controller);

    $problem = $controller->getMetricCollectionByIdentifierString($funcId)
        ->get(MetricKey::PARAMETER_COUNT)?->getProblems()[0] ?? null;

    expect($problem?->getMessage())->toContain('9');
});

it('problem level returned by getLevel() is WARNING', function () {
    $prediction = new TooManyParametersPrediction(new Config());

    expect($prediction->getLevel())->toBe(PredictionInterface::WARNING);
});

it('uses configurable warning threshold', function () {
    $controller = makeTooManyParamsController();
    // paramCount=3, default warning threshold=4 → no fire; custom threshold=2 → fires
    createFunctionWithParams($controller, 'smallFunc', 3);

    $config = new Config();
    $config->set('thresholds', ['tooManyParameters' => ['warning' => 2]]);

    $prediction = new TooManyParametersPrediction($config);

    expect($prediction->predict($controller))->toBe(1);
});

it('uses configurable error threshold', function () {
    $controller = makeTooManyParamsController();
    // paramCount=6, default error=7 → WARNING; custom error=5 → ERROR
    $funcId = createFunctionWithParams($controller, 'midFunc', 6);

    $config = new Config();
    $config->set('thresholds', ['tooManyParameters' => ['warning' => 4, 'error' => 5]]);

    $prediction = new TooManyParametersPrediction($config);
    $prediction->predict($controller);

    $problem = $controller->getMetricCollectionByIdentifierString($funcId)
        ->get(MetricKey::PARAMETER_COUNT)?->getProblems()[0] ?? null;

    expect($problem?->getProblemLevel())->toBe(PredictionInterface::ERROR);
});

it('counts multiple functions with too many parameters independently', function () {
    $controller = makeTooManyParamsController();
    createFunctionWithParams($controller, 'funcA', 5, '/src/a.php');
    createFunctionWithParams($controller, 'funcB', 9, '/src/b.php');
    createFunctionWithParams($controller, 'funcC', 3, '/src/c.php');

    $prediction = new TooManyParametersPrediction(new Config());

    expect($prediction->predict($controller))->toBe(2);
});

it('skips class collections', function () {
    $controller = makeTooManyParamsController();

    // Create a ClassCollection — TooManyParametersPrediction should ignore it
    $path = '/src/App/MyClass.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => 'App\\MyClass', 'path' => $path]);
    $classId = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => 'App\\MyClass', 'path' => $path])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($classId, MetricKey::PARAMETER_COUNT, 10);

    $prediction = new TooManyParametersPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('also fires for method collections (MethodCollection creates FunctionMetricsCollection)', function () {
    $controller = makeTooManyParamsController();

    // MethodCollection also creates FunctionMetricsCollection
    $controller->createMetricCollection(MetricCollectionTypeEnum::MethodCollection, ['path' => 'App\\SomeClass', 'name' => 'execute']);
    $methodId = $controller->getMetricCollection(MetricCollectionTypeEnum::MethodCollection, ['path' => 'App\\SomeClass', 'name' => 'execute'])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($methodId, MetricKey::PARAMETER_COUNT, 6);

    $prediction = new TooManyParametersPrediction(new Config());

    expect($prediction->predict($controller))->toBe(1);
});
