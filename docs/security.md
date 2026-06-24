# Security

Best practices for securing your M-Pesa integration.

## Credentials Security

### Never Commit Credentials

```bash
# ❌ Never do this
MPESA_CONSUMER_KEY=abc123...

# ✅ Use .env (already in .gitignore)
```

### Separate Sandbox and Production

```bash
# Development (.env.local)
MPESA_API_STATUS=sandbox
MPESA_CONSUMER_KEY=sandbox-key

# Production (.env.production)
MPESA_API_STATUS=live
MPESA_CONSUMER_KEY=production-key
```

### Rotate Credentials

- Rotate keys every 90 days
- Immediately rotate if compromised
- Use different keys per environment

## Callback Security

### Always Use HTTPS

```php
// ✅ Production
MPESA_API_STATUS=live
MPESA_ALLOW_INSECURE_CALLBACKS=false

// ✅ Development only
MPESA_API_STATUS=sandbox
MPESA_ALLOW_INSECURE_CALLBACKS=true
```

### Validate Callback Structure

```php
use LaravelMpesa\Traits\ValidatesMpesaCallback;

public function callback(Request $request)
{
    try {
        $callback = $this->validateStkCallback($request);
        // Validated and parsed
    } catch (InvalidArgumentException $e) {
        // Invalid structure - possible attack
        Log::warning('Invalid callback structure');
        return response()->json(['ResultCode' => 0]);
    }
}
```

### Verify Shortcode

```php
if ($callback['business_short_code'] !== config('mpesa.apps.c2b.shortcode')) {
    Log::warning('Shortcode mismatch', [
        'expected' => config('mpesa.apps.c2b.shortcode'),
        'received' => $callback['business_short_code'],
    ]);
    return response()->json(['ResultCode' => 1]);
}
```

### Implement Idempotency

```php
// Prevent duplicate processing
$payment = Payment::where('mpesa_trans_id', $callback['trans_id'])->first();

if ($payment && $payment->status !== 'pending') {
    Log::info('Duplicate callback ignored');
    return response()->json(['ResultCode' => 0]);
}
```

## Payment Validation

### Verify Amount

```php
$order = Order::find($orderId);

if ($callback['trans_amount'] !== $order->total) {
    Log::alert('Amount mismatch', [
        'expected' => $order->total,
        'received' => $callback['trans_amount'],
    ]);
    // Hold for manual review
    return response()->json(['ResultCode' => 1]);
}
```

### Verify Account Reference

```php
$order = Order::where('reference', $callback['bill_ref_number'])->first();

if (!$order) {
    Log::warning('Unknown reference', [
        'reference' => $callback['bill_ref_number'],
    ]);
    return response()->json(['ResultCode' => 1]);
}
```

### Use Database Transactions

```php
DB::transaction(function () use ($callback) {
    $payment = Payment::lockForUpdate()
        ->where('checkout_request_id', $callback['checkout_request_id'])
        ->first();
        
    if ($payment->status !== 'pending') {
        return; // Already processed
    }
    
    $payment->update(['status' => 'completed']);
    
    // Fulfill order atomically
    $payment->order->markAsPaid();
});
```

## Logging Security

### Don't Log Sensitive Data

```php
// ❌ Logs everything including credentials
Log::info('M-Pesa request', $requestData);

// ✅ Package excludes sensitive fields automatically
$mpesa->stkPush(...);  // Password excluded from logs
```

### Sanitize User Input

```php
Log::info('Payment initiated', [
    'user_id' => $user->id,
    'amount' => $amount,
    // Don't log raw user input
]);
```

### Use Separate Log Channels

```php
// config/logging.php
'channels' => [
    'mpesa' => [
        'driver' => 'daily',
        'path' => storage_path('logs/mpesa.log'),
        'level' => 'info',
    ],
],

// Usage
Log::channel('mpesa')->info('Payment processed');
```

## Rate Limiting

### Limit STK Push Requests

```php
// routes/api.php
Route::middleware(['throttle:5,1'])->group(function () {
    Route::post('payments/initiate', [PaymentController::class, 'initiate']);
});
```

### Limit Per User

```php
use Illuminate\Support\Facades\RateLimiter;

RateLimiter::for('mpesa-per-user', function (Request $request) {
    return Limit::perMinute(3)->by($request->user()->id);
});
```

## Input Validation

### Validate Phone Numbers

```php
$request->validate([
    'phone' => [
        'required',
        'regex:/^2547\d{8}$/',
    ],
]);
```

### Validate Amounts

```php
$request->validate([
    'amount' => [
        'required',
        'numeric',
        'min:1',
        'max:150000',
    ],
]);
```

### Sanitize References

```php
$reference = preg_replace('/[^A-Za-z0-9-_]/', '', $request->reference);
$reference = substr($reference, 0, 100);
```

## Production Checklist

- [ ] All credentials in environment variables
- [ ] Different credentials for sandbox/production
- [ ] HTTPS enforced for callbacks
- [ ] Callback validation implemented
- [ ] Idempotency checks in place
- [ ] Amount and reference verification
- [ ] Database transactions for atomicity
- [ ] Rate limiting enabled
- [ ] Input validation on all endpoints
- [ ] Logging excludes sensitive data
- [ ] Separate log channel for M-Pesa
- [ ] Regular credential rotation
- [ ] Security audit completed
- [ ] Dependency updates automated

## Security Audit

Run security checks:

```bash
# Check for known vulnerabilities
composer audit

# Run static analysis
composer analyse

# Check code style
composer format:test
```

## Reporting Vulnerabilities

If you discover a security vulnerability:

1. **Do not** open a public issue
2. Email: security@example.com
3. Include detailed information
4. Allow time for a fix before disclosure

## Additional Resources

- [Safaricom Security Guidelines](https://developer.safaricom.co.ke/docs)
- [OWASP PHP Security](https://owasp.org/www-project-php-security/)
- [Laravel Security Best Practices](https://laravel.com/docs/security)

## Next Steps

- [Testing your integration](testing.md)
- [Error handling](error-handling.md)
- [Troubleshooting](troubleshooting.md)
