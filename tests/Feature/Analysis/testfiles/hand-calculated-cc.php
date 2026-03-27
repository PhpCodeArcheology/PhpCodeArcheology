<?php

/**
 * Hand-calculated Cyclomatic Complexity (CC) fixture.
 *
 * === CC FORMULA ===
 * CC = 1 (baseline per function/class/file) + number of decision points
 *
 * What counts as +1:
 *   if, elseif, for, foreach, while, do-while, catch,
 *   ternary (?:), null-coalesce (??),
 *   non-default case labels, match expression (whole), spaceship (<=>)
 *
 * What does NOT count:
 *   else, switch (itself), default
 *
 * ============================================================
 * FUNCTION: complexFunction
 * ============================================================
 * Baseline:              +1
 * if ($a > 0):           +1  [Node\Stmt\If_]
 * foreach ($b as $item): +1  [Node\Stmt\Foreach_]
 * if ($item > 5):        +1  [Node\Stmt\If_]
 * $c ? 'yes' : 'no':    +1  [Node\Expr\Ternary]
 * ------------------------------------------------
 * CC = 1 + 4 = 5
 *
 * ============================================================
 * CLASS: SimpleCalc
 * ============================================================
 * Class baseline:        +1
 * while ($n > 0):        +1  [Node\Stmt\While_]  (from method loop)
 * if ($n > $limit):      +1  [Node\Stmt\If_]      (from method loop)
 * ------------------------------------------------
 * Class CC = 1 + 2 = 3
 *
 * METHOD: SimpleCalc::add
 *   Baseline: +1, no branches
 *   CC = 1
 *
 * METHOD: SimpleCalc::loop
 *   Baseline:          +1
 *   while ($n > 0):    +1
 *   if ($n > $limit):  +1
 *   CC = 1 + 2 = 3
 *
 * ============================================================
 * FILE CC
 * ============================================================
 * Baseline:                        +1
 * complexFunction branches:        +4  (if + foreach + if + ternary)
 * SimpleCalc method branches:      +2  (while + if from loop)
 * ------------------------------------------------
 * File CC = 1 + 4 + 2 = 7
 */

// CC = 5
function complexFunction(int $a, array $b, bool $c): string
{
    if ($a > 0) {               // +1
        foreach ($b as $item) { // +1
            if ($item > 5) {    // +1
                return 'big';
            }
        }
    }
    return $c ? 'yes' : 'no';  // +1 (ternary)
}

class SimpleCalc
{
    // CC = 1 (baseline only, no branches)
    public function add(int $a, int $b): int
    {
        return $a + $b;
    }

    // CC = 3 (baseline + while + if)
    public function loop(int $n, int $limit): int
    {
        $result = 0;
        while ($n > 0) {         // +1
            if ($n > $limit) {   // +1
                $result += $n;
            }
            $n--;
        }
        return $result;
    }
}
