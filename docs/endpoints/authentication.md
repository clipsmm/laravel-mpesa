# Authentication

Every other endpoint needs a Daraja OAuth access token. The package handles
this for you — you only call it directly if you want to warm the token ahead
of time or check its state.

- Daraja endpoint: `GET /oauth/v1/generate?grant_type=client_credentials`
- Config required: `consumer_key`, `consumer_secret` (see
  [Configuration](../configuration.md))

## `authenticate(): bool`

```php
use LaravelMpesa\MpesaSdk;

$mpesa = MpesaSdk::instance('c2b');

$mpesa->authenticate(); // true once a token is cached
```

Sends the consumer key/secret as HTTP Basic auth and caches the returned
token and its expiry on the manager instance.

- Returns `false` when Daraja rejects the credentials (non-2xx response).
- Throws `InvalidArgumentException` when `consumer_key` or `consumer_secret`
  is missing from config.
- Throws `RuntimeException` when Daraja returns a 2xx response without a
  usable `access_token` — a malformed success response.

You rarely need to call this yourself: every other endpoint method
(`registerUrls()`, `stkPush()`, `transactionStatus()`) calls it automatically
before sending, if the cached token isn't valid.

## `isAuthenticated(): bool`

```php
$mpesa->isAuthenticated(); // true if a call would reuse the cached token
```

Returns whether a previously cached token is still valid, with a 30-second
expiry margin — a token about to expire is treated as invalid so a request
doesn't fail mid-flight.

The token is cached on the `RequestManager` instance itself, not shared
across requests — a fresh `MpesaSdk::instance()` call builds a fresh
`RequestManager` with no cached token. Reuse the same instance/variable
within a request if you're calling multiple endpoints back to back.
