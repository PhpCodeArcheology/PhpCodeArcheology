<?php

declare(strict_types=1);

function createMethodNames(int $count): array
{
    return array_map(function($i) {
        return 'testMethod' . $i;
    }, range(1, $count));
}

return [
    [
        __DIR__ . '/../testfiles/ClassWithMethods.php',
        [
            'classCount' => 1,
            'methodCount' => 7,
            'methodNames' => createMethodNames(7),
            'publicMethods' => 4,
            'privateMethods' => 3,
            'staticMethods' => 2,
        ],
    ],
    [
        __DIR__ . '/../testfiles/AnonymousClassWithMethods.php',
        [
            'classCount' => 2,
            'methodCount' => 3,
            'methodNames' => createMethodNames(3),
            'publicMethods' => 2,
            'privateMethods' => 1,
            'staticMethods' => null,
            'methodCountAnonymousClass' => 2,
            'methodNamesAnonymousClass' => ['testMethod2', 'testMethod3'],
        ],
    ],
];