# Security Audit

Audit date: 2026-06-19

## Scope

- Composer lock file and runtime/development dependency constraints.
- OAuth authentication, endpoint construction, callback registration, STK
  payload creation, configuration access, and service-provider bindings.

## Implemented Controls

- Replaced vulnerable PHPUnit 4 and abandoned components with PHPUnit 11 and a
  maintained Testbench stack.
- Replaced development-branch dependency resolution with stable releases.
- Raised Guzzle/PSR-7, Laravel, Monolog, and Mockery dependency floors.
- Replaced manually assembled Basic tokens with Laravel HTTP Basic auth.
- Added finite timeouts, required configuration validation, and malformed OAuth
  response handling.
- Rejected absolute/traversing API paths and insecure callback URLs.
- Added receiver, amount, reference, and description validation.
- Removed arbitrary runtime `env()` fallback and placeholder passwords.
- Added a security-focused test suite.

## Verification

`composer audit --locked` reported no known advisories after regenerating the
stable lock file. Unit tests cover the primary authentication, SSRF/path,
callback, and transaction-validation controls.

## Residual Responsibilities

Host applications own callback controllers. They must validate Safaricom
payloads, authenticate where supported, reconcile amount/reference/shortcode,
make processing idempotent, rate-limit initiation endpoints, and avoid logging
tokens, passkeys, or full callback bodies.
