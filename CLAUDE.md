# CLAUDE.md — PhpCodeArcheology

## Project Overview

PhpCodeArcheology is a PHP static analysis tool focused on architecture and maintainability. It measures 60+ code quality metrics, detects problems via 14 built-in rules, and generates reports in HTML, Markdown, JSON, SARIF, and Knowledge Graph formats. It includes test analysis (auto-detects PHPUnit/Pest/Codeception, maps test files to classes, integrates Clover XML coverage) and an MCP server for AI-native code intelligence.

## Language

The tool, documentation, CLI output, and all user-facing text are in **English**. Code comments and variable names are English. The author communicates in German.

## Tech Stack

- **PHP 8.2+** (works on 8.2, 8.3, 8.4, 8.5)
- **nikic/php-parser** for AST parsing
- **Twig** for HTML/Markdown report templates
- **logiscape/mcp-sdk-php** for MCP server (switched from php-mcp/server in v2.3.0)
- **Tailwind CSS** for HTML report styling (compiled `output.css` committed)
- **Chart.js** and **D3.js** for visualizations in the HTML report
- **Pest** for testing

## Project Structure

- `src/` — PHP source code
  - `Analysis/` — AST visitors (metrics collection during parsing)
  - `Calculators/` — Post-parse calculators (coupling, cycles, health score, etc.)
  - `Predictions/` — Problem detectors (too complex, god class, dead code, etc.)
  - `Report/` — Report generators and data providers
  - `Mcp/` — MCP server command and tools
  - `Application/` — CLI app, config, analyzers, services (e.g. `Service/CloverXmlParser.php`)
- `data/metrics/` — Metric type definitions (used for glossary and report rendering)
- `templates/html/` — Twig templates for HTML report
- `templates/markdown/` — Twig templates for Markdown report
- `tests/` — Pest test suite
- `bin/phpcodearcheology` — CLI entry point

## Running

```bash
# Analyze and generate HTML report
php vendor/bin/phpcodearcheology src/

# Quick terminal-only output
php vendor/bin/phpcodearcheology --quick src/

# With Clover XML coverage data
php vendor/bin/phpcodearcheology --coverage-file clover.xml src/

# Generate coverage first (PHPUnit or Pest)
# Requires Xdebug or PCOV PHP extension
XDEBUG_MODE=coverage vendor/bin/pest --coverage-clover clover.xml
# or: XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-clover clover.xml

# Run tests
php vendor/bin/pest

# Rebuild Tailwind CSS (only if templates change)
npm run build:css
```

## Versioning

This project follows **Semantic Versioning** (semver.org):
- **MAJOR**: Breaking changes to CLI interface, config format, or report structure
- **MINOR**: New features, new metrics, new report types (backwards-compatible)
- **PATCH**: Bug fixes, documentation updates

## Related Projects

- **Tool website**: `~/Projects/PhpCodeArcheology-website`
- **Author website** (Astro): `~/Projects/marcuskober.de-astro`
  - Blog article (EN): "How I Use My Own Tool: PhpCodeArcheology in Practice" — `how-i-use-phpcodearcheology`
  - Blog article (EN): "PhpCodeArcheology: Measure Code Quality, Don't Guess" — `phpcodearcheology`

## Key Conventions

- Config file: `php-codearch-config.yaml` (YAML) or `.phpcodearch.json` (JSON)
- Report output: `tmp/report/{type}/` (e.g. `html/`, `json/`, `graph/`)
- Metric definitions in `data/metrics/*.php` drive the glossary and detail views
- All problem detectors use `PredictionTrait` for config access and framework-aware helpers
- The `lcomExclude` pattern (class name + interface matching) is the model for all exclusion logic
- Framework detection is automatic via `composer.json` parsing — adjustments are per-pattern, not blanket
- Test framework detection is automatic via `composer.json` (PHPUnit/Pest/Codeception) — test directories are scanned and test files mapped to production classes via namespace, naming convention, and directory structure
