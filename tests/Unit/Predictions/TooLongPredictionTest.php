<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\Problems\TooLongProblem;
use PhpCodeArch\Predictions\TooLongPrediction;

function makeTooLongController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

function createFileCollection(MetricsController $controller, string $path, int $lloc): string
{
    $controller->createMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $path]);
    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $path])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($id, MetricKey::LLOC, $lloc);

    return $id;
}

function createLongClass(MetricsController $controller, string $name, int $lloc): string
{
    $path = '/src/'.str_replace('\\', '/', $name).'.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path]);
    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($id, MetricKey::LLOC, $lloc);

    return $id;
}

/**
 * Creates a class with a method and returns [classId, methodId].
 *
 * @return array{string, string}
 */
function createClassWithMethod(MetricsController $controller, string $className, int $classLloc, string $methodName, int $methodLloc): array
{
    $classPath = '/src/'.str_replace('\\', '/', $className).'.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $className, 'path' => $classPath]);
    $classId = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $className, 'path' => $classPath])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($classId, MetricKey::LLOC, $classLloc);

    // Method path matches the class name (that's how the analysis wires it)
    $controller->createMetricCollection(MetricCollectionTypeEnum::MethodCollection, ['path' => $className, 'name' => $methodName]);
    $methodId = $controller->getMetricCollection(MetricCollectionTypeEnum::MethodCollection, ['path' => $className, 'name' => $methodName])
        ->getIdentifier()->__toString();
    $controller->setMetricValueByIdentifierString($methodId, MetricKey::LLOC, $methodLloc);

    // Attach method to the class's 'methods' collection
    $methodsCollection = new FileNameCollection();
    $methodsCollection->set($methodName, $methodId);
    $controller->getMetricCollectionByIdentifierString($classId)->setCollection('methods', $methodsCollection);

    return [$classId, $methodId];
}

// --- File-level tests ---

it('returns 0 when no collections exist', function () {
    $controller = makeTooLongController();
    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('fires WARNING when file lloc exceeds threshold', function () {
    $controller = makeTooLongController();
    $fileId = createFileCollection($controller, '/src/BigFile.php', 401);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());
    $count = $prediction->predict();

    expect($count)->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($fileId)
        ->get(MetricKey::LLOC)?->getProblems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBeInstanceOf(TooLongProblem::class);
});

it('does not fire when file lloc is exactly at threshold', function () {
    $controller = makeTooLongController();
    createFileCollection($controller, '/src/OkFile.php', 400);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('does not fire when file lloc is below threshold', function () {
    $controller = makeTooLongController();
    createFileCollection($controller, '/src/ShortFile.php', 200);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

// --- Class-level tests ---

it('fires WARNING when class lloc exceeds threshold', function () {
    $controller = makeTooLongController();
    $classId = createLongClass($controller, 'App\\BigClass', 301);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());
    $count = $prediction->predict();

    expect($count)->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::LLOC)?->getProblems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBeInstanceOf(TooLongProblem::class)
        ->and($problems[0]->getName())->toBe('Too long');
});

it('does not fire when class lloc is at or below threshold', function () {
    $controller = makeTooLongController();
    createLongClass($controller, 'App\\NormalClass', 300);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

// --- Method-level tests ---

it('fires for long method and adds problem on both method and class', function () {
    $controller = makeTooLongController();
    [$classId, $methodId] = createClassWithMethod($controller, 'App\\Service', 10, 'doWork', 31);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());
    $count = $prediction->predict();

    // 1 problem for the long method itself
    expect($count)->toBe(1);

    // Method gets a problem on its lloc metric
    $methodProblems = $controller->getMetricCollectionByIdentifierString($methodId)
        ->get(MetricKey::LLOC)?->getProblems();
    expect($methodProblems)->toHaveCount(1)
        ->and($methodProblems[0])->toBeInstanceOf(TooLongProblem::class);

    // Class also gets a problem on its lloc metric (bubble-up)
    $classProblems = $controller->getMetricCollectionByIdentifierString($classId)
        ->get(MetricKey::LLOC)?->getProblems();
    expect($classProblems)->not->toBeEmpty();
});

it('does not fire for method lloc at or below threshold', function () {
    $controller = makeTooLongController();
    createClassWithMethod($controller, 'App\\Service', 10, 'doWork', 30);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('sets predictionTooLong=true on a too-long file', function () {
    $controller = makeTooLongController();
    $fileId = createFileCollection($controller, '/src/HugeFile.php', 500);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $flag = $controller->getMetricValueByIdentifierString($fileId, MetricKey::PREDICTION_TOO_LONG);
    expect($flag?->asBool())->toBeTrue();
});

it('sets predictionTooLong=false on a short file', function () {
    $controller = makeTooLongController();
    $fileId = createFileCollection($controller, '/src/TinyFile.php', 50);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $flag = $controller->getMetricValueByIdentifierString($fileId, MetricKey::PREDICTION_TOO_LONG);
    expect($flag?->asBool())->toBeFalse();
});

// --- Custom threshold tests ---

it('uses custom file threshold from Config', function () {
    $controller = makeTooLongController();
    // lloc=450 is above default 400 but below custom 500
    $fileId = createFileCollection($controller, '/src/MediumFile.php', 450);

    $config = new Config();
    $config->set('thresholds', ['tooLong' => ['file' => 500]]);

    $prediction = new TooLongPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

it('uses custom class threshold from Config', function () {
    $controller = makeTooLongController();
    // lloc=320 above default 300 but below custom 400
    createLongClass($controller, 'App\\LongClass', 320);

    $config = new Config();
    $config->set('thresholds', ['tooLong' => ['class' => 400]]);

    $prediction = new TooLongPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

it('uses custom method threshold from Config', function () {
    $controller = makeTooLongController();
    // method lloc=35 above default 30 but below custom 50
    createClassWithMethod($controller, 'App\\Svc', 5, 'run', 35);

    $config = new Config();
    $config->set('thresholds', ['tooLong' => ['method' => 50]]);

    $prediction = new TooLongPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

it('counts multiple long files independently', function () {
    $controller = makeTooLongController();
    createFileCollection($controller, '/src/FileA.php', 500);
    createFileCollection($controller, '/src/FileB.php', 600);

    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(2);
});

it('problem level is WARNING', function () {
    $controller = makeTooLongController();
    $prediction = new TooLongPrediction($controller, $controller, $controller, new Config());

    expect($prediction->getLevel())->toBe(PhpCodeArch\Predictions\PredictionInterface::WARNING);
});
