# Changelog

All notable changes to `clipsmm/laravel-mpesa` will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- Typed response DTOs: `StkPushResponse`, `TransactionStatusResponse`, and
  `RegisterUrlResponse`, alongside the existing `StkCallbackData`.
- `security_credential` and `security_credential_cert` configuration keys per
  application, and a documented `Initiator credentials and SecurityCredential`
  section in the README.
- `MpesaSdk` instance methods (`authenticate`, `isAuthenticated`,
  `registerUrls`, `stkPush`, `transactionStatus`, `getEndpoint`, `app`,
  `requestManager`) so the `Mpesa` facade can proxy to them.

### Fixed

- `transactionStatus()` now sends `SecurityCredential` RSA-encrypted with the
  configured Safaricom public certificate instead of the plaintext initiator
  password, which Daraja rejects.
- `MpesaSdk`'s constructor now merges the package's shared configuration
  (timeouts, `allow_insecure_callbacks`) the same way `MpesaSdk::instance()`
  already did.
- The `Mpesa` facade now resolves to usable methods instead of throwing
  `BadMethodCallException` on every call.

### Changed

- `b2b` now has its own independent `MPESA_B2B_*` environment variables
  instead of silently reusing `c2b`'s credentials.
- `RequestManager::__construct()` dropped the unused `bool $auth` parameter.

### Removed

- A stale `tests/mocks` PSR-4 autoload entry in `composer.json` pointing at a
  nonexistent directory.

### Docs

- Replaced the single long-form README with a topic-per-page documentation
  site under `docs/` (Docsify, served via GitHub Pages), covering getting
  started, configuration, usage, API reference, DTOs, callbacks, console
  commands, security, and testing. The root README is now a short landing
  page linking into it. `docs/wikis/api.md` was folded into the new
  `callbacks.md` and `console-commands.md` pages and removed.

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
