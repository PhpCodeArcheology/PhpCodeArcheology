<?php

declare(strict_types=1);

return [
    [
        __DIR__ . '/../testfiles/globals.php',
        [
            'file' => [
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
