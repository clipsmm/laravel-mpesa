# STK Push (Lipa Na M-Pesa)

STK Push sends a payment request directly to a customer's phone, prompting them to enter their M-Pesa PIN.

## Basic Usage

```php
use LaravelMpesa\MpesaSdk;

$response = MpesaSdk::instance()->stkPush(
    receiver: '254712345678',
    amount: 1000,
    ref: 'ORDER-123',
    description: 'Payment for order #123',
    callbackUrl: 'https://example.com/api/mpesa/callback',
    returnDto: true
);

if ($response->isSuccessful()) {
    $checkoutRequestId = $response->getCheckoutRequestId();
    // Store $checkoutRequestId to query status later
} else {
    $error = $response->getErrorMessage();
}
```

## Parameters

### `receiver` (required)
Customer's phone number in international format.

```php
// ✅ Correct format
receiver: '254712345678'

// ❌ Wrong formats
receiver: '0712345678'    // Missing country code
receiver: '+254712345678' // Don't use + prefix
receiver: '712345678'     // Missing country code
```

### `amount` (required)
Payment amount in Kenyan Shillings (KES).

```php
// ✅ Valid amounts
amount: 1        // Minimum: 1 KES
amount: 1000
amount: 150000   // Maximum: 150,000 KES

// ❌ Invalid
amount: 0        // Throws InvalidArgumentException
amount: -100     // Throws InvalidArgumentException
```

### `ref` (required)
Account reference shown to customer (max 100 characters).

```php
ref: 'ORDER-123'
ref: 'INV-2024-001'
ref: 'ACCT-USER-456'
```

### `description` (required)
Transaction description shown to customer (max 182 characters).

```php
description: 'Payment for order #123'
description: 'Invoice payment'
description: 'Monthly subscription fee'
```

### `callbackUrl` (required)
HTTPS URL where M-Pesa will send the payment result.

```php
callbackUrl: 'https://example.com/api/mpesa/callback'
```

!> Callback URLs must use HTTPS in production.

### `transactionType` (optional)
Default: `'CustomerPayBillOnline'`

```php
transactionType: 'CustomerPayBillOnline'  // For paybill
transactionType: 'CustomerBuyGoodsOnline' // For till number
```

### `returnDto` (optional)
Whether to return a typed DTO instead of an array. Default: `false`

```php
// Array return (legacy)
[$success, $data] = $mpesa->stkPush(...);

// DTO return (recommended)
$response = $mpesa->stkPush(..., returnDto: true);
```

## Response

### Using DTO (Recommended)

```php
$response = $mpesa->stkPush(..., returnDto: true);

// Check success
if ($response->isSuccessful()) {
    // Get data
    $merchantRequestId = $response->getMerchantRequestId();
    $checkoutRequestId = $response->getCheckoutRequestId();
    $responseCode = $response->getResponseCode();
    $responseDescription = $response->getResponseDescription();
    $customerMessage = $response->getCustomerMessage();
    
    // Store checkout ID for status queries
    session(['checkout_request_id' => $checkoutRequestId]);
}

// Check failure
if ($response->isFailed()) {
    $error = $response->getErrorMessage();
    Log::error('STK Push failed', ['error' => $error]);
}
```

### Using Array

```php
[$successful, $data] = $mpesa->stkPush(...);

if ($successful) {
    $checkoutRequestId = $data['CheckoutRequestID'] ?? null;
}
```

## Example: Complete Flow

```php
namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use LaravelMpesa\MpesaSdk;

class PaymentController extends Controller
{
    public function initiate(Request $request)
    {
        $validated = $request->validate([
            'phone' => 'required|regex:/^2547\d{8}$/',
            'amount' => 'required|numeric|min:1|max:150000',
        ]);

        $orderId = 'ORDER-' . time();

        $response = MpesaSdk::instance()->stkPush(
            receiver: $validated['phone'],
            amount: (int) $validated['amount'],
            ref: $orderId,
            description: "Payment for {$orderId}",
            callbackUrl: route('mpesa.callback'),
            returnDto: true
        );

        if ($response->isSuccessful()) {
            // Store payment record
            Payment::create([
                'order_id' => $orderId,
                'checkout_request_id' => $response->getCheckoutRequestId(),
                'merchant_request_id' => $response->getMerchantRequestId(),
                'phone_number' => $validated['phone'],
                'amount' => $validated['amount'],
                'status' => 'pending',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payment request sent. Please check your phone.',
                'checkout_request_id' => $response->getCheckoutRequestId(),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $response->getErrorMessage(),
        ], 400);
    }
}
```

## Error Handling

```php
use InvalidArgumentException;
use RuntimeException;

try {
    $response = $mpesa->stkPush(..., returnDto: true);
    
    if ($response->isFailed()) {
        // M-Pesa rejected the request
        Log::warning('M-Pesa rejected STK push', [
            'error' => $response->getErrorMessage(),
        ]);
    }
} catch (InvalidArgumentException $e) {
    // Invalid parameters (phone format, amount, etc.)
    return back()->withErrors(['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    // Authentication or network failure
    Log::error('STK push failed', ['error' => $e->getMessage()]);
    return back()->withErrors(['error' => 'Payment service unavailable']);
}
```

## Common Response Codes

| Code | Meaning |
|------|---------|
| `0` | Success - Request accepted |
| `1` | Insufficient funds |
| `1032` | Request cancelled by user |
| `1037` | Timeout - User didn't enter PIN |
| `2001` | Invalid transaction details |

## Best Practices

✅ **DO:**
- Validate phone numbers before sending
- Store checkout request ID for status queries
- Handle timeouts gracefully (user has 60 seconds)
- Show user-friendly messages
- Log failures for debugging

❌ **DON'T:**
- Don't assume success means payment completed (check callback)
- Don't retry immediately on failure
- Don't expose M-Pesa error codes to users
- Don't send duplicate requests

## Next Steps

- [Query STK status](stk-query.md)
- [Handle callbacks](callbacks.md)
- [Learn about response DTOs](response-dtos.md)
