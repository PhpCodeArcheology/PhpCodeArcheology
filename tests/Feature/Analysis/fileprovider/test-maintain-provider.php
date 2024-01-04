<?php

return [
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-1.php',
        [
            'file' => [
                'halstead' => [
                    'mi' => 95.58107205108799,
                    'miWOC' => 84.13643335566397,
                    'cW' =>  11.444638695424013,
                ],
            ],
            'functions' => [
                'cycloTest' => [
                    'halstead' => [
                        'mi' => 139.45732708575824,
                        'miWOC' => 114.7697848454017,
                        'cW' => 24.68754224035654,
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
                        'mi' => 95.64358452872992,
                        'miWOC' => 95.64358452872992,
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
                                'mi' => 106.28704481137235,
                                'miWOC' => 106.28704481137235,
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
                    'mi' => 50,
                    'miWOC' => 50,
                    'cW' => 0,
                ],
            ],
            'functions' => [
                'emptyFunction' => [
                    'halstead' => [
                        'mi' => 50,
                        'miWOC' => 50,
                        'cW' => 0,
                    ],
                ],
            ],
            'classes' => [
                'emptyClass' => [
                    'halstead' => [
                        'mi' => 50,
                        'miWOC' => 50,
                        'cW' => 0,
                    ],
                    'methods' => [],
                ],
                'emptyMethodClass' => [
                    'halstead' => [
                        'mi' => 50,
                        'miWOC' => 50,
                        'cW' => 0,
                    ],
                    'methods' => [
                        'emptyMethod' => [
                            'halstead' => [
                                'mi' => 50,
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
