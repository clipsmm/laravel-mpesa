# HTTP API Surface

This package registers a configurable inbound STK callback endpoint:

- `POST /signal/ingress/pulse`

The default path intentionally avoids obvious provider, payment, and callback
terms because Safaricom may reject URLs containing those words. Host
applications should keep the same idea when overriding the route.

Configuration lives under `mpesa.callbacks`:

- `enabled`: registers or disables callback routes.
- `middleware`: route middleware for callback endpoints.
- `path_prefix`: shared callback route prefix.
- `allowed_ips`: callback source IP allowlist. Defaults to `*` for local and
	testing environments, and Safaricom callback IPs otherwise. Set
	`MPESA_CALLBACK_ALLOWED_IPS` to a comma-separated list to override.
- `routes.stk`: full route override for the STK callback.
- `controllers.stk`: controller class override. Host applications may replace
	the class or extend `LaravelMpesa\Http\Controllers\Callbacks\StkCallbackController`.
- `events.stk_received`, `events.stk_succeeded`, `events.stk_failed`: event
	class overrides. Compatible events must accept a `StkCallbackData` instance.

The default controller emits a received event for every accepted payload, then a
succeeded event when `ResultCode` is `0` or a failed event for every other result
code. It responds with JSON containing `accepted`, `checkoutRequestId`, and
`resultCode`.

Applications should include the configured callback route in their OpenAPI
document when they publish a public API contract.

The package's outbound public PHP API is documented in the root
[`README.md`](../../README.md#public-api).

## Console Commands

```bash
php artisan mpesa:stk --phone=254712345678 --amount=100 --ref=ORDER-1 --callback=https://example.test/signal/ingress/pulse
php artisan mpesa:status --receipt=UGEHJB6GMF --result=https://example.test/status/result --timeout=https://example.test/status/timeout
php artisan mpesa:status --conversationId=AG_20260714_12345
```

`mpesa:status` requires exactly one of `--receipt` or `--conversationId`. When
`--result` or `--timeout` are omitted, it falls back to
`MPESA_TRANSACTION_STATUS_RESULT_URL` and `MPESA_TRANSACTION_STATUS_TIMEOUT_URL`.
