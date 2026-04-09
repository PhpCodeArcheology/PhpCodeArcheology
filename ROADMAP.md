# Roadmap

This document outlines planned features and improvements for PhpCodeArcheology. Items are roughly prioritized but not tied to specific deadlines. Contributions and feedback are welcome — if you'd like to work on something, open an issue first so we can discuss the approach.

## 2.10.0

Tracking: [2.10.0 milestone](https://github.com/PhpCodeArcheology/PhpCodeArcheology/milestone/2)

- **Test coverage for TooComplexPrediction** ([#11](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/11)) — dedicated unit tests for the most complex prediction class, with hand-calculated expected values.
- **DocBlock display in detail views** ([#16](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/16)) — show PHPDoc comments alongside metrics in the HTML report class and method detail views.

## 2.11.0

Tracking: [2.11.0 milestone](https://github.com/PhpCodeArcheology/PhpCodeArcheology/milestone/3)

- **Split MetricsController into Registry / Reader / Writer** ([#13](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/13)) — break up the monolithic MetricsController into focused classes with narrow interfaces. Internal refactoring, no user-visible change.

## 2.12.0

Tracking: [2.12.0 milestone](https://github.com/PhpCodeArcheology/PhpCodeArcheology/milestone/4)

- **Introduce AnalysisContext and finish typed-getter migration** ([#14](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/14)) — separate runtime analysis state from user configuration. Completes the untyped `Config::get()` / `Config::set()` migration.

## 3.0.0 (Next Major)

Tracking: [3.0.0 milestone](https://github.com/PhpCodeArcheology/PhpCodeArcheology/milestone/1). Development happens on the `3.0.x` branch.

**Why a major version:** 3.0 reworks how problem thresholds are calculated for metrics that compare against project averages (Effort, Maintainability Index, LCOM). The current "percentage above/below average" rule is mathematically weak on right-skewed distributions — it produces noise in framework-heavy projects where structural patterns (e.g. Doctrine entities) pull the average up and a significant share of classes lands over the threshold by construction. The replacement will use robust statistical outlier detection (Median + MAD or Q3 + IQR).

**Impact:** Problem counts, Refactoring Priority, Health Score, and Technical Debt Score will shift noticeably. History comparisons against 2.x reports will not be directly comparable — a one-time migration notice is planned.

- **Threshold design prototype** ([#12](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/12)) — run Median+MAD and Q3+IQR on real projects, pick the algorithm based on data.
- **Threshold rework** ([#15](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/15)) — replace relative-to-average with the chosen statistical outlier detection. Recalibrate Health Score, Technical Debt Score, and Refactoring Priority.

The 2.x line continues to receive bug fixes and non-breaking improvements on `main`. The internal refactorings in 2.10–2.12 prepare the codebase for the 3.0 threshold rework.

## Next Up

- **More test coverage.** Currently at ~75% line coverage with 720 tests. Goal: 85%+, especially for the remaining Prediction rules and Report DataProviders.

## Planned

- **Incremental analysis.** Cache AST parse results and only re-analyze changed files. Prerequisite for `--watch` mode. Significant performance improvement for large codebases.
- **Custom rules.** User-defined problem detection rules via config. Configurable thresholds already exist (since v2.7.0) — this extends it to custom rule logic (e.g., "flag classes matching pattern X with metric Y above Z").
- **Plugin system.** Extensibility via external plugins — custom metrics, custom report formats, custom rules.

## Ideas

These are longer-term ideas that may or may not happen. If any of these would be particularly useful for your workflow, let us know by opening an issue.

- **`--watch` mode.** Continuous analysis that re-runs on file changes (depends on incremental analysis).
- **History memory optimization.** Compact history storage for projects with many analysis runs (partially implemented — further improvements possible).
- **Additional report formats.** Confluence, Notion, or other integrations based on demand.

## Contributing

If you'd like to contribute, check the [open issues](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues) for bugs and feature requests. For roadmap items, open an issue to discuss before starting work — some of these have architectural implications that are worth aligning on first.
