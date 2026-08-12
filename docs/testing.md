# Testing

```bash
composer install
composer test
composer audit
```

`composer test` runs the PHPUnit suite (Orchestra Testbench). It uses
Laravel's HTTP client fakes throughout and never contacts Safaricom, so it's
safe to run without sandbox credentials.

`composer audit` checks `composer.lock` against known advisories — run it
locally after any dependency change, and it should run in CI on every push.

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
