# API Reference

## `MpesaSdk`

### `MpesaSdk::__construct(?string $app = null, array $opts = [])`

Creates an SDK instance for a configured application, merging that
application's configuration over the package's shared settings (timeouts,
`allow_insecure_callbacks`, etc.). Runtime `$opts` override both for the
instance only. Unknown applications throw `InvalidArgumentException`.

### `MpesaSdk::instance(?string $app = null): RequestManager`

Returns the request manager for the selected application. Equivalent to
`(new MpesaSdk($app))->requestManager()`.

### `MpesaSdk` instance methods

`authenticate()`, `isAuthenticated()`, `registerUrls(...)`, `stkPush(...)`,
`transactionStatus(...)`, and `getEndpoint(string $url)` all delegate to the
instance's `RequestManager` and share its signatures below. `app(): string`
returns the configured application name and `requestManager(): RequestManager`
returns the underlying manager. These are also what the [`Mpesa` facade](usage.md#the-mpesa-facade)
proxies to.

## `RequestManager`

### `RequestManager::__construct(array $config)`

Creates a manager from an immutable configuration snapshot. Authentication is
lazy — the first request that needs a token triggers `authenticate()`.

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
validation and confirmation URLs. Returns `[bool $successful, array $response]`
— wrap the response in [`RegisterUrlResponse`](dtos.md#registerurlresponse)
for typed access.

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
description, and an approved callback URL. Returns
`[bool $successful, array $response]` — wrap the response in
[`StkPushResponse`](dtos.md#stkpushresponse) for typed access.

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
identifier. The result and timeout URLs must be valid HTTPS callback URLs
unless the app is in sandbox and insecure callbacks are explicitly allowed.
The `SecurityCredential` sent with the query is either the configured
`security_credential` override or `initiator_password` encrypted against
`security_credential_cert` — see
[Initiator credentials and SecurityCredential](configuration.md#initiator-credentials-and-securitycredential).
Returns `[bool $successful, array $response]` — wrap the response in
[`TransactionStatusResponse`](dtos.md#transactionstatusresponse) for typed
access.

### `RequestManager::getEndpoint(string $url): string`

Resolves a relative Daraja path against the configured live or sandbox host.
Absolute URLs and path traversal are rejected.

### `RequestManager::isAuthenticated(): bool`

Returns whether the cached token remains valid with a 30-second expiry margin.

### `RequestManager::getConfig(string $key, mixed $default = null): mixed`

Reads the manager's configuration snapshot. It does not query arbitrary
environment variables at runtime.
