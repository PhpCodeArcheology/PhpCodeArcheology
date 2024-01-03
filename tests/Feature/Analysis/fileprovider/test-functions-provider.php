<?php

declare(strict_types=1);

return [
    [
        __DIR__ . '/../testfiles/two-functions.php',
        [
            'functionCount' => 2,
            'functionNames' => ['f1', 'f2'],
        ],
    ],
    [
        __DIR__ . '/../testfiles/nested-functions.php',
        [
            'functionCount' => 2,
            'functionNames' => ['outerFunction', 'innerFunction'],
        ],
    ],
    [
        __DIR__ . '/../testfiles/namespaced-function.php',
        [
            'functionCount' => 1,
            'functionNames' => ['Testfile\myFunction'],
        ],
    ],
];
