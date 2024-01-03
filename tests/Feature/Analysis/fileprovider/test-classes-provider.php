<?php

declare(strict_types=1);

return [
    [
        __DIR__ . '/../testfiles/AClass.php',
        [
            'classCount' => 1,
            'classNames' => ['AClass'],
        ],
    ],
    [
        __DIR__ . '/../testfiles/anonymous-class.php',
        [
            'classCount' => 2,
            'classNames' => ['CreateClass', 'anonymous'],
        ],
    ],
    [
        __DIR__ . '/../testfiles/ANamespacedClass.php',
        [
            'classCount' => 1,
            'classNames' => ['Testfile\ANamespacedClass'],
        ],
    ],
];
