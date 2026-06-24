# Changelog

All notable changes to `clipsmm/laravel-mpesa` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Response DTOs (`StkPushResponse`, `StkQueryResponse`, `UrlRegistrationResponse`) for type-safe API responses
- STK Query method for checking payment status with `isPaymentSuccessful()`, `isPaymentPending()`, and `isPaymentFailed()` helpers
- `ValidatesMpesaCallback` trait for secure callback validation and parsing
- Request/response logging support via `MPESA_LOGGING` config
- Static analysis with PHPStan (Level 8) and Larastan
- Code style enforcement with Laravel Pint
- Comprehensive test coverage (26+ tests covering all features)
- Response DTO tests and callback validation tests
- `.editorconfig` for consistent code formatting
- `CONTRIBUTING.md` guide for contributors
- New composer scripts: `test:coverage`, `analyse`, `format`, `format:test`

### Changed

- All API methods now support optional `returnDto` parameter for typed responses
- Logging excludes sensitive fields (passwords, credentials) automatically
- Improved test coverage from 6 to 26+ tests with 80+ assertions

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
