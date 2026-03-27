<?php

/**
 * Hand-calculated Maintainability Index test provider.
 *
 * Fixture: tests/Feature/Analysis/testfiles/hand-calculated-mi.php
 * PHP code:
 *   // Hand-calculated Maintainability Index fixture
 *   function simpleReturn($x) { return $x > 0; }
 *
 * =========================================================================
 * HALSTEAD METRICS (used by MI formula)
 * =========================================================================
 *
 * Operators inside simpleReturn($x):
 *   Expr\BinaryOp\Greater — "$x > 0"       → 1 occurrence
 *   Stmt\Return_          — "return …"      → 1 occurrence
 *   N1 = 2, n1 = 2
 *
 * Operands inside simpleReturn($x):
 *   Variable('x') inside Param  → 'x'                    (1)
 *   Param node for $x           → 'PhpParser\Node\Param' (2)
 *   Variable('x') in Greater    → 'x'                    (3)
 *   Scalar\Int_(0)              → 0                      (4)
 *   N2 = 4, n2 = 3  { 'x', 'PhpParser\Node\Param', 0 }
 *
 *   n = 2+3 = 5, N = 2+4 = 6
 *   Volume = N × log₂(n) = 6 × log₂(5) ≈ 13.931568569324174
 *
 * =========================================================================
 * LOC / CLOC / LLOC
 * =========================================================================
 *
 * FILE LEVEL:
 *   loc   = 5   (last AST node — Function_ — ends at line 5)
 *   cloc  = 1   (PrettyPrinter attaches the leading "// ..." comment to
 *                Function_; getClocAndLloc strips it → +1 comment line)
 *   lloc  = 4   (pretty-printed lines after comment removal:
 *                  "function simpleReturn($x)", "{",
 *                  "    return $x > 0;", "}")
 *
 * FUNCTION LEVEL:
 *   loc   = 3   (lines 3–5: declaration + body + closing brace, 5-3+1 = 3)
 *   cloc  = 0   (no comments in function body nodes)
 *   lloc  = 1   (body pretty-print = "return $x > 0;" — single line)
 *
 * =========================================================================
 * CYCLOMATIC COMPLEXITY
 * =========================================================================
 *
 *   CC (file)     = 1  (baseline only — no branches in the file)
 *   CC (function) = 1  (baseline, no if/switch/loops)
 *
 * =========================================================================
 * MAINTAINABILITY INDEX FORMULA
 * =========================================================================
 *
 * MaintainabilityIndexCalculator uses PHP's log() = ln (natural logarithm):
 *
 *   MI_without_comments = max(171 − 5.2×ln(V) − 0.23×CC − 16.2×ln(LLOC), 0)
 *   commentWeight       = 50 × sin(√(2.4 × cloc/loc))      [when loc > 0]
 *   MI                  = MI_without_comments + commentWeight
 *
 * --- FUNCTION simpleReturn (V=13.9316, CC=1, LLOC=1, LOC=3, CLOC=0): ---
 *
 *   5.2 × ln(13.9316)  = 5.2 × 2.63456  ≈ 13.6997
 *   0.23 × 1           =                   0.23
 *   16.2 × ln(1)       = 16.2 × 0        = 0.0
 *
 *   MI_WOC = 171 − 13.6997 − 0.23 − 0 ≈ 157.07238159728848
 *   cW     = 0.0  (cloc=0)
 *   MI     = 157.07238159728848
 *
 * --- FILE LEVEL (V=13.9316, CC=1, LLOC=4, LOC=5, CLOC=1): ---
 *
 *   5.2 × ln(13.9316)  = 5.2 × 2.63456  ≈ 13.6997
 *   0.23 × 1           =                   0.23
 *   16.2 × ln(4)       = 16.2 × 1.38629 ≈ 22.4579
 *
 *   MI_WOC = 171 − 13.6997 − 0.23 − 22.4579 ≈ 134.61441294714626
 *
 *   commentWeight = cloc/loc = 1/5 = 0.2
 *   commentWeight = 50 × sin(√(2.4 × 0.2))
 *                 = 50 × sin(√0.48)
 *                 = 50 × sin(0.69282…)
 *                 ≈ 50 × 0.63906…
 *                 ≈ 31.935490532850302
 *
 *   MI = 134.61441294714626 + 31.935490532850302 ≈ 166.54990347999657
 */

return [
    [
        __DIR__ . '/../testfiles/hand-calculated-mi.php',
        [
            'file' => [
                'halstead' => [
                    'mi'    => 166.54990347999657,
                    'miWOC' => 134.61441294714626,
                    'cW'    => 31.935490532850302,
                ],
            ],
            'functions' => [
                'simpleReturn' => [
                    'halstead' => [
                        'mi'    => 157.07238159728848,
                        'miWOC' => 157.07238159728848,
                        'cW'    => 0.0,
                    ],
                ],
            ],
            'classes' => [],
        ],
    ],
];
