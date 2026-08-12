# Getting Started

## Requirements

- PHP 8.2 or newer.
- Laravel 12.61.1 or newer, or Laravel 13.12 or newer.
- Safaricom Daraja consumer credentials and an application shortcode. Register
  an app at the [Daraja API portal](https://developer.safaricom.co.ke/) to
  get sandbox credentials, or production credentials once Safaricom approves
  your go-live application.

## Installation

```bash
composer require clipsmm/laravel-mpesa
php artisan vendor:publish --provider="LaravelMpesa\MpesaServiceProvider"
```

Laravel package discovery registers `LaravelMpesa\MpesaServiceProvider`
automatically — no manual provider registration is needed. Publishing copies
`config/mpesa.php` into your application so you can adjust it directly instead
of only through environment variables.

Continue to [Configuration](configuration.md) to set up an application (`c2b`,
`b2c`, or `b2b`), then [Usage](usage.md) to send your first request.
