# Laravel Mpesa

Laravel integration for Safaricom Daraja: OAuth authentication, C2B callback
URL registration, M-Pesa Express STK Push, and Transaction Status queries —
direct to Safaricom, no intermediary gateway.

📖 **[Full documentation](https://clipsmm.github.io/laravel-mpesa/)** —
configuration, the public API, DTOs, callbacks, console commands, security,
and testing.

## Requirements

- PHP 8.2 or newer.
- Laravel 12.61.1 or newer, or Laravel 13.12 or newer.
- Safaricom Daraja consumer credentials and an application shortcode.

## Installation

```bash
composer require clipsmm/laravel-mpesa
php artisan vendor:publish --provider="LaravelMpesa\MpesaServiceProvider"
```

## Quick start

```dotenv
MPESA_DEFAULT_APP=c2b
MPESA_API_STATUS=sandbox
MPESA_CONSUMER_KEY=your-consumer-key
MPESA_CONSUMER_SECRET=your-consumer-secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your-passkey
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

See the [full documentation](https://clipsmm.github.io/laravel-mpesa/) for
the `b2c`/`b2b` applications, the `SecurityCredential` certificate required by
`transactionStatus()`, the `Mpesa` facade, typed DTOs, the inbound STK
callback route, and the `mpesa:stk` / `mpesa:status` console commands.

## Security

Never log OAuth tokens, passkeys, initiator passwords, security credentials,
or full callback payloads. See the full
[security checklist](https://clipsmm.github.io/laravel-mpesa/#/security) and
[audit history](docs/guides/security-audit.md).

## Testing

```bash
composer install
composer test
composer audit
```

The suite uses Laravel HTTP fakes and never contacts Safaricom.

## License

This package is released under the MIT license declared in `composer.json`.
