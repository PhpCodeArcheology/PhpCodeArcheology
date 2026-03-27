<?php

return [
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-1.php',
        [
            'file' => [
                'halstead' => [
                    'mi' => 93.87149287270134,
                    'miWOC' => 82.66875275648027,
                    'cW' => 11.202740116221063,
                ],
            ],
            'functions' => [
                'cycloTest' => [
                    'halstead' => [
                        'mi' => 132.92245909953922,
                        'miWOC' => 110.40754555249518,
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
                                'mi' => 127.44387581438755,
                                'miWOC' => 127.44387581438755,
                                'cW' => 0.0,
                            ],
                        ],
                        'testMethod2' => [
                            'halstead' => [
                                'mi' => 123.59727021386229,
                                'miWOC' => 123.59727021386229,
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
                                'mi' => 127.44387581438755,
                                'miWOC' => 127.44387581438755,
                                'cW' => 0.0,
                            ],
                        ],
                        'testMethod2' => [
                            'halstead' => [
                                'mi' => 107.15572605162079,
                                'miWOC' => 107.15572605162079,
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
                                'mi' => 120.60441626567822,
                                'miWOC' => 120.60441626567822,
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
                    'miWOC' => 171,
                    'cW' => 0,
                ],
            ],
            'functions' => [
                'emptyFunction' => [
                    'halstead' => [
                        'mi' => 171,
                        'miWOC' => 171,
                        'cW' => 0,
                    ],
                ],
            ],
            'classes' => [
                'emptyClass' => [
                    'halstead' => [
                        'mi' => 171,
                        'miWOC' => 171,
                        'cW' => 0,
                    ],
                    'methods' => [],
                ],
                'emptyMethodClass' => [
                    'halstead' => [
                        'mi' => 171,
                        'miWOC' => 171,
                        'cW' => 0,
                    ],
                    'methods' => [
                        'emptyMethod' => [
                            'halstead' => [
                                'mi' => 171,
                                'miWOC' => 171,
                                'cW' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
