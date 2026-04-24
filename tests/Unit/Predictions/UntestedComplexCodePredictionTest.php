<?php

declare(strict_types=1);

use PhpCodeArch\Application\Config;
use PhpCodeArch\Application\Service\FrameworkDetectionResult;
use PhpCodeArch\Application\Service\TestScanResult;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Predictions\Problems\UntestedComplexCodeProblem;
use PhpCodeArch\Predictions\UntestedComplexCodePrediction;

function makeUntestedController(): MetricsController
{
    $container = new MetricsContainer();
    $controller = new MetricsController($container);
    $controller->registerMetricTypes();
    $controller->createProjectMetricsCollection(['/src']);

    return $controller;
}

function createComplexClass(
    MetricsController $controller,
    string $name,
    array $metrics = [],
    array $flags = [],
): string {
    $path = '/src/'.str_replace('\\', '/', $name).'.php';

    $controller->createMetricCollection(
        MetricCollectionTypeEnum::ClassCollection,
        ['name' => $name, 'path' => $path]
    );

    $id = $controller->getMetricCollection(
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
        'hasTest' => false,
    ];

    $controller->setMetricValuesByIdentifierString($id, array_merge($defaults, $flags, $metrics));

    return $id;
}

function makeTestFrameworkConfig(): Config
{
    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(pestDetected: true));

    return $config;
}

it('returns 0 when no test infrastructure exists', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'ComplexService', ['cc' => 15]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, new Config());

    expect($prediction->predict())->toBe(0);
});

it('returns 0 when class has hasTest=true', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'TestedService', ['cc' => 15, 'hasTest' => true]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());

    expect($prediction->predict())->toBe(0);
});

it('fires WARNING when cc >= 8 and hasTest=false', function () {
    $controller = makeUntestedController();
    $classId = createComplexClass($controller, 'ComplexService', ['cc' => 8]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());
    $count = $prediction->predict();

    expect($count)->toBe(1);

    $problems = $controller->getMetricCollectionByIdentifierString($classId)
        ->get('hasTest')
        ?->getProblems();

    expect($problems)->toHaveCount(1)
        ->and($problems[0])->toBeInstanceOf(UntestedComplexCodeProblem::class);
});

it('does not fire when cc < 8', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'SimpleService', ['cc' => 7]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());

    expect($prediction->predict())->toBe(0);
});

it('skips interfaces', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'MyInterface', ['cc' => 20], ['interface' => true]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());

    expect($prediction->predict())->toBe(0);
});

it('skips traits', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'MyTrait', ['cc' => 20], ['trait' => true]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());

    expect($prediction->predict())->toBe(0);
});

it('skips enums', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'MyEnum', ['cc' => 20], ['enum' => true]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());

    expect($prediction->predict())->toBe(0);
});

it('skips abstract classes', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'AbstractBase', ['cc' => 20], ['abstract' => true]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());

    expect($prediction->predict())->toBe(0);
});

it('uses configurable threshold', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'ModerateService', ['cc' => 5]);

    $config = new Config();
    $config->set('frameworkDetection', new FrameworkDetectionResult(pestDetected: true));
    $config->set('thresholds', ['untestedComplexCode' => ['cc' => 4]]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, $config);

    // cc=5 >= threshold=4 → fires
    expect($prediction->predict())->toBe(1);
});

it('attaches UntestedComplexCodeProblem to the hasTest metric', function () {
    $controller = makeUntestedController();
    $classId = createComplexClass($controller, 'ComplexService', ['cc' => 12]);

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());
    $prediction->predict();

    $hasTestValue = $controller->getMetricCollectionByIdentifierString($classId)->get('hasTest');

    expect($hasTestValue)->not->toBeNull()
        ->and($hasTestValue->getProblems())->toHaveCount(1)
        ->and($hasTestValue->getProblems()[0])->toBeInstanceOf(UntestedComplexCodeProblem::class)
        ->and($hasTestValue->getProblems()[0]->getName())->toBe('Untested Complex Code');
});

it('returns 0 when testScanResult has empty testDirectories and no framework', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'ComplexService', ['cc' => 15]);

    $config = new Config();
    $config->set('testScanResult', new TestScanResult(testDirectories: []));

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, $config);

    expect($prediction->predict())->toBe(0);
});

it('detects test infrastructure from testScanResult directories', function () {
    $controller = makeUntestedController();
    createComplexClass($controller, 'ComplexService', ['cc' => 10]);

    $config = new Config();
    $config->set('testScanResult', new TestScanResult(testDirectories: ['/tests']));

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, $config);

    // testScanResult has directories → infrastructure detected → fires
    expect($prediction->predict())->toBe(1);
});

it('skips classes flagged as excludedByPhpunitSource', function () {
    $controller = makeUntestedController();
    createComplexClass(
        $controller,
        'App\\DataFixtures\\UserFixtures',
        ['cc' => 15],
        ['excludedByPhpunitSource' => true],
    );

    $prediction = new UntestedComplexCodePrediction($controller, $controller, $controller, makeTestFrameworkConfig());

    // Even though cc=15 and hasTest=false, the source exclusion wins
    expect($prediction->predict())->toBe(0);
});
