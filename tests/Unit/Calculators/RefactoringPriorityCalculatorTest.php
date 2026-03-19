<?php

declare(strict_types=1);

use PhpCodeArch\Calculators\RefactoringPriorityCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\Problems\TooComplexProblem;
use PhpCodeArch\Predictions\Problems\SolidViolationProblem;
use PhpCodeArch\Predictions\PredictionInterface;

beforeEach(function () {
    $this->container = new MetricsContainer();
    $this->controller = new MetricsController($this->container);
    $this->controller->registerMetricTypes();
    $this->controller->createProjectMetricsCollection(['/src']);

    $this->calculator = new RefactoringPriorityCalculator($this->controller);
});

function createClass(MetricsController $controller, string $name, array $values, array $flags = []): string
{
    $path = '/src/' . str_replace('\\', '/', $name) . '.php';

    // Create file collection for git data lookup
    $controller->createMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => $path]
    );

    $controller->createMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['name' => $name, 'path' => $path]
    );

    $classId = $controller->getMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['name' => $name, 'path' => $path]
    )->getIdentifier()->__toString();

    $defaults = [
        'filePath' => $path,
        'interface' => false,
        'trait' => false,
        'enum' => false,
        'abstract' => false,
        'cc' => 1,
        'lcom' => 0,
        'lloc' => 20,
        'usedFromOutsideCount' => 0,
        'inDependencyCycle' => false,
        'dependencyCycleLength' => 0,
        'layerViolationCount' => 0,
        'solidViolationCount' => 0,
    ];

    $merged = array_merge($defaults, $flags, $values);

    $controller->setMetricValuesByIdentifierString($classId, $merged);

    return $classId;
}

function addProblemToClass(MetricsController $controller, string $classId, int $level): void
{
    $problem = TooComplexProblem::ofProblemLevelAndMessage($level, 'Test problem');
    $controller->setProblemByIdentifierString($classId, 'cc', $problem);
}

function getClassMetric(MetricsController $controller, string $classId, string $key): mixed
{
    $collection = $controller->getMetricCollectionByIdentifierString($classId);
    return $collection->get($key)?->getValue();
}

function getProjectMetricVal(MetricsController $controller, string $key): mixed
{
    $collection = $controller->getMetricCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null
    );
    return $collection->get($key)?->getValue();
}

it('scores 0 for a class with no problems and no structural issues', function () {
    $classId = createClass($this->controller, 'CleanDto', [
        'cc' => 1, 'lcom' => 0, 'lloc' => 20,
    ]);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    expect(getClassMetric($this->controller, $classId, 'refactoringPriority'))->toBe(0.0);
});

it('scores 0 for an interface', function () {
    $classId = createClass($this->controller, 'MyInterface', [
        'cc' => 10, 'lcom' => 5,
    ], ['interface' => true]);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    // Interface is skipped, so metric remains unset (null)
    expect(getClassMetric($this->controller, $classId, 'refactoringPriority'))->toBeNull();
});

it('scores 0 for a trait', function () {
    $classId = createClass($this->controller, 'MyTrait', [
        'cc' => 10,
    ], ['trait' => true]);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    expect(getClassMetric($this->controller, $classId, 'refactoringPriority'))->toBeNull();
});

it('scores 0 for an enum', function () {
    $classId = createClass($this->controller, 'MyEnum', [
        'cc' => 5,
    ], ['enum' => true]);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    expect(getClassMetric($this->controller, $classId, 'refactoringPriority'))->toBeNull();
});

it('gives high score to a god class', function () {
    $classId = createClass($this->controller, 'GodClass', [
        'cc' => 45, 'lcom' => 5, 'lloc' => 400,
        'usedFromOutsideCount' => 15,
        'inDependencyCycle' => true,
        'dependencyCycleLength' => 4,
        'layerViolationCount' => 2,
        'solidViolationCount' => 2,
    ]);

    // Add 4 errors
    for ($i = 0; $i < 4; $i++) {
        addProblemToClass($this->controller, $classId, PredictionInterface::ERROR);
    }

    // Set git data on the file collection
    $this->controller->setMetricValues(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '/src/GodClass.php'],
        ['gitChurnCount' => 25, 'gitAuthorCount' => 5, 'gitCodeAgeDays' => 30]
    );

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    $score = getClassMetric($this->controller, $classId, 'refactoringPriority');
    expect($score)->toBeGreaterThanOrEqual(60)
        ->and($score)->toBeLessThanOrEqual(100);
});

it('gives low score to a mildly complex class', function () {
    $classId = createClass($this->controller, 'MildClass', [
        'cc' => 12, 'lcom' => 2, 'lloc' => 80,
    ]);

    addProblemToClass($this->controller, $classId, PredictionInterface::WARNING);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    $score = getClassMetric($this->controller, $classId, 'refactoringPriority');
    expect($score)->toBeGreaterThan(0)
        ->and($score)->toBeLessThan(25);
});

it('does not exceed 100 with extreme values', function () {
    $classId = createClass($this->controller, 'ExtremeClass', [
        'cc' => 500, 'lcom' => 100, 'lloc' => 10000,
        'usedFromOutsideCount' => 100,
        'inDependencyCycle' => true,
        'dependencyCycleLength' => 50,
        'layerViolationCount' => 20,
        'solidViolationCount' => 20,
    ]);

    for ($i = 0; $i < 20; $i++) {
        addProblemToClass($this->controller, $classId, PredictionInterface::ERROR);
    }

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    $score = getClassMetric($this->controller, $classId, 'refactoringPriority');
    expect($score)->toBeLessThanOrEqual(100.0);
});

it('uses impact multiplier of 1.0 without git data', function () {
    $classId = createClass($this->controller, 'NoGitClass', [
        'cc' => 30, 'lcom' => 3, 'lloc' => 200,
    ]);

    addProblemToClass($this->controller, $classId, PredictionInterface::ERROR);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    $score = getClassMetric($this->controller, $classId, 'refactoringPriority');
    // With impact 1.0, score = severity / 2 → should be moderate
    expect($score)->toBeGreaterThan(0)
        ->and($score)->toBeLessThan(50);
});

it('generates contextual recommendation for high complexity', function () {
    $classId = createClass($this->controller, 'ComplexClass', [
        'cc' => 40, 'lcom' => 1, 'lloc' => 50,
    ]);

    addProblemToClass($this->controller, $classId, PredictionInterface::WARNING);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    $recommendation = getClassMetric($this->controller, $classId, 'refactoringPriorityRecommendation');
    expect($recommendation)->toContain('CC=40');
});

it('generates contextual recommendation for dependency cycles', function () {
    $classId = createClass($this->controller, 'CycleClass', [
        'cc' => 5, 'lcom' => 0, 'lloc' => 50,
        'inDependencyCycle' => true,
        'dependencyCycleLength' => 6,
    ]);

    $collection = $this->controller->getMetricCollectionByIdentifierString($classId);
    $this->calculator->calculate($collection);

    $recommendation = getClassMetric($this->controller, $classId, 'refactoringPriorityRecommendation');
    $drivers = getClassMetric($this->controller, $classId, 'refactoringPriorityDrivers');

    expect($recommendation)->toContain('6-class dependency cycle')
        ->and($drivers)->toContain('dependency cycle');
});

it('computes project-level aggregates', function () {
    $class1 = createClass($this->controller, 'Bad', ['cc' => 30, 'lcom' => 5, 'lloc' => 300]);
    addProblemToClass($this->controller, $class1, PredictionInterface::ERROR);

    $class2 = createClass($this->controller, 'Clean', ['cc' => 1, 'lcom' => 0, 'lloc' => 20]);

    $col1 = $this->controller->getMetricCollectionByIdentifierString($class1);
    $col2 = $this->controller->getMetricCollectionByIdentifierString($class2);

    $this->calculator->calculate($col1);
    $this->calculator->calculate($col2);
    $this->calculator->afterTraverse();

    $avg = getProjectMetricVal($this->controller, 'overallAvgRefactoringPriority');
    $max = getProjectMetricVal($this->controller, 'overallMaxRefactoringPriority');
    $needing = getProjectMetricVal($this->controller, 'overallClassesNeedingRefactoring');

    expect($avg)->toBeGreaterThan(0)
        ->and($max)->toBeGreaterThan(0)
        ->and($needing)->toBe(1); // Only 'Bad' has score > 0
});
