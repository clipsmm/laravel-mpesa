# Changelog

All notable changes to `clipsmm/laravel-mpesa` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.0] - 2026-06-19

### Added

- Merge-only GitHub releases for pull requests targeting `main` or `master`.
- Named release commands: `composer release:patch`, `composer release:minor`, and `composer release:major`.
- Safe release dry runs with the `--dry-run` option.

### Security

- Replaced vulnerable and abandoned development dependencies with maintained stable releases.
- Added secure authentication, HTTPS callback enforcement, input validation, finite timeouts, and safe endpoint construction.
- Added a focused security test suite and public API documentation.

[Unreleased]: https://github.com/clipsmm/laravel-mpesa/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/clipsmm/laravel-mpesa/releases/tag/v0.1.0
