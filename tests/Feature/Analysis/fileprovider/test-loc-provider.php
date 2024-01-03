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
                [
                    'name' => 'testFunction',
                    'loc' => 3,
                    'lloc' => 1,
                    'cloc' => 0,
               ]
            ],
            'classes' => [
                [
                    'name' => 'testClass',
                    'loc' => 20,
                    'lloc' => 14,
                    'cloc' => 1,
                    'methods' => [
                        [
                            'name' => 'testMethod1',
                            'loc' => 5,
                            'lloc' => 1,
                        ],
                        [
                            'name' => 'testMethod2',
                            'loc' => 7,
                            'lloc' => 1,
                        ],
                        [
                            'name' => 'testMethod3',
                            'loc' => 3,
                            'lloc' => 0,
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
];
