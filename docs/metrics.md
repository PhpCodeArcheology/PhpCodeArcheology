# Metric Reference

PhpCodeArcheology measures 60+ metrics across files, classes, functions, and the entire project. This document describes each metric category, what it measures, and how to interpret the values.

## Table of Contents

- [Size Metrics](#size-metrics)
- [Complexity Metrics](#complexity-metrics)
- [Maintainability Metrics](#maintainability-metrics)
- [Halstead Metrics](#halstead-metrics)
- [Coupling & Dependency Metrics](#coupling--dependency-metrics)
- [Cohesion Metrics](#cohesion-metrics)
- [Inheritance Metrics](#inheritance-metrics)
- [Package Metrics](#package-metrics)
- [SOLID Metrics](#solid-metrics)
- [Git Metrics](#git-metrics)
- [Project-Level Scores](#project-level-scores)
- [Problem Detection Rules](#problem-detection-rules)

---

## Size Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Lines of Code | LOC | File, Class, Function | Total lines including comments and blanks. |
| Logical Lines of Code | LLOC | File, Class, Function | Lines without comments and empty lines. The primary size indicator. |
| Comment Lines of Code | CLOC | File, Class, Function | Lines containing only comments. Higher is generally better. |
| HTML Lines of Code | HTML LOC | File | Lines containing HTML output mixed with PHP. Lower is better. |
| Comment Weight | CW | File | Ratio of comment lines to logical lines (`CLOC / LLOC`). |

**Interpretation:** LLOC is the most meaningful size metric. A file with 400+ LLOC or a method with 30+ LLOC is typically flagged as "too long".

## Complexity Metrics

| Metric | Abbr. | Applies to | Description |
|--------|-------|------------|-------------|
| Cyclomatic Complexity | CC | File, Class, Function | Number of independent execution paths (McCabe). Each `if`, `case`, `&&`, `\|\|`, `catch`, `?:` adds 1. |
| Cognitive Complexity | CogC | File, Class, Function | How difficult code is to *understand* (SonarSource model). Penalises nesting depth and breaks in linear flow. |
| Max Cyclomatic Complexity | Max CC | File, Class | Highest CC of any method/function within this entity. |
| Avg Cyclomatic Complexity | Avg CC | Class | Average CC across all methods in the class. |

**Thresholds (configurable):**

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
| Volume | V | Information content: `N × log2(n)`. Measures the "size" of an algorithm. |
| Difficulty | D | Error-proneness: `(n1/2) × (N2/n2)`. Higher = harder to write correctly. |
| Effort | E | Implementation effort: `D × V`. |
| Estimated Bugs | B | Predicted bugs: `V / 3000`. |
| Time to Implement | T | Estimated seconds: `E / 18`. |

**Interpretation:** Volume indicates how much information a reader must absorb. Difficulty indicates how error-prone the code is. Both should be kept low.

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
| Total Commits | Project | Total commits in the analysis period. |
| Active Authors | Project | Distinct commit authors in the period. |

**Hotspots:** Files with both high churn (many changes) and high complexity are "hotspots" — the highest-risk files in your codebase. These are prime refactoring candidates.

## Project-Level Scores

### Health Score (0–100, Grade A–F)

A weighted composite score reflecting overall project quality:

| Component | Weight | Source |
|-----------|--------|--------|
| Maintainability Index | 30% | Average MI across all files |
| Problem Density | 25% | Errors and warnings per entity |
| Cyclomatic Complexity | 20% | Average CC across all code |
| Coupling Balance | 15% | Distance from main sequence |
| Code Structure | 10% | Ratio of code inside vs. outside classes |

| Grade | Score | Interpretation |
|-------|-------|----------------|
| A | 90–100 | Excellent — well-maintained codebase |
| B | 80–89 | Good — minor issues |
| C | 65–79 | Moderate — some areas need attention |
| D | 50–64 | Poor — significant quality issues |
| F | < 50 | Critical — major refactoring needed |

### Technical Debt Score

Weighted problem points per 100 logical lines of code:

- Error = 3 points
- Warning = 1 point
- Info = 0.5 points

Formula: `Score = (total points / total LLOC) × 100`

Lower is better. A score of 0 means no detected problems.

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
