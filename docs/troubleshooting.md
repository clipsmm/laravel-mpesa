# Troubleshooting

Common issues and solutions.

## Authentication Issues

### Problem: "Unable to authenticate with Mpesa"

**Causes:**
- Invalid consumer key or secret
- Network connectivity issues
- M-Pesa API unavailable

**Solutions:**

1. **Verify credentials:**
   ```bash
   # Check .env file
   cat .env | grep MPESA
   
   # Ensure no extra spaces
   MPESA_CONSUMER_KEY=abc123  # ✅
   MPESA_CONSUMER_KEY= abc123 # ❌ Extra space
   ```

2. **Test credentials manually:**
   ```bash
   curl -X GET \
     -H "Authorization: Basic <base64(key:secret)>" \
     https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials
   ```

3. **Check API status:**
   ```bash
   # Sandbox
   ping sandbox.safaricom.co.ke
   
   # Production
   ping api.safaricom.co.ke
   ```

4. **Clear config cache:**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

### Problem: Token expires too quickly

**Solution:**

Tokens are cached for 1 hour. If experiencing issues:

```php
// Force re-authentication
$manager = MpesaSdk::instance();
$manager->authenticate();
```

## STK Push Issues

### Problem: "Receiver must use the 2547XXXXXXXX format"

**Cause:** Phone number not in correct format.

**Solution:**

```php
// ❌ Wrong formats
'0712345678'     // Missing country code
'+254712345678'  // Don't use + prefix
'254712345678'   // Missing digit (should be 2547...)
'712345678'      // Missing country code

// ✅ Correct format
'254712345678'   // 254 + 7 + 8 digits
```

**Format converter:**

```php
function formatPhoneNumber(string $phone): string
{
    // Remove spaces and special characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert 0712345678 to 254712345678
    if (str_starts_with($phone, '0')) {
        $phone = '254' . substr($phone, 1);
    }
    
    // Convert 712345678 to 254712345678
    if (strlen($phone) === 9) {
        $phone = '254' . $phone;
    }
    
    return $phone;
}
```

### Problem: "STK amount must be greater than zero"

**Cause:** Amount is 0, negative, or not an integer.

**Solution:**

```php
// ✅ Correct
amount: 1000       // Integer
amount: (int)$request->amount  // Cast to int

// ❌ Wrong
amount: 0          // Zero
amount: -100       // Negative
amount: "1000"     // String (works but use int)
amount: 1000.50    // Float (cast to int first)
```

### Problem: User doesn't receive STK prompt

**Causes:**
- Phone is offline
- SIM card issue
- M-Pesa app not installed
- Network congestion

**Solutions:**

1. **Verify phone number:**
   ```php
   // Test with your own phone first
   receiver: '254YOUR_NUMBER'
   ```

2. **Check phone is on Safaricom network:**
   - Only works with Safaricom SIM cards in Kenya

3. **Wait and retry:**
   ```php
   // Wait 30 seconds before retry
   sleep(30);
   $response = $mpesa->stkPush(...);
   ```

4. **Query status:**
   ```php
   // Check if request was sent
   $status = $mpesa->stkQuery($checkoutRequestId, returnDto: true);
   ```

## Callback Issues

### Problem: Callbacks not arriving

**Causes:**
- URL not publicly accessible
- Not using HTTPS
- Firewall blocking M-Pesa IPs
- Server timeout

**Solutions:**

1. **Verify URL is accessible:**
   ```bash
   curl -X POST https://yoursite.com/api/mpesa/callback \
     -H "Content-Type: application/json" \
     -d '{"test": "data"}'
   ```

2. **Check HTTPS:**
   ```bash
   # Must use HTTPS in production
   MPESA_ALLOW_INSECURE_CALLBACKS=false
   ```

3. **For local development, use ngrok:**
   ```bash
   ngrok http 8000
   
   # Use ngrok URL
   callbackUrl: 'https://abc123.ngrok.io/api/mpesa/callback'
   ```

4. **Check server logs:**
   ```bash
   tail -f /var/log/nginx/access.log
   tail -f storage/logs/laravel.log
   ```

5. **Increase timeout:**
   ```nginx
   # nginx
   proxy_read_timeout 300;
   fastcgi_read_timeout 300;
   ```

### Problem: Duplicate callbacks

**Cause:** M-Pesa retries if no response received.

**Solution:** Implement idempotency:

```php
public function callback(Request $request)
{
    $callback = $this->validateStkCallback($request);
    
    // Check if already processed
    $payment = Payment::where('checkout_request_id', $callback['checkout_request_id'])
        ->lockForUpdate()
        ->first();
        
    if ($payment && $payment->status !== 'pending') {
        Log::info('Duplicate callback ignored');
        return response()->json(['ResultCode' => 0]);
    }
    
    // Process callback
    // ...
    
    // Always respond with success
    return response()->json(['ResultCode' => 0]);
}
```

## Configuration Issues

### Problem: "Mpesa application [app] is not configured"

**Cause:** App not defined in config file.

**Solution:**

```php
// config/mpesa.php
'apps' => [
    'c2b' => [  // ✅ Defined
        'status' => 'sandbox',
        // ...
    ],
],

// Usage
MpesaSdk::instance('c2b');  // ✅ Works
MpesaSdk::instance('xyz');  // ❌ Throws exception
```

### Problem: Config changes not taking effect

**Solution:**

```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear

# Restart queue workers
php artisan queue:restart
```

## Payment Status Issues

### Problem: Payment shows as pending forever

**Causes:**
- User cancelled
- User didn't enter PIN
- Callback failed

**Solutions:**

1. **Query status manually:**
   ```php
   $status = $mpesa->stkQuery($checkoutRequestId, returnDto: true);
   
   if ($status->isPaymentPending()) {
       // Still pending or timed out
   }
   ```

2. **Set timeout for pending payments:**
   ```php
   // Auto-cancel after 5 minutes
   Payment::where('status', 'pending')
       ->where('created_at', '<', now()->subMinutes(5))
       ->update(['status' => 'cancelled']);
   ```

## Network Issues

### Problem: Timeouts

**Solution:**

Increase timeouts:

```bash
MPESA_CONNECT_TIMEOUT=10
MPESA_TIMEOUT=30
```

Or in code:

```php
$mpesa = new MpesaSdk('c2b', [
    'connect_timeout' => 10,
    'timeout' => 30,
]);
```

### Problem: SSL/TLS errors

**Solution:**

1. **Update CA certificates:**
   ```bash
   sudo apt-get update
   sudo apt-get install ca-certificates
   ```

2. **Update cURL:**
   ```bash
   php -i | grep cURL
   ```

## Debugging Tips

### Enable detailed logging

```bash
MPESA_LOGGING=true
LOG_LEVEL=debug
```

### Check raw HTTP requests

```php
use Illuminate\Support\Facades\Http;

Http::fake();

// Make request
$response = $mpesa->stkPush(...);

// See what was sent
Http::assertSent(function ($request) {
    dump($request->url());
    dump($request->body());
    dump($request->headers());
    return true;
});
```

### Test in isolation

```php
php artisan tinker

>>> $mpesa = \LaravelMpesa\MpesaSdk::instance();
>>> $mpesa->authenticate();
=> true

>>> $response = $mpesa->stkPush(...);
>>> $response
```

### Check package version

```bash
composer show clipsmm/laravel-mpesa
```

### Update package

```bash
composer update clipsmm/laravel-mpesa
php artisan config:clear
```

## Getting Help

If you're still stuck:

1. **Check the documentation:**
   - [Installation](installation.md)
   - [Configuration](configuration.md)
   - [API Reference](api-reference.md)

2. **Enable logging:**
   ```bash
   MPESA_LOGGING=true
   ```

3. **Search existing issues:**
   - [GitHub Issues](https://github.com/clipsmm/laravel-mpesa/issues)

4. **Create a new issue:**
   - Include Laravel version
   - Include PHP version
   - Include package version
   - Include relevant logs
   - Include steps to reproduce

5. **Ask in discussions:**
   - [GitHub Discussions](https://github.com/clipsmm/laravel-mpesa/discussions)

## Common Error Messages

| Error | Meaning | Solution |
|-------|---------|----------|
| "Invalid Access Token" | Token expired or invalid | Re-authenticate |
| "Bad Request - Invalid ShortCode" | Wrong shortcode in config | Verify shortcode |
| "The service request is processed successfully" | Success | No action needed |
| "Request cancelled by user" | User cancelled prompt | Allow retry |
| "Insufficient funds" | Low M-Pesa balance | Inform user |

## Next Steps

- [Error handling](error-handling.md)
- [Security guide](security.md)
- [Testing guide](testing.md)
