# Register URLs (C2B)

Registers the validation and confirmation URLs Safaricom calls when a
customer pays your C2B shortcode directly (paybill/till), as opposed to an
STK Push you initiated. This is a one-time (or infrequent) setup call, not
something you run per transaction.

- Daraja endpoint: `POST /mpesa/c2b/v2/registerurl`
- Config required: `shortcode` (see [Configuration](../configuration.md))

## `registerUrls(...)`

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
- Authenticates automatically if needed (see [Authentication](authentication.md)).

Returns `[bool $successful, array $response]`. `$successful` reflects HTTP
transport/acceptance only. Wrap `$response` in
[`RegisterUrlResponse`](../dtos.md#registerurlresponse) for typed access:

```php
use LaravelMpesa\DTOs\Responses\RegisterUrlResponse;

$dto = RegisterUrlResponse::fromArray($response);
$dto->originatorConversationId;
$dto->responseDescription;
```

## Receiving the confirmation callback

Safaricom calls `validationUrl` first (only if C2B validation is enabled on
your shortcode — most integrations skip it), then `confirmationUrl` once the
transaction is complete. This package ships a controller and route for
**confirmation**:

- `POST /signal/ingress/echo`

It uses the same `mpesa.callbacks` block as STK (`enabled`, `middleware`,
`path_prefix`, `allowed_ips`), keyed by `confirmation` instead of `stk`:

- `routes.confirmation` — full route override for the confirmation callback.
- `controllers.confirmation` — controller class override. Host applications
  may replace the class or extend
  `LaravelMpesa\Http\Controllers\Callbacks\ConfirmationCallbackController`.
- `events.c2b_confirmation_received` — event class override. Compatible
  events must accept a `C2bConfirmationData` instance.

The default controller dispatches a `C2bConfirmationReceived` event for every
IP-allowlisted payload and responds `{"ResultCode": 0, "ResultDesc": "Success"}`
— the acknowledgment Safaricom's Confirmation URL contract expects. The event
payload (`$event->confirmation`) is a
[`C2bConfirmationData`](../dtos.md#c2bconfirmationdata) DTO:

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
allowlist as [STK Push callbacks](stk-push.md#receiving-the-stk-callback).
Apply the same [host application responsibilities](stk-push.md#receiving-the-stk-callback)
here — reconcile the payload against what you expect and make processing
idempotent using `TransID`.

**Validation** is not implemented by this package — it's an opt-in Safaricom
feature most integrations don't enable, and its contract (synchronous
accept/reject) doesn't fit the fire-and-forget event pattern used above. If
you enable it, build `validationUrl` as its own route the same way you'd
build any inbound webhook.
