<?php

declare(strict_types=1);

function arrayTest(array $x) {
    $x = array_map(function($item) {
        if ($item['x'] === 3) {
            $item['s'] = 'foo';
        }

        return isset($item['test']);
    }, $x);
}
