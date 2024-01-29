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
use PhpCodeArch\Metrics\Model\MetricType;
use PhpCodeArch\Metrics\Model\MetricValue;

beforeEach(function() {
    return;
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


    array_walk($this->classes, function(&$class) use (&$classes) {
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
            $metricType = MetricType::fromKey($key);
            $metricValue = MetricValue::ofValueAndType($value, $metricType);
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

    $packageIACalc = new PackageInstabilityAbstractnessCalculator($this->metricsController);
    $couplingCalculator = new CouplingCalculator($this->metricsController, $packageIACalc);

    $couplingCalculator->beforeTraverse();

    foreach ($this->metricsController->getAllCollections() as $metric) {
        $couplingCalculator->calculate($metric);
    }
    $couplingCalculator->afterTraverse();
});

it('calculates dependency counts correctly', function() {
    array_walk($this->classes, function($class) {
        $classMetrics = $this->metricsController->getMetricCollectionByIdentifierString($class['id']);

        expect($classMetrics->get('usesInProjectCount')->getValue())->toBe($class['expected']['usesInProjectCount'])
            ->and($classMetrics->get('usedByCount')->getValue())->toBe($class['expected']['usedByCount']);
    });
})->skip();


it('calculates instability correctly', function() {
    array_walk($this->classes, function($class) {
        $classMetrics = $this->metricsController->getMetricCollectionByIdentifierString($class['id']);

        expect($classMetrics->get('instability')->getValue())->toBe($class['expected']['instability']);
    });
})->skip();
