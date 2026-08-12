# Usage

```php
use LaravelMpesa\MpesaSdk;

$mpesa = MpesaSdk::instance('c2b');

[$registered, $registration] = $mpesa->registerUrls(
    validationUrl: 'https://merchant.example/api/mpesa/validation',
    confirmationUrl: 'https://merchant.example/api/mpesa/confirmation',
);

[$accepted, $response] = $mpesa->stkPush(
    receiver: '254712345678',
    amount: 1500,
    ref: 'ORDER-1001',
    description: 'Payment for ORDER-1001',
    callbackUrl: 'https://merchant.example/api/mpesa/stk/callback',
);
```

Both operations return `[bool $successful, array $response]`. Treat the
boolean as transport/provider acceptance only; reconcile final payment state
from a validated callback (see [Callbacks & Webhooks](callbacks.md)). Wrap
`$response` in a [DTO](dtos.md) for typed access.

## The `Mpesa` facade

The `Mpesa` facade proxies to the application container's `MpesaSdk` singleton
(the `mpesa.default` application) and exposes the same operations statically:

```php
use LaravelMpesa\Mpesa;

[$accepted, $response] = Mpesa::stkPush(
    receiver: '254712345678',
    amount: 1500,
    ref: 'ORDER-1001',
    description: 'Payment for ORDER-1001',
    callbackUrl: 'https://merchant.example/api/mpesa/stk/callback',
);
```

Use `MpesaSdk::instance('b2c')` (or `new MpesaSdk('b2c')`) instead of the
facade when you need an application other than the configured default — the
facade always resolves `mpesa.default`.

Every method available this way is documented in full in the
[API Reference](api-reference.md).
