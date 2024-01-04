<?php

declare(strict_types=1);

function cycloTest($x) {
    // Comment
    if ($x === 3) {
        return 4;
    }
    else {
        return 2;
    }
}

switch (cycloTest(2)) {
    case 4:
        echo "x";
        break;

    case 2:
        echo "y";
        break;
}

class Traverser
{
    public function testMethod1($arr)
    {
        foreach ($arr as $a) {
            if ($a >= 4) {
                continue;
            }
        }
    }

    public function testMethod2($c) {
        switch ($c) {
            case 'a':
                return 'z';

            case 'b':
                return 'y';
        }
    }
}
