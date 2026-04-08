# Roadmap

This document outlines planned features and improvements for PhpCodeArcheology. Items are roughly prioritized but not tied to specific versions or deadlines. Contributions and feedback are welcome — if you'd like to work on something, open an issue first so we can discuss the approach.

## Next Major (3.0)

A major version is in development on the `3.0.x` branch. Tracking happens in the [3.0.0 milestone](https://github.com/PhpCodeArcheology/PhpCodeArcheology/milestone/1).

**Why a major version:** 3.0 reworks how problem thresholds are calculated for metrics that compare against project averages (Effort, Maintainability Index, LCOM). The current "percentage above/below average" rule is mathematically weak on right-skewed distributions — it produces noise in framework-heavy projects where structural patterns (e.g. Doctrine entities) pull the average up and a significant share of classes lands over the threshold by construction. The replacement will use robust statistical outlier detection (Median + MAD or Q3 + IQR).

**Impact:** Problem counts, Refactoring Priority, Health Score, and Technical Debt Score will shift noticeably. History comparisons against 2.x reports will not be directly comparable — a one-time migration notice is planned.

The 2.x line continues to receive bug fixes and non-breaking improvements on `main`. Internal refactorings without user-visible effects (MetricsController split, Config split, visitor cleanup) are not gated on 3.0 and may land in 2.x minors.

## Next Up

- ~~**Graph filter accessibility.** Rework the Knowledge Graph filter chips for better readability and WCAG AA contrast in both dark and light themes. Active vs inactive state needs a clearer visual distinction beyond opacity.~~
- ~~**Graph class/package selector.** Allow focusing the Knowledge Graph on specific classes or packages instead of toggling entire node types. Needs a UX that scales to hundreds of classes (searchable typeahead, package drill-down, or click-to-focus on neighborhood).~~
- **DocBlock display in class/method detail views.** Show descriptive PHPDoc comments alongside metrics in the HTML report, giving more context when reviewing individual classes and methods.
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
