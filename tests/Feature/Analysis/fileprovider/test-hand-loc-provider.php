<?php

/**
 * Hand-calculated Lines of Code test provider.
 *
 * Every expected value below is derived from a step-by-step manual trace
 * of LocVisitor on the fixture file.
 *
 * Fixture: testfiles/hand-calculated-loc.php
 *
 * === HOW LOC IS MEASURED ===
 * LOC  = $lastNode->getEndLine() — physical line number of the last token
 * CLOC = comment lines in getClocAndLloc() applied to prettyPrinter output
 * LLOC = non-empty, non-comment lines in getClocAndLloc() after stripping
 *
 * For functions: LLOC is the body only (not declaration + braces).
 *   The whole-function LLOC (including declaration) is tracked separately
 *   for the llocOutside calculation.
 *
 * For classes: LLOC is the whole class (prettyPrint([$classNode])).
 *
 * For methods: LLOC is the body only (not declaration + braces).
 *
 * llocOutside = fileLloc − insideLloc
 *   where insideLloc = Σ wholeFunctionLloc + Σ classLloc
 *
 * ============================================================
 * FIXTURE FILE LAYOUT (16 physical lines)
 * ============================================================
 * Line  1: <?php
 * Line  2: (empty)
 * Line  3: function pureLogic(int $n): int
 * Line  4: {
 * Line  5:     $a = $n * 2;
 * Line  6:     $b = $a + 1;
 * Line  7:     return $b;
 * Line  8: }
 * Line  9: (empty)
 * Line 10: class PureCalc
 * Line 11: {
 * Line 12:     public function multiply(int $a, int $b): int
 * Line 13:     {
 * Line 14:         return $a * $b;
 * Line 15:     }
 * Line 16: }
 *
 * FILE:
 *   LOC  = 16  (Class_ node ends at line 16)
 *   CLOC = 0   (no comments in file)
 *   LLOC = 13  (prettyPrint of all nodes, remove blank lines, count:
 *               function pureLogic(...) : int  ← 1
 *               {                               ← 2
 *                   $a = $n * 2;               ← 3
 *                   $b = $a + 1;               ← 4
 *                   return $b;                 ← 5
 *               }                               ← 6
 *               class PureCalc                  ← 7
 *               {                               ← 8
 *                   public function multiply(...)  ← 9
 *                   {                           ← 10
 *                       return $a * $b;         ← 11
 *                   }                           ← 12
 *               }                               ← 13)
 *
 * FUNCTION: pureLogic
 *   LOC  = 8 − 3 + 1 = 6  (lines 3–8)
 *   CLOC = 0
 *   LLOC = 3  (body: "$a = $n * 2;" + "$b = $a + 1;" + "return $b;")
 *   wholeFunctionLloc = 6  (prettyPrint of whole Function_ node, 6 non-empty lines)
 *
 * CLASS: PureCalc
 *   LOC  = 16 − 10 + 1 = 7  (lines 10–16)
 *   CLOC = 0
 *   LLOC = 7  (prettyPrint([$classNode]): class + { + method sig + { + body + } + })
 *
 * METHOD: PureCalc::multiply
 *   LOC  = 15 − 12 + 1 = 4  (lines 12–15)
 *   CLOC = 0
 *   LLOC = 1  (body: "return $a * $b;")
 *
 * llocOutside:
 *   insideLloc = wholeFunctionLloc(pureLogic) + classLloc(PureCalc)
 *              = 6 + 7 = 13
 *   llocOutside = fileLloc − insideLloc = 13 − 13 = 0
 *   (the file contains only the function and class, no top-level code)
 */

return [
    // ============================================================
    // EDGE CASE 1: Empty file (opening PHP tag + closing tag, no code)
    // ============================================================
    // Fixture: testfiles/empty-file.php  (content: opening PHP tag, closing tag, newline)
    //
    // PHP-Parser parses the empty PHP section and produces $nodes = [].
    // The LocVisitor::beforeTraverse() short-circuits when $nodes is empty:
    //   array_key_last([]) === null → $loc stays at its initial value of 0
    //   $loc === 0 → getClocAndLloc() is never called → CLOC = 0, LLOC = 0
    //
    // llocOutside = fileLloc(0) − insideLloc(0) = 0
    // htmlLoc = 0 (no InlineHTML nodes with content)
    [
        __DIR__ . '/../testfiles/empty-file.php',
        [
            'loc'  => 0,
            'lloc' => 0,
            'cloc' => 0,
            'file' => [
                'loc'         => 0,
                'lloc'        => 0,
                'cloc'        => 0,
                'llocOutside' => 0,
                'htmlLoc'     => 0,
            ],
        ],
    ],

    // ============================================================
    // EDGE CASE 2: File with only PHP comments, no statements
    // ============================================================
    // Fixture: testfiles/loc-comments-only.php
    // (content: <?php + empty line + three // comment lines)
    //
    // PHP-Parser 5.x generates a Stmt_Nop node to hold dangling comments
    // when there are no real statements. The Nop node sits at the line of
    // the last comment (line 5 in the minimal fixture).
    //
    // LOC = 5  (Stmt_Nop::getEndLine() = 5, the last comment line)
    //
    // loc > 0, so getClocAndLloc() IS called on prettyPrint([$nopNode]):
    //   prettyPrint output:
    //     "// comment line one\n// comment line two\n// comment line three"
    //   Step 1: single-line comment regex strips all 3 lines → CLOC = 3
    //   Step 2: remaining code = "" (empty string after trim)
    //   Step 3: preg_split('/\r\n|\r|\n/', "") → [""] (one-element array)
    //   Step 4: count([""])  = 1 → LLOC = 1
    //
    // This is a known preg_split artifact: splitting an empty string always
    // yields a one-element array. LLOC = 1 does NOT mean there is a code
    // line — it is an implementation quirk.
    //
    // CLOC = 3, LLOC = 1
    // insideLloc = 0 (no functions, no classes)
    // llocOutside = fileLloc(1) − insideLloc(0) = 1
    // htmlLoc = 0 (no InlineHTML nodes)
    [
        __DIR__ . '/../testfiles/loc-comments-only.php',
        [
            'loc'  => 5,
            'lloc' => 1,
            'cloc' => 3,
            'file' => [
                'loc'         => 5,
                'lloc'        => 1,
                'cloc'        => 3,
                'llocOutside' => 1,
                'htmlLoc'     => 0,
            ],
        ],
    ],

    // ============================================================
    // EDGE CASE 3: PHP file with inline HTML
    // ============================================================
    // Fixture: testfiles/php-file-with-html.php
    // (content: declare + empty function test() + HTML block + echo "foo")
    //
    // LOC = 14  (last AST node — echo "foo" — ends at line 14)
    //
    // The prettyPrint of nodes-without-HTML produces:
    //   declare(strict_types=1);   ← 1
    //   function test()             ← 2
    //   {                           ← 3
    //   }                           ← 4
    //   echo 'foo';                 ← 5
    // LLOC = 5, CLOC = 0
    //
    // insideLloc = wholeFunctionLloc(test):
    //   prettyPrint([Function_ node]):  function test() { } → 3 lines
    //   wholeFunctionLloc = 3
    // llocOutside = 5 − 3 = 2  (the declare and echo statements)
    //
    // htmlLoc = countInlineHtml: getEndLine() − getStartLine()
    //   InlineHTML spans from line 9 (<div>) to line 12 (before <?php)
    //   htmlLoc = 12 − 9 = 3
    [
        __DIR__ . '/../testfiles/php-file-with-html.php',
        [
            'loc'  => 14,
            'lloc' => 5,
            'cloc' => 0,
            'file' => [
                'loc'         => 14,
                'lloc'        => 5,
                'cloc'        => 0,
                'llocOutside' => 2,
                'htmlLoc'     => 3,
            ],
            'functions' => [],
            'classes'   => [],
        ],
    ],

    [
        __DIR__ . '/../testfiles/hand-calculated-loc.php',
        [
            'loc'  => 16,
            'lloc' => 13,
            'cloc' => 0,
            'file' => [
                'loc'         => 16,
                'lloc'        => 13,
                'cloc'        => 0,
                'llocOutside' => 0,
                'htmlLoc'     => 0,
            ],
            'functions' => [
                'pureLogic' => [
                    'loc'  => 6,
                    'lloc' => 3,
                    'cloc' => 0,
                ],
            ],
            'classes' => [
                'PureCalc' => [
                    'data' => [
                        'loc'  => 7,
                        'lloc' => 7,
                        'cloc' => 0,
                    ],
                    'methods' => [
                        'multiply' => [
                            'loc'  => 4,
                            'lloc' => 1,
                            'cloc' => 0,
                        ],
                    ],
                ],
            ],
        ],
    ],
];
