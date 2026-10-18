# Laravel Mpesa

[![CI](https://github.com/clipsmm/laravel-mpesa/actions/workflows/ci.yml/badge.svg)](https://github.com/clipsmm/laravel-mpesa/actions/workflows/ci.yml)

Laravel integration for Safaricom Daraja: OAuth authentication, C2B callback
URL registration, M-Pesa Express STK Push, and Transaction Status queries —
direct to Safaricom, no intermediary gateway.

## Table of contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Configuration](#configuration)
  - [Applications](#applications)
  - [Initiator credentials and SecurityCredential](#initiator-credentials-and-securitycredential)
  - [Command default callback URLs](#command-default-callback-urls)
- [Usage](#usage)
  - [Obtaining an SDK instance](#obtaining-an-sdk-instance)
  - [The Mpesa facade](#the-mpesa-facade)
  - [Phone numbers](#phone-numbers)
- [Endpoints](#endpoints)
  - [Authentication](#authentication)
  - [Register URLs (C2B)](#register-urls-c2b)
  - [STK Push](#stk-push)
  - [Transaction Status](#transaction-status)
- [DTOs](#dtos)
- [Security](#security)
- [Testing](#testing)
- [Changelog](#changelog)
- [License](#license)

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

## Configuration

### Applications

Configure each application in `.env`. Three applications are pre-defined —
`c2b`, `b2c`, and `b2b` — each with its own independent set of credentials;
none of them share values by default, so configure only the ones you use.

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

`b2c` and `b2b` use the same shape of variables, prefixed `MPESA_B2C_` and
`MPESA_B2B_` respectively — for example `MPESA_B2C_CONSUMER_KEY`,
`MPESA_B2B_SHORTCODE`.

### Initiator credentials and SecurityCredential

`transactionStatus()` (and any future B2C/reversal command) authenticates as
an initiator using Daraja's `SecurityCredential` field. Daraja never accepts a
plaintext password here — it must be the initiator password RSA-encrypted
(PKCS#1) with Safaricom's public certificate for the target environment.

Safaricom's sandbox and production certificates are public and identical for
every integration, so the package **bundles both** (`certs/SandboxCertificate.cer`
and `certs/ProductionCertificate.cer`) and picks the right one automatically
based on the app's `status` (`sandbox` or `live`). Only the initiator name and
password need configuring:

```dotenv
MPESA_INITIATOR_NAME=your-initiator
MPESA_INITIATOR_PASSWORD=your-initiator-password
```

The equivalent B2C and B2B variables are `MPESA_B2C_INITIATOR_NAME` /
`MPESA_B2C_INITIATOR_PASSWORD` and `MPESA_B2B_INITIATOR_NAME` /
`MPESA_B2B_INITIATOR_PASSWORD`.

> Both bundled certificates show an expired X.509 validity period when
> inspected (`openssl x509 -noout -dates`) — Safaricom's own published certs
> have looked like this for years. This is expected and does not affect
> encryption: only the RSA public key inside the certificate is used, and
> `openssl_pkey_get_public()` doesn't check certificate validity dates.

If Safaricom rotates a certificate before the package ships an update, or you
need a non-standard certificate, set `MPESA_SECURITY_CREDENTIAL_CERT` (or the
`_B2C_` / `_B2B_` equivalent) to an absolute path — it takes priority over the
bundled certificate:

```dotenv
MPESA_SECURITY_CREDENTIAL_CERT=/absolute/path/to/SandboxCertificate.cer
```

If you already compute the encrypted credential elsewhere, set
`MPESA_SECURITY_CREDENTIAL` (or the `_B2C_` / `_B2B_` equivalent) to the
final base64 value instead — when set, it is used as-is and no certificate is
read at all.

### Command default callback URLs

The `mpesa:stk` and `mpesa:status` console commands (see
[STK Push](#stk-push) and [Transaction Status](#transaction-status)) fall
back to these when their `--callback` / `--result` / `--timeout` options are
omitted:

```dotenv
MPESA_STK_CALLBACK_URL=https://merchant.example/api/mpesa/stk/callback
MPESA_TRANSACTION_STATUS_RESULT_URL=https://merchant.example/api/mpesa/status/result
MPESA_TRANSACTION_STATUS_TIMEOUT_URL=https://merchant.example/api/mpesa/status/timeout
```

The published `config/mpesa.php` also controls the inbound STK and
confirmation callback routes, middleware, IP allowlist, and dispatched
events — see the `mpesa.callbacks` key, documented under
[STK Push](#stk-push) and [Register URLs (C2B)](#register-urls-c2b) below.

## Usage

Every endpoint is a method on a request manager, obtained one of two ways.

### Obtaining an SDK instance

```php
use LaravelMpesa\MpesaSdk;

$mpesa = MpesaSdk::instance('c2b');
```

`MpesaSdk::instance(?string $app = null): RequestManager` builds a
`RequestManager` for the named application (or `mpesa.default` when
omitted), merging that application's config over the package's shared
settings (timeouts, `allow_insecure_callbacks`, etc.). Unknown applications
throw `InvalidArgumentException`.

`new MpesaSdk(?string $app = null, array $opts = [])` gives the same manager
plus a few extra accessors — `app(): string`, `requestManager(): RequestManager`
— and lets `$opts` override configuration for that instance only:

```php
$mpesa = new MpesaSdk('c2b', ['shortcode' => '999999']);
```

### The Mpesa facade

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

Every endpoint method returns `[bool $successful, array $response]`. Treat
the boolean as transport/provider acceptance only — reconcile final state
from a validated callback where one applies. Wrap `$response` in a
[DTO](#dtos) for typed access.

`getEndpoint(string $url): string` resolves a relative Daraja path against
the configured live or sandbox host (absolute URLs and path traversal are
rejected), and `getConfig(string $key, mixed $default = null): mixed` reads
the manager's configuration snapshot — both available on `RequestManager`
and `MpesaSdk`.

### Phone numbers

Safaricom's Daraja API expects Kenyan MSISDNs as `254XXXXXXXXX` — E.164 without
the leading `+`. The package normalizes and validates phone numbers with
[propaganistas/laravel-phone](https://github.com/propaganistas/laravel-phone)
(Google's libphonenumber), and exposes the same helper it uses internally so
host applications can normalize input before an STK push:

```php
use LaravelMpesa\Support\PhoneNumber;

PhoneNumber::format('0712345678');       // '254712345678'
PhoneNumber::format('+254 712 345 678'); // '254712345678'
PhoneNumber::format('254712345678');      // '254712345678'
PhoneNumber::format('12345');             // null — not a valid Kenyan mobile number

PhoneNumber::isValid('0712345678');       // true
PhoneNumber::isValid('+14155552671');     // false — not Kenyan
```

`format()` accepts `254XXXXXXXXX`, `+254712345678`, `07XXXXXXXX`, `7XXXXXXXX`,
or a spaced variant, and returns `null` (never throws) for anything that is not
a valid Kenyan mobile number. `isValid()` is a boolean shorthand for
`format() !== null`. Requests built by [`stkPush()`](#stk-push) and the
[`mpesa:stk` command](#mpesastk-console-command) go through the same
normalization, so the number you pass always reaches Daraja as
`254XXXXXXXXX`.

## Endpoints

| Endpoint | Method |
| --- | --- |
| [Authentication](#authentication) | `authenticate()`, `isAuthenticated()` |
| [Register URLs (C2B)](#register-urls-c2b) | `registerUrls(...)` |
| [STK Push](#stk-push) | `stkPush(...)` |
| [Transaction Status](#transaction-status) | `transactionStatus(...)` |

### Authentication

Every other endpoint needs a Daraja OAuth access token. The package handles
this for you — you only call it directly if you want to warm the token ahead
of time or check its state.

- Daraja endpoint: `GET /oauth/v1/generate?grant_type=client_credentials`
- Config required: `consumer_key`, `consumer_secret` (see [Configuration](#configuration))

```php
use LaravelMpesa\MpesaSdk;

$mpesa = MpesaSdk::instance('c2b');

$mpesa->authenticate(); // true once a token is cached
```

`authenticate(): bool` sends the consumer key/secret as HTTP Basic auth and
caches the returned token and its expiry on the manager instance.

- Returns `false` when Daraja rejects the credentials (non-2xx response).
- Throws `InvalidArgumentException` when `consumer_key` or `consumer_secret`
  is missing from config.
- Throws `RuntimeException` when Daraja returns a 2xx response without a
  usable `access_token` — a malformed success response.

You rarely need to call this yourself: every other endpoint method
(`registerUrls()`, `stkPush()`, `transactionStatus()`) calls it automatically
before sending, if the cached token isn't valid.

`isAuthenticated(): bool` returns whether a previously cached token is still
valid, with a 30-second expiry margin — a token about to expire is treated as
invalid so a request doesn't fail mid-flight.

The token is cached on the `RequestManager` instance itself, not shared
across requests — a fresh `MpesaSdk::instance()` call builds a fresh
`RequestManager` with no cached token. Reuse the same instance/variable
within a request if you're calling multiple endpoints back to back.

### Register URLs (C2B)

Registers the validation and confirmation URLs Safaricom calls when a
customer pays your C2B shortcode directly (paybill/till), as opposed to an
STK Push you initiated. This is a one-time (or infrequent) setup call, not
something you run per transaction.

- Daraja endpoint: `POST /mpesa/c2b/v2/registerurl`
- Config required: `shortcode` (see [Configuration](#configuration))

```php
public function registerUrls(
    string $validationUrl,
    string $confirmationUrl,
    string $responseType = 'Cancelled',
): array
```

```php
use LaravelMpesa\MpesaSdk;

[$registered, $registration] = MpesaSdk::instance('c2b')->registerUrls(
    validationUrl: 'https://merchant.example/api/mpesa/validation',
    confirmationUrl: 'https://merchant.example/api/mpesa/confirmation',
);
```

- `$validationUrl` / `$confirmationUrl` must be valid HTTPS URLs, unless the
  app is in sandbox and `allow_insecure_callbacks` is set.
- `$responseType` controls what Safaricom does when your validation endpoint
  is unreachable: `'Completed'` accepts the transaction anyway, `'Cancelled'`
  (the default) rejects it.
- Authenticates automatically if needed (see [Authentication](#authentication)).

Returns `[bool $successful, array $response]`. `$successful` reflects HTTP
transport/acceptance only. Wrap `$response` in
[RegisterUrlResponse](#dtos) for typed access:

```php
use LaravelMpesa\DTOs\Responses\RegisterUrlResponse;

$dto = RegisterUrlResponse::fromArray($response);
$dto->originatorConversationId;
$dto->responseDescription;
```

#### Receiving the confirmation callback

Safaricom calls `validationUrl` first (only if C2B validation is enabled on
your shortcode — most integrations skip it), then `confirmationUrl` once the
transaction is complete. This package ships a controller and route for
**confirmation**:

- `POST /signal/ingress/echo`

It uses the same `mpesa.callbacks` config block as STK (`enabled`,
`middleware`, `path_prefix`, `allowed_ips`), keyed by `confirmation` instead
of `stk`:

- `routes.confirmation` — full route override for the confirmation callback.
- `controllers.confirmation` — controller class override. Host applications
  may replace the class or extend
  `LaravelMpesa\Http\Controllers\Callbacks\ConfirmationCallbackController`.
- `events.c2b_confirmation_received` — event class override. Compatible
  events must accept a `C2bConfirmationData` instance.

The default controller dispatches a `C2bConfirmationReceived` event for every
IP-allowlisted payload and responds `{"ResultCode": 0, "ResultDesc": "Success"}`
— the acknowledgment Safaricom's Confirmation URL contract expects. The event
payload (`$event->confirmation`) is a [C2bConfirmationData](#dtos) DTO:

```php
use LaravelMpesa\Events\Callbacks\C2bConfirmationReceived;

class ReconcileC2bPayment
{
    public function handle(C2bConfirmationReceived $event): void
    {
        $confirmation = $event->confirmation;

        $confirmation->transId;         // Safaricom's transaction ID — use for idempotency
        $confirmation->transAmount;
        $confirmation->billRefNumber;   // whatever the customer typed as the account number
        $confirmation->msisdn;          // e.g. 254712345678
    }
}
```

Unlike the STK callback, C2B confirmation only fires for completed payments —
there's no separate "failed" event, since Safaricom doesn't call your
confirmation URL for a payment that never happened.

Requests from IPs outside `allowed_ips` are rejected with `403 Forbidden`
before the payload is parsed or any event is dispatched, using the same
allowlist as [STK Push callbacks](#stk-push). Apply the same
[host application responsibilities](#security) here — reconcile the payload
against what you expect and make processing idempotent using `TransID`.

**Validation** is not implemented by this package — it's an opt-in Safaricom
feature most integrations don't enable, and its contract (synchronous
accept/reject) doesn't fit the fire-and-forget event pattern used above. If
you enable it, build `validationUrl` as its own route the same way you'd
build any inbound webhook.

### STK Push

Prompts a customer's phone with an STK (SIM Toolkit) prompt to enter their
M-Pesa PIN and pay a specific amount. This is the endpoint most integrations
use for checkout flows.

- Daraja endpoint: `POST /mpesa/stkpush/v1/processrequest`
- Config required: `shortcode`, `passkey` (see [Configuration](#configuration))

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

Validated before any request is sent — throws `InvalidArgumentException` for:

- `$receiver` not a valid Kenyan mobile number.
- `$amount` not a positive whole number.
- `$ref` empty or longer than 100 characters.
- `$description` empty or longer than 182 characters.
- `$callbackUrl` not a valid HTTPS URL (unless sandbox + insecure callbacks
  allowed).

`$receiver` accepts any familiar Kenyan format — `254XXXXXXXXX`,
`+254712345678`, `07XXXXXXXX`, `7XXXXXXXX`, or a spaced variant — and is
normalized with
[propaganistas/laravel-phone](https://github.com/propaganistas/laravel-phone)
(Google's libphonenumber) to the `254XXXXXXXXX` format Daraja expects, without
the leading `+`.

Authenticates automatically if needed (see [Authentication](#authentication)).

Returns `[bool $accepted, array $response]`. `$accepted` reflects Daraja's
synchronous acceptance only — **not** whether the customer actually paid;
that outcome arrives later via the callback below. Wrap `$response` in
[StkPushResponse](#dtos) for typed access:

```php
use LaravelMpesa\DTOs\Responses\StkPushResponse;

$dto = StkPushResponse::fromArray($response);
$dto->checkoutRequestId;
$dto->accepted(); // Daraja accepted the push request, not that it was paid
```

#### Receiving the STK callback

This package registers a configurable inbound callback endpoint that Daraja
calls once the customer completes (or cancels/times out on) the STK prompt:

- `POST /signal/ingress/pulse`

The default path intentionally avoids obvious provider, payment, and callback
terms because Safaricom may reject URLs containing those words. Keep the same
idea if you override the route.

Configuration lives under `mpesa.callbacks` in `config/mpesa.php`:

- `enabled` — registers or disables the callback route.
- `middleware` — route middleware for the callback endpoint.
- `path_prefix` — shared callback route prefix.
- `allowed_ips` — callback source IP allowlist. Defaults to `*` for local and
  testing environments, and Safaricom's published callback IPs otherwise. Set
  `MPESA_CALLBACK_ALLOWED_IPS` to a comma-separated list to override.
- `routes.stk` — full route override for the STK callback.
- `controllers.stk` — controller class override. Host applications may
  replace the class or extend
  `LaravelMpesa\Http\Controllers\Callbacks\StkCallbackController`.
- `events.stk_received`, `events.stk_succeeded`, `events.stk_failed` — event
  class overrides. Compatible events must accept a `StkCallbackData` instance.

The default controller emits a received event for every accepted payload,
then a succeeded event when `ResultCode` is `0` or a failed event for every
other result code. It responds with JSON containing `accepted`,
`checkoutRequestId`, and `resultCode`. The event payload (`$event->callback`)
is a [StkCallbackData](#dtos) DTO:

```php
use LaravelMpesa\Events\Callbacks\StkCallbackSucceeded;

class ReconcilePayment
{
    public function handle(StkCallbackSucceeded $event): void
    {
        $callback = $event->callback;

        $callback->checkoutRequestId;
        $callback->metadataValue('MpesaReceiptNumber');
        $callback->metadataValue('Amount');
    }
}
```

Requests from IPs outside `allowed_ips` are rejected with `403 Forbidden`
before the payload is parsed or any event is dispatched.

Safaricom callbacks are not signed. IP allowlisting narrows the source, but
your handler must still:

1. Reconcile amount, reference, shortcode, and MSISDN against the original
   `stkPush()` request before changing payment state.
2. Make processing idempotent using `checkoutRequestId` /
   `merchantRequestId` — Safaricom may retry callback delivery.
3. Avoid logging full callback payloads, tokens, passkeys, or credentials.

See [Security](#security) for the full checklist. Include the configured
callback route in your OpenAPI document if you publish a public API contract.

#### mpesa:stk console command

Sends an STK Push via `MpesaSdk::instance()->stkPush(...)` and prints the raw
JSON response — handy for manual testing against sandbox.

```bash
php artisan mpesa:stk --phone=254712345678 --amount=100 --ref=ORDER-1 --callback=https://example.test/signal/ingress/pulse
```

| Option | Required | Description |
| --- | --- | --- |
| `--phone` | Yes | Customer phone number — `254XXXXXXXXX`, `+254712345678`, `07XXXXXXXX`, `7XXXXXXXX`, or a spaced variant. Normalized to `254XXXXXXXXX`. |
| `--amount` | Yes | Whole-number amount to charge, greater than zero. |
| `--ref` | Yes | Account reference (max 100 characters). |
| `--callback` | No | STK callback URL. Falls back to `MPESA_STK_CALLBACK_URL` when omitted. |
| `--description` | No | Payment description (max 182 characters). Defaults to `Description`. |

Exits with a failure code and an error message when the phone number, amount,
reference, or callback URL is missing or invalid, or when the request itself
fails.

### Transaction Status

Queries Daraja for the status of a completed or previously-initiated M-Pesa
transaction, identified by its receipt number or the conversation ID Daraja
issued for it. Useful for reconciling a payment when a callback was missed or
delayed.

- Daraja endpoint: `POST /mpesa/transactionstatus/v1/query`
- Config required: `shortcode`, `initiator_name`, `initiator_password` (see
  [Configuration](#configuration))

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

```php
use LaravelMpesa\MpesaSdk;

[$accepted, $response] = MpesaSdk::instance('c2b')->transactionStatus(
    identifier: 'UGEHJB6GMF',
    resultUrl: 'https://merchant.example/api/mpesa/status/result',
    timeoutUrl: 'https://merchant.example/api/mpesa/status/timeout',
);
```

- `$identifier` is either an Mpesa receipt number (`identifierType: '4'`,
  the default) or a provider/originator conversation ID
  (`identifierType: '1'`).
- `$resultUrl` / `$timeoutUrl` must be valid HTTPS URLs, unless the app is in
  sandbox and `allow_insecure_callbacks` is set. This package does not ship
  controllers for these — build them the same way you would any inbound
  webhook, following the same rules as [STK Push callbacks](#stk-push).
- Authenticates automatically if needed (see [Authentication](#authentication)).

Returns `[bool $accepted, array $response]`. `$accepted` reflects Daraja's
synchronous acceptance of the *query* — the actual transaction status still
arrives asynchronously at `$resultUrl`. Wrap `$response` in
[TransactionStatusResponse](#dtos) for typed access:

```php
use LaravelMpesa\DTOs\Responses\TransactionStatusResponse;

$dto = TransactionStatusResponse::fromArray($response);
$dto->conversationId;
$dto->accepted();
```

This is the only endpoint in the package that authenticates as an
*initiator* rather than just a consumer app, so it's the one that needs the
`SecurityCredential` field — see
[Initiator credentials and SecurityCredential](#initiator-credentials-and-securitycredential).

#### mpesa:status console command

Queries transaction status via `MpesaSdk::instance()->transactionStatus(...)`
and prints the raw JSON response.

```bash
php artisan mpesa:status --receipt=UGEHJB6GMF --result=https://example.test/status/result --timeout=https://example.test/status/timeout
php artisan mpesa:status --conversationId=AG_20260714_12345
```

| Option | Required | Description |
| --- | --- | --- |
| `--receipt` | One of receipt/conversationId | Mpesa receipt number. Queried with `IdentifierType=4`. |
| `--conversationId` | One of receipt/conversationId | Provider conversation or originator conversation ID. Queried with `IdentifierType=1`. |
| `--result` | No | Result callback URL. Falls back to `MPESA_TRANSACTION_STATUS_RESULT_URL`. |
| `--timeout` | No | Timeout callback URL. Falls back to `MPESA_TRANSACTION_STATUS_TIMEOUT_URL`. |
| `--remarks` | No | Query remarks. Defaults to `Transaction status query`. |
| `--occasion` | No | Query occasion. Defaults to `TransactionStatus`. |

Exactly one of `--receipt` or `--conversationId` must be provided. The command
fails with an error message when both or neither are given, or when the
result/timeout URLs are missing. It builds `SecurityCredential` the same way
as above.

## DTOs

Every payload the package sends or receives has a corresponding readonly DTO
with a `fromArray()`/`fromPayload()` factory. Unrecognized or missing fields
are `null`, and unmapped fields survive on the DTO's `payload` property, so a
Daraja field you haven't mapped is never silently lost.

### StkCallbackData

`LaravelMpesa\DTOs\Callbacks\StkCallbackData`

Built by `StkCallbackController` from the [STK callback](#stk-push) body, and
available on every dispatched callback event as `$event->callback`.

```php
use LaravelMpesa\DTOs\Callbacks\StkCallbackData;

$callback = StkCallbackData::fromPayload($request->all());

$callback->merchantRequestId;   // string
$callback->checkoutRequestId;   // string
$callback->resultCode;          // int
$callback->resultDescription;   // string
$callback->metadata;            // array<string, mixed> — flattened CallbackMetadata.Item
$callback->payload;             // array<string, mixed> — the raw callback body
$callback->succeeded();         // bool — true when resultCode === 0
$callback->metadataValue('MpesaReceiptNumber'); // mixed|null
```

### StkPushResponse

`LaravelMpesa\DTOs\Responses\StkPushResponse`

Wraps the synchronous response from [stkPush()](#stk-push).

```php
use LaravelMpesa\DTOs\Responses\StkPushResponse;

[, $response] = $mpesa->stkPush(/* ... */);
$dto = StkPushResponse::fromArray($response);

$dto->merchantRequestId;   // ?string
$dto->checkoutRequestId;   // ?string
$dto->responseCode;        // ?string
$dto->responseDescription; // ?string
$dto->customerMessage;     // ?string
$dto->accepted();          // bool — true when responseCode === '0'
```

`accepted()` reflects Daraja's synchronous acceptance only; the customer's
final payment outcome still arrives via the [STK callback](#stk-push).

### TransactionStatusResponse

`LaravelMpesa\DTOs\Responses\TransactionStatusResponse`

Wraps the synchronous response from [transactionStatus()](#transaction-status).

```php
use LaravelMpesa\DTOs\Responses\TransactionStatusResponse;

[, $response] = $mpesa->transactionStatus(/* ... */);
$dto = TransactionStatusResponse::fromArray($response);

$dto->originatorConversationId; // ?string
$dto->conversationId;           // ?string
$dto->responseCode;             // ?string
$dto->responseDescription;      // ?string
$dto->accepted();                // bool — true when responseCode === '0'
```

The actual transaction status still arrives asynchronously at the configured
result URL.

### RegisterUrlResponse

`LaravelMpesa\DTOs\Responses\RegisterUrlResponse`

Wraps the response from [registerUrls()](#register-urls-c2b).

```php
use LaravelMpesa\DTOs\Responses\RegisterUrlResponse;

[, $response] = $mpesa->registerUrls(/* ... */);
$dto = RegisterUrlResponse::fromArray($response);

$dto->originatorConversationId; // ?string
$dto->responseDescription;      // ?string
```

### C2bConfirmationData

`LaravelMpesa\DTOs\Callbacks\C2bConfirmationData`

Built by `ConfirmationCallbackController` from the
[C2B confirmation callback](#register-urls-c2b) body, and available on the
dispatched event as `$event->confirmation`.

```php
use LaravelMpesa\DTOs\Callbacks\C2bConfirmationData;

$confirmation = C2bConfirmationData::fromPayload($request->all());

$confirmation->transactionType;    // string
$confirmation->transId;            // string — Safaricom's transaction ID
$confirmation->transTime;          // string — YmdHis
$confirmation->transAmount;        // float
$confirmation->businessShortCode;  // string
$confirmation->billRefNumber;      // string — the account number the customer entered
$confirmation->invoiceNumber;      // string
$confirmation->orgAccountBalance;  // ?float — null when Safaricom sends it empty
$confirmation->thirdPartyTransId;  // string
$confirmation->msisdn;             // string — e.g. 254712345678
$confirmation->firstName;          // string
$confirmation->middleName;         // string
$confirmation->lastName;           // string
$confirmation->payload;            // array<string, mixed> — the raw callback body
```

## Security

1. Keep consumer secrets, passkeys, initiator passwords, and certificates
   outside source control.
2. Validate callback payloads against Safaricom's documented contract before
   changing payment state.
3. Make callbacks idempotent using provider transaction identifiers.
4. Verify order reference, shortcode, MSISDN, and amount before fulfillment.
5. Rate-limit application-owned initiation endpoints.
6. Never log OAuth tokens, passkeys, initiator passwords, security
   credentials, or full callback payloads.
7. Keep dependencies updated and run `composer audit` in CI.
8. Never send `initiator_password` as `SecurityCredential` directly — Daraja
   requires it RSA-encrypted with Safaricom's public certificate. The package
   does this for you by default; see
   [Initiator credentials and SecurityCredential](#initiator-credentials-and-securitycredential).

Open a private security advisory on the
[GitHub repository](https://github.com/clipsmm/laravel-mpesa) to report a
vulnerability, rather than a public issue.

## Testing

```bash
composer install
composer check
```

`composer check` runs everything CI runs: [Pint](https://github.com/laravel/pint)
(`composer lint`), [PHPStan](https://phpstan.org) via
[Larastan](https://github.com/larastan/larastan) (`composer analyse`, level
8), then the PHPUnit test suite (`composer test`). `composer format` fixes
style issues in place. `composer audit` checks `composer.lock` against known
advisories.

`pint.json` disables Pint's `php_unit_method_casing` rule: this codebase
deliberately uses `test_snakeCase` method names with the `#[Test]` attribute
rather than PHPUnit's `testCamelCase` convention.

`.github/workflows/ci.yml` runs on every push and pull request against
`main`/`master`, on published releases, and manually via `workflow_dispatch`:
a PHPUnit matrix (PHP 8.2/8.3/8.4, plus a `prefer-lowest` run on PHP 8.2),
Pint, PHPStan, and `composer audit`. The test suite uses Laravel's HTTP
client fakes throughout and never contacts Safaricom, so it's safe to run
without sandbox credentials.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for a full history of changes, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## License

This package is released under the MIT license declared in `composer.json`.
