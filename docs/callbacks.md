# Callbacks

Securely handle payment notifications from M-Pesa.

## Overview

M-Pesa sends callbacks to your application when:
- STK Push payment is completed/failed
- C2B payment is received
- Transaction status changes

## Callback Validation Trait

Use the `ValidatesMpesaCallback` trait to parse and validate callbacks:

```php
use LaravelMpesa\Traits\ValidatesMpesaCallback;

class MpesaCallbackController extends Controller
{
    use ValidatesMpesaCallback;
}
```

## STK Push Callbacks

### Route Setup

```php
// routes/api.php
Route::post('mpesa/stk/callback', [MpesaCallbackController::class, 'stkCallback']);
```

### Controller Implementation

```php
namespace App\Http\Controllers\Api;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LaravelMpesa\Traits\ValidatesMpesaCallback;

class MpesaCallbackController extends Controller
{
    use ValidatesMpesaCallback;

    public function stkCallback(Request $request): JsonResponse
    {
        try {
            // Validate and parse callback
            $callback = $this->validateStkCallback($request);
            
            // Check if payment was successful
            if ($this->isStkCallbackSuccessful($callback)) {
                // Extract payment details
                $details = $this->extractStkPaymentDetails($callback);
                
                // Update payment record
                Payment::where('checkout_request_id', $callback['checkout_request_id'])
                    ->update([
                        'status' => 'completed',
                        'mpesa_receipt_number' => $details['mpesa_receipt_number'],
                        'amount_paid' => $details['amount'],
                        'phone_number' => $details['phone_number'],
                        'transaction_date' => $details['transaction_date'],
                    ]);
                    
                // Fulfill order, send confirmation email, etc.
            } else {
                // Payment failed or cancelled
                Payment::where('checkout_request_id', $callback['checkout_request_id'])
                    ->update([
                        'status' => 'failed',
                        'result_desc' => $callback['result_desc'],
                    ]);
            }
            
            // Always respond with success
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
            
        } catch (\InvalidArgumentException $e) {
            Log::error('Invalid M-Pesa callback', ['error' => $e->getMessage()]);
            
            // Still respond with success to prevent retries
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
        }
    }
}
```

### Callback Structure

**Successful Payment:**
```php
[
    'merchant_request_id' => '29115-34620561-1',
    'checkout_request_id' => 'ws_CO_191220191020363925',
    'result_code' => 0,
    'result_desc' => 'The service request is processed successfully.',
    'callback_metadata' => [
        'Amount' => 1.00,
        'MpesaReceiptNumber' => 'NLJ7RT61SV',
        'TransactionDate' => 20191219102115,
        'PhoneNumber' => 254708374149,
    ],
]
```

**Failed Payment:**
```php
[
    'merchant_request_id' => '29115-34620561-1',
    'checkout_request_id' => 'ws_CO_191220191020363925',
    'result_code' => 1032,
    'result_desc' => 'Request cancelled by user',
    'callback_metadata' => [],
]
```

## C2B Callbacks

### Confirmation Callback

```php
public function c2bConfirmation(Request $request): JsonResponse
{
    try {
        $callback = $this->validateC2bCallback($request);
        
        // Store payment
        Payment::create([
            'mpesa_trans_id' => $callback['trans_id'],
            'transaction_type' => $callback['transaction_type'],
            'amount' => $callback['trans_amount'],
            'phone_number' => $callback['msisdn'],
            'bill_ref_number' => $callback['bill_ref_number'],
            'status' => 'completed',
        ]);
        
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    } catch (\Exception $e) {
        Log::error('C2B confirmation failed', ['error' => $e->getMessage()]);
        
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    }
}
```

### Validation Callback

```php
public function c2bValidation(Request $request): JsonResponse
{
    try {
        $callback = $this->validateC2bCallback($request);
        
        // Validate business logic
        if ($callback['trans_amount'] < 1) {
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Amount too low',
            ]);
        }
        
        if ($callback['business_short_code'] !== config('mpesa.apps.c2b.shortcode')) {
            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Invalid shortcode',
            ]);
        }
        
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Accepted',
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'ResultCode' => 1,
            'ResultDesc' => 'Rejected',
        ]);
    }
}
```

## Helper Methods

### `validateStkCallback(Request $request)`

Validates and parses STK Push callback payload.

**Returns:**
```php
[
    'merchant_request_id' => string,
    'checkout_request_id' => string,
    'result_code' => int,
    'result_desc' => ?string,
    'callback_metadata' => array,
]
```

**Throws:** `InvalidArgumentException` if invalid

### `validateC2bCallback(Request $request)`

Validates and parses C2B callback payload.

**Returns:**
```php
[
    'transaction_type' => string,
    'trans_id' => string,
    'trans_time' => string,
    'trans_amount' => float,
    'business_short_code' => string,
    'bill_ref_number' => string,
    'msisdn' => string,
    // ... more fields
]
```

### `isStkCallbackSuccessful(array $callback)`

Checks if STK callback indicates successful payment.

```php
if ($this->isStkCallbackSuccessful($callback)) {
    // Payment completed
}
```

### `extractStkPaymentDetails(array $callback)`

Extracts payment metadata from successful STK callback.

**Returns:**
```php
[
    'amount' => float,
    'mpesa_receipt_number' => ?string,
    'transaction_date' => ?string,
    'phone_number' => ?string,
]
```

## Security Best Practices

### 1. Idempotency

Always check for duplicates:

```php
$exists = Payment::where('mpesa_trans_id', $callback['trans_id'])->exists();

if ($exists) {
    Log::info('Duplicate callback ignored');
    return response()->json(['ResultCode' => 0]);
}
```

### 2. Always Respond with Success

```php
// ✅ Correct
return response()->json([
    'ResultCode' => 0,
    'ResultDesc' => 'Accepted',
]);

// ❌ Wrong - causes M-Pesa to retry
return response()->json(['error' => 'Failed'], 500);
```

### 3. Use Database Transactions

```php
DB::transaction(function () use ($callback) {
    $payment = Payment::lockForUpdate()->find($id);
    
    if ($payment->status !== 'pending') {
        return; // Already processed
    }
    
    $payment->update(['status' => 'completed']);
});
```

### 4. Validate Shortcode

```php
if ($callback['business_short_code'] !== config('mpesa.apps.c2b.shortcode')) {
    Log::warning('Invalid shortcode');
    return;
}
```

### 5. Don't Log Sensitive Data

```php
// ❌ Don't log full callback
Log::info('Callback', $request->all());

// ✅ Log minimal data
Log::info('Payment callback', [
    'checkout_id' => $callback['checkout_request_id'],
    'result_code' => $callback['result_code'],
]);
```

## CSRF Protection

Exclude M-Pesa callbacks from CSRF verification:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'api/mpesa/*',
];
```

## Testing Callbacks

```php
public function test_successful_stk_callback()
{
    $payment = Payment::factory()->create([
        'checkout_request_id' => 'ws_CO_123',
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/mpesa/stk/callback', [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '123',
                'CheckoutRequestID' => 'ws_CO_123',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk();
    
    $payment->refresh();
    $this->assertEquals('completed', $payment->status);
}
```

## Troubleshooting

**Callbacks not arriving?**
- Check URL is publicly accessible
- Verify HTTPS in production
- Check firewall/server configuration
- Enable logging to see if requests reach your server

**Duplicate callbacks?**
- M-Pesa may retry if no response
- Always implement idempotency
- Use unique transaction IDs

**Callback structure differs?**
- M-Pesa may update callback format
- Always check raw response when issues occur
- Use try-catch to handle gracefully

## Complete Example

See the [full callback controller example](https://github.com/clipsmm/laravel-mpesa/blob/main/docs/guides/callback-controller-example.md) for a production-ready implementation.

## Next Steps

- [STK Query for backup verification](stk-query.md)
- [Security best practices](security.md)
- [Error handling](error-handling.md)
