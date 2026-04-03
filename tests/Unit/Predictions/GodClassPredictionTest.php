<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\GodClassPrediction;
use PhpCodeArch\Predictions\PredictionInterface;

function makeGodClassController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

/**
 * Creates a class collection and sets metric values.
 *
 * @param array<string, mixed> $metrics
 */
function createGodClassCandidate(
    MetricsController $controller,
    string $name,
    array $metrics = [],
): string {
    $path = '/src/'.str_replace('\\', '/', $name).'.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path]);

    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();

    $defaults = [
        MetricKey::PUBLIC_COUNT => 0,
        MetricKey::USES_COUNT => 0,
        MetricKey::USED_BY_COUNT => 0,
        MetricKey::LCOM => 0.0,
    ];

    $controller->setMetricValuesByIdentifierString($id, array_merge($defaults, $metrics));

    return $id;
}

/**
 * Attaches N dummy methods to a class so that shouldSkipLcom does not skip due to method count.
 * Returns the method identifiers.
 *
 * @return string[]
 */
function addMethodsToGodClass(
    MetricsController $controller,
    string $classId,
    string $className,
    int $methodCount = 2,
    bool $firstIsTooLong = false,
): array {
    $methodsCollection = new FileNameCollection();
    $methodIds = [];

    for ($i = 1; $i <= $methodCount; ++$i) {
        $methodName = 'method'.$i;
        $controller->createMetricCollection(
            MetricCollectionTypeEnum::MethodCollection,
            ['path' => $className, 'name' => $methodName]
        );

        $methodId = $controller->getMetricCollection(
            MetricCollectionTypeEnum::MethodCollection,
            ['path' => $className, 'name' => $methodName]
        )->getIdentifier()->__toString();

        $isTooLong = (1 === $i && $firstIsTooLong);
        $controller->setMetricValueByIdentifierString($methodId, MetricKey::PREDICTION_TOO_LONG, $isTooLong);

        $methodsCollection->set($methodName, $methodId);
        $methodIds[] = $methodId;
    }

    $controller->getMetricCollectionByIdentifierString($classId)->setCollection('methods', $methodsCollection);

    return $methodIds;
}

// --- Basic fire/no-fire tests ---

it('returns 0 when no classes exist', function () {
    $controller = makeGodClassController();
    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('does not fire when suspectIndex is 0', function () {
    $controller = makeGodClassController();
    createGodClassCandidate($controller, 'App\\SimpleClass', [
        MetricKey::PUBLIC_COUNT => 5,
        MetricKey::USES_COUNT => 2,
        MetricKey::USED_BY_COUNT => 2,
        MetricKey::LCOM => 0.5,
    ]);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('does not fire when suspectIndex is 2 (below threshold of 3)', function () {
    $controller = makeGodClassController();
    // Factor 1: publicCount > 10
    // Factor 2: usesCount + usedByCount > 10 (6+5=11)
    // No LCOM factor (lcom <= 1), no long methods → suspectIndex=2
    createGodClassCandidate($controller, 'App\\AlmostGod', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 0.5,
    ]);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('fires when suspectIndex reaches exactly 3', function () {
    $controller = makeGodClassController();
    // Factor 1: publicCount=11
    // Factor 2: coupling=11 (6+5)
    // Factor 3: lcom=2.0 — need >= 2 methods to not be skipped
    $classId = createGodClassCandidate($controller, 'App\\GodClass', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 2.0,
    ]);
    addMethodsToGodClass($controller, $classId, 'App\\GodClass', 2);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(1);
});

it('fires with all 4 factors active', function () {
    $controller = makeGodClassController();
    $classId = createGodClassCandidate($controller, 'App\\UltimateGod', [
        MetricKey::PUBLIC_COUNT => 15,
        MetricKey::USES_COUNT => 8,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 3.0,
    ]);
    // Add methods, first one is too long
    addMethodsToGodClass($controller, $classId, 'App\\UltimateGod', 2, firstIsTooLong: true);

    $prediction = new GodClassPrediction(new Config());
    $count = $prediction->predict($controller);

    expect($count)->toBe(1);

    $suspectIndex = $controller->getMetricValueByIdentifierString($classId, MetricKey::GOD_OBJECT_SUSPECT_INDEX);
    expect($suspectIndex?->asInt())->toBe(4);
});

it('sets predictionGodObject=true and suspectIndex on firing class', function () {
    $controller = makeGodClassController();
    $classId = createGodClassCandidate($controller, 'App\\GodService', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 2.0,
    ]);
    addMethodsToGodClass($controller, $classId, 'App\\GodService', 2);

    $prediction = new GodClassPrediction(new Config());
    $prediction->predict($controller);

    $isGodObject = $controller->getMetricValueByIdentifierString($classId, MetricKey::PREDICTION_GOD_OBJECT);
    $suspectIndex = $controller->getMetricValueByIdentifierString($classId, MetricKey::GOD_OBJECT_SUSPECT_INDEX);

    expect($isGodObject?->asBool())->toBeTrue()
        ->and($suspectIndex?->asInt())->toBeGreaterThanOrEqual(3);
});

it('sets predictionGodObject=false when suspectIndex < 3', function () {
    $controller = makeGodClassController();
    $classId = createGodClassCandidate($controller, 'App\\NormalClass', [
        MetricKey::PUBLIC_COUNT => 5,
        MetricKey::USES_COUNT => 2,
        MetricKey::USED_BY_COUNT => 2,
        MetricKey::LCOM => 0.5,
    ]);

    $prediction = new GodClassPrediction(new Config());
    $prediction->predict($controller);

    $isGodObject = $controller->getMetricValueByIdentifierString($classId, MetricKey::PREDICTION_GOD_OBJECT);
    expect($isGodObject?->asBool())->toBeFalse();
});

// --- Long methods factor ---

it('counts long methods as a suspect factor', function () {
    $controller = makeGodClassController();
    // Only 2 factors without long methods: publicCount, coupling
    // Add long method → 3rd factor → fires
    $classId = createGodClassCandidate($controller, 'App\\BigMethods', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 0.5,
    ]);
    addMethodsToGodClass($controller, $classId, 'App\\BigMethods', 2, firstIsTooLong: true);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(1);
});

it('does not count long methods factor when no method is too long', function () {
    $controller = makeGodClassController();
    $classId = createGodClassCandidate($controller, 'App\\ShortMethods', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 0.5,
    ]);
    // 2 methods, neither too long → only 2 factors → no fire
    addMethodsToGodClass($controller, $classId, 'App\\ShortMethods', 2, firstIsTooLong: false);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

// --- shouldSkipLcom tests ---

it('skips LCOM factor when class has only 1 method', function () {
    $controller = makeGodClassController();
    // publicCount=11, coupling=11, lcom=5.0 but 1 method → LCOM skipped → suspectIndex=2 → no fire
    $classId = createGodClassCandidate($controller, 'App\\SingleMethod', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 5.0,
    ]);
    addMethodsToGodClass($controller, $classId, 'App\\SingleMethod', 1);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

it('skips LCOM factor when class name matches Exception pattern', function () {
    $controller = makeGodClassController();
    // *Exception → shouldSkipLcom → LCOM not counted
    $classId = createGodClassCandidate($controller, 'App\\MyException', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 5.0,
    ]);
    addMethodsToGodClass($controller, $classId, 'App\\MyException', 2);

    $prediction = new GodClassPrediction(new Config());

    // suspectIndex = 2 (publicCount + coupling) → no fire
    expect($prediction->predict($controller))->toBe(0);
});

it('counts LCOM factor when class has >= 2 methods and no exclusions', function () {
    $controller = makeGodClassController();
    // Only publicCount=11 + lcom=2.0 → suspectIndex=2 → no fire (coupling=4 is below 10)
    $classId = createGodClassCandidate($controller, 'App\\HighLcom', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 2,
        MetricKey::USED_BY_COUNT => 2,
        MetricKey::LCOM => 2.0,
    ]);
    addMethodsToGodClass($controller, $classId, 'App\\HighLcom', 2);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(0);
});

// --- Framework controller threshold adjustment ---

it('raises coupling threshold for Symfony controller classes', function () {
    $controller = makeGodClassController();
    // usesCount+usedByCount=20 — above default 10, but Symfony controllers get threshold=25
    // publicCount=11, coupling=20 → with Symfony+Controller: coupling does NOT count
    // lcom=0.5, no long methods → only 1 factor → no fire
    $classId = createGodClassCandidate($controller, 'App\\UserController', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 12,
        MetricKey::USED_BY_COUNT => 8,
        MetricKey::LCOM => 0.5,
    ]);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));

    $prediction = new GodClassPrediction($config);

    expect($prediction->predict($controller))->toBe(0);
});

it('still counts coupling for Symfony controller exceeding raised threshold', function () {
    $controller = makeGodClassController();
    // coupling=26 > 25 → counts even for Symfony controller
    // publicCount=11, coupling=26, lcom=2.0, 2 methods → suspectIndex=3 → fires
    $classId = createGodClassCandidate($controller, 'App\\BigController', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 14,
        MetricKey::USED_BY_COUNT => 12,
        MetricKey::LCOM => 2.0,
    ]);
    addMethodsToGodClass($controller, $classId, 'App\\BigController', 2);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));

    $prediction = new GodClassPrediction($config);

    expect($prediction->predict($controller))->toBe(1);
});

it('uses default coupling threshold for non-controller Symfony classes', function () {
    $controller = makeGodClassController();
    // coupling=15 > 10 but it's a Service not a Controller → default threshold applies → coupling counts
    // publicCount=11, coupling=15, lcom=0.5 → suspectIndex=2 → no fire
    createGodClassCandidate($controller, 'App\\UserService', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 8,
        MetricKey::USED_BY_COUNT => 7,
        MetricKey::LCOM => 0.5,
    ]);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));

    $prediction = new GodClassPrediction($config);

    // suspectIndex=2 (publicCount + coupling) → no fire — same as without framework
    expect($prediction->predict($controller))->toBe(0);
});

it('counts multiple god class candidates independently', function () {
    $controller = makeGodClassController();

    // God class A (3 factors)
    $classIdA = createGodClassCandidate($controller, 'App\\GodA', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 2.0,
    ]);
    addMethodsToGodClass($controller, $classIdA, 'App\\GodA', 2);

    // God class B (3 factors)
    $classIdB = createGodClassCandidate($controller, 'App\\GodB', [
        MetricKey::PUBLIC_COUNT => 11,
        MetricKey::USES_COUNT => 6,
        MetricKey::USED_BY_COUNT => 5,
        MetricKey::LCOM => 2.0,
    ]);
    addMethodsToGodClass($controller, $classIdB, 'App\\GodB', 2);

    $prediction = new GodClassPrediction(new Config());

    expect($prediction->predict($controller))->toBe(2);
});

it('problem level is ERROR', function () {
    $prediction = new GodClassPrediction(new Config());

    expect($prediction->getLevel())->toBe(PredictionInterface::ERROR);
});
