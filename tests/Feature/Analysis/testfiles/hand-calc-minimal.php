<?php

/**
 * Minimal hand-calculated fixture for testing all metrics.
 * Every value is manually calculated and verified.
 *
 * CC Calculations:
 * - function minimalIf: baseline 1 + if(+1) = 2
 * - File: 2
 *
 * CogC Calculations:
 * - function minimalIf: if(+1, nesting 0) = 1
 * - File: 1
 *
 * Halstead Calculations (operators/operands/unique):
 * - $x: variable operand
 * - =: assign operator
 * - $x > 0: comparison operator, variable operand, number operand
 * - if: if operator
 * - return: return operator
 * - true/false: scalar operands
 * See detailed counting below.
 */

function minimalIf($x) {
    if ($x > 0) {
        return true;
    }
    return false;
}
