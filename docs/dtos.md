# DTOs

Every payload the package sends or receives has a corresponding readonly DTO
with a `fromArray()`/`fromPayload()` factory. Unrecognized or missing fields
are `null`, and unmapped fields survive on the DTO's `payload` property, so a
Daraja field you haven't mapped is never silently lost.

## `StkCallbackData`

`LaravelMpesa\DTOs\Callbacks\StkCallbackData`

Built by `StkCallbackController` from the [STK callback](endpoints/stk-push.md#receiving-the-stk-callback)
body, and available on every dispatched callback event as `$event->callback`.

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

## `StkPushResponse`

`LaravelMpesa\DTOs\Responses\StkPushResponse`

Wraps the synchronous response from [`stkPush()`](endpoints/stk-push.md).

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
final payment outcome still arrives via the
[STK callback](endpoints/stk-push.md#receiving-the-stk-callback).

## `TransactionStatusResponse`

`LaravelMpesa\DTOs\Responses\TransactionStatusResponse`

Wraps the synchronous response from [`transactionStatus()`](endpoints/transaction-status.md).

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

## `RegisterUrlResponse`

`LaravelMpesa\DTOs\Responses\RegisterUrlResponse`

Wraps the response from [`registerUrls()`](endpoints/register-url.md).

```php
use LaravelMpesa\DTOs\Responses\RegisterUrlResponse;

[, $response] = $mpesa->registerUrls(/* ... */);
$dto = RegisterUrlResponse::fromArray($response);

$dto->originatorConversationId; // ?string
$dto->responseDescription;      // ?string
```

## `C2bConfirmationData`

`LaravelMpesa\DTOs\Callbacks\C2bConfirmationData`

Built by `ConfirmationCallbackController` from the
[C2B confirmation callback](endpoints/register-url.md#receiving-the-confirmation-callback)
body, and available on the dispatched event as `$event->confirmation`.

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
