# Laravel Mpesa

Laravel integration for Safaricom Daraja: OAuth authentication, C2B callback
URL registration, M-Pesa Express STK Push, and Transaction Status queries —
direct to Safaricom, no intermediary gateway.

```bash
composer require clipsmm/laravel-mpesa
php artisan vendor:publish --provider="LaravelMpesa\MpesaServiceProvider"
```

```php
use LaravelMpesa\MpesaSdk;

[$accepted, $response] = MpesaSdk::instance('c2b')->stkPush(
    receiver: '254712345678',
    amount: 1500,
    ref: 'ORDER-1001',
    description: 'Payment for ORDER-1001',
    callbackUrl: 'https://merchant.example/api/mpesa/stk/callback',
);
```

## Where to go next

- **[Getting Started](getting-started.md)** — requirements and installation.
- **[Configuration](configuration.md)** — the `c2b`/`b2c`/`b2b` applications,
  environment variables, and the `SecurityCredential` certificate.
- **[Usage](usage.md)** — the SDK and the `Mpesa` facade.
- **[API Reference](api-reference.md)** — every public method on `MpesaSdk`
  and `RequestManager`.
- **[DTOs](dtos.md)** — typed access to callback and response payloads.
- **[Callbacks & Webhooks](callbacks.md)** — the inbound STK callback route,
  IP allowlisting, and events.
- **[Console Commands](console-commands.md)** — `mpesa:stk` and
  `mpesa:status`.
- **[Security](security.md)** — the operational checklist and audit history.
- **[Testing](testing.md)** — running the package's own test suite.
- **[Changelog](changelog.md)**.

## Requirements

- PHP 8.2 or newer.
- Laravel 12.61.1 or newer, or Laravel 13.12 or newer.
- Safaricom Daraja consumer credentials and an application shortcode.

Released under the MIT license.
