<?php

return [
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-1.php',
        [
            'file' => [
                'cc' => 8,
            ],
            'function' => [
                'cycloTest' => [
                    'cc' => 2,
                ],
            ],
            'classes' => [
                'Traverser' => [
                    'cc' => 5,
                    'methods' => [
                        'testMethod1' => [
                            'cc' => 3,
                        ],
                        'testMethod2' => [
                            'cc' => 3,
                        ],
                    ],
                ],
            ],
        ],
    ],
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-2.php',
        [
            'file' => [
                'cc' => 6,
            ],
            'function' => [
            ],
            'classes' => [
                'Creator' => [
                    'cc' => 4,
                    'methods' => [
                        'testMethod1' => [
                            'cc' => 3,
                        ],
                        'testMethod2' => [
                            'cc' => 2,
                        ],
                    ],
                ],
                'anonymous' => [
                    'cc' => 3,
                    'methods' => [
                        'testMethod3' => [
                            'cc' => 3,
                        ],
                    ],
                ],
            ],
        ],
    ],
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-3.php',
        [
            'file' => [
                'cc' => 2,
            ],
            'function' => [
                'arrayTest' => [
                    'cc' => 2,
                ],
            ],
            'classes' => [],
        ],
    ],
];
