<?php

return [
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-1.php',
        [
            'file' => [
                'halstead' => [
                    'mi' => 93.64149287270132,
                    'miWOC' => 82.43875275648026,
                    'cW' => 11.202740116221063,
                ],
            ],
            'functions' => [
                'cycloTest' => [
                    'halstead' => [
                        'mi' => 132.69245909953924,
                        'miWOC' => 110.17754555249519,
                        'cW' => 22.514913547044053,
                    ],
                ],
            ],
            'classes' => [
                'Traverser' => [
                    'halstead' => [
                        'mi' => 99.20410586904502,
                        'miWOC' => 99.20410586904502,
                        'cW' => 0.0,
                    ],
                    'methods' => [
                        'testMethod1' => [
                            'halstead' => [
                                'mi' => 127.87304080932475,
                                'miWOC' => 127.87304080932475,
                                'cW' => 0.0,
                            ],
                        ],
                        'testMethod2' => [
                            'halstead' => [
                                'mi' => 124.92958495221083,
                                'miWOC' => 124.92958495221083,
                                'cW' => 0.0,
                            ],
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
                'halstead' => [
                    'mi' => 89.34266183789683,
                    'miWOC' => 89.34266183789683,
                    'cW' => 0.0,
                ],
            ],
            'functions' => [],
            'classes' => [
                'Creator' => [
                    'halstead' => [
                        'mi' => 92.5988459868775,
                        'miWOC' => 92.5988459868775,
                        'cW' => 0.0,
                    ],
                    'methods' => [
                        'testMethod1' => [
                            'halstead' => [
                                'mi' => 127.87304080932475,
                                'miWOC' => 127.87304080932475,
                                'cW' => 0.0,
                            ],
                        ],
                        'testMethod2' => [
                            'halstead' => [
                                'mi' => 107.44222338008983,
                                'miWOC' => 107.44222338008983,
                                'cW' => 0.0,
                            ],
                        ],
                    ],
                ],
                'anonymous' => [
                    'halstead' => [
                        'mi' =>  110.57598108949739,
                        'miWOC' => 110.57598108949739,
                        'cW' => 0.0,
                    ],
                    'methods' => [
                        'testMethod3' => [
                            'halstead' => [
                                'mi' => 121.93673100402677,
                                'miWOC' => 121.93673100402677,
                                'cW' => 0.0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],

    [
        __DIR__ . '/../testfiles/empty.php',
        [
            'file' => [
                'halstead' => [
                    'mi' => 171,
                    'miWOC' => 50,
                    'cW' => 0,
                ],
            ],
            'functions' => [
                'emptyFunction' => [
                    'halstead' => [
                        'mi' => 171,
                        'miWOC' => 50,
                        'cW' => 0,
                    ],
                ],
            ],
            'classes' => [
                'emptyClass' => [
                    'halstead' => [
                        'mi' => 171,
                        'miWOC' => 50,
                        'cW' => 0,
                    ],
                    'methods' => [],
                ],
                'emptyMethodClass' => [
                    'halstead' => [
                        'mi' => 171,
                        'miWOC' => 50,
                        'cW' => 0,
                    ],
                    'methods' => [
                        'emptyMethod' => [
                            'halstead' => [
                                'mi' => 171,
                                'miWOC' => 50,
                                'cW' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
