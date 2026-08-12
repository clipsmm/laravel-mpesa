# Configuration

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

## Initiator credentials and `SecurityCredential`

`transactionStatus()` (and any future B2C/reversal command) authenticates as
an initiator using Daraja's `SecurityCredential` field. Daraja never accepts a
plaintext password here — it must be the initiator password RSA-encrypted
(PKCS#1) with Safaricom's public certificate for the target environment.
Download the sandbox or production certificate from the
[Daraja API portal](https://developer.safaricom.co.ke/) and point the package
at it:

```dotenv
MPESA_INITIATOR_NAME=your-initiator
MPESA_INITIATOR_PASSWORD=your-initiator-password
MPESA_SECURITY_CREDENTIAL_CERT=/absolute/path/to/SandboxCertificate.cer
```

The equivalent B2C and B2B variables are `MPESA_B2C_INITIATOR_NAME` /
`MPESA_B2C_INITIATOR_PASSWORD` / `MPESA_B2C_SECURITY_CREDENTIAL_CERT` and
`MPESA_B2B_INITIATOR_NAME` / `MPESA_B2B_INITIATOR_PASSWORD` /
`MPESA_B2B_SECURITY_CREDENTIAL_CERT`.

If you already compute the encrypted credential elsewhere, set
`MPESA_SECURITY_CREDENTIAL` (or the `_B2C_` / `_B2B_` equivalent) to the
final base64 value instead — when set, it is used as-is and the certificate
is not read.

## Command default callback URLs

The `mpesa:stk` and `mpesa:status` console commands (see
[Console Commands](console-commands.md)) fall back to these when their
`--callback` / `--result` / `--timeout` options are omitted:

```dotenv
MPESA_STK_CALLBACK_URL=https://merchant.example/api/mpesa/stk/callback
MPESA_TRANSACTION_STATUS_RESULT_URL=https://merchant.example/api/mpesa/status/result
MPESA_TRANSACTION_STATUS_TIMEOUT_URL=https://merchant.example/api/mpesa/status/timeout
```

## Full config reference

The published `config/mpesa.php` also controls the inbound callback route,
middleware, IP allowlist, and dispatched events — see
[Callbacks & Webhooks](callbacks.md) for the `mpesa.callbacks` key.
