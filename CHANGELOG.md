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
- Bundled Safaricom's public `SecurityCredential` certificates
  (`certs/SandboxCertificate.cer`, `certs/ProductionCertificate.cer`) — they
  are public and identical for every integration, so `security_credential_cert`
  is no longer required; it now only overrides the bundled default.
- A C2B confirmation callback endpoint (`POST /signal/ingress/echo` by
  default): `ConfirmationCallbackController`, a `C2bConfirmationData` DTO
  parsed from Safaricom's documented flat Confirmation payload (`TransID`,
  `TransAmount`, `MSISDN`, `BillRefNumber`, etc. — distinct from the STK
  callback's nested `Body.stkCallback` shape), and a `C2bConfirmationReceived`
  event. Configured the same way as the STK callback, under
  `mpesa.callbacks.routes.confirmation` / `.controllers.confirmation` /
  `.events.c2b_confirmation_received`. C2B *validation* remains unimplemented
  — it's an opt-in Safaricom feature most integrations don't enable.
- Extracted the STK callback controller's IP-allowlist logic into a
  `ChecksCallbackIp` trait, shared with the new confirmation controller.

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

### Dev tooling

- Added Larastan (PHPStan level 8) and Laravel Pint, with `composer analyse`,
  `composer lint`, `composer format`, and an aggregate `composer check`.
  Fixed the resulting findings across the codebase (missing array-shape
  docblocks, unguarded `(string)`/`(int)` casts on `mixed` console-option and
  config values) rather than suppressing them, and reformatted the whole
  source/test tree with Pint. `pint.json` disables `php_unit_method_casing`
  to preserve this codebase's intentional `test_snakeCase` + `#[Test]`
  convention.
- Added `.github/workflows/ci.yml`: a PHPUnit matrix (PHP 8.2/8.3/8.4, plus a
  `prefer-lowest` run), Pint, PHPStan, and `composer audit`, on every push and
  pull request to `main`/`master`.

### Docs

- Consolidated all documentation into a single `README.md` with a table of
  contents and anchor-linked sections (Configuration, Usage, Endpoints —
  Authentication / Register URLs / STK Push / Transaction Status — DTOs,
  Security, Testing, Changelog). `docs/wikis/api.md` was folded in and
  removed.

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
