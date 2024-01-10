<?php

declare(strict_types=1);

namespace Test\Feature\Calculators;

use PhpCodeArch\Calculators\CouplingCalculator;
use PhpCodeArch\Metrics\ClassMetrics\ClassMetrics;
use PhpCodeArch\Metrics\Metrics;
use PhpCodeArch\Metrics\ProjectMetrics\ProjectMetrics;

beforeEach(function() {
    $this->metrics = new Metrics();

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
       $classMetrics = new ClassMetrics('', $class['name']);
       $id = (string) $classMetrics->getIdentifier();
       $classes[$id] = $classMetrics->getName();

       $class['id'] = $id;

       foreach ($class['data'] as $key => $value) {
           $classMetrics->set($key, $value);
       }

       $this->metrics->push($classMetrics);
    });

    $this->metrics->set('classes', $classes);
    $this->metrics->set('interfaces', []);

    $projectMetrics = new ProjectMetrics('');
    $this->metrics->set('project', $projectMetrics);

    $couplingCalculator = new CouplingCalculator($this->metrics);

    $couplingCalculator->beforeTraverse();

    foreach ($this->metrics->getAll() as $metric) {
        if (is_array($metric)) {
            continue;
        }

        $couplingCalculator->calculate($metric);
    }
    $couplingCalculator->afterTraverse();
});

it('calculates dependency counts correctly', function() {
    array_walk($this->classes, function($class) {
        $classMetrics = $this->metrics->get($class['id']);

        expect($classMetrics->get('usesInProjectCount'))->toBe($class['expected']['usesInProjectCount'])
            ->and($classMetrics->get('usedByCount'))->toBe($class['expected']['usedByCount']);
    });
});


it('calculates instability correctly', function() {
    array_walk($this->classes, function($class) {
        $classMetrics = $this->metrics->get($class['id']);

        expect($classMetrics->get('instability'))->toBe($class['expected']['instability']);
    });
});
