<?php

/**
 * Hand-calculated Cognitive Complexity test provider.
 *
 * Every expected value below is derived from a step-by-step manual trace
 * of CognitiveComplexityVisitor::enterNode() on the fixture file.
 *
 * Fixture: testfiles/hand-calculated-cogc.php
 *
 * Notation used below:
 *   [N=x] = nestingLevel when the node is entered
 *   +k    = increment added (1 + nestingLevel, or 1 flat)
 *
 * ============================================================
 * FUNCTION: flatChain — CogC = 3
 * ============================================================
 * Demonstrates: elseif and else get a flat +1 (no nesting bonus).
 * The outer if pushes nesting but elseif/else do NOT add to it.
 *
 *  +1  Node\Stmt\If_       if ($a > 0)     [N=0] → 1+0=1, nestingLevel→1
 *  +1  Node\Stmt\ElseIf_  elseif ($b > 0)  flat
 *  +1  Node\Stmt\Else_    else             flat
 *  (leave If_: nestingLevel→0)
 *  ---
 *   3  CogC
 *
 * ============================================================
 * FUNCTION: boolSequences — CogC = 8
 * ============================================================
 * Demonstrates: boolean operator sequences.
 * Each CONTIGUOUS run of the same operator type counts as +1 flat.
 * Implemented via left-child detection: only the INNERMOST node of a
 * run fires (where left child is NOT the same operator).
 *
 * First if ($a && $b && $c):
 *   AST: &&( &&($a,$b), $c )
 *   outer &&: left is &&, same type → skip
 *   inner &&: left is $a, NOT && → +1
 *  +1  Node\Stmt\If_        if         [N=0] → 1, nestingLevel→1
 *  +1  Node\Expr\BoolAnd    && (inner, start of run)
 *  (leave If_: nestingLevel→0)
 *  subtotal: 2
 *
 * Second if ($a || $b || $c):
 *   AST: ||( ||($a,$b), $c )
 *  +1  Node\Stmt\If_        if         [N=0] → 1, nestingLevel→1
 *  +1  Node\Expr\BoolOr     || (inner, start of run)
 *  (leave If_: nestingLevel→0)
 *  subtotal: 2
 *
 * Third if ($a && $b || $c && $d):
 *   AST: ||( &&($a,$b), &&($c,$d) )
 *  +1  Node\Stmt\If_        if         [N=0] → 1, nestingLevel→1
 *  +1  Node\Expr\BoolOr     ||  : left is &&, NOT || → +1
 *  +1  Node\Expr\BoolAnd    && (left): left is $a, NOT && → +1
 *  +1  Node\Expr\BoolAnd    && (right): left is $c, NOT && → +1
 *  (leave If_: nestingLevel→0)
 *  subtotal: 4
 *
 * CogC = 2 + 2 + 4 = 8
 *
 * ============================================================
 * FUNCTION: deepNested — CogC = 10
 * ============================================================
 * Demonstrates: maximum nesting penalty accumulation.
 *
 *  +1  Node\Stmt\If_        if ($a > 0)       [N=0] → 1+0=1, nestingLevel→1
 *  +2  Node\Stmt\Foreach_   foreach ($d as $x) [N=1] → 1+1=2, nestingLevel→2
 *  +3  Node\Stmt\If_        if ($x > $b)       [N=2] → 1+2=3, nestingLevel→3
 *  +4  Node\Stmt\While_     while ($c > 0)     [N=3] → 1+3=4, nestingLevel→4
 *  (leaves: nestingLevel 4→3→2→1→0)
 *  ---
 *  10  CogC
 *
 * ============================================================
 * CLASS: SwitchAndCatch — class CogC = 4
 * ============================================================
 *
 * METHOD: getLabel — CogC = 1
 *   switch statement counts as ONE structural increment (unlike CC
 *   which counts each case). Case labels do not add to CogC.
 *
 *  +1  Node\Stmt\Switch_  switch ($code)  [N=0] → 1+0=1, nestingLevel→1
 *  (leave Switch_: nestingLevel→0)
 *  CogC = 1
 *
 * METHOD: divide — CogC = 3
 *   try does NOT increment. catch does.
 *
 *  +1  Node\Stmt\Catch_   catch (\Throwable $e)  [N=0] → 1+0=1, nestingLevel→1
 *  +2  Node\Stmt\If_      if ($a === 0)           [N=1] → 1+1=2, nestingLevel→2
 *  (leave If_: nestingLevel→1)
 *  (leave Catch_: nestingLevel→0)
 *  CogC = 1 + 2 = 3
 *
 * Class accumulates all method increments: 1 + 3 = 4
 *
 * ============================================================
 * FILE COGC = 25
 * ============================================================
 *  flatChain:      3
 *  boolSequences:  8
 *  deepNested:    10
 *  SwitchAndCatch: 4  (via its methods)
 *  ---
 *  25  file CogC
 */

return [
    // ============================================================
    // EDGE CASE 1: Empty function / empty method — CogC = 0
    // ============================================================
    // Fixture: testfiles/empty.php
    //
    // Rule: CogC baseline = 0 (unlike CC which starts at 1).
    // A function with no branches and no operators = CogC 0.
    //
    // FUNCTION: emptyFunction — CogC = 0
    //   0  baseline, no increments at all
    //
    // CLASS: emptyClass — CogC = 0
    //   No methods, no increments
    //
    // CLASS: emptyMethodClass — CogC = 0
    //   METHOD: emptyMethod — CogC = 0
    //     No branches, no operators
    //   Class accumulates 0 from emptyMethod → class CogC = 0
    //
    // Note: $classCogC in the test is the LAST class visited.
    // Since all classes have CogC = 0, all checks pass regardless of order.
    //
    // FILE CogC = 0
    [
        __DIR__ . '/../testfiles/empty.php',
        [
            'functions' => [
                'emptyFunction' => ['cogc' => 0],
            ],
            'classes' => [
                'emptyClass' => ['cogc' => 0],
                'emptyMethodClass' => [
                    'cogc' => 0,
                    'methods' => [
                        'emptyMethod' => ['cogc' => 0],
                    ],
                ],
            ],
            'file' => ['cogc' => 0],
        ],
    ],

    // ============================================================
    // EDGE CASE 2: Boolean operators without structural construct
    // ============================================================
    // Fixture: testfiles/cogc-bool-only.php
    //
    // Tests that &&/|| contribute to CogC even without if/for/while.
    // Boolean operator sequences are a flat +1 per run of same type.
    //
    // FUNCTION: boolOnly — CogC = 1
    //   return $a && $b && $c;
    //   AST: &&( &&($a,$b), $c )
    //   outer &&: left IS &&, same type → SKIP (not the start of the run)
    //   inner &&: left is $a, NOT && → addIncrement(1)
    //   No structural increments (no if, for, catch, switch, etc.)
    //   nestingLevel = 0 throughout (no nesting)
    //   CogC = 1
    //
    // FILE CogC = 1
    [
        __DIR__ . '/../testfiles/cogc-bool-only.php',
        [
            'functions' => [
                'boolOnly' => ['cogc' => 1],
            ],
            'classes' => [],
            'file' => ['cogc' => 1],
        ],
    ],

    [
        __DIR__ . '/../testfiles/hand-calculated-cogc.php',
        [
            'functions' => [
                'flatChain'     => ['cogc' => 3],
                'boolSequences' => ['cogc' => 8],
                'deepNested'    => ['cogc' => 10],
            ],
            'classes' => [
                'SwitchAndCatch' => [
                    'cogc' => 4,
                    'methods' => [
                        'getLabel' => ['cogc' => 1],
                        'divide'   => ['cogc' => 3],
                    ],
                ],
            ],
            'file' => ['cogc' => 25],
        ],
    ],
];
