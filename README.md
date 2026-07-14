# Laravel Mpesa

Laravel integration for Safaricom Daraja authentication, C2B callback URL
registration, and M-Pesa Express STK Push requests.

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
```

Use `MPESA_API_STATUS=live` only with production credentials. Live and sandbox
requests always use Safaricom HTTPS hosts. Callback URLs must use HTTPS unless
the application is in sandbox and `MPESA_ALLOW_INSECURE_CALLBACKS=true` is set
for local development.

## Usage

```php
use LaravelMpesa\MpesaSdk;

$mpesa = MpesaSdk::instance('c2b');

[$registered, $registration] = $mpesa->registerUrls(
    validationUrl: 'https://merchant.example/api/mpesa/validation',
    confirmationUrl: 'https://merchant.example/api/mpesa/confirmation',
);

[$accepted, $response] = $mpesa->stkPush(
    receiver: '254712345678',
    amount: 1500,
    ref: 'ORDER-1001',
    description: 'Payment for ORDER-1001',
    callbackUrl: 'https://merchant.example/api/mpesa/stk/callback',
);
```

Both operations return `[bool $successful, array $response]`. Treat the boolean
as transport/provider acceptance only; reconcile final payment state from a
validated callback.

## Public API

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
): array
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
): array
```

Requires a `2547XXXXXXXX` receiver, positive amount, bounded reference and
description, and an approved callback URL.

### `RequestManager::transactionStatus(...)`

```php
public function transactionStatus(
    string $identifier,
    string $resultUrl,
    string $timeoutUrl,
    string $identifierType = '4',
    string $remarks = 'Transaction status query',
    string $occasion = 'TransactionStatus',
): array
```

Queries Daraja TransactionStatus for an Mpesa receipt or provider conversation
identifier. The result and timeout URLs must be valid HTTPS callback URLs unless
the app is in sandbox and insecure callbacks are explicitly allowed.

Console helpers:

```bash
php artisan mpesa:status --receipt=UGEHJB6GMF --result=https://example.test/status/result --timeout=https://example.test/status/timeout
php artisan mpesa:status --conversationId=AG_20260714_12345
```

When `--result` or `--timeout` are omitted, the command reads
`MPESA_TRANSACTION_STATUS_RESULT_URL` and `MPESA_TRANSACTION_STATUS_TIMEOUT_URL`.

### `RequestManager::getEndpoint(string $url): string`

Resolves a relative Daraja path against the configured live or sandbox host.
Absolute URLs and path traversal are rejected.

### `RequestManager::isAuthenticated(): bool`

Returns whether the cached token remains valid with a 30-second expiry margin.

### `RequestManager::getConfig(string $key, mixed $default = null): mixed`

Reads the manager's configuration snapshot. It does not query arbitrary
environment variables at runtime.

## HTTP Endpoints and OpenAPI

This package registers a configurable STK callback endpoint:

- `POST /signal/ingress/pulse`

The route, middleware, controller, and emitted events can be overridden from
`config/mpesa.php` under the `callbacks` key. Host applications can replace the
controller class or extend `LaravelMpesa\Http\Controllers\Callbacks\StkCallbackController`.
Callback requests are IP-allowlisted. The package defaults to `*` in local and
testing environments and Safaricom callback IPs in other environments. Override
with `MPESA_CALLBACK_ALLOWED_IPS` or `mpesa.callbacks.allowed_ips`.
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
composer test
composer audit
```

The suite uses Laravel HTTP fakes and never contacts Safaricom.

## License

This package is released under the MIT license declared in `composer.json`.
