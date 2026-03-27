# Metrics Formulas Reference

This document lists the mathematical formulas used in PhpCodeArcheology, with original sources and implementation notes.

---

## Changes in v2.7.0

Several metric calculations were corrected in v2.7.0. This section documents what changed and how it affects your results.

### Halstead Metrics — Method-Level Operand Fix (HIGH impact)

**Bug:** Method-level operands were recorded as AST node class names (`PhpParser\Node\Expr\Variable`) instead of actual operand values (`$myVar`). This made `uniqueOperands` far too low for methods.

**Effect:** Method-level `difficulty` was significantly inflated, `effort` was too high. After the fix, `difficulty` and `effort` values for methods will be **lower** (more realistic). File-level and class-level Halstead metrics were not affected.

### Cyclomatic Complexity — Spaceship Operator (LOW impact)

**Bug:** The spaceship operator (`<=>`) counted as CC+2 instead of CC+1.

**Effect:** CC values for code using `<=>` (e.g. sorting comparators) will be slightly **lower**.

### TooComplex Prediction — Double Counting (MEDIUM impact)

**Bug:** Classes exceeding the `avgMethodCc` threshold were flagged twice (on keys `cc` and `avgMethodCc`).

**Effect:** Error counts will **decrease**. Classes that previously showed 2 complexity problems may now show 1.

### God Class Prediction — suspectIndex (MEDIUM impact)

**Bug:** The suspect index was incremented once per long method instead of once for "has any long method". A class with 5 long methods had suspectIndex=5 instead of 1.

**Effect:** Fewer **false positive** God Class detections, especially for large classes with many long methods.

### LCOM Tolerance — Zero Average Floor (MEDIUM impact)

**Bug:** When project average LCOM was 0, the tolerance threshold was also 0, flagging every class with LCOM > 0.

**Effect:** Fewer LCOM warnings in small projects or projects with many interfaces.

### Layer Violation — Layer 0 Classes (LOW impact)

**Bug:** Entity/Model/Domain classes (layer 0) were skipped entirely, even when they imported higher-layer classes.

**Effect:** More layer violations detected for domain classes that depend on services or controllers.

### Coupling — Distance from Mainline (LOW impact)

**Bug:** The formula `D = A + I - 1` was missing the absolute value. Negative distances were possible.

**Effect:** `distanceFromMainline` is now always >= 0, as per Robert C. Martin's original formula.

### Coupling — File/Function handleMetric (LOW impact)

**Bug:** `handleMetric()` operated on MetricValue objects instead of their values, causing incorrect or failing file/function coupling calculations.

**Effect:** File and function coupling metrics are now correct.

### Maintainability Index — Default Value (LOW impact)

**Bug:** The fallback for `maintainabilityIndexWithoutComments` was 50 while `maintainabilityIndex` was 171 (with `commentWeight` = 0). This was inconsistent.

**Effect:** Empty/trivial files now consistently report MI = 171 (maximum).

### Package Cohesion — Normalization (LOW impact)

**Bug:** Cohesion values could exceed 1.0 (e.g. 3.0 for highly connected packages).

**Effect:** Package cohesion is now capped at 1.0.

### Halstead Difficulty Threshold — Recalibration (MEDIUM impact)

**Change:** Default threshold raised from 20 to 30 (non-framework) and 35 to 45 (framework projects).

**Rationale:** Unlike McCabe CC (where 10 is a widely accepted threshold), there is no established consensus in the literature for a Halstead Difficulty threshold. Verifysoft, IBM, MATLAB/Simulink, PHPMetrics, Schneider Electric, and the original Halstead (1977) paper define no recommended Difficulty ranges. The metric was designed for trend analysis, not binary pass/fail judgments.

Our previous threshold of 20 was self-chosen and flagged 22% of all entities — too aggressive for a meaningful signal. The new threshold of 30 flags entities that are genuinely hard to understand while reducing noise.

The tool also applies a **relative check**: effort values more than 30% above the project average are flagged separately. This combination of absolute floor + relative outlier detection provides better signal than either approach alone.

**Effect:** Fewer "Difficulty too high" errors (~40% reduction). Error counts will decrease significantly.

### Expected Impact on Your Results

Based on real-world validation (1,731-file Symfony/Doctrine project):

| Metric | Expected Change | Magnitude |
|--------|----------------|-----------|
| **Error count** | **Decrease** — fewer false positives from double-counting, God Class, LCOM, and recalibrated Difficulty threshold | **-40% to -50%** |
| **Warning count** | Mostly unchanged | Minimal |
| **Health Score** | Slight increase — fewer errors improve problem density | **+0.5 to +2 points** |
| **Avg CC** | Unchanged for most projects. Slightly lower if `<=>` is used heavily | Minimal |
| **Avg MI** | Unchanged at file/class level. Method-level MI may shift due to corrected Halstead values | Minimal |
| **Halstead Difficulty (methods)** | **Decrease** — previously inflated due to operand tracking bug | **Significant** |
| **Halstead Effort (methods)** | **Decrease** — follows from corrected Difficulty | **Significant** |
| **God Class detections** | Decrease — fewer false positives for classes with many long methods | Moderate |
| **LCOM warnings** | Decrease in small projects — zero-average floor prevents false positives | Moderate |
| **Layer violations** | May increase — Entity/Model classes are now checked | Low |
| **Distance from Mainline** | Now always >= 0, was sometimes negative | Low |

**Summary:** Most projects will see **significantly fewer errors** and a **better Health Score**. The recalibrated Difficulty threshold (20→30) combined with the Halstead operand bug fix are the biggest contributors. The overall architectural assessment (Coupling, Abstractness, Instability) remains stable.

### A Note on Interpreting Scores

The Health Score is a **guideline**, not a verdict. A score of 75 in a project with complex mathematical algorithms, protocol parsers, or domain-heavy business logic can represent excellent engineering — some code is inherently complex and cannot (and should not) be simplified further.

Use the score to:
- **Track trends** — is the codebase getting better or worse over time?
- **Find outliers** — which classes or methods deviate significantly from the project average?
- **Prioritize refactoring** — focus effort where it has the most impact

Do **not** use the score to:
- Judge a project as "good" or "bad" based on a single number
- Set a mandatory minimum score that all code must achieve
- Compare scores between fundamentally different types of projects

---

## LCOM — Lack of Cohesion of Methods

**Original source:** Henderson-Sellers, B. (1996). *Object-Oriented Metrics: Measures of Complexity.* Prentice Hall. (LCOM4 variant)

**Conceptual basis:** Chidamber, S. R. & Kemerer, C. F. (1994). "A Metrics Suite for Object-Oriented Design." *IEEE Transactions on Software Engineering*, 20(6), 476–493.

### Formula

```
LCOM = number of connected components in the method-property graph
```

### What is counted

The method-property graph is built as follows:

- **Nodes:** Each method becomes a node `methodName()`. Each property accessed via `$this->prop` becomes a node `propName`.
- **Edges (undirected):**
  - `method() ↔ prop` — whenever a method accesses `$this->property`
  - `method() ↔ otherMethod()` — whenever a method calls `$this->otherMethod()`
- **LCOM** = number of DFS traversals required to visit all nodes = number of connected components

### Interpretation

| Value | Meaning |
|-------|---------|
| 0 | No methods (empty class) |
| 1 | All methods are cohesive (single connected component) |
| > 1 | Class should potentially be split into N classes |

### Example (hand-calculated)

```
class Example {
    private $x;
    private $z;

    function getX() { return $this->x; }   // edge: getX() ↔ x
    function setX($v) { $this->x = $v; }   // edge: setX() ↔ x
    function getZ() { return $this->z; }   // edge: getZ() ↔ z
}

Graph: getX() ─── x ─── setX()    getZ() ─── z

Components: {getX, x, setX} and {getZ, z}  →  LCOM = 2
```

### Implementation

`src/Analysis/LcomVisitor.php` — builds the graph during AST traversal, counts connected components in `leaveNode` for `Class_`, `Trait_`, and `Enum_` nodes.

---

## Instability (I)

**Original source:** Martin, R. C. (2002). *Agile Software Development: Principles, Patterns, and Practices.* Prentice Hall. Chapter 20: "Principles of Package Design."

### Formula

```
I = Ce / (Ca + Ce)
```

### What is counted

| Symbol | Name | Definition |
|--------|------|-----------|
| Ce | Efferent Coupling | Number of classes inside the project that this class depends on ("fan-out"). Traits are excluded. |
| Ca | Afferent Coupling | Number of classes inside the project that depend on this class ("fan-in"). |
| I  | Instability | Range [0, 1] |

### Interpretation

| Value | Meaning |
|-------|---------|
| 0 | Maximally stable — many dependents, hard to change without breaking others |
| 1 | Maximally unstable — no dependents, safe to change freely |
| 0.5 | Balanced — equal fan-in and fan-out |

**Martin's Stable Dependency Principle (SDP):** Depend in the direction of stability (I should decrease along dependency arrows).

### Example (hand-calculated)

```
HandCalcServiceA  ──depends on──▶  HandCalcServiceB

ServiceA: Ce=1, Ca=0  →  I = 1 / (0 + 1) = 1   (unstable)
ServiceB: Ce=0, Ca=1  →  I = 0 / (1 + 0) = 0   (stable)
```

**PHP note:** When Ce + Ca divides evenly, PHP's `/` operator returns `int`, not `float`: `1/1 = int(1)`, `0/1 = int(0)`, `1/2 = float(0.5)`.

### Implementation

`src/Calculators/CouplingCalculator.php` — `afterTraverse()` calculates instability per class after all dependencies have been resolved:

```php
$instability = ($usesForInstabilityCount + $usedByCount) > 0
    ? $usesForInstabilityCount / ($usesForInstabilityCount + $usedByCount)
    : 0;
```

Trait dependencies are excluded from Ce (`usesForInstabilityCount`) but still appear in `usesInProjectCount`.

---

## Abstractness (A)

**Original source:** Martin, R. C. (2002). *Agile Software Development.* Chapter 20.

### Formula

```
A = Na / (Na + Nc)
```

| Symbol | Definition |
|--------|-----------|
| Na | Number of abstract classes and interfaces in the package |
| Nc | Number of concrete classes in the package |

### Implementation

`src/Calculators/Helpers/PackageInstabilityAbstractnessCalculator.php`

---

## Distance from Main Sequence (D)

**Original source:** Martin, R. C. (2002). *Agile Software Development.* Chapter 20.

### Formula

```
D = |A + I - 1|
```

### Interpretation

- **Main sequence:** A + I = 1 (the ideal line where abstractness and instability balance)
- **D = 0:** Class sits exactly on the main sequence
- **D = 1:** Maximum deviation — either the "Zone of Pain" (A≈0, I≈0: stable but concrete) or the "Zone of Uselessness" (A≈1, I≈1: abstract but unstable)

### Implementation

`src/Calculators/CouplingCalculator.php` — `afterTraverse()`:

```php
$overallDistanceFromMainline = abs($overallAbstractness + $avgInstability - 1);
```

---

## Halstead Metrics

**Original source:** Halstead, M. H. (1977). *Elements of Software Science.* Elsevier North-Holland.

### What is counted

Two categories of tokens are identified in the AST:

**Operators** — structural AST node types that drive control flow or compute a result:

| Category | AST nodes counted |
|----------|-------------------|
| Arithmetic / binary | `BinaryOp\*` (Plus, Minus, Mul, Div, Mod, …) |
| Assignment | `Expr\Assign`, `Expr\AssignOp\*` |
| Comparison / logical | part of `BinaryOp\*` (Greater, Smaller, Equal, And, Or, …) |
| Unary | `Expr\UnaryMinus`, `Expr\UnaryPlus`, `Expr\BooleanNot`, `Expr\BitwiseNot` |
| Increment / decrement | `Expr\PreInc`, `Expr\PostInc`, `Expr\PreDec`, `Expr\PostDec` |
| Control flow | `Stmt\If_`, `Stmt\ElseIf_`, `Stmt\Else_`, `Stmt\Return_`, `Stmt\While_`, `Stmt\Do_`, `Stmt\For_`, `Stmt\Foreach_`, `Stmt\Switch_`, `Expr\Match_`, `Expr\Ternary` |
| OOP / calls | `Expr\FuncCall`, `Expr\MethodCall`, `Expr\StaticCall`, `Expr\New_`, `Expr\Instanceof_` |
| Error handling | `Stmt\TryCatch`, `Stmt\Catch_`, `Expr\Throw_` |

Each operator is recorded as its fully-qualified PHP-Parser class name (e.g. `PhpParser\Node\Expr\BinaryOp\Plus`), so identical operation types are counted as unique once.

**Operands** — data values identified from these AST node types:

| Node type | Recorded value |
|-----------|---------------|
| `Expr\Variable` | `$node->name` — the variable name string (e.g. `'sum'`) |
| `Node\Param` | `$node::class` — all parameters share the same class name; no per-name distinction |
| `Node\Scalar\*` | `$node->value` — the literal value (integer, float, or string) |
| `Expr\Cast\*` | `$node::class` — cast type (e.g. `PhpParser\Node\Expr\Cast\Int_`) |

> **PHP-Parser note:** `Node\Param` has no `name` or `value` property (it uses `$var` for the variable). The visitor's `isset($node->name)` and `isset($node->value)` checks both fail, so all `Param` nodes resolve to the same class-name string.

Operators and operands are counted independently for **file**, **function/method**, and **class** scopes. File-level totals are the union of all tokens in the file.

### Symbols

| Symbol | Name | Definition |
|--------|------|-----------|
| N1 | Total operators | Sum of all operator token occurrences |
| N2 | Total operands | Sum of all operand token occurrences |
| n1 | Unique operators | Number of distinct operator types |
| n2 | Unique operands | Number of distinct operand values |
| N  | Program length | N = N1 + N2 |
| n  | Vocabulary | n = n1 + n2 |

### Formulas

```
Calculated length  = n × log₂(n)
Volume             = N × log₂(n)
Difficulty         = (n1 / 2) × (N2 / n2)
Effort             = Difficulty × Volume
complexityDensity  = Difficulty / (n + N)   [PhpCodeArcheology extension]
```

### Interpretation

| Metric | What it measures |
|--------|-----------------|
| Volume | Size of the implementation in bits of information |
| Difficulty | How hard it is to write or understand the code |
| Effort | Estimated mental effort required to implement or read the code |
| complexityDensity | Relative complexity per token; high values signal dense/hard-to-parse logic |

### Example (hand-calculated)

See `tests/Feature/Analysis/testfiles/hand-calculated-halstead.php` for a fully annotated fixture.

```
function add($a, $b) { $sum = $a + $b; return $sum; }

Operators: BinaryOp\Plus(1), Expr\Assign(1), Stmt\Return_(1)
  N1=3, n1=3

Operands:  Variable('a')(2), Param_class(2), Variable('b')(2), Variable('sum')(2)
  N2=8, n2=4  { 'a', 'PhpParser\Node\Param', 'b', 'sum' }

n=7, N=11
Volume  = 11 × log₂(7) ≈ 30.88
Difficulty = (3/2) × (8/4) = 3.0
Effort  = 3.0 × 30.88 ≈ 92.64
```

### Implementation

`src/Analysis/HalsteadMetricsVisitor.php` — `countOperators()` and `countOperands()` are called in `leaveNode()` for every node. Metrics are stored per scope and finalised in `leaveNode()` for Function_/ClassMethod/Class_ and `afterTraverse()` for the file.

---

## Maintainability Index (MI)

**Original source:** Oman, P. & Hagemeister, J. (1992). "Metrics for assessing a software system's maintainability." *Proceedings of the International Conference on Software Maintenance (ICSM)*, pp. 337–344.

**Adopted by:** Coleman, D. et al. (1994). "Using Metrics to Evaluate Software System Maintainability." *IEEE Computer*, 27(8), 44–49. — and subsequently the Software Engineering Institute (SEI) at Carnegie Mellon University.

### Formula

```
MI_without_comments = max( 171 − 5.2 × ln(V) − 0.23 × CC − 16.2 × ln(LLOC) , 0 )

commentWeight       = 50 × sin( √(2.4 × CLOC/LOC) )    [when LOC > 0]

MI                  = MI_without_comments + commentWeight
```

> **Important:** The formula uses the **natural logarithm** (`ln`). PHP's `log()` function without a base argument is `ln`, not log₂ or log₁₀.

### What is counted

| Symbol | Name | Definition |
|--------|------|-----------|
| V  | Halstead Volume | N × log₂(n) — see Halstead Metrics section above |
| CC | Cyclomatic Complexity | McCabe's CC for the same scope |
| LLOC | Logical Lines of Code | Non-blank, non-comment source lines (from AST pretty-print) |
| CLOC | Comment Lines of Code | Lines containing only comments |
| LOC | Lines of Code | Total physical lines (used only for comment weight ratio) |

LLOC and CLOC are computed from the **PrettyPrinter output** of the AST nodes, not from the raw source. Comments stored as node attributes are preserved by the printer, so they affect CLOC. Blank lines are stripped before LLOC is counted.

### Interpretation

| Range | Colour | Meaning |
|-------|--------|---------|
| ≥ 85  | Green  | Highly maintainable |
| 65–84 | Yellow | Moderately maintainable |
| < 65  | Red    | Difficult to maintain |

MI > 100 or MI > 171 is possible for very small, comment-rich functions.

### Example (hand-calculated)

See `tests/Feature/Analysis/testfiles/hand-calculated-mi.php` and `tests/Feature/Analysis/fileprovider/test-hand-mi-provider.php` for the full worked calculation.

```
// Hand-calculated Maintainability Index fixture
function simpleReturn($x) { return $x > 0; }

Halstead:  N1=2, n1=2, N2=4, n2=3  →  Volume = 6 × log₂(5) ≈ 13.93

--- function simpleReturn (LLOC=1, CC=1, CLOC=0, LOC=3) ---
MI_WOC = 171 − 5.2×ln(13.93) − 0.23×1 − 16.2×ln(1)
       = 171 − 13.70         − 0.23    − 0
       ≈ 157.07
cW     = 0.0  (no comments in body)
MI     ≈ 157.07

--- file level (LLOC=4, CC=1, CLOC=1, LOC=5) ---
MI_WOC = 171 − 5.2×ln(13.93) − 0.23×1 − 16.2×ln(4)
       = 171 − 13.70         − 0.23    − 22.46
       ≈ 134.61
cW     = 50 × sin(√(2.4 × 1/5))
       = 50 × sin(√0.48) ≈ 50 × 0.639 ≈ 31.94
MI     ≈ 166.55
```

### Implementation

`src/Calculators/MaintainabilityIndexCalculator.php` — runs as a post-parse calculator (after all visitors). It reads `volume`, `cc`, `loc`, `cloc`, `lloc` from the metrics collection and writes `maintainabilityIndex`, `maintainabilityIndexWithoutComments`, `commentWeight`.

```php
$maintainabilityIndexWithoutComments = max(
    171 - 5.2 * log($volume) - 0.23 * $cc - 16.2 * log($lloc),
    0
);
$commentWeight = 50 * sin(sqrt(2.4 * ($cloc / $loc)));
```

Applied to `FileMetricsCollection`, `ClassMetricsCollection`, and `FunctionMetricsCollection`.

---

## Cyclomatic Complexity (CC)

**Original source:** McCabe, T. J. (1976). "A Complexity Measure." *IEEE Transactions on Software Engineering*, SE-2(4), 308–320.

### Formula

```
CC = 1 + number of decision points (linearly independent paths through the code)
```

The baseline of 1 represents the single straight-line path through any function. Each decision point adds one additional path.

### What is counted

| Node type | Notes |
|-----------|-------|
| `if` | +1 |
| `elseif` | +1 (each branch) |
| `for` | +1 |
| `foreach` | +1 |
| `while` | +1 |
| `do` | +1 |
| `catch` | +1 (each catch clause) |
| Ternary `?:` | +1 |
| Null-coalesce `??` | +1 |
| `match` expression | +1 (the whole match, NOT per arm) |
| Non-default `case` in `switch` | +1 per case with an expression condition |
| Spaceship `<=>` | +1 |

**Does NOT count:** `else`, `default` case in switch, `try` block, `finally`.

**Scope:** CC is tracked independently for files, functions, and classes.
- File CC = 1 baseline + all decision points in the file (across all functions and classes)
- Function/method CC = 1 baseline + decision points in that scope
- Class CC = 1 baseline + all decision points in all methods of the class

### Interpretation

| Range | Meaning |
|-------|---------|
| 1 | No branches — linear, easy to test |
| 2–5 | Simple; easy to understand |
| 6–10 | Moderate; worth watching |
| > 10 | High; hard to test and maintain |
| > 20 | Very high; refactoring strongly recommended |

One test case is needed per path, so CC is also a lower bound for the number of unit tests required for full branch coverage.

### Example (hand-calculated)

```php
function complexFunction(int $a, array $b, bool $c): string
{
    if ($a > 0) {                         // +1
        foreach ($b as $item) {           // +1
            if ($item > 5) {              // +1
                return 'big';
            }
        }
    }
    return $c ? 'yes' : 'no';            // +1 (ternary)
}
// CC = 1 (baseline) + 4 = 5
```

See `tests/Feature/Analysis/testfiles/hand-calculated-cc.php` and `tests/Feature/Analysis/fileprovider/test-hand-cc-provider.php` for the full annotated fixture.

### Implementation

`src/Analysis/CyclomaticComplexityVisitor.php` — `getIncreaseForNode()` is called in `leaveNode()` for every node. Returns the increment (0 or 1) for that node type. CC counters (file, function, class) are initialised at 1 and incremented for each decision node encountered.

---

## Cognitive Complexity (CogC)

**Original source:** Campbell, G. A. (2018). "Cognitive Complexity: A new way of measuring understandability." SonarSource. [sonarcloud.io white paper]

**Reference:** Campbell, G. A. (2023). *Cognitive Complexity — A guide to source code difficulty.* SonarSource white paper (v1.8+).

### Formula

```
CogC = Σ structural_increments(1 + nestingLevel) + Σ flat_increments(1) + Σ boolean_sequence_increments(1)
```

Unlike Cyclomatic Complexity, there is **no baseline** — an empty function has CogC = 0.

### What is counted

**Structural increments** (increment = 1 + current nesting level, THEN increase nesting):

| Node type | Nesting change |
|-----------|---------------|
| `if` | pushes nesting |
| `for` | pushes nesting |
| `foreach` | pushes nesting |
| `while` | pushes nesting |
| `do` | pushes nesting |
| `catch` | pushes nesting |
| `switch` | pushes nesting |
| `match` expression | pushes nesting |
| Ternary `?:` | pushes nesting |
| Closures / anonymous functions | pushes nesting (but no increment itself) |

**Flat increments** (always +1, no nesting bonus, do NOT push nesting):

| Node type |
|-----------|
| `elseif` |
| `else` |
| Null-coalesce `??` |

**Boolean sequence increments** (+1 per contiguous run of the same operator type):

- `&&` / `and` — one increment per run, regardless of how many chained `&&`
- `||` / `or` — one increment per run

Sequences are detected via left-child traversal in the AST (PHP binary operators are left-associative). Only the innermost node of a same-operator chain fires (the node whose left child is a different type or a leaf).

**Does NOT count:** `try`, `finally`, `default` case, individual `case` labels.

### Interpretation

| Range | Meaning |
|-------|---------|
| 0 | No control flow — trivial |
| 1–5 | Low — easy to understand |
| 6–10 | Moderate — acceptable |
| 11–20 | High — should be simplified |
| > 20 | Very high — hard to reason about |

### Example (hand-calculated)

```php
function deepNested(int $a, int $b, int $c, array $d): int
{
    if ($a > 0) {                        // +1 (N=0 → +1+0=1), nesting→1
        foreach ($d as $x) {             // +2 (N=1 → +1+1=2), nesting→2
            if ($x > $b) {               // +3 (N=2 → +1+2=3), nesting→3
                while ($c > 0) {         // +4 (N=3 → +1+3=4), nesting→4
                    $c--;
                }
            }
        }
    }
    return $a;
}
// CogC = 1 + 2 + 3 + 4 = 10
```

See `tests/Feature/Analysis/testfiles/hand-calculated-cogc.php` and `tests/Feature/Analysis/fileprovider/test-hand-cogc-provider.php` for the full annotated fixture including boolean sequence examples.

### Implementation

`src/Analysis/CognitiveComplexityVisitor.php` — `enterNode()` handles all increment types. A `nestingStack` is maintained by pushing on structural-node entry and popping on leave. Boolean sequence detection uses `handleBooleanOperator()`, which checks if the left child is the same operator type (skip if yes, increment if no).

---

## LOC — Lines of Code

**Original source:** Derived from traditional software size metrics. The specific LOC/LLOC/CLOC split used here follows common industry conventions (e.g., as described in Jones, C. (1978). "Measuring Programming Quality and Productivity." *IBM Systems Journal*, 17(1), 39–63).

### Formula

```
LOC  = physical line number of the last AST node in the file
CLOC = comment lines in the pretty-printed AST output
LLOC = non-blank, non-comment lines in the pretty-printed AST output
```

### What is counted

**LOC** is obtained from the PHP-Parser AST: the `getEndLine()` of the last node in the `$nodes` array passed to `beforeTraverse()`. This is the last physical line of real code in the file.

**CLOC and LLOC** are computed on the `PrettyPrinter\Standard` output of all non-HTML nodes:

1. Multi-line `/* ... */` comments are counted line by line and removed.
2. Single-line `//` and `#` comments are counted (+1 each) and removed.
3. The remaining string is trimmed and blank lines are stripped.
4. `LLOC = count(preg_split('/\r\n|\r|\n/', $remainingCode))`.

> **Implementation note — `preg_split` artifact:** `preg_split('/\r\n|\r|\n/', "")` returns `[""]` (one-element array), not `[]`. This means a file whose non-comment content is completely empty (e.g., a file with only comments) yields LLOC = 1 instead of 0. This is a known implementation quirk.

**llocOutside** = fileLloc − insideLloc
- `insideLloc` = Σ wholeFunctionLloc + Σ classLloc
- `wholeFunctionLloc` = LLOC of the full function node (declaration + braces + body)
- `classLloc` = LLOC of the full class node (declaration + braces + all methods)

**htmlLoc** = Σ (InlineHTML.getEndLine() − InlineHTML.getStartLine()) for each InlineHTML node.

### Scoped LLOC

For function/method bodies, LLOC is measured on only the **body statements** (not the function declaration line or the braces). For classes, the full class node including the declaration is used.

### Edge cases

| File content | LOC | CLOC | LLOC | Notes |
|-------------|-----|------|------|-------|
| Empty (only `<?php`) | 0 | 0 | 0 | `$nodes = []` — no last node |
| Comments only | 5* | 3* | 1* | PHP-Parser 5.x creates a `Stmt_Nop` node to hold dangling comments; LLOC=1 is the preg_split artifact |
| PHP + inline HTML | actual | from PHP | from PHP | `htmlLoc` counts the HTML span |

*Values for the minimal 3-comment-line fixture in `testfiles/loc-comments-only.php`.

### Example (hand-calculated)

```php
function pureLogic(int $n): int   // line 3
{                                 // line 4
    $a = $n * 2;                  // line 5
    $b = $a + 1;                  // line 6
    return $b;                    // line 7
}                                 // line 8

class PureCalc                    // line 10
{                                 // line 11
    public function multiply(int $a, int $b): int   // line 12
    {                             // line 13
        return $a * $b;           // line 14
    }                             // line 15
}                                 // line 16

// File: LOC=16, LLOC=13, CLOC=0
// pureLogic: LOC=6 (lines 3–8), LLOC=3 (body: 3 statements), wholeFunctionLloc=6
// PureCalc:  LOC=7 (lines 10–16), LLOC=7 (whole class prettyPrint)
// multiply:  LOC=4 (lines 12–15), LLOC=1 (body: return $a * $b)
// llocOutside = 13 − (6 + 7) = 0
```

See `tests/Feature/Analysis/testfiles/hand-calculated-loc.php` and `tests/Feature/Analysis/fileprovider/test-hand-loc-provider.php` for the full annotated fixture.

### Implementation

`src/Analysis/LocVisitor.php`:
- `beforeTraverse()` — calculates file-level LOC/CLOC/LLOC from the node array
- `handleFunctionNode()` — calculates function LLOC (body) and wholeFunctionLloc (full node)
- `leaveNode()` for `Class_` — calculates class LLOC from the full class prettyPrint
- `leaveNode()` for `ClassMethod` — calculates method LLOC (body only)
- `getClocAndLloc(string $code)` — shared helper implementing the comment-stripping and line-counting logic
