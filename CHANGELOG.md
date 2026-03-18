# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
