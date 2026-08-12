# Transaction Status

Queries Daraja for the status of a completed or previously-initiated M-Pesa
transaction, identified by its receipt number or the conversation ID Daraja
issued for it. Useful for reconciling a payment when a callback was missed or
delayed.

- Daraja endpoint: `POST /mpesa/transactionstatus/v1/query`
- Config required: `shortcode`, `initiator_name`, `initiator_password` (see
  [Configuration](../configuration.md))

## `transactionStatus(...)`

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
  webhook, following the same rules as
  [STK Push callbacks](stk-push.md#receiving-the-stk-callback).
- Authenticates automatically if needed (see [Authentication](authentication.md)).

Returns `[bool $accepted, array $response]`. `$accepted` reflects Daraja's
synchronous acceptance of the *query* — the actual transaction status still
arrives asynchronously at `$resultUrl`. Wrap `$response` in
[`TransactionStatusResponse`](../dtos.md#transactionstatusresponse) for typed
access:

```php
use LaravelMpesa\DTOs\Responses\TransactionStatusResponse;

$dto = TransactionStatusResponse::fromArray($response);
$dto->conversationId;
$dto->accepted();
```

## `SecurityCredential`

This is the only endpoint in the package that authenticates as an
*initiator* rather than just a consumer app, so it's the one that needs the
`SecurityCredential` field: `initiator_password` RSA-encrypted (PKCS#1) with
Safaricom's public certificate. The package bundles Safaricom's sandbox and
production certificates and picks the right one automatically — you only
need to set `initiator_name` and `initiator_password`. See
[Initiator credentials and SecurityCredential](../configuration.md#initiator-credentials-and-securitycredential)
for the full details, including how to override the certificate if Safaricom
ever rotates one.

## `mpesa:status` console command

Queries transaction status via `MpesaSdk::instance()->transactionStatus(...)`
and prints the raw JSON response.

```bash
php artisan mpesa:status --receipt=UGEHJB6GMF --result=https://example.test/status/result --timeout=https://example.test/status/timeout
php artisan mpesa:status --conversationId=AG_20260714_12345
```

| Option           | Required                       | Description                                                                            |
| ----------------- | ------------------------------- | ------------------------------------------------------------------------------------------ |
| `--receipt`        | One of receipt/conversationId   | Mpesa receipt number. Queried with `IdentifierType=4`.                                 |
| `--conversationId` | One of receipt/conversationId   | Provider conversation or originator conversation ID. Queried with `IdentifierType=1`.  |
| `--result`         | No                               | Result callback URL. Falls back to `MPESA_TRANSACTION_STATUS_RESULT_URL`.              |
| `--timeout`        | No                               | Timeout callback URL. Falls back to `MPESA_TRANSACTION_STATUS_TIMEOUT_URL`.            |
| `--remarks`        | No                               | Query remarks. Defaults to `Transaction status query`.                                 |
| `--occasion`       | No                               | Query occasion. Defaults to `TransactionStatus`.                                       |

Exactly one of `--receipt` or `--conversationId` must be provided. The command
fails with an error message when both or neither are given, or when the
result/timeout URLs are missing. It builds `SecurityCredential` the same way
as above.
