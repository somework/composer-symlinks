# Changelog

## Unreleased
- Removed deprecated `SKIP_MISSED_TARGET` option. Use `SKIP_MISSING_TARGET` instead.
- Added Windows fallback strategies with configurable `windows-mode` (`symlink`, `junction`, `copy`).
- Improved error messages suggesting enabling Developer Mode or switching Windows strategies when symlinks fail.
- Documented Windows behaviours and added Windows CI coverage with end-to-end tests.
