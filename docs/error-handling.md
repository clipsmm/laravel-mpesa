# Error Handling

Handle errors gracefully in your M-Pesa integration.

## Exception Types

### InvalidArgumentException

Thrown for invalid parameters before making API requests.

**Common Causes:**
- Invalid phone number format
- Invalid amount (≤ 0 or > 150,000)
- Empty/too long reference or description
- Invalid callback URL
- Missing configuration

**Example:**
```php
try {
    $response = $mpesa->stkPush(
        receiver: '0712345678',  // ❌ Wrong format
        amount: 1000,
        ref: 'ORDER-123',
        description: 'Payment',
        callbackUrl: 'https://example.com/callback'
    );
} catch (InvalidArgumentException $e) {
    // Handle validation error
    return back()->withErrors([
        'phone' => 'Phone number must be in format 2547XXXXXXXX'
    ]);
}
```

### RuntimeException

Thrown for authentication failures, network errors, or malformed responses.

**Common Causes:**
- Authentication failed (invalid credentials)
- Network connectivity issues
- M-Pesa API unavailable
- Malformed API response

**Example:**
```php
try {
    $response = $mpesa->stkPush(...);
} catch (RuntimeException $e) {
    // Log error for debugging
    Log::error('M-Pesa error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    
    // Show user-friendly message
    return back()->with('error', 'Payment service temporarily unavailable');
}
```

## Response Failures

Even when no exception is thrown, check if the response was successful:

```php
$response = $mpesa->stkPush(..., returnDto: true);

if ($response->isFailed()) {
    // M-Pesa rejected the request
    $error = $response->getErrorMessage();
    
    Log::warning('M-Pesa rejected STK push', [
        'error' => $error,
        'response' => $response->getRawResponse(),
    ]);
    
    return response()->json([
        'success' => false,
        'message' => 'Payment request failed. Please try again.',
    ], 400);
}
```

## Complete Error Handling

```php
use InvalidArgumentException;
use RuntimeException;
use LaravelMpesa\MpesaSdk;

public function initiatePayment(Request $request)
{
    try {
        // Validate input
        $validated = $request->validate([
            'phone' => 'required|regex:/^2547\d{8}$/',
            'amount' => 'required|numeric|min:1|max:150000',
        ]);
        
        // Initiate STK push
        $response = MpesaSdk::instance()->stkPush(
            receiver: $validated['phone'],
            amount: (int) $validated['amount'],
            ref: 'ORDER-' . time(),
            description: 'Payment for order',
            callbackUrl: route('mpesa.callback'),
            returnDto: true
        );
        
        // Check M-Pesa response
        if ($response->isFailed()) {
            return response()->json([
                'success' => false,
                'message' => 'Payment request was rejected',
            ], 400);
        }
        
        // Success
        return response()->json([
            'success' => true,
            'checkout_request_id' => $response->getCheckoutRequestId(),
            'message' => 'Payment request sent. Please check your phone.',
        ]);
        
    } catch (InvalidArgumentException $e) {
        // Validation errors
        Log::warning('Invalid payment parameters', [
            'error' => $e->getMessage(),
            'input' => $request->except(['password']),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 422);
        
    } catch (RuntimeException $e) {
        // System errors
        Log::error('Payment system error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Payment service temporarily unavailable. Please try again later.',
        ], 503);
        
    } catch (\Exception $e) {
        // Unexpected errors
        Log::critical('Unexpected payment error', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'An unexpected error occurred. Please contact support.',
        ], 500);
    }
}
```

## Error Codes

### Common M-Pesa Response Codes

| Code | Meaning | Action |
|------|---------|--------|
| `0` | Success | Proceed |
| `1` | Insufficient funds | Inform user |
| `1032` | Cancelled by user | Allow retry |
| `1037` | Timeout | Allow retry |
| `2001` | Invalid details | Check configuration |

### HTTP Status Codes

| Status | Meaning | Handling |
|--------|---------|----------|
| `200` | Success | Process response |
| `400` | Bad request | Check parameters |
| `401` | Unauthorized | Check credentials |
| `500` | Server error | Retry or escalate |
| `503` | Service unavailable | Retry later |

## Retry Strategy

### Don't Retry Validation Errors

```php
// ❌ Don't retry
if ($e instanceof InvalidArgumentException) {
    // Fix the input instead
}
```

### Retry Network Errors

```php
use Illuminate\Support\Facades\Retry;

$response = Retry::times(3)
    ->sleep(1000)  // Wait 1 second between retries
    ->when(fn ($e) => $e instanceof RuntimeException)
    ->throw(fn () => $mpesa->stkPush(...));
```

### Exponential Backoff

```php
Retry::times(3)
    ->exponentialBackoff()  // 1s, 2s, 4s
    ->when(fn ($e) => $e instanceof RuntimeException)
    ->throw(fn () => $mpesa->stkPush(...));
```

## User-Friendly Messages

```php
private function getUserMessage(\Exception $e): string
{
    return match(true) {
        $e instanceof InvalidArgumentException => 'Please check your phone number and amount',
        $e instanceof RuntimeException => 'Payment service temporarily unavailable',
        str_contains($e->getMessage(), 'timeout') => 'Request timed out. Please try again',
        default => 'An error occurred. Please try again later'
    };
}

// Usage
} catch (\Exception $e) {
    return back()->with('error', $this->getUserMessage($e));
}
```

## Error Monitoring

### Sentry Integration

```bash
composer require sentry/sentry-laravel
```

```php
// config/logging.php
'sentry' => [
    'driver' => 'sentry',
],

// Log to Sentry
try {
    $response = $mpesa->stkPush(...);
} catch (\Exception $e) {
    app('sentry')->captureException($e);
    throw $e;
}
```

### Custom Error Context

```php
Log::error('STK push failed', [
    'error' => $e->getMessage(),
    'user_id' => auth()->id(),
    'phone' => $phone,
    'amount' => $amount,
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);
```

## Testing Error Scenarios

```php
public function test_handles_invalid_phone_number()
{
    $response = $this->postJson('/api/payments/initiate', [
        'phone' => '0712345678',
        'amount' => 1000,
    ]);
    
    $response->assertStatus(422);
    $response->assertJson([
        'success' => false,
    ]);
}

public function test_handles_mpesa_rejection()
{
    Http::fake([
        '*' => Http::response(['errorMessage' => 'Invalid shortcode'], 400),
    ]);
    
    $response = $this->postJson('/api/payments/initiate', [
        'phone' => '254712345678',
        'amount' => 1000,
    ]);
    
    $response->assertStatus(400);
}
```

## Best Practices

✅ **DO:**
- Catch specific exceptions first
- Log errors with context
- Show user-friendly messages
- Implement retry logic for transient errors
- Monitor error rates
- Test error scenarios

❌ **DON'T:**
- Don't expose technical details to users
- Don't retry validation errors
- Don't ignore errors silently
- Don't log sensitive data
- Don't retry indefinitely

## Next Steps

- [Troubleshooting guide](troubleshooting.md)
- [Security best practices](security.md)
- [Testing guide](testing.md)
