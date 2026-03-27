<?php

/**
 * Hand-calculated Cyclomatic Complexity test provider.
 *
 * Every expected value below is derived from a step-by-step manual trace
 * of CyclomaticComplexityVisitor::getIncreaseForNode() on the fixture file.
 *
 * Fixture: testfiles/hand-calculated-cc.php
 *
 * ============================================================
 * FUNCTION: complexFunction — CC = 5
 * ============================================================
 * Rule: baseline = 1, then +1 for each decision node encountered
 * in leaveNode() via getIncreaseForNode()
 *
 *   1  baseline (functionCc[$name] = 1)
 *  +1  Node\Stmt\If_         if ($a > 0)
 *  +1  Node\Stmt\Foreach_    foreach ($b as $item)
 *  +1  Node\Stmt\If_         if ($item > 5)
 *  +1  Node\Expr\Ternary     $c ? 'yes' : 'no'
 *  ---
 *   5  CC
 *
 * ============================================================
 * CLASS: SimpleCalc — class CC = 3
 * ============================================================
 * The class CC counter starts at 1 and accumulates every branch
 * increment from ALL methods (just like file CC):
 *
 *   1  baseline (classCc[$name] = 1)
 *  +1  Node\Stmt\While_      while ($n > 0)   — from loop()
 *  +1  Node\Stmt\If_         if ($n > $limit) — from loop()
 *  ---
 *   3  class CC
 *
 * METHOD: SimpleCalc::add — CC = 1
 *   1  baseline, no branching constructs in body
 *
 * METHOD: SimpleCalc::loop — CC = 3
 *   1  baseline
 *  +1  Node\Stmt\While_   while ($n > 0)
 *  +1  Node\Stmt\If_      if ($n > $limit)
 *  ---
 *   3  CC
 *
 * ============================================================
 * FILE CC = 7
 * ============================================================
 *   1  baseline (fileCc = 1)
 *  +4  from complexFunction: if + foreach + if + ternary
 *  +2  from SimpleCalc methods: while + if (in loop)
 *  ---
 *   7  file CC
 */

return [
    [
        __DIR__ . '/../testfiles/hand-calculated-cc.php',
        [
            'file' => [
                'cc' => 7,
            ],
            'function' => [
                'complexFunction' => [
                    'cc' => 5,
                ],
            ],
            'classes' => [
                'SimpleCalc' => [
                    'cc' => 3,
                    'methods' => [
                        'add' => [
                            'cc' => 1,
                        ],
                        'loop' => [
                            'cc' => 3,
                        ],
                    ],
                ],
            ],
        ],
    ],

    // ============================================================
    // EDGE CASE 1: Empty function / empty class / empty method
    // ============================================================
    // Fixture: testfiles/empty.php
    //
    // Rule: baseline = 1 always, even with zero decision points.
    //
    // FUNCTION: emptyFunction — CC = 1
    //   1  baseline (functionCc = 1)
    //   (no branches)
    //   CC = 1
    //
    // CLASS: emptyMethodClass — class CC = 1
    //   1  baseline (classCc = 1)
    //   (no branches in any method)
    //   class CC = 1
    //
    // METHOD: emptyMethodClass::emptyMethod — CC = 1
    //   1  baseline (methodCc = 1)
    //   (no branches)
    //   CC = 1
    //
    // FILE CC = 1
    //   1  baseline (fileCc = 1)
    //   (no branches anywhere in the file)
    //   file CC = 1
    [
        __DIR__ . '/../testfiles/empty.php',
        [
            'file' => [
                'cc' => 1,
            ],
            'function' => [
                'emptyFunction' => [
                    'cc' => 1,
                ],
            ],
            'classes' => [
                'emptyMethodClass' => [
                    'cc' => 1,
                    'methods' => [
                        'emptyMethod' => [
                            'cc' => 1,
                        ],
                    ],
                ],
            ],
        ],
    ],

    // ============================================================
    // EDGE CASE 2: File-level code only, no functions or classes
    // ============================================================
    // Fixture: testfiles/cc-file-level-only.php
    //
    // Tests that the file CC is calculated independently when there are
    // no function or class definitions in the file.
    //
    // FILE CC = 3
    //   1  baseline (fileCc = 1)
    //  +1  Node\Stmt\If_       if ($value > 5)
    //  +1  Node\Stmt\ElseIf_   elseif ($value > 2)
    //   0  else (does NOT count)
    //   file CC = 3
    [
        __DIR__ . '/../testfiles/cc-file-level-only.php',
        [
            'file' => [
                'cc' => 3,
            ],
            'function' => [],
            'classes' => [],
        ],
    ],
];
