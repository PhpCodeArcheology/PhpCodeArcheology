<?php

/**
 * Hand-calculated fixture for Halstead Metrics testing.
 *
 * Code: function add($a, $b) — simple addition with assignment and return.
 *
 * =========================================================================
 * OPERATOR COUNTING (HalsteadMetricsVisitor::countOperators)
 * =========================================================================
 *
 * Operators are recorded as fully-qualified class names.
 * Only nodes matching the explicit switch cases in countOperators() count.
 *
 * Traversal within function add():
 *
 *   Node                 | Class                                  | Count
 *   ---------------------|----------------------------------------|------
 *   $a + $b              | PhpParser\Node\Expr\BinaryOp\Plus      |  (1)
 *   $sum = $a + $b       | PhpParser\Node\Expr\Assign             |  (2)
 *   return $sum          | PhpParser\Node\Stmt\Return_            |  (3)
 *
 * N1 (total operators)  = 3
 * n1 (unique operators) = 3  (all three are distinct class names)
 *
 * =========================================================================
 * OPERAND COUNTING (HalsteadMetricsVisitor::countOperands)
 * =========================================================================
 *
 * Rules from countOperands():
 *   Variable → uses $node->name  (string variable name, e.g. 'a')
 *   Param    → neither 'name' nor 'value' property on Node\Param
 *              → falls through to $node::class  (= 'PhpParser\Node\Param')
 *   Scalar   → uses $node->value (integer/float/string value)
 *   Cast     → uses $node::class
 *
 * Traversal order (PHP-Parser visits params before stmts):
 *
 *   Node                                  | Recorded value            | #
 *   --------------------------------------|---------------------------|---
 *   Variable inside Param $a              | 'a'                       | 1
 *   Param node for $a (no name/value)     | 'PhpParser\Node\Param'    | 2
 *   Variable inside Param $b              | 'b'                       | 3
 *   Param node for $b (no name/value)     | 'PhpParser\Node\Param'    | 4
 *   Variable $sum  — left of Assign       | 'sum'                     | 5
 *   Variable $a    — left of Plus         | 'a'                       | 6
 *   Variable $b    — right of Plus        | 'b'                       | 7
 *   Variable $sum  — expression in Return | 'sum'                     | 8
 *
 * N2 (total operands)  = 8
 * Unique values: { 'a', 'PhpParser\Node\Param', 'b', 'sum' }
 * n2 (unique operands) = 4
 *
 * =========================================================================
 * HALSTEAD FORMULAS  (calculateMetrics() in HalsteadMetricsVisitor)
 * =========================================================================
 *
 *   n  (vocabulary)   = n1 + n2            = 3 + 4       = 7
 *   N  (length)       = N1 + N2            = 3 + 8       = 11
 *
 *   calcLength        = n  × log₂(n)       = 7  × log₂(7)   ≈ 19.651484454403228
 *   Volume            = N  × log₂(n)       = 11 × log₂(7)   ≈ 30.880904142633646
 *   Difficulty        = (n1/2) × (N2/n2)   = (3/2) × (8/4)  = 1.5 × 2.0 = 3.0
 *   Effort            = Difficulty × Volume = 3.0 × Volume   ≈ 92.64271242790093
 *   complexityDensity = Difficulty / (n+N) = 3 / (7+11)      = 3/18 ≈ 0.16666666666666666
 *
 * Note: The file-level metrics equal the function-level metrics because the
 * file contains exactly one function and all counted nodes are inside it.
 */

function add($a, $b) {
    $sum = $a + $b;
    return $sum;
}
