# Laravel M-Pesa

> Elegant Laravel integration for Safaricom M-Pesa Daraja API

[![Tests](https://github.com/clipsmm/laravel-mpesa/workflows/Tests/badge.svg)](https://github.com/clipsmm/laravel-mpesa/actions)
[![Latest Version](https://img.shields.io/packagist/v/clipsmm/laravel-mpesa.svg)](https://packagist.org/packages/clipsmm/laravel-mpesa)
[![License](https://img.shields.io/packagist/l/clipsmm/laravel-mpesa.svg)](https://packagist.org/packages/clipsmm/laravel-mpesa)

## Features

✨ **Type-Safe** - Response DTOs with full IDE support  
🔒 **Secure** - Built-in callback validation and HTTPS enforcement  
📝 **Logging** - Optional request/response logging for debugging  
🧪 **Well-Tested** - 26+ tests with 80+ assertions  
📚 **Documented** - Comprehensive guides and examples  
🔄 **Backward Compatible** - Supports both array and DTO returns  

## Quick Start

```bash
# Install package
composer require clipsmm/laravel-mpesa

# Publish configuration
php artisan vendor:publish --provider="LaravelMpesa\MpesaServiceProvider"
```

Configure your `.env`:

```bash
MPESA_CONSUMER_KEY=your-consumer-key
MPESA_CONSUMER_SECRET=your-consumer-secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your-passkey
MPESA_API_STATUS=sandbox
```

Send an STK Push:

```php
use LaravelMpesa\MpesaSdk;

$response = MpesaSdk::instance()->stkPush(
    receiver: '254712345678',
    amount: 1000,
    ref: 'ORDER-123',
    description: 'Payment for order',
    callbackUrl: 'https://example.com/callback',
    returnDto: true
);

if ($response->isSuccessful()) {
    $checkoutId = $response->getCheckoutRequestId();
}
```

## Documentation

- [Installation](installation.md) - Get started with the package
- [Configuration](configuration.md) - Configure your M-Pesa credentials
- [STK Push](stk-push.md) - Send payment requests to customers
- [STK Query](stk-query.md) - Check payment status
- [Callbacks](callbacks.md) - Handle M-Pesa callbacks securely
- [Response DTOs](response-dtos.md) - Type-safe responses
- [API Reference](api-reference.md) - Complete API documentation
- [Testing](testing.md) - Test your M-Pesa integration
- [Security](security.md) - Best practices and security guide

## Support

- 📖 [Documentation](https://clipsmm.github.io/laravel-mpesa/)
- 🐛 [Issue Tracker](https://github.com/clipsmm/laravel-mpesa/issues)
- 💬 [Discussions](https://github.com/clipsmm/laravel-mpesa/discussions)

## License

The MIT License (MIT). Please see [License File](https://github.com/clipsmm/laravel-mpesa/blob/main/LICENSE) for more information.
