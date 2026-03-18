<?php

// Simple function: CogC = 1 (one if)
function simpleFunction($a) {
    if ($a > 0) { // +1
        return true;
    }
    return false;
}

// Nested function: CogC = 3 (if +1, nested if +2)
function nestedFunction($a, $b) {
    if ($a > 0) { // +1 (nesting 0)
        if ($b > 0) { // +1 +1 nesting = +2 (nesting 1)
            return true;
        }
    }
    return false;
}

// Boolean operators: CogC = 2 (if +1, && sequence +1)
function booleanSequence($a, $b, $c) {
    if ($a && $b && $c) { // if: +1, &&: +1 (sequence counts as 1)
        return true;
    }
    return false;
}

// Mixed boolean: CogC = 3 (if +1, && +1, || +1)
function mixedBoolean($a, $b, $c) {
    if ($a && $b || $c) { // if: +1, &&: +1, ||: +1
        return true;
    }
    return false;
}

// Loop with nesting: CogC = 4 (for +1, if +2, else +1)
function loopWithNesting($items) {
    for ($i = 0; $i < count($items); $i++) { // +1 (nesting 0)
        if ($items[$i] > 0) { // +1 +1 nesting = +2 (nesting 1)
            echo $items[$i];
        } else { // +1
            echo 'neg';
        }
    }
}

class CognitiveTestClass {
    // Method CogC = 6 (if +1, nested for +2, nested if +3)
    public function deeplyNested($data) {
        if (count($data) > 0) { // +1 (nesting 0)
            foreach ($data as $item) { // +1 +1 nesting = +2 (nesting 1)
                if ($item > 10) { // +1 +2 nesting = +3 (nesting 2)
                    return $item;
                }
            }
        }
        return null;
    }

    // Method CogC = 1 (just a switch)
    public function simpleSwitch($val) {
        switch ($val) { // +1 (nesting 0)
            case 1:
                return 'one';
            case 2:
                return 'two';
            default:
                return 'other';
        }
    }

    // Method CogC = 3 (try-catch does not increment, catch +1, if in catch +2)
    public function tryCatch($x) {
        try {
            return $x / 2;
        } catch (\Exception $e) { // +1 (nesting 0)
            if ($e->getCode() > 0) { // +1 +1 nesting = +2 (nesting 1)
                throw $e;
            }
        }
    }
}
