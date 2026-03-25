# Metric Reference

PhpCodeArcheology measures 60+ metrics across files, classes, functions, and the entire project. This document describes each metric category, what it measures, and how to interpret the values.

## Table of Contents

- [Size Metrics](#size-metrics)
- [Complexity Metrics](#complexity-metrics)
- [Maintainability Metrics](#maintainability-metrics)
- [Halstead Metrics](#halstead-metrics)
- [Type Coverage Metrics](#type-coverage-metrics)
- [Documentation Coverage Metrics](#documentation-coverage-metrics)
- [Code Duplication Metrics](#code-duplication-metrics)
- [Coupling & Dependency Metrics](#coupling--dependency-metrics)
- [Cohesion Metrics](#cohesion-metrics)
- [Inheritance Metrics](#inheritance-metrics)
- [Package Metrics](#package-metrics)
- [SOLID Metrics](#solid-metrics)
- [Git Metrics](#git-metrics)
- [Project-Level Scores](#project-level-scores)
- [Refactoring Priority](#refactoring-priority)
- [Problem Detection Rules](#problem-detection-rules)

---

## Size Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Lines of Code | LOC | File, Class, Function | Total lines including comments and blanks. |
| Logical Lines of Code | LLOC | File, Class, Function | Lines without comments and empty lines. The primary size indicator. |
| Comment Lines of Code | CLOC | File, Class, Function | Lines containing only comments. Higher is generally better. |
| HTML Lines of Code | HTML LOC | File | Lines containing HTML output mixed with PHP. Lower is better. |
| LLOC Outside | LLOC outside | File | Logical lines of code outside of classes and functions. Lower is better. |
| Comment Weight | CW | File | Ratio of comment lines to logical lines (`CLOC / LLOC`). |
| Output Count | OC | File, Class, Function | Number of `echo`/`print`/output statements. |

**Interpretation:** LLOC is the most meaningful size metric. A file with 400+ LLOC or a method with 30+ LLOC is typically flagged as "too long".

## Complexity Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Cyclomatic Complexity | CC | File, Class, Function | Number of independent execution paths (McCabe). Each `if`, `case`, `&&`, `\|\|`, `catch`, `?:` adds 1. |
| Cognitive Complexity | CogC | File, Class, Function | How difficult code is to *understand* (SonarSource model). Penalises nesting depth and breaks in linear flow. |
| Max Cyclomatic Complexity | Max CC | File, Class | Highest CC of any method/function within this entity. |
| Avg Cyclomatic Complexity | Avg CC | Class | Average CC across all methods in the class. |
| Avg Cognitive Complexity | Avg CogC | Class | Average cognitive complexity across all methods in the class. |
| Complexity Density | Compl. dens. | File, Class, Function | Ratio of Halstead Difficulty to code size: `D / (n + N)`. Higher values indicate more complex interactions between code elements. |
| Estimated Runtime Complexity | Runtime | File, Class, Function | Estimated Big-O complexity based on maximum loop nesting depth. |

**Estimated Runtime Complexity values:**

| Max Loop Nesting | Complexity Class |
|-------------------|-----------------|
| 0 | O(1) |
| 1 | O(n) |
| 2 | O(n²) |
| 3+ | O(n³+) |

**CC Thresholds (configurable):**

| CC Value | Rating | Action |
|----------|--------|--------|
| 1–5 | Excellent | No action needed |
| 6–10 | Good | Acceptable for most code |
| 11–20 | Moderate | Consider refactoring |
| 21+ | High | Refactoring strongly recommended |

## Maintainability Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Maintainability Index | MI | File, Class, Function | Composite score from CC, Halstead Volume, and LOC. Scale 0–171, higher is better. |
| MI without Comments | MI w/o | File, Class, Function | Same as MI but ignoring comment contribution. Shows raw code maintainability. |

**Thresholds:**

| MI Value | Rating | Interpretation |
|----------|--------|----------------|
| 85+ | Good | Easy to maintain |
| 65–84 | Moderate | Some refactoring may help |
| < 65 | Low | Difficult to maintain, refactoring recommended |

**Formula:** `MI = 171 - 5.2 × ln(V) - 0.23 × CC - 16.2 × ln(LOC) + 50 × sin(√(2.4 × CM))`

Where V = Halstead Volume, CC = Cyclomatic Complexity, LOC = Lines of Code, CM = Comment ratio.

## Halstead Metrics

Based on counting distinct operators and operands in the code.

| Metric | Symbol | Description |
|--------|--------|-------------|
| Vocabulary | n | Total distinct operators + operands (`n1 + n2`). |
| Length | N | Total occurrences of operators + operands (`N1 + N2`). |
| Calculated Length | N̂ | Estimated length: `n1 × log2(n1) + n2 × log2(n2)`. |
| Volume | V | Information content: `N × log2(n)`. Measures the "size" of an algorithm. |
| Difficulty | D | Error-proneness: `(n1/2) × (N2/n2)`. Higher = harder to write correctly. |
| Effort | E | Implementation effort: `D × V`. |
| Estimated Bugs | B | Predicted bugs: `V / 3000`. |
| Time to Implement | T | Estimated seconds: `E / 18`. |

**Interpretation:** Volume indicates how much information a reader must absorb. Difficulty indicates how error-prone the code is. Both should be kept low.

## Type Coverage Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Type Coverage | Type Cov. | File, Class, Function | Weighted percentage of typed parameters, return types, and properties. |
| Typed Parameters | — | File, Class | Number of parameters with type declarations. |
| Total Parameters | — | File, Class | Total number of parameters. |
| Typed Return Types | — | File, Class | Number of functions/methods with return type declarations. |
| Typed Properties | — | File, Class | Number of properties with type declarations. |
| Parameter Count | Params | Function | Total parameters of a function/method. |
| Optional Parameters | Opt. Params | Function | Parameters with default values. |
| Nullable Parameters | Nullable Params | Function | Nullable parameters. |

**Type Coverage Formula:**

```
Type Coverage = (paramCoverage × 0.25 + returnCoverage × 0.35 + propertyCoverage × 0.40) × 100
```

Where each sub-coverage is the ratio of typed to total items. If a class has no properties, the weights are redistributed proportionally across parameters (0.4167) and returns (0.5833).

**Interpretation:** Higher type coverage means better IDE support, fewer runtime errors, and easier refactoring. The weighting emphasises properties (40%) and return types (35%) over parameters (25%), because untyped properties and returns tend to cause more subtle bugs.

## Documentation Coverage Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Documentation Coverage | Doc Cov. | File, Class | Percentage of public methods/functions with PHPDoc blocks. |
| Has Docblock | — | Function | Whether the function/method has a PHPDoc block. |
| Parameter Doc Coverage | Param Doc | Function | Percentage of parameters documented in `@param` tags. |

**Formula:**

- Classes: `docCoverage = documentedPublicMethods / totalPublicMethods × 100`
- Files: `docCoverage = documentedFunctions / totalFunctions × 100`

Magic methods are excluded. Returns 100% if there are no methods/functions to document.

## Code Duplication Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Duplication Rate | Dupl. | File, Project | Percentage of code that is duplicated across files. |
| Duplicated Lines | — | File | Number of lines involved in duplication. |

**Detection Algorithm:**

1. PHP source is tokenised and normalised (variables → `$V`, strings → `"S"`, numbers → `0`)
2. Sliding windows of 50 normalised tokens are hashed (rolling hash)
3. Matching hashes across files or locations indicate duplicated code blocks
4. Per-file rate: `duplicatedLines / LOC × 100`

**Interpretation:** Duplicated code increases maintenance burden — a bug fix in one copy must be applied to all copies. Focus on extracting shared logic into reusable functions or classes.

## Coupling & Dependency Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Uses Count | Ce | File, Class | Number of other classes/files this entity depends on (efferent coupling). |
| Used By Count | Ca | File, Class | Number of other classes/files that depend on this entity (afferent coupling). |
| Instability | I | Class, Package | `Ce / (Ce + Ca)`. Range 0 (stable) to 1 (unstable). |
| Dependency Cycle | — | Class | Whether the class is part of a circular dependency. |
| Dependency Cycle Length | — | Class | Number of classes involved in the cycle. |
| Layer Violations | — | Class | Architectural layer violations (e.g. Repository depending on Controller). |

**Interpretation:** High efferent coupling means a class depends on many others — changes ripple inward. High afferent coupling means many others depend on it — changes here have wide impact. Dependency cycles should always be resolved.

## Cohesion Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Lack of Cohesion of Methods | LCOM | Class | Measures how related a class's methods are to each other via shared properties. |

**Interpretation:** LCOM = 0 means perfect cohesion (all methods use the same properties). High LCOM suggests the class has multiple responsibilities and should be split.

## Inheritance Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Depth of Inheritance Tree | DIT | Class | How deep this class sits in the inheritance hierarchy. |
| Number of Children | NOC | Class | Number of direct subclasses. |

**Thresholds:**

| DIT Value | Rating |
|-----------|--------|
| 0–3 | Good |
| 4–5 | Warning — deep hierarchy adds complexity |
| 6+ | Error — very deep, consider composition over inheritance |

## Package Metrics

Packages are derived from PHP namespaces (configurable depth via `packageSize`).

| Metric | Abbr. | Description |
|--------|-------|-------------|
| Relational Cohesion | H | Internal relationships: `(R+1)/N`. Higher means the package is more self-contained. |
| Abstractness | A | Ratio of abstract classes/interfaces to total classes. Range 0–1. |
| Instability | I | Same as class-level instability, but for the package. |
| Distance from Main Sequence | D | `\|A + I - 1\|`. Measures balance between abstractness and instability. 0 is ideal. |
| Efferent Coupling | Ce | Number of packages this package depends on. |
| Afferent Coupling | Ca | Number of packages that depend on this package. |

**The Main Sequence:** Packages should ideally sit on the line where `A + I = 1`. Packages far from this line are either too abstract without dependents ("zone of uselessness") or too concrete with many dependents ("zone of pain").

## SOLID Metrics

| Metric | Description |
|--------|-------------|
| SOLID Violation Count | Total number of detected SOLID principle violations. |
| SRP Violation | Single Responsibility Principle — class has too many responsibilities. |
| ISP Violation | Interface Segregation Principle — interface is too large. |
| DIP Score | Dependency Inversion — percentage of dependencies on abstractions vs. concrete classes. Higher is better. |

## Git Metrics

Requires git integration to be enabled (default: `true`).

| Metric | Applies to | Description |
|--------|------------|-------------|
| Change Frequency (Churn) | File | Number of commits modifying this file in the analysis period. |
| Code Age | File | Days since last modification. |
| Author Count | File | Number of distinct authors. |
| Last Modified | File | Date of the most recent commit. |
| Authors | File | List of authors who modified the file. |
| Total Commits | Project | Total commits in the analysis period. |
| Active Authors | Project | Distinct commit authors in the period. |
| Analysis Period | Project | Timeframe used for git analysis (configurable via `git.since`). |

**Hotspots:** Files with both high churn (many changes) and high complexity are "hotspots" — the highest-risk files in your codebase. These are prime refactoring candidates.

## Project-Level Scores

### Health Score (0–100, Grade A–F)

A weighted composite score reflecting overall project quality across 9 dimensions:

| Component | Weight | Description | Scoring |
|-----------|--------|-------------|---------|
| Maintainability Index | 15% | Average MI across all files | MI 40 = 0, MI 120+ = 100 |
| Problem Density | 10% | Errors and warnings per entity | Logarithmic decay: `100 - 30 × log(1 + density)` |
| Cyclomatic Complexity | 10% | Average CC across all code | `100 - (avgCC - 1) × 5` |
| Coupling Balance | 10% | Distance from main sequence | `(1 - avgDistance) × 100` |
| Code Structure | 5% | LLOC outside classes/functions | `(1 - outsideRatio) × 100` |
| HTML-in-PHP Ratio | 15% | Inline HTML mixed with PHP | Cubic decay: `100 × (1 - htmlRatio)³` |
| Encapsulation Quality | 15% | Method visibility + static ratio | 60% private score + 40% static score |
| Dependency Health | 10% | Cycles and classes in cycles | Penalises cycle breadth and count |
| Abstractness | 10% | Ratio of abstracts/interfaces | Reaches 100 at 10% abstractness |

| Grade | Score | Interpretation |
|-------|-------|----------------|
| A | 90–100 | Excellent — well-maintained codebase |
| B | 80–89 | Good — minor issues |
| C | 65–79 | Moderate — some areas need attention |
| D | 50–64 | Poor — significant quality issues |
| F | < 50 | Critical — major refactoring needed |

### Technical Debt Score

Weighted problem points per 100 logical lines of code. Available at file, class, and project level.

- Error = 3 points
- Warning = 1 point
- Info = 0.5 points

Formula: `Score = (total points / total LLOC) × 100`

Lower is better. A score of 0 means no detected problems.

### Encapsulation Score (Project-Level)

Combined score (0–100) measuring method visibility distribution and static method usage:

```
Encapsulation = 0.6 × privateScore + 0.4 × staticScore
```

- **Private score:** `min(100, privateRatio × 333)` — reaches 100 when 30%+ methods are non-public
- **Static score:** `max(0, 100 - max(0, staticRatio - 0.10) × 200)` — free zone up to 10% static, then -20 points per additional 10%

Higher values indicate better encapsulation and testability.

### Additional Project-Level Metrics

| Metric | Description |
|--------|-------------|
| Duplication Rate | Overall code duplication percentage across all files |
| HTML-in-PHP Ratio | Ratio of inline HTML to total LOC |
| Public Method Ratio | Percentage of public methods across all classes |
| Static Method Ratio | Percentage of static methods across all classes |
| Dependency Cycles | Number of circular dependency cycles detected |
| Classes in Cycles | Number of classes involved in circular dependencies |

## Refactoring Priority

A per-class score (0–100) that ranks which classes should be refactored first, combining problem severity with impact factors. Available via the `get_refactoring_priorities` MCP tool.

### Severity Score (5 weighted factors)

| Factor | Weight | Formula |
|--------|--------|---------|
| Problem Score | 30% | `min(100, errors × 12 + warnings × 4 + infos)` |
| Complexity Score | 25% | `min(100, (CC - 5) × 4)` |
| Cohesion Score | 15% | `min(100, (LCOM - 1) × 20)` |
| Size Score | 10% | `min(100, (LLOC - 100) × 0.25)` |
| Structural Score | 20% | Sum of: in-cycle (30pts), cycle length (5pts each, max 30), layer violations (10pts each, max 20), SOLID violations (5pts each, max 20) |

### Impact Multiplier

The severity is scaled by how much impact the class has:

| Factor | Max Bonus | Calculation |
|--------|-----------|-------------|
| Used By (coupling) | +40% | `usedFromOutside × 0.04` |
| Git Churn | +30% | `churnCount × 0.015` |
| Author Count | +15% | `authorCount × 0.05` |
| Recently Modified | +15% | +15% if < 90 days, +8% if < 365 days |

### Final Score

```
Priority = min(100, severity × impact / 2.0)
```

### Drivers

Each class is assigned one or more "drivers" — the dominant reasons it needs refactoring:

| Driver | Condition |
|--------|-----------|
| High complexity | Complexity score >= 60 |
| Low cohesion | Cohesion score >= 40 |
| Dependency cycle | Class is in a cycle |
| Layer violations | Layer violation count > 0 |
| SOLID violations | Structural score >= 40 with SOLID violations |
| Many issues | Problem score >= 50 |
| Excessive size | Size score >= 50 |

### Project-Level Aggregates

| Metric | Description |
|--------|-------------|
| Avg. Refactoring Priority | Average priority across all concrete/abstract classes |
| Max Refactoring Priority | Highest priority score found in any class |
| Classes Needing Refactoring | Number of classes with priority > 0 |

## Problem Detection Rules

PhpCodeArcheology includes 13 built-in problem detectors:

| Rule | Level | What it detects |
|------|-------|-----------------|
| Too Long | Error/Warning | Files, classes, or methods exceeding size thresholds |
| Too Complex | Error/Warning | High cyclomatic or cognitive complexity |
| God Class | Error | Classes with too many methods, properties, and high LCOM |
| Too Dependent | Warning | Excessive efferent coupling |
| Too Many Parameters | Error/Warning | Functions/methods with too many parameters |
| Low Type Coverage | Error/Warning | Insufficient type declarations on parameters/returns |
| Deep Inheritance | Error/Warning | Inheritance trees that are too deep |
| Too Much HTML | Warning | PHP files with excessive HTML mixed in |
| Dead Code | Info | Unused private methods within a class |
| Security Smell | Warning | Potential security issues (eval, shell_exec, etc.) |
| Dependency Cycle | Error | Circular dependencies between classes |
| SOLID Violation | Warning | Detected SRP or ISP violations |
| Hotspot | Warning | Files with high churn AND high complexity |

All thresholds are configurable via `php-codearch-config.yaml`. See the [Configuration section](../README.md#configuration) in the README.
