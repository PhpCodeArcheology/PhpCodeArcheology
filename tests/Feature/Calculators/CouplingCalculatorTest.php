<?php

declare(strict_types=1);

namespace Test\Feature\Calculators;

use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Calculators\Helpers\PackageInstabilityAbstractnessCalculator;
use PhpCodeArch\Metrics\Controller\MetricsController;
use PhpCodeArch\Metrics\MetricCollectionTypeEnum;
use PhpCodeArch\Metrics\Model\Collections\ClassNameCollection;
use PhpCodeArch\Metrics\Model\Collections\EnumNameCollection;
use PhpCodeArch\Metrics\Model\Collections\InterfaceNameCollection;
use PhpCodeArch\Metrics\Model\Collections\PackageNameCollection;
use PhpCodeArch\Metrics\Model\Collections\TraitNameCollection;
use PhpCodeArch\Metrics\Model\MetricsContainer;
use PhpCodeArch\Metrics\Model\MetricValue;

beforeEach(function () {
    $metrics = new MetricsContainer();

    $this->metricsController = new MetricsController($metrics);
    $this->metricsController->createProjectMetricsCollection(['']);
    $this->metricsController->createMetricCollection(
        MetricCollectionTypeEnum::FileCollection,
        ['path' => '']
    );

    $this->classes = [
        [
            'id' => '',
            'name' => 'ClassA',
            'data' => [
                'realClass' => true,
                'abstract' => false,
                'interface' => false,
                'dependencies' => [
                    'ClassB',
                    'ClassC',
                ],
                'interfaces' => [],
                'extends' => [],
                'package' => '_global',
            ],
            'expected' => [
                'instability' => 1,
                'usesInProjectCount' => 2,
                'usedByCount' => 0,
            ],
        ],
        [
            'id' => '',
            'name' => 'ClassB',
            'data' => [
                'realClass' => true,
                'abstract' => false,
                'interface' => false,
                'dependencies' => [
                ],
                'interfaces' => [],
                'extends' => [],
                'package' => '_global',
            ],
            'expected' => [
                'instability' => 0,
                'usesInProjectCount' => 0,
                'usedByCount' => 2,
            ],
        ],
        [
            'id' => '',
            'name' => 'ClassC',
            'data' => [
                'realClass' => true,
                'abstract' => false,
                'interface' => false,
                'dependencies' => [
                    'ClassB',
                ],
                'interfaces' => [],
                'extends' => [],
                'package' => '_global',
            ],
            'expected' => [
                'instability' => 0.5,
                'usesInProjectCount' => 1,
                'usedByCount' => 1,
            ],
        ],
        [
            'id' => '',
            'name' => 'ClassD',
            'data' => [
                'realClass' => true,
                'abstract' => false,
                'interface' => false,
                'dependencies' => [
                    'TraitA',
                ],
                'interfaces' => [],
                'extends' => [],
                'package' => '_global',
            ],
            'expected' => [
                'instability' => 1,
                'usesInProjectCount' => 1,
                'usedByCount' => 0,
            ],
        ],
        [
            'id' => '',
            'name' => 'TraitA',
            'data' => [
                'realClass' => false,
                'abstract' => false,
                'interface' => false,
                'dependencies' => [
                ],
                'interfaces' => [],
                'extends' => [],
                'package' => '_global',
            ],
            'expected' => [
                'instability' => 0,
                'usesInProjectCount' => 0,
                'usedByCount' => 1,
            ],
        ],
    ];

    $classes = [];

    array_walk($this->classes, function (&$class) use (&$classes) {
        $classMetrics = $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::ClassCollection,
            [
                'path' => '',
                'name' => $class['name'],
            ],
        );

        $id = (string) $classMetrics->getIdentifier();
        $classes[$id] = $classMetrics->getName();

        $class['id'] = $id;

        $collections = [
            'dependencies' => ClassNameCollection::class,
            'interfaces' => ClassNameCollection::class,
            'extends' => ClassNameCollection::class,
        ];

        foreach ($collections as $collectionKey => $collectionClass) {
            $collection = new $collectionClass($class['data'][$collectionKey]);
            unset($class['data'][$collectionKey]);

            $classMetrics->setCollection(
                $collectionKey,
                $collection
            );
        }

        foreach ($class['data'] as $key => $value) {
            $metricValue = MetricValue::ofValueAndTypeKey($value, $key);
            $classMetrics->set($key, $metricValue);
        }
    });

    $this->metricsController->setCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        new ClassNameCollection($classes),
        'classes'
    );

    $this->metricsController->setCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        new InterfaceNameCollection([]),
        'interfaces'
    );

    $this->metricsController->setCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        new TraitNameCollection([]),
        'traits'
    );

    $this->metricsController->setCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        new EnumNameCollection([]),
        'enums'
    );

    $this->metricsController->setCollection(
        MetricCollectionTypeEnum::ProjectCollection,
        null,
        new PackageNameCollection([]),
        'packages'
    );

    $packageIACalc = new PackageInstabilityAbstractnessCalculator($this->metricsController, $this->metricsController);
    $couplingCalculator = new CouplingCalculator($this->metricsController, $this->metricsController, $packageIACalc);

    $couplingCalculator->beforeTraverse();

    foreach ($this->metricsController->getAllCollections() as $metric) {
        $couplingCalculator->calculate($metric);
    }
    $couplingCalculator->afterTraverse();
});

it('calculates dependency counts correctly', function () {
    array_walk($this->classes, function ($class) {
        $classMetrics = $this->metricsController->getMetricCollectionByIdentifierString($class['id']);

        expect($classMetrics->get('usesInProjectCount')->getValue())->toBe($class['expected']['usesInProjectCount'])
            ->and($classMetrics->get('usedByCount')->getValue())->toBe($class['expected']['usedByCount']);
    });
});

it('calculates instability correctly', function () {
    array_walk($this->classes, function ($class) {
        $classMetrics = $this->metricsController->getMetricCollectionByIdentifierString($class['id']);

        expect($classMetrics->get('instability')->getValue())->toBe($class['expected']['instability']);
    });
});

/*
 * Hand-calculated scenario: ServiceA → ServiceB
 *
 * Instability formula: I = Ce / (Ca + Ce)
 *   Ce = efferent coupling (classes this class depends on, "fan-out")
 *   Ca = afferent coupling (classes that depend on this class, "fan-in")
 *
 * ServiceA ──depends on──▶ ServiceB
 *
 *   ServiceA: Ce=1, Ca=0  →  I = 1 / (0 + 1) = 1.0  (maximally unstable)
 *   ServiceB: Ce=0, Ca=1  →  I = 0 / (1 + 0) = 0.0  (maximally stable)
 *
 * See: tests/Feature/Analysis/testfiles/hand-calculated-coupling.php
 */
describe('hand-calculated ServiceA → ServiceB', function () {
    beforeEach(function () {
        $metrics = new MetricsContainer();
        $this->metricsController = new MetricsController($metrics);
        $this->metricsController->createProjectMetricsCollection(['']);
        $this->metricsController->createMetricCollection(
            MetricCollectionTypeEnum::FileCollection,
            ['path' => '']
        );

        // ServiceA: Ce=1 (depends on ServiceB), Ca=0
        // I = 1/(0+1) = 1.0
        $this->handCalcClasses = [
            [
                'name' => 'HandCalcServiceA',
                'dependencies' => ['HandCalcServiceB'],
                'expected' => [
                    'usesForInstabilityCount' => 1,
                    'usedByCount' => 0,
                    'instability' => 1,   // PHP: 1/1 = int(1), not float(1.0)
                ],
            ],
            // ServiceB: Ce=0, Ca=1 (used by ServiceA)
            // I = 0/(1+0) = 0   (PHP: 0/1 = int(0))
            [
                'name' => 'HandCalcServiceB',
                'dependencies' => [],
                'expected' => [
                    'usesForInstabilityCount' => 0,
                    'usedByCount' => 1,
                    'instability' => 0,   // PHP: 0/1 = int(0), not float(0.0)
                ],
            ],
        ];

        $classes = [];

        array_walk($this->handCalcClasses, function (&$class) use (&$classes) {
            $classMetrics = $this->metricsController->createMetricCollection(
                MetricCollectionTypeEnum::ClassCollection,
                ['path' => '', 'name' => $class['name']]
            );

            $id = (string) $classMetrics->getIdentifier();
            $classes[$id] = $classMetrics->getName();
            $class['id'] = $id;

            $depCollection = new ClassNameCollection($class['dependencies']);
            $classMetrics->setCollection('dependencies', $depCollection);

            foreach (['realClass' => true, 'abstract' => false, 'interface' => false, 'package' => '_global'] as $key => $value) {
                $classMetrics->set($key, MetricValue::ofValueAndTypeKey($value, $key));
            }

            foreach (['interfaces' => [], 'extends' => []] as $key => $value) {
                $classMetrics->setCollection($key, new ClassNameCollection($value));
            }
        });

        $this->metricsController->setCollection(
            MetricCollectionTypeEnum::ProjectCollection,
            null,
            new ClassNameCollection($classes),
            'classes'
        );

        foreach (['interfaces' => InterfaceNameCollection::class, 'traits' => TraitNameCollection::class, 'enums' => EnumNameCollection::class, 'packages' => PackageNameCollection::class] as $key => $collClass) {
            $this->metricsController->setCollection(
                MetricCollectionTypeEnum::ProjectCollection,
                null,
                new $collClass([]),
                $key
            );
        }

        $packageIACalc = new PackageInstabilityAbstractnessCalculator($this->metricsController, $this->metricsController);
        $couplingCalculator = new CouplingCalculator($this->metricsController, $this->metricsController, $packageIACalc);

        $couplingCalculator->beforeTraverse();
        foreach ($this->metricsController->getAllCollections() as $metric) {
            $couplingCalculator->calculate($metric);
        }
        $couplingCalculator->afterTraverse();
    });

    it('calculates usesForInstabilityCount and usedByCount correctly', function () {
        array_walk($this->handCalcClasses, function ($class) {
            $classMetrics = $this->metricsController->getMetricCollectionByIdentifierString($class['id']);

            expect($classMetrics->get('usesForInstabilityCount')->getValue())
                ->toBe($class['expected']['usesForInstabilityCount'])
                ->and($classMetrics->get('usedByCount')->getValue())
                ->toBe($class['expected']['usedByCount']);
        });
    });

    it('calculates instability correctly for hand-calculated scenario', function () {
        array_walk($this->handCalcClasses, function ($class) {
            $classMetrics = $this->metricsController->getMetricCollectionByIdentifierString($class['id']);

            expect($classMetrics->get('instability')->getValue())
                ->toBe($class['expected']['instability']);
        });
    });
});
