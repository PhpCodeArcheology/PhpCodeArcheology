<?php

return [
    [
        __DIR__ . '/../testfiles/loc.php',
        [
            'loc' => 33,
            'lloc' => 20,
            'cloc' => 4,
            'file' => [
                'loc' => 33,
                'lloc' => 20,
                'cloc' => 4,
                'llocOutside' => 2,
                'htmlLoc' => 0,
            ],
            'functions' => [
                'testFunction' => [
                    'loc' => 3,
                    'lloc' => 1,
                    'cloc' => 0,
               ]
            ],
            'classes' => [
                'testClass' => [
                    'data' => [
                        'loc' => 20,
                        'lloc' => 14,
                        'cloc' => 1,
                    ],
                    'methods' => [
                        'testMethod1' => [
                            'loc' => 5,
                            'lloc' => 1,
                            'cloc' => 1,
                        ],
                        'testMethod2' => [
                            'loc' => 7,
                            'lloc' => 1,
                            'cloc' => 0,
                        ],
                        'testMethod3' => [
                            'loc' => 3,
                            'lloc' => 0,
                            'cloc' => 0,
                        ],
                    ],
                ],
            ],
        ]
    ],
    [
        __DIR__ . '/../testfiles/php-file-with-html.php',
        [
            'loc' => 14,
            'lloc' => 5,
            'cloc' => 0,
            'file' => [
                'loc' => 14,
                'lloc' => 5,
                'cloc' => 0,
                'llocOutside' => 2,
                'htmlLoc' => 3,
            ],
            'functions' => [],
            'classes' => [],
        ],
    ],
    [
        __DIR__ . '/../testfiles/AnonymousClassWithMethods.php',
        [
            'loc' => 24,
            'lloc' => 19,
            'cloc' => 1,
            'file' => [
                'loc' => 24,
                'lloc' => 19,
                'cloc' => 1,
                'llocOutside' => -11,
                'htmlLoc' => 0,
            ],
            'functions' => [],
            'classes' => [
                'AnonymousClassWithMethods' => [
                    'data' => [
                        'loc' => 20,
                        'lloc' => 18,
                        'cloc' => 1,
                    ],
                    'methods' => [
                        'testMethod1' => [
                            'loc' => 17,
                            'lloc' => 12,
                            'cloc' => 1,
                        ],
                    ],
                ],
                'anonymous' => [
                    'data' => [
                        'loc' => 14,
                        'lloc' => 12,
                        'cloc' => 1,
                    ],
                    'methods' => [
                        'testMethod2' => [
                            'loc' => 4,
                            'lloc' => 1,
                            'cloc' => 0,
                        ],
                        'testMethod3' => [
                            'loc' => 7,
                            'lloc' => 2,
                            'cloc' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
