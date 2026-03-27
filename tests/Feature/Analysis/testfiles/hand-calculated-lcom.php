<?php

/**
 * Hand-calculated LCOM test fixtures
 *
 * LCOM = number of connected components in the method-property graph.
 *
 * Algorithm (LcomVisitor.php, connected-components variant):
 *   - Each method becomes a node: methodName()
 *   - Each $this->property access creates an edge: method() ↔ propertyName
 *   - Each $this->otherMethod() call creates an edge: caller() ↔ callee()
 *   - All edges are undirected (both ends store the edge)
 *   - LCOM = number of DFS traversals required to visit all nodes
 *     (= number of connected components)
 *
 * Reference: Henderson-Sellers LCOM4 variant
 */

declare(strict_types=1);

/**
 * HandCalcLcomTwo — Expected LCOM: 2
 *
 * Properties: $x, $y, $z
 * Methods:    getX(), setX(), moveXtoY(), getZ(), setZ()
 *
 * Graph edges:
 *   getX()      → x               ($this->x in return)
 *   setX()      → x               ($this->x in assignment)
 *   moveXtoY()  → y               ($this->y on left side)
 *   moveXtoY()  → x               ($this->x on right side)
 *   getZ()      → z               ($this->z in return)
 *   setZ()      → z               ($this->z in assignment)
 *
 * Graph (adjacency):
 *
 *   getX() ─── x ─── setX()
 *              │
 *         moveXtoY() ─── y
 *
 *   getZ() ─── z ─── setZ()
 *
 * Connected components:
 *   Component 1: {getX(), x, setX(), moveXtoY(), y}
 *     DFS from getX(): getX→x→setX (done); x→moveXtoY→y (done)
 *   Component 2: {getZ(), z, setZ()}
 *     DFS from getZ(): getZ→z→setZ (done)
 *
 * LCOM = 2
 */
class HandCalcLcomTwo
{
    private int $x = 0;
    private int $y = 0;
    private int $z = 0;

    public function getX(): int
    {
        return $this->x;             // edge: getX() ↔ x
    }

    public function setX(int $val): void
    {
        $this->x = $val;             // edge: setX() ↔ x
    }

    public function moveXtoY(): void
    {
        $this->y = $this->x;         // edges: moveXtoY() ↔ y, moveXtoY() ↔ x
    }                                //   → connects moveXtoY to Component 1 via x

    public function getZ(): int
    {
        return $this->z;             // edge: getZ() ↔ z
    }

    public function setZ(int $val): void
    {
        $this->z = $val;             // edge: setZ() ↔ z
    }
}

/**
 * HandCalcLcomThree — Expected LCOM: 3
 *
 * Properties: $alpha, $beta, $gamma
 * Methods:    getAlpha(), setAlpha(), getBeta(), getGamma(), resetGamma()
 *
 * Graph edges:
 *   getAlpha()    → alpha            ($this->alpha in return)
 *   setAlpha()    → alpha            ($this->alpha in assignment)
 *   getBeta()     → beta             ($this->beta in return)
 *   getGamma()    → gamma            ($this->gamma in return)
 *   resetGamma()  → gamma            ($this->gamma in assignment)
 *
 * Graph (adjacency):
 *
 *   getAlpha() ─── alpha ─── setAlpha()
 *
 *   getBeta() ─── beta
 *
 *   getGamma() ─── gamma ─── resetGamma()
 *
 * Connected components:
 *   Component 1: {getAlpha(), alpha, setAlpha()}
 *     DFS from getAlpha(): getAlpha→alpha→setAlpha (done)
 *   Component 2: {getBeta(), beta}
 *     DFS from getBeta(): getBeta→beta (done)
 *   Component 3: {getGamma(), gamma, resetGamma()}
 *     DFS from getGamma(): getGamma→gamma→resetGamma (done)
 *
 * LCOM = 3
 */
class HandCalcLcomThree
{
    private string $alpha = '';
    private int $beta = 0;
    private float $gamma = 0.0;

    public function getAlpha(): string
    {
        return $this->alpha;         // edge: getAlpha() ↔ alpha
    }

    public function setAlpha(string $val): void
    {
        $this->alpha = $val;         // edge: setAlpha() ↔ alpha
    }

    public function getBeta(): int
    {
        return $this->beta;          // edge: getBeta() ↔ beta
    }                                //   isolated from alpha and gamma groups

    public function getGamma(): float
    {
        return $this->gamma;         // edge: getGamma() ↔ gamma
    }

    public function resetGamma(): void
    {
        $this->gamma = 0.0;          // edge: resetGamma() ↔ gamma
    }
}
