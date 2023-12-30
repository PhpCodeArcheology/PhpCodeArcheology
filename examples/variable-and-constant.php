<?php

declare(strict_types=1);

define('BUG', 1);

$x = 1;

if (BUG === 1) {
    echo "Bug";
}

echo $x;


