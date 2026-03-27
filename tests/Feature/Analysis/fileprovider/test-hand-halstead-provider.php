<?php

/**
 * Hand-calculated Halstead test provider.
 *
 * Fixture: tests/Feature/Analysis/testfiles/hand-calculated-halstead.php
 * PHP code: function add($a, $b) { $sum = $a + $b; return $sum; }
 *
 * See hand-calculated-halstead.php for the complete operator/operand trace.
 * This provider documents only the key counts and final verified values.
 *
 * =========================================================================
 * OPERATOR COUNT SUMMARY (inside function add, same for file level)
 * =========================================================================
 *
 *   Expr\BinaryOp\Plus  — "$a + $b"            → 1 occurrence
 *   Expr\Assign         — "$sum = …"            → 1 occurrence
 *   Stmt\Return_        — "return $sum"          → 1 occurrence
 *
 *   N1 (total)  = 3
 *   n1 (unique) = 3
 *
 * =========================================================================
 * OPERAND COUNT SUMMARY (inside function add, same for file level)
 * =========================================================================
 *
 *   Variable 'a'  (in param + in Plus lhs)            → 2 occurrences
 *   Param class   (two Param nodes, same class string) → 2 occurrences
 *   Variable 'b'  (in param + in Plus rhs)            → 2 occurrences
 *   Variable 'sum'(Assign lhs + Return)               → 2 occurrences
 *
 *   N2 (total)  = 8
 *   n2 (unique) = 4  { 'a', 'PhpParser\Node\Param', 'b', 'sum' }
 *
 * =========================================================================
 * DERIVED HALSTEAD METRICS
 * =========================================================================
 *
 *   n  (vocabulary)   = n1 + n2            = 3 + 4      = 7
 *   N  (length)       = N1 + N2            = 3 + 8      = 11
 *   calcLength        = n × log₂(n)        = 7 × log₂(7)   ≈ 19.651484454403228
 *   Volume            = N × log₂(n)        = 11 × log₂(7)  ≈ 30.880904142633646
 *   Difficulty        = (n1/2) × (N2/n2)   = 1.5 × 2.0    = 3.0
 *   Effort            = Difficulty × Volume = 3.0 × V       ≈ 92.64271242790093
 *   complexityDensity = Difficulty / (n+N) = 3 / 18         ≈ 0.16666666666666666
 *
 * Both file-level and function-level yield identical values because the file
 * contains exactly one function and all counted nodes reside inside it.
 */

return [
    [
        __DIR__ . '/../testfiles/hand-calculated-halstead.php',
        [
            'file' => [
                'counted' => [
                    'operators'      => 3,
                    'operands'       => 8,
                    'uniqueOperators' => 3,
                    'uniqueOperands' => 4,
                ],
            ],
            'functions' => [
                'add' => [
                    'counted' => [
                        'operators'      => 3,
                        'operands'       => 8,
                        'uniqueOperators' => 3,
                        'uniqueOperands' => 4,
                    ],
                ],
            ],
            'classes' => [],
        ],
    ],
];
