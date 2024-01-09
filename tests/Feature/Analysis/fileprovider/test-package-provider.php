<?php

return [
    [
        [
            __DIR__ . '/../testfiles/package-test-1.php',
            __DIR__ . '/../testfiles/package-test-2.php',
            __DIR__ . '/../testfiles/package-test-3.php',
            __DIR__ . '/../testfiles/package-test-4.php',
            __DIR__ . '/../testfiles/package-test-5.php',
            __DIR__ . '/../testfiles/package-test-6.php',
        ],
        [
            'fileNamespaces' => [
                __DIR__ . '/../testfiles/package-test-1.php' => 'global',
                __DIR__ . '/../testfiles/package-test-2.php' => 'TestFile',
                __DIR__ . '/../testfiles/package-test-3.php' => 'TestFile\SubPackage1',
                __DIR__ . '/../testfiles/package-test-4.php' => 'TestFile\SubPackage2',
                __DIR__ . '/../testfiles/package-test-5.php' => 'TestFile\SubPackage1\SubSubPackage',
                __DIR__ . '/../testfiles/package-test-6.php' => 'TestFile\SubPackage1\SubSubPackage',
            ],
            'packageMetrics' => [
                'global' => [
                    'fileCount' => 1,
                    'functionCount' => 2,
                    'classCount' => 0,
                ],
                'TestFile' => [
                    'fileCount' => 1,
                    'functionCount' => 2,
                    'classCount' => 0,
                ],
                'TestFile\SubPackage1' => [
                    'fileCount' => 1,
                    'functionCount' => 0,
                    'classCount' => 1,
                ],
                'TestFile\SubPackage1\SubSubPackage' => [
                    'fileCount' => 2,
                    'functionCount' => 0,
                    'classCount' => 3,
                ],
                'TestFile\SubPackage2' => [
                    'fileCount' => 1,
                    'functionCount' => 0,
                    'classCount' => 0,
                ],
            ],
        ],
    ],
];
