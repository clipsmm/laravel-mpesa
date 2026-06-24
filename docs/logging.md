# Logging

Enable request/response logging for debugging and monitoring.

## Enable Logging

Set environment variable:

```bash
MPESA_LOGGING=true
```

Or in configuration:

```php
// config/mpesa.php
'logging' => env('MPESA_LOGGING', false),
```

## What Gets Logged

### Request Logging

```
[timestamp] Mpesa API Request
{
    "url": "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest",
    "payload": {
        "BusinessShortCode": "174379",
        "Timestamp": "20240624103045",
        "TransactionType": "CustomerPayBillOnline",
        "Amount": 1000,
        "PartyA": "254712345678",
        // Password field is EXCLUDED
    }
}
```

### Response Logging

```
[timestamp] Mpesa API Response
{
    "url": "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest",
    "successful": true,
    "status": 200,
    "data": {
        "MerchantRequestID": "29115-34620561-1",
        "CheckoutRequestID": "ws_CO_191220191020363925",
        "ResponseCode": "0",
        "ResponseDescription": "Success. Request accepted for processing"
    }
}
```

## Sensitive Fields Excluded

The following fields are automatically excluded from logs:

- `Password`
- `SecurityCredential`

## Log Channels

### Default Channel

Logs to Laravel's default log channel:

```php
// Uses config/logging.php 'default' channel
MPESA_LOGGING=true
```

### Custom Channel

Create a dedicated M-Pesa log channel:

```php
// config/logging.php
'channels' => [
    'mpesa' => [
        'driver' => 'daily',
        'path' => storage_path('logs/mpesa.log'),
        'level' => 'info',
        'days' => 14,
    ],
],
```

Update logging to use custom channel (requires code modification):

```php
// In RequestManager::send()
Log::channel('mpesa')->info('Mpesa API Request', [
    'url' => $url,
    'payload' => Arr::except($payload, ['Password', 'SecurityCredential']),
]);
```

## Log Levels

### Production

```bash
# Minimal logging
MPESA_LOGGING=false
LOG_LEVEL=warning
```

Log only errors and critical issues.

### Development

```bash
# Verbose logging
MPESA_LOGGING=true
LOG_LEVEL=debug
```

Log all requests and responses.

## Monitoring

### Log Analysis

Use log aggregation tools:

- **Laravel Telescope** - Local development
- **Papertrail** - Cloud log management
- **Sentry** - Error tracking
- **LogRocket** - Session replay

### Key Metrics to Monitor

1. **Authentication failures**
   ```php
   if (!$manager->authenticate()) {
       Log::error('M-Pesa authentication failed');
   }
   ```

2. **Failed transactions**
   ```php
   if ($response->isFailed()) {
       Log::warning('STK push failed', [
           'error' => $response->getErrorMessage(),
       ]);
   }
   ```

3. **Callback processing errors**
   ```php
   catch (Exception $e) {
       Log::error('Callback processing failed', [
           'error' => $e->getMessage(),
       ]);
   }
   ```

## Performance Impact

Logging adds minimal overhead:

- ~1-2ms per request
- Disk I/O when writing logs
- JSON encoding overhead

### Disable in High-Traffic Production

```bash
# High-traffic production
MPESA_LOGGING=false

# Enable selectively for debugging
```

## Log Rotation

Configure log rotation to prevent disk space issues:

```php
// config/logging.php
'channels' => [
    'mpesa' => [
        'driver' => 'daily',
        'days' => 7,  // Keep 7 days
        'level' => 'info',
    ],
],
```

## Example Log Output

### Successful STK Push

```
[2024-06-24 10:30:45] local.INFO: Mpesa API Request
{
  "url": "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest",
  "payload": {
    "BusinessShortCode": "174379",
    "Amount": 1000,
    "PhoneNumber": "254712345678",
    "AccountReference": "ORDER-123"
  }
}

[2024-06-24 10:30:46] local.INFO: Mpesa API Response
{
  "url": "https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest",
  "successful": true,
  "status": 200,
  "data": {
    "ResponseCode": "0",
    "CheckoutRequestID": "ws_CO_191220191020363925"
  }
}
```

### Failed Authentication

```
[2024-06-24 10:30:45] local.ERROR: Unable to authenticate with Mpesa
{
  "exception": "RuntimeException",
  "message": "Unable to authenticate with Mpesa.",
  "trace": "..."
}
```

## Best Practices

✅ **DO:**
- Enable logging in development
- Use log levels appropriately
- Rotate logs to prevent disk issues
- Monitor for patterns and errors
- Use separate channels for M-Pesa

❌ **DON'T:**
- Don't log in high-traffic production
- Don't log sensitive customer data
- Don't log full callback payloads
- Don't ignore log size growth

## Debugging Tips

### Enable Logging Temporarily

```bash
# Enable for debugging
php artisan tinker
>>> config(['mpesa.logging' => true]);
```

### View Recent Logs

```bash
tail -f storage/logs/laravel.log
```

### Search Logs

```bash
# Find all STK push requests
grep "stkpush" storage/logs/laravel.log

# Find failures
grep "failed\|error" storage/logs/laravel.log -i
```

## Next Steps

- [Error handling](error-handling.md)
- [Testing](testing.md)
- [Troubleshooting](troubleshooting.md)
