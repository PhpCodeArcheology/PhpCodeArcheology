<?php

return [
    [
        __DIR__ . '/../testfiles/cyclomatic-complexity-1.php',
        [
            'file' => [
                'counted' => [
                    'operators' => 13,
                    'operands' => 25,
                    'uniqueOperators' => 8,
                    'uniqueOperands' => 12,
                ],
            ],
            'functions' => [
                'cycloTest' => [
                    'counted' => [
                        'operators' => 5,
                        'operands' => 6,
                        'uniqueOperators' => 4,
                        'uniqueOperands' => 5,
                    ],
                ],
            ],
            'classes' => [
                'Traverser' => [
                    'counted' => [
                        'operators' => 6,
                        'operands' => 13,
                        'uniqueOperators' => 5,
                        'uniqueOperands' => 8,
                    ],
                    'methods' => [
                        'testMethod1' => [
                            'counted' => [
                                'operators' => 3,
                                'operands' => 6,
                                'uniqueOperators' => 3,
                                'uniqueOperands' => 4,
                            ],
                        ],
                        'testMethod2' => [
                            'counted' => [
                                'operators' => 3,
                                'operands' => 7,
                                'uniqueOperators' => 2,
                                'uniqueOperands' => 6,
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
                'counted' => [
                    'operators' => 12,
                    'operands' => 19,
                    'uniqueOperators' => 8,
                    'uniqueOperands' => 9,
                ],
            ],
            'functions' => [],
            'classes' => [
                'Creator' => [
                    'counted' => [
                        'operators' => 6,
                        'operands' => 7,
                        'uniqueOperators' => 3,
                        'uniqueOperands' => 6,
                    ],
                    'methods' => [
                        'testMethod1' => [
                            'counted' => [
                                'operators' => 3,
                                'operands' => 6,
                                'uniqueOperators' => 3,
                                'uniqueOperands' => 4,
                            ],
                        ],
                        'testMethod2' => [
                            'counted' => [
                                'operators' => 6,
                                'operands' => 7,
                                'uniqueOperators' => 3,
                                'uniqueOperands' => 6,
                            ],
                        ],
                    ],
                ],
                'anonymous' => [
                    'counted' => [
                        'operators' => 4,
                        'operands' => 7,
                        'uniqueOperators' => 2,
                        'uniqueOperands' => 6,
                    ],
                    'methods' => [
                        'testMethod3' => [
                            'counted' => [
                                'operators' => 4,
                                'operands' => 7,
                                'uniqueOperators' => 2,
                                'uniqueOperands' => 6,
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
                'counted' => [
                    'operators' => 0,
                    'operands' => 0,
                    'uniqueOperators' => 0,
                    'uniqueOperands' => 0,
                ],
            ],
            'functions' => [
                'emptyFunction' => [
                    'counted' => [
                        'operators' => 0,
                        'operands' => 0,
                        'uniqueOperators' => 0,
                        'uniqueOperands' => 0,
                    ],
                ],
            ],
            'classes' => [
                'emptyClass' => [
                    'counted' => [
                        'operators' => 0,
                        'operands' => 0,
                        'uniqueOperators' => 0,
                        'uniqueOperands' => 0,
                    ],
                    'methods' => [],
                ],
                'emptyMethodClass' => [
                    'counted' => [
                        'operators' => 0,
                        'operands' => 0,
                        'uniqueOperators' => 0,
                        'uniqueOperands' => 0,
                    ],
                    'methods' => [
                        'emptyMethod' => [
                            'counted' => [
                                'operators' => 0,
                                'operands' => 0,
                                'uniqueOperators' => 0,
                                'uniqueOperands' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];