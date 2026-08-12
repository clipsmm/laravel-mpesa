# Testing

```bash
composer install
composer check
```

`composer check` runs everything CI runs: Pint (`lint`), PHPStan (`analyse`),
then the test suite (`test`), in that order. Each is also available on its
own:

```bash
composer lint      # vendor/bin/pint --test — check style without fixing
composer format     # vendor/bin/pint — fix style in place
composer analyse    # vendor/bin/phpstan analyse (level 8, via Larastan)
composer test        # vendor/bin/phpunit
composer audit        # composer.lock against known advisories
```

`composer test` runs the PHPUnit suite (Orchestra Testbench). It uses
Laravel's HTTP client fakes throughout and never contacts Safaricom, so it's
safe to run without sandbox credentials.

`composer audit` checks `composer.lock` against known advisories — run it
locally after any dependency change.

## Continuous integration

`.github/workflows/ci.yml` runs on every push and pull request against
`main`/`master`, plus manually via `workflow_dispatch`:

- **tests** — the PHPUnit suite across PHP 8.2/8.3/8.4, plus a `prefer-lowest`
  run on PHP 8.2 to catch code that only works with the newest allowed
  dependency versions.
- **lint** — `composer lint` (Pint, style only — no auto-fixing in CI).
- **static-analysis** — `composer analyse` (PHPStan level 8 via Larastan,
  which understands Laravel facades/container bindings that plain PHPStan
  would otherwise flag as undefined methods).
- **audit** — `composer audit --locked`.

`pint.json` disables Pint's `php_unit_method_casing` rule: this codebase
deliberately uses `test_snakeCase` method names with the `#[Test]` attribute
rather than PHPUnit's `testCamelCase` convention, and that's intentional, not
a style violation.

## What's covered

- OAuth authentication, including malformed-response handling.
- Input validation for `stkPush()` and `transactionStatus()`.
- SSRF/path-traversal rejection in `getEndpoint()`.
- HTTPS enforcement on callback URLs, including the sandbox
  `allow_insecure_callbacks` escape hatch.
- `SecurityCredential` encryption against a real, test-generated RSA
  certificate (round-tripped through decryption to assert correctness), the
  `security_credential` override path, and the missing-certificate error.
- The STK callback controller: event dispatch, IP allowlisting, and
  route/controller overrides from config.
- Both console commands (`mpesa:stk`, `mpesa:status`), including their
  argument validation and config fallbacks.
- Every DTO's `fromArray()` / `fromPayload()` parsing, including malformed or
  rejected-response input.
