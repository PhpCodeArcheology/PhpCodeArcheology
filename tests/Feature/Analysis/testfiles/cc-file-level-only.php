<?php

// Edge case: File with only top-level code, no functions or classes.
// Tests that the file-level CC counter works independently of functions/classes.
//
// CC calculation:
//   Baseline:              +1
//   if ($value > 5):       +1  [Node\Stmt\If_]
//   elseif ($value > 2):   +1  [Node\Stmt\ElseIf_]
//   else:                   0  (else does NOT count)
//   --------------------------------
//   File CC = 3

$value = 10;
if ($value > 5) {       // +1
    echo 'big';
} elseif ($value > 2) { // +1
    echo 'medium';
} else {                // 0
    echo 'small';
}
