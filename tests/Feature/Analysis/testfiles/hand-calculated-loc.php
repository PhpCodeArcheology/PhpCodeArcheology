<?php

function pureLogic(int $n): int
{
    $a = $n * 2;
    $b = $a + 1;
    return $b;
}

class PureCalc
{
    public function multiply(int $a, int $b): int
    {
        return $a * $b;
    }
}
