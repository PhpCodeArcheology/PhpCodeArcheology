Release a new version of PhpCodeArcheology.

## Version Detection

Automatically determine the next version based on Semantic Versioning:

1. Read the current version from `src/Application/Version.php` (the `CURRENT` constant)
2. Read the `## [Unreleased]` section in `CHANGELOG.md`
3. Analyze the changelog entries to determine the version bump:
   - **MAJOR** (X.0.0): If there's a `### Breaking Changes` section or entries that describe breaking API/CLI/config changes
   - **MINOR** (X.Y.0): If there's a `### Added` or `### Changed` section with new features or significant behavioral changes
   - **PATCH** (X.Y.Z): If there are only `### Fixed` entries (bug fixes, corrections)
4. Present the suggested version to the user with a brief rationale, and let them confirm or override

If the user provides a version as argument (e.g. `/release 2.8.0`), use that instead of auto-detecting.

## Steps

1. **Determine version**: Auto-detect as described above, or use the user-provided version. Validate semver format (X.Y.Z) and ensure it's greater than the current version.

2. **Pre-flight checks**:
   - Run `php vendor/bin/pest` — all tests must pass
   - Run `php -d memory_limit=1G vendor/bin/phpstan analyse --no-progress` — must be clean
   - Working tree must be clean (no uncommitted changes)
   - `CHANGELOG.md` must have a non-empty `## [Unreleased]` section

3. **Update files**:
   - `src/Application/Version.php`: Set `CURRENT` to the new version
   - `CHANGELOG.md`: Replace `## [Unreleased]` with `## [X.Y.Z] - YYYY-MM-DD` (today's date) and add a fresh empty `## [Unreleased]` section above it

4. **Commit**: Stage both files and commit with message `vX.Y.Z: <summary>` where summary is extracted from the first changelog entry. Include Co-Authored-By.

5. **Tag**: Create annotated git tag `vX.Y.Z`.

6. **Ask before push**: Show the user what will be pushed and ask for confirmation before running `git push origin main --tags`.

7. **GitHub Release**: After push, create a GitHub release using `gh release create vX.Y.Z --title "X.Y.Z" --notes "<changelog section>"`. Extract the release notes from the changelog section for this version.

8. **Summary**: Print the release URL and confirm everything is done.

## Important
- Always ask for confirmation before pushing and creating the GitHub release
- If any pre-flight check fails, stop and report the issue
- The changelog section for the release notes is everything between `## [X.Y.Z]` and the next `## [` line
- Follow the project's commit conventions (see recent git log)
