# Contributing to PhpCodeArcheology

Thank you for your interest in contributing to PhpCodeArcheology! This guide will help you get started.

## Getting Started

1. Fork the repository
2. Clone your fork: `git clone https://github.com/YOUR-USERNAME/PhpCodeArcheology.git`
3. Install dependencies: `composer install`
4. Create a feature branch: `git checkout -b feature/your-feature`

## Development Setup

- **PHP 8.2+** is required
- Run tests: `php vendor/bin/pest`
- Run the analyzer: `php vendor/bin/phpcodearcheology src/`

## How to Contribute

### Reporting Bugs

- Use the [Bug Report](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/new?template=bug_report.yml) issue template
- Include PHP version, OS, and steps to reproduce
- If possible, include a minimal code sample that triggers the issue

### Suggesting Features

- Use the [Feature Request](https://github.com/PhpCodeArcheology/PhpCodeArcheology/issues/new?template=feature_request.yml) issue template
- Explain the use case and why the feature would be valuable

### Submitting Changes

1. Make sure all tests pass: `php vendor/bin/pest`
2. Follow existing code style and conventions
3. Write tests for new functionality
4. Keep commits focused — one logical change per commit
5. Use descriptive commit messages following the project convention:
   - `NEW`: New features
   - `FIX`: Bug fixes
   - `MOD`: Refactoring or modifications
6. Open a pull request against the `main` branch

## Project Structure

See [CLAUDE.md](CLAUDE.md) for an overview of the codebase architecture and conventions.

## Code Style

- Follow PSR-12 coding standards
- Use type declarations for parameters and return types
- Keep methods focused and under reasonable complexity

## Questions?

Open an issue or start a discussion — happy to help!
