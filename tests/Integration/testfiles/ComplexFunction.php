<?php

declare(strict_types=1);

/**
 * Deliberately complex function fixture for integration tests.
 *
 * CC calculation (baseline = 1):
 *   if ($a < 0)                  +1
 *   elseif ($a === 0)            +1
 *   elseif ($a < 10)             +1
 *   elseif ($a < 100)            +1
 *   foreach ($items as $item)    +1
 *   if ($item > 0)               +1
 *   elseif ($item < 0)           +1
 *   for ($i = 0 ...)             +1
 *   if ($flag)                   +1
 *   while ($b > 0)               +1
 *   ternary (? :)                +1
 * --------------------------------
 * Total CC = 1 + 11 = 12
 *
 * With LLOC <= 20, threshold is 10 → CC 12 > 10 → TooComplex triggered.
 */
function complexDecision(int $a, int $b, array $items, bool $flag, string $mode): string
{
    if ($a < 0) {
        return 'negative';
    } elseif (0 === $a) {
        return 'zero';
    } elseif ($a < 10) {
        return 'small';
    } elseif ($a < 100) {
        $result = '';
        foreach ($items as $item) {
            if ($item > 0) {
                $result .= 'pos';
            } elseif ($item < 0) {
                $result .= 'neg';
            }
        }

        return $result;
    }

    for ($i = 0; $i < $b; ++$i) {
        if ($flag) {
            continue;
        }
    }

    while ($b > 0) {
        --$b;
    }

    return 'A' === $mode ? 'mode-a' : 'mode-b';
}
