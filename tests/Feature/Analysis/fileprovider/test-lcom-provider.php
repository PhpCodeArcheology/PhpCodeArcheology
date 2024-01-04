<?php

return [
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-1.php',
        [
            'classes' => [
                'Traverser' => [
                    'lcom' => 2,
                ],
            ],
        ],
    ],

    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-2.php',
        [
            'classes' => [
                'Creator' => [
                    'lcom' => 0,
                ],
                'anonymous' => [
                    'lcom' => 7,
                ],
            ],
        ],
    ],

    [
        __DIR__ . '/../testfiles/empty.php',
        [
            'classes' => [
                'emptyClass' => [
                    'lcom' => 0,
                ],
                'emptyMethodClass' => [
                    'lcom' => 1,
                ],
            ],
        ],
    ],
];
