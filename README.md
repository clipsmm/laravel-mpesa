# Laravel Mpesa

Laravel integration for Safaricom Daraja authentication, C2B callback URL
registration, and M-Pesa Express STK Push requests.

[![Tests](https://github.com/clipsmm/laravel-mpesa/workflows/Tests/badge.svg)](https://github.com/clipsmm/laravel-mpesa/actions)
[![Latest Version](https://img.shields.io/packagist/v/clipsmm/laravel-mpesa.svg)](https://packagist.org/packages/clipsmm/laravel-mpesa)
[![License](https://img.shields.io/packagist/l/clipsmm/laravel-mpesa.svg)](LICENSE)

## Features

✨ **Type-Safe** - Response DTOs with full IDE support  
🔒 **Secure** - Built-in callback validation and HTTPS enforcement  
📝 **Logging** - Optional request/response logging for debugging  
🧪 **Well-Tested** - 26+ tests with 80+ assertions  
📚 **Documented** - Comprehensive guides and examples  
🔄 **Backward Compatible** - Supports both array and DTO returns

## Documentation

📖 **Full documentation:** https://clipsmm.github.io/laravel-mpesa/

- [Installation Guide](https://clipsmm.github.io/laravel-mpesa/#/installation)
- [Configuration](https://clipsmm.github.io/laravel-mpesa/#/configuration)
- [STK Push](https://clipsmm.github.io/laravel-mpesa/#/stk-push)
- [STK Query](https://clipsmm.github.io/laravel-mpesa/#/stk-query)
- [Callbacks](https://clipsmm.github.io/laravel-mpesa/#/callbacks)
- [API Reference](https://clipsmm.github.io/laravel-mpesa/#/api-reference)

## Requirements

- PHP 8.2 or newer.
- Laravel 12.61.1 or newer, or Laravel 13.12 or newer.
- Safaricom Daraja consumer credentials and an application shortcode.

## Installation

```bash
composer require clipsmm/laravel-mpesa
php artisan vendor:publish --provider="LaravelMpesa\MpesaServiceProvider"
```

Laravel package discovery registers `LaravelMpesa\MpesaServiceProvider`.

## Configuration

Configure each application in `.env`:

```dotenv
MPESA_DEFAULT_APP=c2b
MPESA_API_STATUS=sandbox
MPESA_CONSUMER_KEY=your-consumer-key
MPESA_CONSUMER_SECRET=your-consumer-secret
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your-passkey
MPESA_CONNECT_TIMEOUT=5
MPESA_TIMEOUT=15
MPESA_ALLOW_INSECURE_CALLBACKS=false
MPESA_LOGGING=false
```

Use `MPESA_API_STATUS=live` only with production credentials. Live and sandbox
requests always use Safaricom HTTPS hosts. Callback URLs must use HTTPS unless
the application is in sandbox and `MPESA_ALLOW_INSECURE_CALLBACKS=true` is set
for local development.

Set `MPESA_LOGGING=true` to enable request/response logging for debugging (excludes
sensitive fields like passwords).

## Usage

### STK Push (Lipa Na M-Pesa)

```php
use LaravelMpesa\MpesaSdk;

$mpesa = MpesaSdk::instance('c2b');

// Using array return (backward compatible)
[$accepted, $response] = $mpesa->stkPush(
    receiver: '254712345678',
    amount: 1500,
    ref: 'ORDER-1001',
    description: 'Payment for ORDER-1001',
    callbackUrl: 'https://merchant.example/api/mpesa/stk/callback',
);

// Using response DTO (recommended)
$response = $mpesa->stkPush(
    receiver: '254712345678',
    amount: 1500,
    ref: 'ORDER-1001',
    description: 'Payment for ORDER-1001',
    callbackUrl: 'https://merchant.example/api/mpesa/stk/callback',
    returnDto: true,
);

if ($response->isSuccessful()) {
    $checkoutRequestId = $response->getCheckoutRequestId();
    // Store $checkoutRequestId to query payment status later
}
```

### STK Query (Check Payment Status)

```php
// Using array return
[$successful, $data] = $mpesa->stkQuery('ws_CO_191220191020363925');

// Using response DTO (recommended)
$response = $mpesa->stkQuery('ws_CO_191220191020363925', returnDto: true);

if ($response->isPaymentSuccessful()) {
    // Payment completed successfully
} elseif ($response->isPaymentPending()) {
    // Payment still pending or timed out
} elseif ($response->isPaymentFailed()) {
    // Payment failed or was cancelled
}
```

### C2B URL Registration

```php
[$registered, $registration] = $mpesa->registerUrls(
    validationUrl: 'https://merchant.example/api/mpesa/validation',
    confirmationUrl: 'https://merchant.example/api/mpesa/confirmation',
);

// Using response DTO
$response = $mpesa->registerUrls(
    validationUrl: 'https://merchant.example/api/mpesa/validation',
    confirmationUrl: 'https://merchant.example/api/mpesa/confirmation',
    returnDto: true,
);
```

### Handling Callbacks

Use the `ValidatesMpesaCallback` trait in your controllers:

```php
use LaravelMpesa\Traits\ValidatesMpesaCallback;

class MpesaCallbackController extends Controller
{
    use ValidatesMpesaCallback;

    public function stkCallback(Request $request)
    {
        try {
            $callback = $this->validateStkCallback($request);
            
            if ($this->isStkCallbackSuccessful($callback)) {
                $details = $this->extractStkPaymentDetails($callback);
                
                // Process successful payment
                // $details['amount']
                // $details['mpesa_receipt_number']
                // $details['phone_number']
            }
            
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\InvalidArgumentException $e) {
            Log::error('Invalid M-Pesa callback', ['error' => $e->getMessage()]);
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 400);
        }
    }

    public function c2bConfirmation(Request $request)
    {
        try {
            $callback = $this->validateC2bCallback($request);
            
            // Process C2B payment
            // $callback['trans_id']
            // $callback['trans_amount']
            // $callback['msisdn']
            
            return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ResultCode' => 1, 'ResultDesc' => 'Rejected'], 400);
        }
    }
}
```

Both operations return `[bool $successful, array $response]` when using array returns,
or typed response objects when using `returnDto: true`. Treat the boolean as transport/
provider acceptance only; reconcile final payment state from a validated callback.

## Public API

### Response DTOs

The package now supports typed response objects for better IDE support and type safety:

- `StkPushResponse` - Response from STK Push requests
- `StkQueryResponse` - Response from STK Query requests with payment status helpers
- `UrlRegistrationResponse` - Response from C2B URL registration

All methods support both legacy array returns and new DTO returns via the `returnDto` parameter.

### `MpesaSdk::__construct(?string $app = null, array $opts = [])`

Creates an SDK instance for a configured application. Runtime options override
that application's configuration for the instance only. Unknown applications
throw `InvalidArgumentException`.

### `MpesaSdk::instance(?string $app = null): RequestManager`

Returns a request manager using the selected application and shared timeout
settings.

### `RequestManager::__construct(array $config, bool $auth = true)`

Creates a manager from an immutable configuration snapshot. Authentication is
lazy; the `$auth` parameter remains for backward compatibility.

### `RequestManager::authenticate(): bool`

Requests and caches a Daraja OAuth token using HTTP Basic authentication. It
returns `false` for a rejected request and throws for missing configuration,
transport failures, or malformed successful responses.

### `RequestManager::registerUrls(...)`

```php
public function registerUrls(
    string $validationUrl,
    string $confirmationUrl,
    string $responseType = 'Cancelled',
    bool $returnDto = false,
): array|UrlRegistrationResponse
```

Validates callback URLs, authenticates when necessary, and registers C2B
validation and confirmation URLs.

### `RequestManager::stkPush(...)`

```php
public function stkPush(
    string $receiver,
    int $amount,
    string $ref,
    string $description,
    string $callbackUrl,
    string $transactionType = 'CustomerPayBillOnline',
    bool $returnDto = false,
): array|StkPushResponse
```

Requires a `2547XXXXXXXX` receiver, positive amount, bounded reference and
description, and an approved callback URL. Returns checkout request ID for
status queries.

### `RequestManager::stkQuery(...)`

```php
public function stkQuery(
    string $checkoutRequestId,
    bool $returnDto = false,
): array|StkQueryResponse
```

Query the status of an STK Push transaction using the checkout request ID
returned from `stkPush()`. Response includes payment status helpers.

### `RequestManager::getEndpoint(string $url): string`

Resolves a relative Daraja path against the configured live or sandbox host.
Absolute URLs and path traversal are rejected.

### `RequestManager::isAuthenticated(): bool`

Returns whether the cached token remains valid with a 30-second expiry margin.

### `RequestManager::getConfig(string $key, mixed $default = null): mixed`

Reads the manager's configuration snapshot. It does not query arbitrary
environment variables at runtime.

### Callback Validation Trait

```php
use LaravelMpesa\Traits\ValidatesMpesaCallback;
```

Provides helper methods for validating and parsing M-Pesa callbacks:

- `validateStkCallback(Request $request): array` - Validate STK Push callback
- `validateC2bCallback(Request $request): array` - Validate C2B confirmation callback
- `isStkCallbackSuccessful(array $callback): bool` - Check if payment succeeded
- `extractStkPaymentDetails(array $callback): array` - Extract payment metadata

## HTTP Endpoints and OpenAPI

This package registers no controllers or inbound routes. Host applications must
implement, authenticate, validate, and document their own callback endpoints.
See [the API surface note](docs/wikis/api.md).

## Security

1. Keep consumer secrets and passkeys outside source control.
2. Validate callback payloads against Safaricom's documented contract before
   changing payment state.
3. Make callbacks idempotent using provider transaction identifiers.
4. Verify order reference, shortcode, MSISDN, and amount before fulfillment.
5. Rate-limit application-owned initiation endpoints.
6. Never log OAuth tokens, passkeys, passwords, or full callback payloads.
7. Keep dependencies updated and run `composer audit` in CI.

## Testing

```bash
composer install
composer test              # Run test suite
composer test:coverage     # Generate HTML coverage report
composer analyse           # Run static analysis (PHPStan)
composer format            # Format code with Laravel Pint
composer format:test       # Check code style without fixing
composer audit             # Security audit
```

The suite uses Laravel HTTP fakes and never contacts Safaricom. Test coverage
includes 26+ tests covering authentication, API methods, response DTOs, callback
validation, and error scenarios.

## License

This package is released under the MIT license declared in `composer.json`.
