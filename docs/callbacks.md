# Callbacks & Webhooks

This package registers a configurable inbound STK callback endpoint:

- `POST /signal/ingress/pulse`

The default path intentionally avoids obvious provider, payment, and callback
terms because Safaricom may reject URLs containing those words. Host
applications should keep the same idea when overriding the route.

## Configuration

Configuration lives under `mpesa.callbacks` in `config/mpesa.php`:

- `enabled` — registers or disables callback routes.
- `middleware` — route middleware for callback endpoints.
- `path_prefix` — shared callback route prefix.
- `allowed_ips` — callback source IP allowlist. Defaults to `*` for local and
  testing environments, and Safaricom callback IPs otherwise. Set
  `MPESA_CALLBACK_ALLOWED_IPS` to a comma-separated list to override.
- `routes.stk` — full route override for the STK callback.
- `controllers.stk` — controller class override. Host applications may
  replace the class or extend
  `LaravelMpesa\Http\Controllers\Callbacks\StkCallbackController`.
- `events.stk_received`, `events.stk_succeeded`, `events.stk_failed` — event
  class overrides. Compatible events must accept a `StkCallbackData` instance.

## Behavior

The default controller emits a received event for every accepted payload,
then a succeeded event when `ResultCode` is `0` or a failed event for every
other result code. It responds with JSON containing `accepted`,
`checkoutRequestId`, and `resultCode`. The event payload (`$event->callback`)
is a `LaravelMpesa\DTOs\Callbacks\StkCallbackData` DTO — see
[DTOs](dtos.md#stkcallbackdata).

Requests from IPs outside `allowed_ips` are rejected with `403 Forbidden`
before the payload is parsed or any event is dispatched.

Applications should include the configured callback route in their OpenAPI
document when they publish a public API contract.

## Host application responsibilities

Safaricom callbacks are not signed. IP allowlisting narrows the source, but
the host application must still, on every callback:

1. Reconcile amount, reference, shortcode, and MSISDN against the original
   request before changing payment state.
2. Make processing idempotent using `checkoutRequestId` /
   `merchantRequestId` — Safaricom may retry callback delivery.
3. Avoid logging full callback payloads, tokens, passkeys, or credentials.

See [Security](security.md) for the full checklist.
