# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
