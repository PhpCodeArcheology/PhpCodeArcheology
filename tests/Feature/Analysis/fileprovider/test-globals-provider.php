<?php

declare(strict_types=1);

return [
    [
        __DIR__ . '/../testfiles/globals.php',
        [
            'file' => [
                'constants' => [
                    'TEST_CONSTANT_1' => 4,
                ],
                'constantsSum' => 4,
                'superglobals' => [
                    'GLOBALS' => 1,
                    '_SERVER' => 1,
                    '_GET' => 1,
                    '_POST' => 1,
                    '_FILES' => 2,
                    '_COOKIE' => 1,
                    '_SESSION' => 2,
                    '_REQUEST' => 2,
                    '_ENV' => 1,
                ],
                'superglobalsSum' => 12,
            ],
            'functions' => [
                'testRequest' => [
                    'constants' => [
                        'TEST_CONSTANT_1' => 1,
                    ],
                    'constantsSum' => 1,
                    'superglobals' => [
                        'GLOBALS' => 1,
                        '_SERVER' => 0,
                        '_GET' => 1,
                        '_POST' => 0,
                        '_FILES' => 0,
                        '_COOKIE' => 0,
                        '_SESSION' => 0,
                        '_REQUEST' => 2,
                        '_ENV' => 0,
                    ],
                    'superglobalsSum' => 4,
                ],
            ],
            'classes' => [
                'ServerStats' => [
                    'constants' => [
                        'TEST_CONSTANT_1' => 1,
                    ],
                    'constantsSum' => 1,
                    'superglobals' => [
                        'GLOBALS' => 0,
                        '_SERVER' => 1,
                        '_GET' => 0,
                        '_POST' => 0,
                        '_FILES' => 1,
                        '_COOKIE' => 1,
                        '_SESSION' => 1,
                        '_REQUEST' => 0,
                        '_ENV' => 1,
                    ],
                    'superglobalsSum' => 5,
                    'methods' => [
                        'parseData' => [
                            'constants' => [
                                'TEST_CONSTANT_1' => 1,
                            ],
                            'constantsSum' => 1,
                            'superglobals' => [
                                'GLOBALS' => 0,
                                '_SERVER' => 1,
                                '_GET' => 0,
                                '_POST' => 0,
                                '_FILES' => 0,
                                '_COOKIE' => 0,
                                '_SESSION' => 0,
                                '_REQUEST' => 0,
                                '_ENV' => 0,
                            ],
                            'superglobalsSum' => 1,
                        ],
                        'createTesterClass' => [
                            'constants' => [],
                            'constantsSum' => 0,
                            'superglobals' => [
                                'GLOBALS' => 0,
                                '_SERVER' => 0,
                                '_GET' => 0,
                                '_POST' => 0,
                                '_FILES' => 1,
                                '_COOKIE' => 1,
                                '_SESSION' => 1,
                                '_REQUEST' => 0,
                                '_ENV' => 0,
                            ],
                            'superglobalsSum' => 3,
                        ],
                        'reactToEnv' => [
                            'constants' => [],
                            'constantsSum' => 0,
                            'superglobals' => [
                                'GLOBALS' => 0,
                                '_SERVER' => 0,
                                '_GET' => 0,
                                '_POST' => 0,
                                '_FILES' => 0,
                                '_COOKIE' => 0,
                                '_SESSION' => 0,
                                '_REQUEST' => 0,
                                '_ENV' => 1,
                            ],
                            'superglobalsSum' => 1,
                        ],
                    ],
                ],
                'anonymous@000000000000050d0000000000000000' => [
                    'constants' => [],
                    'constantsSum' => 0,
                    'superglobals' => [
                        'GLOBALS' => 0,
                        '_SERVER' => 0,
                        '_GET' => 0,
                        '_POST' => 0,
                        '_FILES' => 1,
                        '_COOKIE' => 0,
                        '_SESSION' => 1,
                        '_REQUEST' => 0,
                        '_ENV' => 0,
                    ],
                    'superglobalsSum' => 2,
                    'methods' => [
                        'test' => [
                            'constants' => [],
                            'constantsSum' => 0,
                            'superglobals' => [
                                'GLOBALS' => 0,
                                '_SERVER' => 0,
                                '_GET' => 0,
                                '_POST' => 0,
                                '_FILES' => 1,
                                '_COOKIE' => 0,
                                '_SESSION' => 1,
                                '_REQUEST' => 0,
                                '_ENV' => 0,
                            ],
                            'superglobalsSum' => 2,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
