<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\MetricKey;
use PhpCodeArch\Metrics\Model\Collections\FileNameCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\PredictionInterface;
use PhpCodeArch\Predictions\TooComplexPrediction;

function makeTooComplexController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

/**
 * @param array<string, mixed> $metrics
 */
function createClassWithMetrics(
    MetricsController $controller,
    string $name,
    array $metrics = [],
): string {
    $path = '/src/'.str_replace('\\', '/', $name).'.php';
    $controller->createMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path]);

    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::ClassCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();

    $defaults = [
        MetricKey::CC => 1,
        MetricKey::LLOC => 10,
        MetricKey::DIFFICULTY => 5.0,
        MetricKey::EFFORT => 100.0,
        MetricKey::MAINTAINABILITY_INDEX => 90.0,
        MetricKey::LCOM => 0.5,
    ];

    $controller->setMetricValuesByIdentifierString($id, array_merge($defaults, $metrics));

    return $id;
}

/**
 * @param array<int, array<string, mixed>> $methodMetrics per-method overrides indexed by method number (0-based)
 *
 * @return string[] method identifiers
 */
function addMethodsToTooComplexClass(
    MetricsController $controller,
    string $classId,
    string $className,
    int $methodCount = 2,
    array $methodMetrics = [],
): array {
    $methodsCollection = new FileNameCollection();
    $methodIds = [];

    $defaults = [
        MetricKey::CC => 1,
        MetricKey::LLOC => 10,
        MetricKey::DIFFICULTY => 5.0,
        MetricKey::EFFORT => 100.0,
        MetricKey::MAINTAINABILITY_INDEX => 90.0,
        MetricKey::COGNITIVE_COMPLEXITY => 1,
    ];

    for ($i = 0; $i < $methodCount; ++$i) {
        $methodName = 'method'.($i + 1);
        $controller->createMetricCollection(
            MetricCollectionTypeEnum::MethodCollection,
            ['path' => $className, 'name' => $methodName]
        );

        $methodId = $controller->getMetricCollection(
            MetricCollectionTypeEnum::MethodCollection,
            ['path' => $className, 'name' => $methodName]
        )->getIdentifier()->__toString();

        $overrides = $methodMetrics[$i] ?? [];
        $controller->setMetricValuesByIdentifierString($methodId, array_merge($defaults, $overrides));

        $methodsCollection->set($methodName, $methodId);
        $methodIds[] = $methodId;
    }

    $controller->getMetricCollectionByIdentifierString($classId)->setCollection('methods', $methodsCollection);

    return $methodIds;
}

/**
 * Sets project-level averages for a given collection type (Class/File/Function).
 *
 * @param array<string, float> $averages e.g. ['Effort' => 1000.0, 'MaintainabilityIndex' => 80.0, 'Lcom' => 1.0]
 */
function setTooComplexProjectAverages(
    MetricsController $controller,
    string $collectionType,
    array $averages,
): void {
    foreach ($averages as $metricName => $value) {
        $key = sprintf('overall%sAvg%s', $collectionType, $metricName);
        $controller->setMetricValue(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            $value,
            $key
        );
    }
}

function createFileWithMetrics(
    MetricsController $controller,
    string $path,
    array $metrics = [],
): string {
    $controller->createMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $path]);

    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::FileCollection, ['path' => $path])
        ->getIdentifier()->__toString();

    $defaults = [
        MetricKey::CC => 1,
        MetricKey::LLOC => 10,
        MetricKey::DIFFICULTY => 5.0,
        MetricKey::EFFORT => 100.0,
        MetricKey::MAINTAINABILITY_INDEX => 90.0,
    ];

    $controller->setMetricValuesByIdentifierString($id, array_merge($defaults, $metrics));

    return $id;
}

function createFunctionWithMetrics(
    MetricsController $controller,
    string $name,
    string $path,
    array $metrics = [],
): string {
    $controller->createMetricCollection(MetricCollectionTypeEnum::FunctionCollection, ['name' => $name, 'path' => $path]);

    $id = $controller->getMetricCollection(MetricCollectionTypeEnum::FunctionCollection, ['name' => $name, 'path' => $path])
        ->getIdentifier()->__toString();

    $defaults = [
        MetricKey::CC => 1,
        MetricKey::LLOC => 10,
        MetricKey::DIFFICULTY => 5.0,
        MetricKey::EFFORT => 100.0,
        MetricKey::MAINTAINABILITY_INDEX => 90.0,
    ];

    $controller->setMetricValuesByIdentifierString($id, array_merge($defaults, $metrics));

    return $id;
}

/**
 * Convenience: set standard harmless project averages for Class and Function collections.
 */
function setHarmlessProjectAverages(MetricsController $controller): void
{
    setTooComplexProjectAverages($controller, 'ClassMetricsCollection', [
        'Effort' => 1000.0,
        'MaintainabilityIndex' => 80.0,
        'Lcom' => 1.0,
    ]);
    setTooComplexProjectAverages($controller, 'FunctionMetricsCollection', [
        'Effort' => 1000.0,
        'MaintainabilityIndex' => 80.0,
    ]);
}

/**
 * Check if a specific metric key has any problem on a given identifier.
 */
function hasProblems(MetricsController $controller, string $id, string $metricKey): bool
{
    $metricValue = $controller->getMetricValueByIdentifierString($id, $metricKey);

    return null !== $metricValue && count($metricValue->getProblems()) > 0;
}

// ============================================================
// Gruppe 1: Basis
// ============================================================

it('returns 0 when no collections exist', function () {
    $controller = makeTooComplexController();
    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('returns 0 for class below all thresholds', function () {
    $controller = makeTooComplexController();
    // CC=5, LLOC=25 (>20 → ccLargeCode=20, 5≤20), Difficulty=10≤30, Effort=500≤1300, MI=90≥64, LCOM skipped (no methods)
    createClassWithMetrics($controller, 'App\\SimpleClass', [
        MetricKey::CC => 5,
        MetricKey::LLOC => 25,
        MetricKey::DIFFICULTY => 10.0,
        MetricKey::EFFORT => 500.0,
        MetricKey::MAINTAINABILITY_INDEX => 90.0,
        MetricKey::LCOM => 0.5,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

// ============================================================
// Gruppe 2: CC-Check
// ============================================================

it('fires CC error for small code exceeding cc threshold', function () {
    $controller = makeTooComplexController();
    // LLOC=15 ≤ 20 → threshold=cc=10, CC=11 > 10 → problem
    createClassWithMetrics($controller, 'App\\HighCc', [
        MetricKey::CC => 11,
        MetricKey::LLOC => 15,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(1);
});

it('does not fire CC for large code below ccLargeCode threshold', function () {
    $controller = makeTooComplexController();
    // LLOC=25 > 20 → threshold=ccLargeCode=20, CC=15 ≤ 20 → no problem
    createClassWithMetrics($controller, 'App\\LargeOk', [
        MetricKey::CC => 15,
        MetricKey::LLOC => 25,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('fires CC error for large code exceeding ccLargeCode threshold', function () {
    $controller = makeTooComplexController();
    // LLOC=25 > 20 → threshold=20, CC=21 > 20 → problem
    createClassWithMetrics($controller, 'App\\LargeComplex', [
        MetricKey::CC => 21,
        MetricKey::LLOC => 25,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(1);
});

// ============================================================
// Gruppe 3: Difficulty-Check
// ============================================================

it('fires Difficulty error when above threshold', function () {
    $controller = makeTooComplexController();
    // LLOC=10 > 5, no framework → threshold=30, Difficulty=31 > 30 → problem
    createClassWithMetrics($controller, 'App\\HardClass', [
        MetricKey::DIFFICULTY => 31.0,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(1);
});

it('does not fire Difficulty for trivial code', function () {
    $controller = makeTooComplexController();
    // LLOC=5 ≤ 5 → trivial exit for Difficulty, Effort, MI
    createClassWithMetrics($controller, 'App\\TrivialHard', [
        MetricKey::DIFFICULTY => 50.0,
        MetricKey::LLOC => 5,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('uses framework Difficulty threshold for Symfony project', function () {
    $controller = makeTooComplexController();
    // Framework → difficultyFramework=45, Difficulty=40 ≤ 45 → no problem
    createClassWithMetrics($controller, 'App\\SymfonyService', [
        MetricKey::DIFFICULTY => 40.0,
    ]);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

it('fires Difficulty for Symfony project exceeding framework threshold', function () {
    $controller = makeTooComplexController();
    // Framework → threshold=45, Difficulty=46 > 45 → problem
    createClassWithMetrics($controller, 'App\\SymfonyHard', [
        MetricKey::DIFFICULTY => 46.0,
    ]);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(1);
});

// ============================================================
// Gruppe 4: Effort-Check (relative-to-average)
// ============================================================

it('fires Effort warning when above average plus tolerance', function () {
    $controller = makeTooComplexController();
    // LLOC=10 > 5, avgEffort=1000, tolerance=0.30, max=1000+300=1300, Effort=1400 > 1300 → problem
    createClassWithMetrics($controller, 'App\\HighEffort', [
        MetricKey::EFFORT => 1400.0,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(1);
});

it('does not fire Effort when within tolerance', function () {
    $controller = makeTooComplexController();
    // max=1300, Effort=1200 ≤ 1300 → no problem
    createClassWithMetrics($controller, 'App\\OkEffort', [
        MetricKey::EFFORT => 1200.0,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('does not fire Effort for trivial LLOC', function () {
    $controller = makeTooComplexController();
    // LLOC=5 ≤ 5 → trivial exit
    createClassWithMetrics($controller, 'App\\TinyEffort', [
        MetricKey::EFFORT => 9999.0,
        MetricKey::LLOC => 5,
    ]);
    setTooComplexProjectAverages($controller, 'ClassMetricsCollection', [
        'Effort' => 100.0,
        'MaintainabilityIndex' => 80.0,
        'Lcom' => 1.0,
    ]);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('uses wider framework Effort tolerance', function () {
    $controller = makeTooComplexController();
    // Framework → tolerance=0.50, max=1000+500=1500, Effort=1400 ≤ 1500 → no problem
    createClassWithMetrics($controller, 'App\\FrameworkEffort', [
        MetricKey::EFFORT => 1400.0,
    ]);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

// ============================================================
// Gruppe 5: MI-Check (relative-to-average)
// ============================================================

it('fires MI warning when below average minus tolerance', function () {
    $controller = makeTooComplexController();
    // no typeCoverage → tolerance=0.20, min=80-80*0.20=64, MI=60 < 64 → problem
    createClassWithMetrics($controller, 'App\\LowMi', [
        MetricKey::MAINTAINABILITY_INDEX => 60.0,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(1);
});

it('does not fire MI when within tolerance', function () {
    $controller = makeTooComplexController();
    // min=64, MI=65 ≥ 64 → no problem
    createClassWithMetrics($controller, 'App\\OkMi', [
        MetricKey::MAINTAINABILITY_INDEX => 65.0,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('does not fire MI for trivial LLOC', function () {
    $controller = makeTooComplexController();
    // LLOC=3 ≤ 5 → trivial exit
    createClassWithMetrics($controller, 'App\\TinyMi', [
        MetricKey::MAINTAINABILITY_INDEX => 10.0,
        MetricKey::LLOC => 3,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('uses wider MI tolerance for well-typed code', function () {
    $controller = makeTooComplexController();
    // typeCoverage=90 > 80 → tolerance=max(0.20, 0.30)=0.30, min=80-80*0.30=56, MI=58 ≥ 56 → no problem
    createClassWithMetrics($controller, 'App\\TypedMi', [
        MetricKey::MAINTAINABILITY_INDEX => 58.0,
        MetricKey::TYPE_COVERAGE => 90.0,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('uses framework MI tolerance as max of all', function () {
    $controller = makeTooComplexController();
    // typeCoverage=90 + framework → tolerance=max(0.20, 0.30, 0.35)=0.35, min=80-80*0.35=52, MI=55 ≥ 52 → no problem
    createClassWithMetrics($controller, 'App\\FrameworkTypedMi', [
        MetricKey::MAINTAINABILITY_INDEX => 55.0,
        MetricKey::TYPE_COVERAGE => 90.0,
    ]);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

it('fires MI when below framework tolerance', function () {
    $controller = makeTooComplexController();
    // no typeCoverage, framework → tolerance=max(0.20, 0.35)=0.35, min=80-80*0.35=52, MI=50 < 52 → problem
    createClassWithMetrics($controller, 'App\\FrameworkLowMi', [
        MetricKey::MAINTAINABILITY_INDEX => 50.0,
    ]);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(1);
});

// ============================================================
// Gruppe 6: LCOM-Check
// ============================================================

it('fires LCOM warning when above threshold', function () {
    $controller = makeTooComplexController();
    // 2 methods, normal name → shouldSkipLcom=false
    // avgLcom=1.0, max=max(1,1.0)+max(1,1.0)*0.30=1+0.30=1.30, LCOM=2.0 > 1.30 → problem
    $classId = createClassWithMetrics($controller, 'App\\HighLcomService', [
        MetricKey::LCOM => 2.0,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\HighLcomService', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeTrue();
});

it('does not fire LCOM when within tolerance', function () {
    $controller = makeTooComplexController();
    // max=1.30, LCOM=1.2 ≤ 1.30 → no problem
    $classId = createClassWithMetrics($controller, 'App\\OkLcomService', [
        MetricKey::LCOM => 1.2,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\OkLcomService', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('uses max(1, avgLcom) floor when avgLcom is below 1', function () {
    $controller = makeTooComplexController();
    // avgLcom=0.3 → max(1, 0.3)=1, max=1+1*0.30=1.30, LCOM=1.5 > 1.30 → problem
    $classId = createClassWithMetrics($controller, 'App\\FloorLcom', [
        MetricKey::LCOM => 1.5,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\FloorLcom', 2);
    setTooComplexProjectAverages($controller, 'ClassMetricsCollection', [
        'Effort' => 1000.0,
        'MaintainabilityIndex' => 80.0,
        'Lcom' => 0.3,
    ]);
    setTooComplexProjectAverages($controller, 'FunctionMetricsCollection', [
        'Effort' => 1000.0,
        'MaintainabilityIndex' => 80.0,
    ]);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeTrue();
});

// ============================================================
// Gruppe 7: shouldSkipLcom
// ============================================================

it('skips LCOM for enum', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\StatusEnum', [
        MetricKey::LCOM => 99.0,
        MetricKey::ENUM => true,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\StatusEnum', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('skips LCOM for interface', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\MyInterface', [
        MetricKey::LCOM => 99.0,
        MetricKey::INTERFACE => true,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\MyInterface', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('skips LCOM for trait', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\MyTrait', [
        MetricKey::LCOM => 99.0,
        MetricKey::TRAIT => true,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\MyTrait', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('skips LCOM for class with 1 method or fewer', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\SingleMethod', [
        MetricKey::LCOM => 99.0,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\SingleMethod', 1);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('skips LCOM for Exception name pattern', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\MyException', [
        MetricKey::LCOM => 99.0,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\MyException', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('skips LCOM for Error name pattern', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\ValidationError', [
        MetricKey::LCOM => 99.0,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\ValidationError', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('skips LCOM when class implements EventSubscriberInterface', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\MySubscriber', [
        MetricKey::LCOM => 99.0,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\MySubscriber', 2);

    // Add interfaces collection
    $interfacesCollection = new FileNameCollection();
    $interfacesCollection->set('EventSubscriberInterface', 'Symfony\\Component\\EventDispatcher\\EventSubscriberInterface');
    $controller->getMetricCollectionByIdentifierString($classId)->setCollection('interfaces', $interfacesCollection);

    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('skips LCOM for Symfony Command pattern when Symfony detected', function () {
    $controller = makeTooComplexController();
    $classId = createClassWithMetrics($controller, 'App\\ImportCommand', [
        MetricKey::LCOM => 99.0,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\ImportCommand', 2);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(symfonyDetected: true));
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeFalse();
});

it('does NOT skip LCOM for Command pattern without Symfony detection', function () {
    $controller = makeTooComplexController();
    // No Symfony → *Command pattern doesn't apply, name doesn't match *Exception/*Error
    // avgLcom=1.0, max=1.30, LCOM=5.0 > 1.30 → fires
    $classId = createClassWithMetrics($controller, 'App\\ImportCommand', [
        MetricKey::LCOM => 5.0,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\ImportCommand', 2);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $classId, MetricKey::LCOM))->toBeTrue();
});

// ============================================================
// Gruppe 8: Per-Method Cognitive Complexity
// ============================================================

it('fires per-method cognitive complexity error', function () {
    $controller = makeTooComplexController();
    // Method cogC=16 > 15 → problem on method
    $classId = createClassWithMetrics($controller, 'App\\CogComplex', [
        MetricKey::LCOM => 0.5,
    ]);
    $methodIds = addMethodsToTooComplexClass($controller, $classId, 'App\\CogComplex', 1, [
        0 => [MetricKey::COGNITIVE_COMPLEXITY => 16],
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $methodIds[0], MetricKey::COGNITIVE_COMPLEXITY))->toBeTrue();
});

it('does not fire cognitive complexity when within threshold', function () {
    $controller = makeTooComplexController();
    // Method cogC=15 ≤ 15 → no problem
    $classId = createClassWithMetrics($controller, 'App\\CogOk', [
        MetricKey::LCOM => 0.5,
    ]);
    $methodIds = addMethodsToTooComplexClass($controller, $classId, 'App\\CogOk', 1, [
        0 => [MetricKey::COGNITIVE_COMPLEXITY => 15],
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    expect(hasProblems($controller, $methodIds[0], MetricKey::COGNITIVE_COMPLEXITY))->toBeFalse();
});

// ============================================================
// Gruppe 9: classTooComplex (avgMethodCc)
// ============================================================

it('sets classTooComplex when avgMethodCc exceeds threshold', function () {
    $controller = makeTooComplexController();
    // avgMethodCc = (12+10)/2 = 11 > 10 → classTooComplex=true
    $classId = createClassWithMetrics($controller, 'App\\AvgCcHigh', [
        MetricKey::LCOM => 0.5,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\AvgCcHigh', 2, [
        0 => [MetricKey::CC => 12],
        1 => [MetricKey::CC => 10],
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $tooComplex = $controller->getMetricValueByIdentifierString($classId, MetricKey::PREDICTION_TOO_COMPLEX);

    expect($tooComplex?->asBool())->toBeTrue();
});

it('does not set classTooComplex when avgMethodCc is within threshold', function () {
    $controller = makeTooComplexController();
    // avgMethodCc = (8+10)/2 = 9 ≤ 10 → false
    $classId = createClassWithMetrics($controller, 'App\\AvgCcOk', [
        MetricKey::LCOM => 0.5,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\AvgCcOk', 2, [
        0 => [MetricKey::CC => 8],
        1 => [MetricKey::CC => 10],
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $tooComplex = $controller->getMetricValueByIdentifierString($classId, MetricKey::PREDICTION_TOO_COMPLEX);

    expect($tooComplex?->asBool())->toBeFalse();
});

it('sets avgMethodCc and avgMethodCogC values on class', function () {
    $controller = makeTooComplexController();
    // CC=[6,9,12] → avg=9.0, cogC=[3,5,7] → avg=5.0
    $classId = createClassWithMetrics($controller, 'App\\AvgCalc', [
        MetricKey::LCOM => 0.5,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\AvgCalc', 3, [
        0 => [MetricKey::CC => 6, MetricKey::COGNITIVE_COMPLEXITY => 3],
        1 => [MetricKey::CC => 9, MetricKey::COGNITIVE_COMPLEXITY => 5],
        2 => [MetricKey::CC => 12, MetricKey::COGNITIVE_COMPLEXITY => 7],
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());
    $prediction->predict();

    $avgCc = $controller->getMetricValueByIdentifierString($classId, MetricKey::AVG_METHOD_CC);
    $avgCogC = $controller->getMetricValueByIdentifierString($classId, MetricKey::AVG_METHOD_COG_C);

    expect($avgCc?->asFloat())->toBe(9.0)
        ->and($avgCogC?->asFloat())->toBe(5.0);
});

// ============================================================
// Gruppe 10: File/Function Collections
// ============================================================

it('handles FileMetricsCollection CC check', function () {
    $controller = makeTooComplexController();
    // CC=11 > 10 (LLOC=10 ≤ 20) → +1
    createFileWithMetrics($controller, '/src/bigfile.php', [
        MetricKey::CC => 11,
    ]);
    setTooComplexProjectAverages($controller, 'FileMetricsCollection', [
        'Effort' => 1000.0,
        'MaintainabilityIndex' => 80.0,
    ]);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(1);
});

it('handles FunctionMetricsCollection CC check', function () {
    $controller = makeTooComplexController();
    // CC=11 > 10 → +1
    createFunctionWithMetrics($controller, 'complexFunction', '/src/functions.php', [
        MetricKey::CC => 11,
    ]);
    setTooComplexProjectAverages($controller, 'FunctionMetricsCollection', [
        'Effort' => 1000.0,
        'MaintainabilityIndex' => 80.0,
    ]);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(1);
});

// ============================================================
// Gruppe 11: Konfigurierbare Thresholds
// ============================================================

it('respects custom CC threshold from config', function () {
    $controller = makeTooComplexController();
    // Custom cc=15, CC=12 ≤ 15 → no problem (default=10 would fire)
    createClassWithMetrics($controller, 'App\\CustomCc', [
        MetricKey::CC => 12,
    ]);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('thresholds', ['tooComplex' => ['cc' => 15]]);
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

it('respects custom cognitiveComplexity threshold from config', function () {
    $controller = makeTooComplexController();
    // Custom cognitiveComplexity=20, method cogC=18 ≤ 20 → no problem
    $classId = createClassWithMetrics($controller, 'App\\CustomCogC', [
        MetricKey::LCOM => 0.5,
    ]);
    addMethodsToTooComplexClass($controller, $classId, 'App\\CustomCogC', 1, [
        0 => [MetricKey::COGNITIVE_COMPLEXITY => 18],
    ]);
    setHarmlessProjectAverages($controller);

    $config = new Config();
    $config->set('thresholds', ['tooComplex' => ['cognitiveComplexity' => 20]]);
    $prediction = new TooComplexPrediction($controller, $controller, $controller, $config);
    $prediction->predict();

    $methodId = array_values($controller->getCollectionByIdentifierString($classId, 'methods')->getAsArray())[0];

    expect(hasProblems($controller, $methodId, MetricKey::COGNITIVE_COMPLEXITY))->toBeFalse();
});

// ============================================================
// Gruppe 12: Kombination / Integration
// ============================================================

it('counts problems from multiple checks independently', function () {
    $controller = makeTooComplexController();
    // CC=11 > 10 → +1 ERROR, Difficulty=31 > 30 → +1 ERROR = 2 problems
    createClassWithMetrics($controller, 'App\\MultiProblem', [
        MetricKey::CC => 11,
        MetricKey::DIFFICULTY => 31.0,
    ]);
    setHarmlessProjectAverages($controller);

    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(2);
});

it('problem level is ERROR', function () {
    $controller = makeTooComplexController();
    $prediction = new TooComplexPrediction($controller, $controller, $controller, new Config());

    expect($prediction->getLevel())->toBe(PredictionInterface::ERROR);
});
