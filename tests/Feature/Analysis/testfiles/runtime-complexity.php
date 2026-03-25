<?php

function noLoops(): void
{
    $a = 1 + 2;
}

function singleLoop(array $items): void
{
    foreach ($items as $item) {
        echo $item;
    }
}

function nestedLoops(array $matrix): void
{
    foreach ($matrix as $row) {
        foreach ($row as $cell) {
            echo $cell;
        }
    }
}

function tripleNestedLoops(array $cube): void
{
    for ($i = 0; $i < 3; $i++) {
        for ($j = 0; $j < 3; $j++) {
            for ($k = 0; $k < 3; $k++) {
                echo $cube[$i][$j][$k];
            }
        }
    }
}

class LoopClass
{
    public function noLoop(): void
    {
        $x = 1;
    }

    public function withLoop(array $items): void
    {
        while (count($items) > 0) {
            array_pop($items);
        }
    }

    public function withNestedLoop(array $items): void
    {
        foreach ($items as $item) {
            do {
                $item--;
            } while ($item > 0);
        }
    }
}
