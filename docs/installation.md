# Installation

## Requirements

- PHP 8.2 or higher
- Laravel 12.61.1+ or Laravel 13.12+
- Safaricom Daraja API credentials

## Install via Composer

```bash
composer require clipsmm/laravel-mpesa
```

The package uses Laravel's auto-discovery feature, so the service provider will be registered automatically.

## Publish Configuration

Publish the configuration file to customize settings:

```bash
php artisan vendor:publish --provider="LaravelMpesa\MpesaServiceProvider"
```

This creates `config/mpesa.php` in your application.

## Verify Installation

Check that the package is installed correctly:

```bash
php artisan tinker
```

```php
>>> app(LaravelMpesa\MpesaSdk::class)
=> LaravelMpesa\MpesaSdk {#...}
```

## Next Steps

- [Configure your credentials](configuration.md)
- [Send your first STK Push](stk-push.md)
- [Set up callback handlers](callbacks.md)
