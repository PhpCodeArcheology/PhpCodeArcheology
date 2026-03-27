<?php

// Edge case: boolean operators outside any structural construct.
// CogC counts boolean operator sequences even without if/for/while etc.
//
// CogC calculation for boolOnly:
//   No structural increment (no if/for/while/etc.) → nesting stays 0
//   $a && $b && $c:
//     AST: &&( &&($a,$b), $c )
//     outer &&: left IS &&, same type → SKIP
//     inner &&: left is $a, NOT && → addIncrement(1)
//   --------------------------------
//   boolOnly CogC = 1
//
//   File CogC = 1

function boolOnly(bool $a, bool $b, bool $c): bool
{
    return $a && $b && $c; // one && run: +1
}
