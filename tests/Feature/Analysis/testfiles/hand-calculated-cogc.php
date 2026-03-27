<?php

/**
 * Hand-calculated Cognitive Complexity (CogC) fixture.
 *
 * === COGC FORMULA (SonarSource algorithm) ===
 * Baseline: 0 (unlike CC which starts at 1)
 *
 * Structural increments WITH nesting penalty (+1 + nestingLevel):
 *   if, for, foreach, while, do-while, catch, switch, match, ternary (?:)
 *
 * Structural increments WITHOUT nesting penalty (flat +1):
 *   elseif, else, null-coalesce (??)
 *
 * Boolean operator sequences (flat +1 per run of same type):
 *   $a && $b && $c → +1 (one && run)
 *   $a && $b || $c → +2 (one && run, one || run)
 *   $a && $b || $c && $d → +3 (two && runs, one || run)
 *
 * Nesting level increases for:
 *   if, for, foreach, while, do-while, catch, switch, match, closures, anon-classes
 * Note: elseif and else do NOT change nesting level
 *
 * ============================================================
 * FUNCTION: flatChain — CogC = 3
 * ============================================================
 * nestingLevel starts at 0
 * if ($a > 0):     +1 (1 + nesting=0)  nestingLevel → 1
 * elseif ($b > 0): +1 (flat)           nestingLevel stays 1
 * else:            +1 (flat)           nestingLevel stays 1
 * (leave if: nestingLevel → 0)
 * CogC = 1 + 1 + 1 = 3
 *
 * ============================================================
 * FUNCTION: boolSequences — CogC = 8
 * ============================================================
 * Boolean operator AST note: $a && $b && $c → &&(&&($a,$b),$c)
 * The OUTER && node: left IS &&, same type → skip
 * The INNER && node: left is $a, NOT &&  → +1
 * So any run of the same operator contributes only 1 increment.
 *
 * First if ($a && $b && $c):
 *   if:  +1 (1+nesting=0)  nestingLevel→1
 *   &&:  +1 (one && run)
 *   → subtotal: 2
 * (leave first if: nestingLevel→0)
 *
 * Second if ($a || $b || $c):
 *   if:  +1 (1+nesting=0)  nestingLevel→1
 *   ||:  +1 (one || run)
 *   → subtotal: 2
 * (leave second if: nestingLevel→0)
 *
 * Third if ($a && $b || $c && $d):
 *   AST: ||( &&($a,$b), &&($c,$d) )
 *   if:       +1 (1+nesting=0)  nestingLevel→1
 *   ||:  left is &&, not || → +1
 *   left &&:  left is $a, not && → +1
 *   right &&: left is $c, not && → +1
 *   → subtotal: 4
 * (leave third if: nestingLevel→0)
 *
 * CogC = 2 + 2 + 4 = 8
 *
 * ============================================================
 * FUNCTION: deepNested — CogC = 10
 * ============================================================
 * if ($a > 0):            +1 (1+nesting=0)  nestingLevel→1
 * foreach ($d as $x):     +2 (1+nesting=1)  nestingLevel→2
 * if ($x > $b):           +3 (1+nesting=2)  nestingLevel→3
 * while ($c > 0):         +4 (1+nesting=3)  nestingLevel→4
 * (leaves: nestingLevel → 3 → 2 → 1 → 0)
 * CogC = 1 + 2 + 3 + 4 = 10
 *
 * ============================================================
 * CLASS: SwitchAndCatch — class CogC = 4
 * ============================================================
 * METHOD: getLabel — CogC = 1
 *   switch ($code): +1 (1+nesting=0)  nestingLevel→1
 *   (case labels do NOT increment in CogC)
 *   (leave switch: nestingLevel→0)
 *   CogC = 1
 *
 * METHOD: divide — CogC = 3
 *   (try block does NOT increment)
 *   catch (\Throwable $e): +1 (1+nesting=0)  nestingLevel→1
 *   if ($a === 0):         +2 (1+nesting=1)  nestingLevel→2
 *   (leave if: nestingLevel→1)
 *   (leave catch: nestingLevel→0)
 *   CogC = 1 + 2 = 3
 *
 * Class accumulates all method increments: 1 + 3 = 4
 *
 * ============================================================
 * FILE COGC
 * ============================================================
 * flatChain:      3
 * boolSequences:  8
 * deepNested:    10
 * SwitchAndCatch: 4
 * -------
 * File CogC = 3 + 8 + 10 + 4 = 25
 */

// CogC = 3
function flatChain(int $a, int $b, int $c): string
{
    if ($a > 0) {           // +1 (1+nesting=0)
        return 'a';
    } elseif ($b > 0) {     // +1 (flat)
        return 'b';
    } else {                // +1 (flat)
        return 'c';
    }
}

// CogC = 8
function boolSequences(bool $a, bool $b, bool $c, bool $d): string
{
    if ($a && $b && $c) {       // if: +1 (nesting=0); && run: +1
        return 'all-and';
    }
    if ($a || $b || $c) {       // if: +1 (nesting=0); || run: +1
        return 'all-or';
    }
    if ($a && $b || $c && $d) { // if: +1 (nesting=0); &&: +1; ||: +1; &&: +1
        return 'mixed';
    }
    return 'none';
}

// CogC = 10
function deepNested(int $a, int $b, int $c, array $d): int
{
    if ($a > 0) {                 // +1 (nesting=0)
        foreach ($d as $x) {     // +2 (nesting=1)
            if ($x > $b) {       // +3 (nesting=2)
                while ($c > 0) { // +4 (nesting=3)
                    $c--;
                }
            }
        }
    }
    return $a;
}

class SwitchAndCatch
{
    // CogC = 1
    public function getLabel(int $code): string
    {
        switch ($code) {    // +1 (nesting=0)
            case 1:
                return 'one';
            case 2:
                return 'two';
            default:
                return 'other';
        }
    }

    // CogC = 3
    public function divide(int $a, int $b): float
    {
        try {
            return $a / $b;
        } catch (\Throwable $e) {   // +1 (nesting=0)
            if ($a === 0) {         // +2 (nesting=1)
                return 0.0;
            }
        }
        return 0.0;
    }
}
