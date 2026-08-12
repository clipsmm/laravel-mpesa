# Console Commands

## `mpesa:stk`

Sends an STK Push via `MpesaSdk::instance()->stkPush(...)` and prints the raw
JSON response.

```bash
php artisan mpesa:stk --phone=254712345678 --amount=100 --ref=ORDER-1 --callback=https://example.test/signal/ingress/pulse
```

| Option          | Required | Description                                                                 |
| ---------------- | -------- | ---------------------------------------------------------------------------- |
| `--phone`        | Yes      | Customer phone number — `2547XXXXXXXX`, `07XXXXXXXX`, or `7XXXXXXXX`.        |
| `--amount`       | Yes      | Whole-number amount to charge, greater than zero.                            |
| `--ref`          | Yes      | Account reference (max 100 characters).                                      |
| `--callback`     | No       | STK callback URL. Falls back to `MPESA_STK_CALLBACK_URL` when omitted.       |
| `--description`  | No       | Payment description (max 182 characters). Defaults to `Description`.         |

Exits with a failure code and an error message when the phone number, amount,
reference, or callback URL is missing or invalid, or when the request itself
fails.

## `mpesa:status`

Queries transaction status via `MpesaSdk::instance()->transactionStatus(...)`
and prints the raw JSON response.

```bash
php artisan mpesa:status --receipt=UGEHJB6GMF --result=https://example.test/status/result --timeout=https://example.test/status/timeout
php artisan mpesa:status --conversationId=AG_20260714_12345
```

| Option              | Required               | Description                                                                    |
| -------------------- | ----------------------- | -------------------------------------------------------------------------------- |
| `--receipt`           | One of receipt/conversationId | Mpesa receipt number. Queried with `IdentifierType=4`.                     |
| `--conversationId`    | One of receipt/conversationId | Provider conversation or originator conversation ID. Queried with `IdentifierType=1`. |
| `--result`            | No                       | Result callback URL. Falls back to `MPESA_TRANSACTION_STATUS_RESULT_URL`.        |
| `--timeout`           | No                       | Timeout callback URL. Falls back to `MPESA_TRANSACTION_STATUS_TIMEOUT_URL`.       |
| `--remarks`           | No                       | Query remarks. Defaults to `Transaction status query`.                           |
| `--occasion`          | No                       | Query occasion. Defaults to `TransactionStatus`.                                 |

Exactly one of `--receipt` or `--conversationId` must be provided. The command
fails with an error message when both or neither are given, or when the
result/timeout URLs are missing.

The `SecurityCredential` this command sends is built the same way as any other
`transactionStatus()` call — see
[Initiator credentials and SecurityCredential](configuration.md#initiator-credentials-and-securitycredential).
