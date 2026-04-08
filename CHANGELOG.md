# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`coverageFile` config option.** The Clover XML path can now be set persistently in `php-codearch-config.yaml` / `.phpcodearch.json` instead of passing `--coverage-file` on every run. Relative paths are resolved against the project's running directory. The CLI flag still takes precedence over the config value.
- **Symfony-style Clover auto-detection.** `var/reports/clover.xml` and `var/coverage/clover.xml` are now part of the auto-detection candidate list, alongside the existing root, `coverage/`, `build/logs/`, and `build/coverage/` locations.
- **Project logo.** New logo (compass-graph hybrid) added to README, HTML report header, and as favicon.
- **Knowledge Graph: focus selector.** Searchable combobox to focus the graph on specific classes, packages, or authors. Type to search, click to select — the graph shows only the selected entities and their direct neighbors. Selected items appear as removable chips below the controls. Double-click any node in the graph to focus on it. Selecting an author automatically activates the `authored_by` edge type. Supports keyboard navigation (arrows, Enter, Escape).

### Changed

- **Metric tiles: dynamic font sizing.** Long metric values (namespace lists, refactoring recommendations) now scale down automatically instead of overflowing. Word-break at backslashes for readable namespace wrapping.
- **Metric tiles: empty value indicator.** Tiles with no value now show a subtle dash instead of being completely empty.
- **Knowledge Graph filter chips: accessibility overhaul.** Replaced opacity-based active/inactive toggle with filled/outline pattern. Active chips now use darkened color backgrounds with white text, inactive chips are transparent with muted text. All combinations pass WCAG AA contrast (4.5:1+) in both dark and light themes. Added keyboard navigation (Tab + Enter/Space), focus-visible ring, and `role="button"`. Fixed German labels ("Node-Typen"/"Edge-Typen" → "Nodes"/"Edges"). Previously invisible chips in light mode (`depends_on`, `belongs_to`, `declares`) now use visible slate colors.
- **Glossary page redesigned.** Replaced the flat table layout with grouped panels per metric category (Class, File, Function, Method, Package, Project, Git). Each metric shows name, description, type badge, and better-direction badge. Threshold table restyled with severity badges. All colors pass WCAG AA contrast in both dark and light themes.
- **17 metric descriptions improved.** Replaced placeholder descriptions (e.g. "LCOM.", "Instability.") with proper explanations including formulas and interpretation guidance for LCOM, Instability, all Halstead metrics, Complexity Density, Maintainability Index, Comment Weight, and Package metrics (Abstractness, Distance, Coupling).
- **Tailwind CSS upgraded from v3 to v4.** Migrated config from `tailwind.config.js` to CSS-native `@theme` and `@utility` directives. Resolves all npm security advisories.
- **Twig updated to v3.24.0.** Resolves sandbox bypass and `__toString()`/`__isset()` security advisories.
- **Dependabot enabled.** Automated dependency update PRs for Composer (weekly), npm (monthly), and GitHub Actions (weekly).

## [2.8.0] - 2026-04-04

### Added

- **Composer Plugin.** PhpCodeArcheology now registers as a Composer plugin, adding the `composer codearch:analyze` command. When no path is given and no config file exists, it auto-detects PSR-4 source directories from `composer.json`. All CLI options are supported via `composer codearch:analyze -- --quick`, `--report-type`, `--coverage-file`, etc.
- **Default directory exclusions.** `vendor/`, `node_modules/`, and `.git/` are now excluded from analysis automatically, even without a config file. The `init` command also includes `vendor` and `node_modules` in the generated config. Fixes [#9](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/9).
- **`memoryLimit` config option documented** in sample config and README with usage examples.
- **259 new tests.** FileList exclusion logic (19), Config memory limit handling (13), YAML/JSON config file parsing (32), integration test for analysis pipeline (12), Problem value objects (96), Prediction tests for DeadCode/TooManyParams/DeepInheritance/TooLong/GodClass/DependencyCycle (87). Test count: 440 → 699.
- **`/release` slash command** for automated releases (version detection, changelog update, tagging, GitHub release).

### Changed

- **Architecture refactoring.** `Application.php` split from 614 to 175 LLOC: `AnalysisPipeline`, `BootstrapService`, `ReportOrchestrator`, `CalculatorRegistry`, `PredictionRegistry`, `ServiceFactory` extracted. Interfaces added: `OutputInterface`, `MetricsReaderInterface`, `MetricsWriterInterface`, `AnalysisConfigInterface`. Typed Config getters replace raw `get()` calls.
- **Monster methods split.** `ImpactAnalysisTool` CC 76→5, `RefactoringTool` CC 51→5, `GetTestCoverageTool` CC 44→5, `HalsteadMetricsVisitor::countOperators` CC 35→5, `HtmlReport::generateIndexPage` CC 38→1, `HistoryService::setDeltas` CC 22→9, `HotspotsTool` CC 26→8.
- **GraphDataProvider split** into `ClassNodeCollector`, `EdgeCollector`, `PackageNodeCollector`.
- **Halstead Difficulty threshold recalibrated.** Default raised from 20 to 30 (45 for framework projects).
- **Test mapping improved.** Classes with Clover XML coverage are now recognized as tested.
- **Internal task files removed from repository.**
- **History memory optimization.** Stream-based reading (fseek instead of file()), selective metric loading for trend charts (8 metrics instead of full entries), last-run caching. New entries are written in compact format (project-level metrics only). Old full entries remain readable — no migration needed.

### Fixed

- **`memory_limit=-1` from php.ini was overridden.** The tool always set `memory_limit` to `1G`, ignoring the system setting. Now respects unlimited (`-1`) from php.ini and only bumps to 1G when the current limit is lower. The `memoryLimit` config option also accepts `"-1"` for unlimited. Fixes [#9](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/9).
- **`memoryLimit` from config file was ignored.** The YAML/JSON config parser did not map the `memoryLimit` key to the application config, so the default of `1G` was always used regardless of the config file setting.
- **Duplicate memory limit code.** Consolidated identical `ini_set` logic from `Application`, `McpCommand`, and `BaselineCommand` into `Config::applyMemoryLimit()`.
- **Test File Ratio removed.** Unreliable metric replaced by class-level tested/untested tracking.

## [2.7.3] - 2026-04-02

### Fixed

- **Unnecessary leading newline in `--version` and `--help` output.** A stray `PHP_EOL` before the actual output produced a blank line that offended every inner Monk out there. Fixes [#8](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/8).

## [2.7.2] - 2026-04-02

### Fixed

- **Fully Qualified Name wrong when class name appears in namespace.** `str_replace()` removed all occurrences of the class/function name from the FQN instead of only the trailing one. For example, `App\Account\EmployeeReport\Model\Account` produced a corrupted namespace. Now uses right-side extraction via `strrpos()`. Fixes [#6](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/6).

### Added

- **Config dist-file support.** `php-codearch-config.yaml.dist` is now loaded as a fallback when `php-codearch-config.yaml` does not exist. This allows teams to commit a shared default config (`.yaml.dist`) while each developer can override it locally (`.yaml`, already gitignored). Fixes [#7](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/7).

## [2.7.1] - 2026-04-01

### Fixed

- **History required 3 runs instead of 2.** `writeHistory()` was called after report generation, so the current run's data was missing from the history file when reports were rendered. Moved before report generation.
- **Dashboard showed "Functions: 0".** Wrong metric keys `overallFunctions` / `overallMethods` instead of `overallFunctionCount` / `overallMethodsCount` in the template, `ProjectCalculator`, and `HealthScoreTool`.
- **Empty dashboard tiles.** Metrics with `ShowEverywhere` visibility that had no value (e.g. `overallCoveragePercent` without Clover XML) rendered as blank tiles. Now filtered out.
- **Test detection missed tests in src/ directory.** Projects that place tests alongside production code (e.g. Flysystem) were not detected. Added fallback to parse `phpunit.xml.dist` / `phpunit.xml` test suite directories.
- **Test files included in production analysis.** Recognized test files now excluded from the main analysis file list, preventing their classes, methods, LOC, and complexity from inflating project metrics.
- **`--version` missing newline.** Output was immediately overwritten by the shell prompt. Fixes [#4](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/4).
- **`--help` not implemented.** Added `--help` flag with full usage information, all options, and available commands. Fixes [#4](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/4).
- **Unclear error/warning summary.** CLI summary line now reads "Problems: X errors | Y warnings | Z info" and includes a hint to check the report for per-file, per-class, and per-function details. Fixes [#5](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/5).

## [2.7.0] - 2026-03-27

### Breaking Changes (Metric Corrections)

Several metric calculations have been corrected. Analysis results may differ from previous runs. The tool will display a one-time notice before the first analysis. See `docs/metrics-formulas.md` for full details.

- **Halstead method-level operand fix.** Method-level operands were tracked by AST node class name instead of actual value, making `uniqueOperands` too low and `difficulty`/`effort` inflated. Method-level Halstead metrics will now be significantly more accurate (lower difficulty/effort).
- **TooComplex double-counting fix.** Classes exceeding `avgMethodCc` threshold were counted as two separate problems. Error counts will decrease by ~30-40%.
- **God Class false positive fix.** Suspect index incremented per long method instead of once for "has any long method". Fewer false God Class detections.
- **LCOM zero-average floor.** When project average LCOM was 0, every class with LCOM > 0 was flagged. Now uses a minimum floor of 1.
- **Layer violation Layer-0 fix.** Entity/Model/Domain classes were skipped entirely. Now correctly checked for upward dependencies.
- **Coupling Distance from Mainline.** Missing `abs()` in Robert C. Martin's formula. Now always >= 0.
- **Coupling file/function handleMetric.** Operated on MetricValue objects instead of values.
- **Maintainability Index default.** Inconsistent fallback (171 vs 50) now consistently 171.
- **Spaceship operator CC.** Counted as +2, now +1 (standard McCabe).
- **Package Cohesion normalization.** Values could exceed 1.0, now capped.
- **Halstead Difficulty threshold recalibration.** Default raised from 20 to 30 (45 for framework projects). The previous threshold had no basis in the literature and flagged 22% of all entities. The new threshold combined with the existing relative effort check provides better signal with less noise.

### Added

- **MetricKey constants.** All 249 metric keys are now available as `MetricKey::CC`, `MetricKey::LLOC`, etc. Eliminates magic strings throughout the codebase.
- **Typed metric accessors.** `MetricValue` now offers `asInt()`, `asFloat()`, `asBool()`, `asString()`, `asArray()`. Collections offer `getInt()`, `getFloat()`, `getBool()`, `getString()`, `getArray()` with null-safe defaults.
- **Visitor interfaces.** `InitializableVisitorInterface`, `ConfigAwareVisitorInterface`, `PathAwareVisitorInterface` replace `method_exists()` checks.
- **Breaking changes notice.** First-run prompt warns users about changed calculations. Acknowledgement is stored in project config (`acknowledgedVersion`).
- **Metrics formulas documentation.** `docs/metrics-formulas.md` with original sources, formulas, and implementation notes for all major metrics.
- **Hand-calculated test fixtures.** Dedicated test files with step-by-step manual calculations for CC, CogC, Halstead, MI, LCOM, LOC, and Coupling.
- **Git pre-push hook.** `.githooks/pre-push` checks tests, CS Fixer, and PHPStan before pushing to main.
- **Composer scripts.** `composer test`, `composer analyse`, `composer cs-check`, `composer cs-fix`, `composer check` (all three).
- **PHPStan at level max.** 0 errors on the entire codebase.
- **PHP CS Fixer with Symfony ruleset.** `declare(strict_types=1)` in all files.

### Fixed

- **XXE protection.** `simplexml_load_file()` now uses `LIBXML_NONET` flag.
- **Script injection in HTML reports.** `json_encode()` now uses `JSON_HEX_TAG | JSON_HEX_AMP` for JSON embedded in `<script>` tags.
- **Twig debug mode.** Now conditional on `APP_DEBUG=1` environment variable instead of always active.
- **MCP tool error messages.** No longer expose internal exception details. Generic error messages returned instead.
- **Memory limit validation.** Config value validated with regex before passing to `ini_set()`.
- **Template XSS.** `|raw` filter replaced with proper Twig loops in `single-class.html.twig`.
- **PrettyPrinter performance.** Cached as property instead of re-instantiated per class/method/function.
- **Output buffer pattern.** `ReportTrait::renderTemplate()` uses direct `file_put_contents()` instead of `ob_start/echo/ob_get_clean`.
- **CalculatorInterface return type.** Added missing `: void`.
- **ArgumentParser exit.** Replaced bare `exit;` with `VersionDisplayException`.
- **`getPackagDataProvider` typo.** Renamed to `getPackageDataProvider`.

### Removed

- **Dead code.** `DataProviderFactory::predictProgrammingParadigm()`, `CalculatorTrait::$usedMetricTypes`, `DependencyVisitor::$currentClassMetrics`.
- **`examples/` directory.** Superseded by `tests/Feature/Analysis/testfiles/`.

## [2.6.1] - 2026-03-26

### Fixed
- **Fatal error with null property/method names in LCOM calculation.** `getNodeName()` can return `null` for dynamic property accesses (`$this->$prop`) and dynamic method calls (`$this->$method()`). The LCOM visitor now skips these instead of passing `null` to `Graph\Node::__construct()`. Fixes [#1](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/1).

## [2.4.0] - 2026-03-25

### Added
- **Automatic framework detection.** Detects Symfony, Laravel, and Doctrine from `composer.json` and adjusts metric thresholds for known framework patterns. Detected frameworks are shown in CLI output, HTML dashboard, and all report types.
- **Doctrine cycle downgrade.** Entity↔Repository 2-node cycles and Entity↔Entity ORM relationship cycles are automatically downgraded from Error to Info when Doctrine is detected. Only cycles matching the specific pattern are affected — genuine architectural cycles remain Error.
- **Controller threshold adjustment.** When Symfony or Laravel is detected, dependency and coupling thresholds for `*Controller` classes are raised (Too Dependent: 20→35, God Class coupling: 10→25) since controllers are orchestrators by design.
- **Extended LCOM exclusions for Symfony.** Classes matching `*Subscriber`, `*Listener`, `*Command`, `*Handler` are excluded from LCOM warnings when Symfony is detected.
- **`framework` config section** with `detect: true` (default) and individually toggleable adjustments (`doctrineCycles`, `entityCycles`, `controllerThresholds`).

### Changed
- **MCP SDK switched to `logiscape/mcp-sdk-php`.** Eliminates `psr/http-message` conflicts with Symfony 7.x and Laravel 10+.

## [2.3.0] - 2026-03-25

### Changed
- **MCP SDK switched from `php-mcp/server` to `logiscape/mcp-sdk-php`.** The previous SDK pulled in `react/http` which requires `psr/http-message ^1.0` — incompatible with Symfony 7.x and Laravel 10+ (which require `psr/http-message ^2.0`). The new SDK has minimal dependencies (`psr/log` only), eliminating all Composer conflicts. PhpCodeArcheology can now be installed as a project dev-dependency in any modern PHP framework without issues.

### Removed
- Dependency conflict installation hint from README (no longer needed).

## [2.2.2] - 2026-03-25

### Fixed
- **Short open tag replacement** added missing space: `<?}?>` was replaced to `<?php}?>` (invalid) instead of `<?php }?>`. Also fixes `<?if(` becoming `<?phpif(`. Reduced PB parse errors from 120 to 5.
- **Skipped file details** now shown with file path and error message instead of just a count.
- **Knowledge graph filter state** now reads from DOM chips at init instead of hardcoded Set, so `method`/`declares`/`calls` render immediately when active by default.

## [2.2.0] - 2026-03-25

### Added
- **Target PHP version config (`php.version`).** Configure the PHP version used for parsing, independent of the local CLI version. Uses PhpParser's emulative lexer to handle syntax differences. Example: `php: { version: "7.4" }`.
- **Short open tag support (`php.shortOpenTags`).** When enabled, `<?` is treated as a PHP open tag (equivalent to `<?php`). Essential for analysing legacy codebases that use short open tags while running on a PHP installation with `short_open_tag` disabled. All occurrences are handled, not just the first one. Default: `false`.

## [2.1.0] - 2026-03-25

### Added
- **Method-level call tracking.** Cross-class method calls (`StaticCall`, `new`) are now tracked in the `DependencyVisitor` and exported as `calls` edges in the Knowledge Graph (`graph.json`). Each edge carries a `weight` indicating the number of call-sites. Configurable via `graph.methodCalls: true` (default: enabled).
- **`get_impact_analysis` MCP tool.** New tool for AI-assisted refactoring — analyzes what breaks when a method changes. Shows outgoing calls, direct callers, transitive callers (configurable depth via BFS), and a summary of affected methods and classes.
- **"Method Calls" section in HTML method detail pages.** Displays "Called by" (incoming) and "Calls to" (outgoing) with links to the respective class and method detail pages.
- **`calls` edge visualization** in the D3 knowledge graph (yellow dashed lines). `method`, `declares`, and `calls` filters are now active by default.
- **Metric Reference** (`docs/metrics.md`) expanded with all previously undocumented metrics: Type Coverage, Documentation Coverage, Code Duplication, Refactoring Priority, Complexity Density, Estimated Runtime Complexity, Encapsulation Score. Health Score corrected from 5 to 9 weighted dimensions with formulas.

### Fixed
- **Report path in summary output** now reflects the subdirectory structure introduced in v1.6.0 (e.g. `html/index.html` instead of `index.html`).
- **Duplicate "Parameters" column** removed from method tables in HTML report. The metric-driven "Params" column remains.
- **German texts in knowledge graph** translated to English ("Nur Probleme" → "Problems only", "Hinweis: >500 Nodes…", "Teil eines Zyklus", legend hints).

## [2.0.0] - 2026-03-24

### Added
- **MCP Server (`mcp` subcommand).** PhpCodeArcheology is the first PHP static analysis tool with native [Model Context Protocol](https://modelcontextprotocol.io) support. AI assistants (Claude, Cursor, etc.) can now query analysis results directly via 9 structured tools: `get_health_score`, `get_problems`, `get_metrics`, `get_hotspots`, `get_refactoring_priorities`, `get_dependencies`, `get_class_list`, `get_graph`, `search_code`.
- **`.mcp.json`** project file for zero-config Claude Code team sharing.
- **README: AI Integration (MCP Server) section** with quick-start instructions and tool reference.

## [1.7.0] - 2026-03-24

### Added
- **Knowledge Graph export (`--report-type=graph`).** Exports the full codebase structure as a machine-readable JSON graph (`graph/graph.json`). Contains five node types (`class`, `method`, `function`, `package`, `author`) with metrics and flags, eight edge types (`declares`, `extends`, `implements`, `uses_trait`, `depends_on`, `belongs_to`, `authored_by`, `cycle_member`), package-based clusters, and detected dependency cycles. Designed for AI tools, graph databases, and custom visualisations.

## [1.6.0] - 2026-03-24

### Added
- **Multiple report types in one run.** `--report-type=html,json` (comma-separated) generates all specified formats in a single analysis pass.

### Changed
- **Report subdirectory isolation.** Each report type now writes into its own subdirectory under `reportDir`: `html/`, `markdown/`, `json/`, `sarif/`, `ai-summary/`. `history.jsonl` remains in the report root.

### Migration
- Existing report files in the root of `reportDir` (e.g. `index.html`, `report.json`) are no longer overwritten and can be safely deleted. The CLI displays a notice if old root-level files are detected.

## [1.5.1] - 2026-03-24

### Fixed
- **Installation broken on PHP 8.2/8.3.** `composer.lock` was generated on PHP 8.5 and locked `symfony/yaml v8.0.6` (requires PHP >=8.4). Removed `composer.lock` from version control — Composer now resolves compatible versions per platform, as recommended for library packages.
- Declared missing `ext-mbstring` requirement in `composer.json`. The codebase uses `mb_strlen`, `mb_substr`, `mb_detect_encoding` etc. — without this declaration, installations lacking mbstring would fail with cryptic errors.
- Removed broken Tests badge from README (pointed to non-existent GitHub Actions workflow).

### Changed
- Dockerfile uses `composer update` instead of `composer install` (no lock file in repo).
- Removed unused `plotly.js-dist` dependency from `package.json`. The project uses Chart.js.
- Added `build:css` and `watch:css` npm scripts for Tailwind CSS development workflow.

### Added
- README: Docker installation instructions with volume mount example.
- README: Global installation via `composer global require`.
- README: Development section explaining the Tailwind CSS build chain.

## [1.5.0] - 2026-03-19

### Added
- **Refactoring Roadmap.** New per-class Refactoring Priority Score (0-100) combining problem severity, complexity, cohesion, structural issues, and impact factors (coupling, git churn, author count). Classes are ranked by urgency with contextual recommendations explaining *why* and *how* to refactor. Includes:
  - New report page `refactoring-roadmap.html` with summary cards, score distribution chart, and ranked table with filter
  - Dashboard widget showing top 5 refactoring priorities
  - AI Summary section with top 10 priorities and recommendations
  - Colored driver tags (high complexity, low cohesion, dependency cycle, SOLID violations, etc.)
  - Zero-floor guarantee: classes with no problems score 0. Interfaces, traits, and enums are skipped.
  - New project metrics: Avg/Max Refactoring Priority, Classes Needing Refactoring

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
