# Security

## Checklist

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
   does this for you when `security_credential_cert` is configured; see
   [Initiator credentials and SecurityCredential](configuration.md#initiator-credentials-and-securitycredential).

## Reporting a vulnerability

Open a private security advisory on the
[GitHub repository](https://github.com/clipsmm/laravel-mpesa) rather than a
public issue.
