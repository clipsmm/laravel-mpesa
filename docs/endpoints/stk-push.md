# STK Push (M-Pesa Express)

Prompts a customer's phone with an STK (SIM Toolkit) prompt to enter their
M-Pesa PIN and pay a specific amount. This is the endpoint most integrations
use for checkout flows.

- Daraja endpoint: `POST /mpesa/stkpush/v1/processrequest`
- Config required: `shortcode`, `passkey` (see [Configuration](../configuration.md))

## `stkPush(...)`

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

- `$receiver` not matching `2547XXXXXXXX`.
- `$amount` not a positive whole number.
- `$ref` empty or longer than 100 characters.
- `$description` empty or longer than 182 characters.
- `$callbackUrl` not a valid HTTPS URL (unless sandbox + insecure callbacks
  allowed).

Authenticates automatically if needed (see [Authentication](authentication.md)).

Returns `[bool $accepted, array $response]`. `$accepted` reflects Daraja's
synchronous acceptance only — **not** whether the customer actually paid;
that outcome arrives later via the callback below. Wrap `$response` in
[`StkPushResponse`](../dtos.md#stkpushresponse) for typed access:

```php
use LaravelMpesa\DTOs\Responses\StkPushResponse;

$dto = StkPushResponse::fromArray($response);
$dto->checkoutRequestId;
$dto->accepted(); // Daraja accepted the push request, not that it was paid
```

## Receiving the STK callback

This package registers a configurable inbound callback endpoint that Daraja
calls once the customer completes (or cancels/times out on) the STK prompt:

- `POST /signal/ingress/pulse`

The default path intentionally avoids obvious provider, payment, and callback
terms because Safaricom may reject URLs containing those words. Keep the same
idea if you override the route.

### Configuration

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

### Behavior

The default controller emits a received event for every accepted payload,
then a succeeded event when `ResultCode` is `0` or a failed event for every
other result code. It responds with JSON containing `accepted`,
`checkoutRequestId`, and `resultCode`. The event payload (`$event->callback`)
is a [`StkCallbackData`](../dtos.md#stkcallbackdata) DTO:

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

See [Security](../security.md) for the full checklist. Include the configured
callback route in your OpenAPI document if you publish a public API contract.

## `mpesa:stk` console command

Sends an STK Push via `MpesaSdk::instance()->stkPush(...)` and prints the raw
JSON response — handy for manual testing against sandbox.

```bash
php artisan mpesa:stk --phone=254712345678 --amount=100 --ref=ORDER-1 --callback=https://example.test/signal/ingress/pulse
```

| Option         | Required | Description                                                            |
| -------------- | -------- | ------------------------------------------------------------------------ |
| `--phone`       | Yes      | Customer phone number — `2547XXXXXXXX`, `07XXXXXXXX`, or `7XXXXXXXX`.  |
| `--amount`      | Yes      | Whole-number amount to charge, greater than zero.                      |
| `--ref`         | Yes      | Account reference (max 100 characters).                                |
| `--callback`    | No       | STK callback URL. Falls back to `MPESA_STK_CALLBACK_URL` when omitted. |
| `--description` | No       | Payment description (max 182 characters). Defaults to `Description`.  |

Exits with a failure code and an error message when the phone number, amount,
reference, or callback URL is missing or invalid, or when the request itself
fails.
