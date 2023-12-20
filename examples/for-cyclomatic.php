<?php

$x = 0;
$y = 2;

if ($x === 0) {
    $y = 1;
}

if ($y === 2) {
    $x = 3;
}
else {
    $x = 4;
}

function test1($x) {
    $y = 0;

    switch ($x) {
        case 1:
            $y = 4;
            break;

        case 2:
            $y = 5;
            break;

        default:
            $y =3;
            break;
    }

    $i = true;
    while ($i === true) {
        if ($y === 4) {
            $i = false;
        }
    }
}

class test
{
    function testMethod($x)
    {
        $y = 0;

        if ($x === 0) {
            $y = 1;
        }
    }
}