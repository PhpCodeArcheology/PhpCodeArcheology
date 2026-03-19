# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.4.1] - 2026-03-19

### Fixed
- **`exclude` config was structurally broken since inception.** The negative-lookahead regex tried to match absolute exclude paths character-by-character inside the file path suffix, which could never work. Replaced the fragile regex approach with a simple `str_starts_with` prefix check. `exclude: ["../lib/phpword"]` now actually excludes files.

## [1.4.0] - 2026-03-19

### Changed
- **Health Score v2: rebalanced formula with 4 new factors.** The score now evaluates 9 dimensions instead of 5. Legacy codebases with heavy HTML-in-PHP mixing, poor encapsulation, dependency cycles, or low abstractness are no longer rewarded with artificially high scores. Clean, well-structured projects score the same or higher than before.
  - **Weights rebalanced:** MI 30→15%, Problems 25→10%, CC 20→10%, Coupling 15→10%, Structure 10→5%.
  - **HTML-in-PHP Ratio (15%):** Cubic decay curve penalizes inline HTML mixing. 0% HTML = no penalty, 59% HTML ≈ 7/100.
  - **Encapsulation Quality (15%):** Combines non-public method ratio (60%) and static method penalty (40%). Rewards projects with proper visibility modifiers and low static usage.
  - **Dependency Health (10%):** Penalizes both the breadth of dependency cycles (% of classes affected) and the cycle count itself.
  - **Abstractness (10%):** Projects with interfaces and abstract classes score higher. Reaches full score at 10% abstractness.
- **New dashboard metrics:** HTML-in-PHP ratio, Public method ratio, Static method ratio, and Encapsulation score are now displayed as metric tiles.

### Added
- **Git analysis progress bar.** The file-level git metadata collection now shows a progress bar with ETA, replacing the static "Running Git analysis..." message. Especially useful for large repositories with thousands of files.

### Fixed
- **`exclude` config option was broken.** Exclude paths were never resolved to absolute paths via `realpath()`, so the negative-lookahead regex never matched. Relative excludes like `../lib/phpword` now work correctly.
- `PredictionTrait::shouldSkipLcom()` crashed with `strrchr(): Argument #1 must be of type string, null given` when an interface name in the implemented-interfaces list was null.
- PHP deprecation warning in `ProgressBar::calculateEta()` — implicit float-to-int conversion on modulo operation.

## [1.3.1] - 2026-03-19

### Fixed
- **Health Score was calculated before problem counts existed.** `HealthScoreCalculator` ran before predictions, so error/warning counts were always 0. Moved to post-prediction phase so the score now correctly reflects problem density.

## [1.3.0] - 2026-03-19

### Changed
- **Health Score formula rebalanced.** MI normalization now uses a realistic scale (MI 40–120 → 0–100) instead of the theoretical 0–171 range. Problem density uses logarithmic decay instead of a linear cliff, so the score degrades gracefully instead of crashing to 0. Error/warning weighting simplified (severity is already handled by the problem system).

### Fixed
- Coupling score now uses `abs()` for distance from main sequence, preventing negative values from inflating the score beyond 100.

## [1.2.0] - 2026-03-19

### Added
- **Class-type-aware LCOM suppression.** Enums, interfaces, traits, and classes with 0-1 methods no longer trigger LCOM warnings (structurally meaningless). Classes matching name patterns (`*Exception`, `*Error`) or implementing handler interfaces (`EventSubscriberInterface`, etc.) are also excluded. Fully configurable via `thresholds.lcomExclude` in YAML config.
- Markdown metric tiles now render array values correctly (fixes Twig "Array to string conversion" warnings).

### Fixed
- Markdown `metric-tile.md.twig` used incorrect property accessors on `MetricValue` objects, causing PHP warnings.
- HTML `metric-tile.html.twig` hardened against array values in the `: ` split check.

## [1.1.2] - 2026-03-19

### Fixed
- PHP deprecation warning in `InheritanceDepthCalculator` when a class extends a parent that the parser couldn't resolve (null array key).

## [1.1.1] - 2026-03-19

### Fixed
- **Git churn data was never assigned to files.** `GitAnalyzer` used `get('filePath')` which returns `null` for `FileMetricsCollection` (skipped by `FileCalculator`). Changed to `getPath()` which returns the correct absolute path. This means the hotspot chart, churn counts, author counts, and code age now actually work.
- **Empty metric tiles** for counters like "Function count" when no items exist. All counter metrics now default to 0 instead of remaining `null`.
- **Hotspot chart** normalized Change Frequency to 0–100% scale and fixed color thresholds.
- **Tooltips cut off** on dashboard: removed `overflow: hidden` from `.dash-panel`.
- **Grade metric** now has a full description with thresholds (A≥90, B≥80, C≥65, D≥50, F<50) in tooltip and glossary.

## [1.1.0] - 2026-03-19

### Added
- New `git.root` config option to specify the Git repository root directory, useful for monorepos or when analyzing subdirectories. Configurable via YAML (`git.root: "../"`) or CLI (`--git-root=DIR`).

### Fixed
- **CRITICAL: Running without config file could delete entire filesystem.** `realpath()` returned `false` for non-existent default report directory, which PHP concatenated to `"/"`. The `clearReportDir()` method then recursively deleted from root. Fixed by replacing `realpath()` with direct path + `mkdir()`, and adding safety guards in `ReportTrait` that refuse to delete paths close to filesystem root.
- `--report-dir` with relative paths (e.g. `../reports`) now works correctly — resolved against the working directory and created automatically if missing.
- `reportDir` handling is now consistent across CLI, YAML config, and default: relative paths are resolved, directories are created on demand, and `realpath()` is only called after the directory exists (with safe fallback for Docker/mount environments).

### Changed
- README: clarified zero-config usage, added `.gitignore` tip, documented `--git-root` and `git.root`.

## [1.0.0] - 2026-03-18

First stable release.

### Upgrade Notes (from 0.x)
- `--report-type` CLI flag now takes precedence over config file (was a bug)
- Problem counts may change: LowTypeCoverage now has two severity tiers, MI threshold is more lenient for well-typed code
- `Application::run()` returns `int` (exit code) instead of `void` — only relevant if using PhpCodeArcheology as a library

### Added
- 60+ metrics per file, class, function, and method
- Git integration: churn, code age, authors, hotspot detection
- 5 report formats: HTML, Markdown, JSON, SARIF, AI Summary
- 13 problem detection rules with configurable thresholds
- Health Score (A-F) and Technical Debt Score
- Trend charts (Quality + Problems) across multiple runs
- Metric glossary page with descriptions and thresholds
- Tooltips on all metric tiles
- CLI: progress bars with ETA, `--quick` mode, `--no-color`, summary output
- Subcommands: `init`, `compare`, `baseline create/check`
- `--fail-on=error|warning` and `--generate-claude-md` flags
- YAML configuration with custom thresholds and quality gates
- History tracking with duplicate detection
- Phar build support and Docker image
- 130 tests (Pest PHP)

## [0.0.1] - 2024-01-02

### Added
- Changelog file.
- First html template (experimental)
