# STK Query

Query the status of an STK Push payment request.

## When to Use

- Check if customer completed payment
- Handle timeout scenarios (user didn't enter PIN)
- Verify payment before fulfillment
- Update order status

## Basic Usage

```php
use LaravelMpesa\MpesaSdk;

$response = MpesaSdk::instance()->stkQuery(
    checkoutRequestId: 'ws_CO_191220191020363925',
    returnDto: true
);

if ($response->isPaymentSuccessful()) {
    // Payment completed - fulfill order
} elseif ($response->isPaymentPending()) {
    // Still waiting or timed out
} elseif ($response->isPaymentFailed()) {
    // Payment failed or cancelled
}
```

## Payment Status States

### Successful Payment
```php
$response->isPaymentSuccessful()  // true when ResultCode = "0"
```

User entered PIN and payment was processed successfully.

### Pending Payment
```php
$response->isPaymentPending()  // true when ResultCode = "1032" or "1037"
```

Common scenarios:
- User cancelled the request
- Request timed out (user didn't enter PIN)

### Failed Payment
```php
$response->isPaymentFailed()  // true for other non-zero codes
```

Common scenarios:
- Insufficient funds
- Invalid transaction details
- Technical errors

## Response Codes

| Code | Status | Description |
|------|--------|-------------|
| `0` | Success | Payment completed successfully |
| `1032` | Pending | Request cancelled by user |
| `1037` | Pending | Timeout - DS timeout user cannot be reached |
| `1` | Failed | Insufficient funds in M-Pesa account |
| `2001` | Failed | Wrong PIN entered |

## Complete Example

```php
namespace App\Http\Controllers;

use App\Models\Payment;
use LaravelMpesa\MpesaSdk;

class PaymentStatusController extends Controller
{
    public function checkStatus(string $checkoutRequestId)
    {
        $payment = Payment::where('checkout_request_id', $checkoutRequestId)
            ->firstOrFail();

        // Query M-Pesa
        $response = MpesaSdk::instance()->stkQuery(
            checkoutRequestId: $checkoutRequestId,
            returnDto: true
        );

        if ($response->isPaymentSuccessful()) {
            $payment->update([
                'status' => 'completed',
                'result_desc' => $response->getResultDescription(),
            ]);

            return response()->json([
                'status' => 'completed',
                'message' => 'Payment successful',
            ]);
        }

        if ($response->isPaymentPending()) {
            return response()->json([
                'status' => 'pending',
                'message' => 'Payment still pending',
            ]);
        }

        if ($response->isPaymentFailed()) {
            $payment->update([
                'status' => 'failed',
                'result_desc' => $response->getResultDescription(),
            ]);

            return response()->json([
                'status' => 'failed',
                'message' => 'Payment failed',
            ], 400);
        }
    }
}
```

## Polling for Status

Don't poll too frequently - M-Pesa responses can take up to 60 seconds.

```php
// ❌ Don't poll every second
// ✅ Poll after user interaction or callback failure

// Example: Check after 30 seconds if no callback received
dispatch(function () use ($checkoutRequestId) {
    $response = MpesaSdk::instance()->stkQuery($checkoutRequestId, true);
    
    if (!$response->isPaymentPending()) {
        // Update payment status
    }
})->delay(now()->addSeconds(30));
```

## Best Practices

✅ **DO:**
- Query when callback fails to arrive
- Use for manual status checks
- Wait at least 30 seconds before querying
- Handle all three states (success, pending, failed)
- Store result description for debugging

❌ **DON'T:**
- Don't poll continuously
- Don't query immediately after STK Push
- Don't rely solely on query (use callbacks as primary)
- Don't expose result codes to users

## Query vs Callback

**Prefer Callbacks:**
- Primary method for payment confirmation
- Real-time notification
- More reliable

**Use Queries For:**
- Callback didn't arrive (network issues)
- Manual status verification
- Administrative checks
- Handling edge cases

## Error Handling

```php
use InvalidArgumentException;

try {
    $response = $mpesa->stkQuery($checkoutRequestId, returnDto: true);
    
    // Check request success (not payment success)
    if ($response->isFailed()) {
        Log::error('STK query failed', [
            'checkout_id' => $checkoutRequestId,
            'error' => $response->getErrorMessage(),
        ]);
    }
} catch (InvalidArgumentException $e) {
    // Invalid checkout request ID
    return response()->json(['error' => 'Invalid request'], 400);
}
```

## Frontend Integration

```javascript
// Check payment status
async function checkPaymentStatus(checkoutRequestId) {
    const response = await fetch(`/api/payments/${checkoutRequestId}/status`);
    const data = await response.json();
    
    if (data.status === 'completed') {
        showSuccess('Payment successful!');
        redirectToThankYou();
    } else if (data.status === 'pending') {
        // Show waiting state
        setTimeout(() => checkPaymentStatus(checkoutRequestId), 5000);
    } else {
        showError('Payment failed');
    }
}
```

## Next Steps

- [Handle callbacks](callbacks.md)
- [Learn about response DTOs](response-dtos.md)
- [Error handling guide](error-handling.md)
