# Usage

Every endpoint is a method on a request manager, obtained one of two ways.

## `MpesaSdk::instance(?string $app = null): RequestManager`

```php
use LaravelMpesa\MpesaSdk;

$mpesa = MpesaSdk::instance('c2b');
```

Builds a `RequestManager` for the named application (or `mpesa.default` when
omitted), merging that application's config over the package's shared
settings (timeouts, `allow_insecure_callbacks`, etc.). Unknown applications
throw `InvalidArgumentException`. Equivalent to
`(new MpesaSdk($app))->requestManager()`.

`new MpesaSdk(?string $app = null, array $opts = [])` gives the same manager
plus a few extra accessors — `app(): string`, `requestManager(): RequestManager`
— and lets `$opts` override configuration for that instance only:

```php
$mpesa = new MpesaSdk('c2b', ['shortcode' => '999999']);
```

## The `Mpesa` facade

The `Mpesa` facade proxies to the application container's `MpesaSdk` singleton
(always the `mpesa.default` application) and exposes every endpoint method
statically:

```php
use LaravelMpesa\Mpesa;

[$accepted, $response] = Mpesa::stkPush(
    receiver: '254712345678',
    amount: 1500,
    ref: 'ORDER-1001',
    description: 'Payment for ORDER-1001',
    callbackUrl: 'https://merchant.example/api/mpesa/stk/callback',
);
```

Use `MpesaSdk::instance('b2c')` instead of the facade when you need an
application other than the default.

## Endpoints

| Endpoint | Method |
| --- | --- |
| [Authentication](endpoints/authentication.md) | `authenticate()`, `isAuthenticated()` |
| [Register URLs (C2B)](endpoints/register-url.md) | `registerUrls(...)` |
| [STK Push](endpoints/stk-push.md) | `stkPush(...)` |
| [Transaction Status](endpoints/transaction-status.md) | `transactionStatus(...)` |

Every endpoint method returns `[bool $successful, array $response]`. Treat
the boolean as transport/provider acceptance only — reconcile final state
from a validated callback where one applies. Wrap `$response` in a
[DTO](dtos.md) for typed access.

## Other methods

### `getEndpoint(string $url): string`

Resolves a relative Daraja path against the configured live or sandbox host.
Absolute URLs and path traversal are rejected.

### `getConfig(string $key, mixed $default = null): mixed`

Reads the manager's configuration snapshot. It does not query arbitrary
environment variables at runtime.
