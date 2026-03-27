<?php

/**
 * Provider for hand-calculated LCOM fixture tests.
 *
 * All expected values are derived by manual graph analysis.
 * Full methodology and graph diagrams are in the fixture file:
 *   tests/Feature/Analysis/testfiles/hand-calculated-lcom.php
 *
 * Algorithm: connected components in method-property graph (LcomVisitor.php)
 *   - LCOM = number of DFS traversals to cover all nodes
 *   - Nodes: method names (method()) and accessed property names
 *   - Edges: method→property ($this->prop access), method→method ($this->call())
 */

return [
    [
        __DIR__ . '/../testfiles/hand-calculated-lcom.php',
        [
            'classes' => [
                // 5 methods, 3 properties, 6 edges
                // Component 1: {getX(), x, setX(), moveXtoY(), y}  (getX+setX share $x; moveXtoY bridges via $x and adds $y)
                // Component 2: {getZ(), z, setZ()}                  (getZ+setZ share $z, isolated from Component 1)
                'HandCalcLcomTwo' => ['lcom' => 2],

                // 5 methods, 3 properties, 5 edges
                // Component 1: {getAlpha(), alpha, setAlpha()}      (share $alpha)
                // Component 2: {getBeta(), beta}                    (isolated, touches only $beta)
                // Component 3: {getGamma(), gamma, resetGamma()}    (share $gamma)
                'HandCalcLcomThree' => ['lcom' => 3],
            ],
        ],
    ],
];
