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

## Command default callback URLs

The `mpesa:stk` and `mpesa:status` console commands (see
[STK Push](endpoints/stk-push.md#mpesastk-console-command) and
[Transaction Status](endpoints/transaction-status.md#mpesastatus-console-command))
fall back to these when their `--callback` / `--result` / `--timeout` options
are omitted:

```dotenv
MPESA_STK_CALLBACK_URL=https://merchant.example/api/mpesa/stk/callback
MPESA_TRANSACTION_STATUS_RESULT_URL=https://merchant.example/api/mpesa/status/result
MPESA_TRANSACTION_STATUS_TIMEOUT_URL=https://merchant.example/api/mpesa/status/timeout
```

## Full config reference

The published `config/mpesa.php` also controls the inbound STK callback
route, middleware, IP allowlist, and dispatched events — see
[STK Push § Receiving the STK callback](endpoints/stk-push.md#receiving-the-stk-callback)
for the `mpesa.callbacks` key.
