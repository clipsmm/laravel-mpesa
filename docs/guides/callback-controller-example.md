# Example Callback Controller

This example shows how to implement secure M-Pesa callback handlers.

## Controller Implementation

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LaravelMpesa\Traits\ValidatesMpesaCallback;

class MpesaCallbackController extends Controller
{
    use ValidatesMpesaCallback;

    /**
     * Handle STK Push callback from M-Pesa.
     */
    public function stkPushCallback(Request $request): JsonResponse
    {
        try {
            // Validate and parse the callback
            $callback = $this->validateStkCallback($request);

            // Process the callback idempotently
            $this->processStkCallback($callback);

            // Always respond with success to M-Pesa
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
        } catch (\InvalidArgumentException $e) {
            // Log validation errors but still respond with success
            Log::error('Invalid M-Pesa STK callback', [
                'error' => $e->getMessage(),
                'request' => $request->except(['Body.stkCallback.CallbackMetadata']),
            ]);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to process M-Pesa callback', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Still respond with success to prevent retries
            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
        }
    }

    /**
     * Handle C2B confirmation callback from M-Pesa.
     */
    public function c2bConfirmation(Request $request): JsonResponse
    {
        try {
            $callback = $this->validateC2bCallback($request);

            $this->processC2bCallback($callback);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to process C2B callback', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
        }
    }

    /**
     * Handle C2B validation callback from M-Pesa.
     */
    public function c2bValidation(Request $request): JsonResponse
    {
        try {
            $callback = $this->validateC2bCallback($request);

            // Perform validation checks
            $isValid = $this->validateC2bPayment($callback);

            if (!$isValid) {
                return response()->json([
                    'ResultCode' => 1,
                    'ResultDesc' => 'Rejected',
                ]);
            }

            return response()->json([
                'ResultCode' => 0,
                'ResultDesc' => 'Accepted',
            ]);
        } catch (\Throwable $e) {
            Log::error('C2B validation failed', ['error' => $e->getMessage()]);

            return response()->json([
                'ResultCode' => 1,
                'ResultDesc' => 'Rejected',
            ]);
        }
    }

    /**
     * Process STK Push callback idempotently.
     */
    private function processStkCallback(array $callback): void
    {
        $checkoutRequestId = $callback['checkout_request_id'];

        // Use database transaction for atomicity
        DB::transaction(function () use ($callback, $checkoutRequestId) {
            // Find the payment record (or fail silently if not found)
            $payment = Payment::where('checkout_request_id', $checkoutRequestId)
                ->lockForUpdate()
                ->first();

            if (!$payment) {
                Log::warning('Payment not found for callback', [
                    'checkout_request_id' => $checkoutRequestId,
                ]);
                return;
            }

            // Check if already processed (idempotency)
            if ($payment->status !== 'pending') {
                Log::info('Callback already processed', [
                    'checkout_request_id' => $checkoutRequestId,
                    'status' => $payment->status,
                ]);
                return;
            }

            // Update payment status based on result
            if ($this->isStkCallbackSuccessful($callback)) {
                $details = $this->extractStkPaymentDetails($callback);

                $payment->update([
                    'status' => 'completed',
                    'mpesa_receipt_number' => $details['mpesa_receipt_number'],
                    'transaction_date' => $details['transaction_date'],
                    'phone_number' => $details['phone_number'],
                    'amount_paid' => $details['amount'],
                    'result_desc' => $callback['result_desc'],
                    'processed_at' => now(),
                ]);

                // Trigger fulfillment (send email, update order, etc.)
                event(new PaymentCompleted($payment));

                Log::info('Payment completed successfully', [
                    'payment_id' => $payment->id,
                    'receipt' => $details['mpesa_receipt_number'],
                ]);
            } else {
                $payment->update([
                    'status' => 'failed',
                    'result_desc' => $callback['result_desc'],
                    'processed_at' => now(),
                ]);

                Log::info('Payment failed', [
                    'payment_id' => $payment->id,
                    'result_desc' => $callback['result_desc'],
                ]);
            }
        });
    }

    /**
     * Process C2B confirmation callback.
     */
    private function processC2bCallback(array $callback): void
    {
        // Check for duplicate using M-Pesa transaction ID
        $exists = Payment::where('mpesa_trans_id', $callback['trans_id'])->exists();

        if ($exists) {
            Log::info('Duplicate C2B callback ignored', [
                'trans_id' => $callback['trans_id'],
            ]);
            return;
        }

        // Create payment record
        Payment::create([
            'mpesa_trans_id' => $callback['trans_id'],
            'transaction_type' => $callback['transaction_type'],
            'amount' => $callback['trans_amount'],
            'phone_number' => $callback['msisdn'],
            'bill_ref_number' => $callback['bill_ref_number'],
            'status' => 'completed',
            'transaction_date' => $callback['trans_time'],
            'first_name' => $callback['first_name'],
            'last_name' => $callback['last_name'],
        ]);

        Log::info('C2B payment recorded', [
            'trans_id' => $callback['trans_id'],
            'amount' => $callback['trans_amount'],
        ]);
    }

    /**
     * Validate C2B payment before accepting.
     */
    private function validateC2bPayment(array $callback): bool
    {
        // Validate shortcode matches
        if ($callback['business_short_code'] !== config('mpesa.apps.c2b.shortcode')) {
            Log::warning('Invalid shortcode in C2B payment', [
                'expected' => config('mpesa.apps.c2b.shortcode'),
                'received' => $callback['business_short_code'],
            ]);
            return false;
        }

        // Validate minimum amount
        if ($callback['trans_amount'] < 1) {
            return false;
        }

        // Add custom validation logic here
        // - Check account/bill reference exists
        // - Verify customer is not blocked
        // - Validate payment amount matches expected
        
        return true;
    }
}
```

## Routes

```php
// routes/api.php
use App\Http\Controllers\Api\MpesaCallbackController;

Route::prefix('mpesa')->group(function () {
    Route::post('stk/callback', [MpesaCallbackController::class, 'stkPushCallback']);
    Route::post('c2b/validation', [MpesaCallbackController::class, 'c2bValidation']);
    Route::post('c2b/confirmation', [MpesaCallbackController::class, 'c2bConfirmation']);
});
```

## Middleware Considerations

M-Pesa callbacks should bypass CSRF protection and authentication:

```php
// app/Http/Middleware/VerifyCsrfToken.php
protected $except = [
    'api/mpesa/*',
];
```

## Security Best Practices

1. **Always validate callbacks** using the `ValidatesMpesaCallback` trait
2. **Process idempotently** using unique transaction IDs
3. **Use database transactions** for atomicity
4. **Verify shortcode** matches your configuration
5. **Validate amounts** match expected values
6. **Log failures** but always respond with success to prevent retries
7. **Never log** full callback bodies (may contain sensitive data)
8. **Rate limit** initiation endpoints (not callbacks)
9. **Monitor** for unusual patterns or failed callbacks

## Testing Callbacks

```php
// tests/Feature/MpesaCallbackTest.php
public function test_successful_stk_callback_updates_payment(): void
{
    $payment = Payment::factory()->create([
        'checkout_request_id' => 'ws_CO_191220191020363925',
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/mpesa/stk/callback', [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_191220191020363925',
                'ResultCode' => 0,
                'ResultDesc' => 'The service request is processed successfully.',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 100],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                        ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk();
    
    $payment->refresh();
    $this->assertEquals('completed', $payment->status);
    $this->assertEquals('NLJ7RT61SV', $payment->mpesa_receipt_number);
}
```
