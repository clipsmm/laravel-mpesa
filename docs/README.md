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
- **[Configuration](configuration.md)** — the `c2b`/`b2c`/`b2b` applications
  and environment variables.
- **[Usage](usage.md)** — obtaining an SDK instance and the `Mpesa` facade.
- **Endpoints** — one page per Daraja operation, each covering its method
  signature, validation, response DTO, and related callback/console command:
  - **[Authentication](endpoints/authentication.md)**
  - **[Register URLs (C2B)](endpoints/register-url.md)** — including the
    inbound confirmation callback.
  - **[STK Push](endpoints/stk-push.md)** — including the inbound callback
    and `mpesa:stk`.
  - **[Transaction Status](endpoints/transaction-status.md)** — including
    `SecurityCredential` and `mpesa:status`.
- **[DTOs](dtos.md)** — every typed request/response payload in one place.
- **[Security](security.md)** — the operational checklist.
- **[Testing](testing.md)** — running the package's own test suite.
- **[Changelog](changelog.md)**.

## Requirements

- PHP 8.2 or newer.
- Laravel 12.61.1 or newer, or Laravel 13.12 or newer.
- Safaricom Daraja consumer credentials and an application shortcode.

Released under the MIT license.
