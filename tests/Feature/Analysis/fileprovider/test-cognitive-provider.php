<?php

/**
 * Cognitive Complexity test provider with hand-calculated values.
 *
 * Every value is verified manually against the CognitiveComplexityVisitor logic.
 */

return [
    [
        __DIR__ . '/../testfiles/cognitive-complexity-1.php',
        [
            'functions' => [
                'simpleFunction' => ['cogc' => 1],
                'nestedFunction' => ['cogc' => 3],
                'booleanSequence' => ['cogc' => 2],
                'mixedBoolean' => ['cogc' => 3],
                'loopWithNesting' => ['cogc' => 4],
            ],
            'classes' => [
                'CognitiveTestClass' => [
                    'cogc' => 10,
                    'methods' => [
                        'deeplyNested' => ['cogc' => 6],
                        'simpleSwitch' => ['cogc' => 1],
                        'tryCatch' => ['cogc' => 3],
                    ],
                ],
            ],
            'file' => ['cogc' => 23],
        ],
    ],

    [
        __DIR__ . '/../testfiles/hand-calc-minimal.php',
        [
            'functions' => [
                'minimalIf' => ['cogc' => 1],
            ],
            'classes' => [],
            'file' => ['cogc' => 1],
        ],
    ],
];
