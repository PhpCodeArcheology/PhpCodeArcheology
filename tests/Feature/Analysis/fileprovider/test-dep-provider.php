<?php

return [
    [
        __DIR__ . '/../testfiles/dependencies-1.php',
        [
            'file' => [
                'dependencyCount' => 1,
                'dependencies' => [
                    \Testfile\ClassWithStaticMethod::class,
                ],
            ],
            'functions' => [
                'Testfile\testFunction1' => [
                    'dependencyCount' => 1,

                ],
                'Testfile\testFunction2' => [
                    'dependencyCount' => 1,

                ],
            ],
            'classes' => [
                \Testfile\FooClass::class => [
                    'dependencyCount' => 2,
                    'dependencies' => [
                        \PhpParser\NodeTraverser::class,
                        \Testfile\FooInterface::class,
                    ],
                    'interfaces' => [
                        \Testfile\FooInterface::class,
                    ],
                    'extends' => [],
                    'methods' => [
                        '__construct' => [
                            'dependencyCount' => 1,
                            'dependencies' => [
                                \PhpParser\NodeTraverser::class,
                            ],
                        ],
                        'testMethod1' => [
                            'dependencyCount' => 0,
                            'dependencies' => [],
                        ],
                    ],
                ],
                \Testfile\BarClass::class => [
                    'dependencyCount' => 2,
                    'dependencies' => [
                        \Testfile\ClassWithStaticMethod::class,
                        \Testfile\FooClass::class,
                    ],
                    'interfaces' => [],
                    'extends' => [
                        \Testfile\FooClass::class,
                    ],
                    'methods' => [
                        'testMethod3' => [
                            'dependencyCount' => 1,
                            'dependencies' => [
                                \Testfile\ClassWithStaticMethod::class,
                            ],
                        ],
                    ],
                ],
                \Testfile\ClassWithStaticMethod::class => [
                    'dependencyCount' => 0,
                    'dependencies' => [],
                    'interfaces' => [],
                    'extends' => [],
                    'methods' => [],
                ],
            ],
        ],
    ],
    [
        __DIR__ . '/../testfiles/dependencies-2.php',
        [
            'file' => [
                'dependencyCount' => 0,
                'dependencies' => [
                ],
            ],
            'classes' => [
                \Testfile\Creator::class => [
                    'dependencyCount' => 0,
                    'dependencies' => [
                    ],
                    'interfaces' => [
                    ],
                    'extends' => [],
                    'methods' => [
                        'create' => [
                            'dependencyCount' => 0,
                            'dependencies' => [],
                        ],
                    ],
                ],
                'anonymous' => [
                    'dependencyCount' => 2,
                    'dependencies' => [
                        'PhpParser\NodeTraverser',
                        'Testfile\AnonClassInterface',
                    ],
                    'interfaces' => [
                        'Testfile\AnonClassInterface',
                    ],
                    'extends' => [],
                    'methods' => [
                        'testMethod1' => [
                            'dependencyCount' => 1,
                            'dependencies' => [
                                'PhpParser\NodeTraverser',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    [
        __DIR__ . '/../testfiles/dependencies-3.php',
        [
            'file' => [
                'dependencyCount' => 0,
                'dependencies' => [
                ],
            ],
            'functions' => [
                'Testfile\create' => [
                    'dependencyCount' => 0,
                    'dependencies' => [
                    ],
                ],
            ],
            'classes' => [
                'anonymous' => [
                    'dependencyCount' => 2,
                    'dependencies' => [
                        \PhpParser\NodeTraverser::class,
                        \Testfile\TheGreatExtender::class,
                    ],
                    'interfaces' => [
                    ],
                    'extends' => [
                        \Testfile\TheGreatExtender::class,
                    ],
                    'methods' => [
                        'testMethod1' => [
                            'dependencyCount' => 1,
                            'dependencies' => [
                                \PhpParser\NodeTraverser::class,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
