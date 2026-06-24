# Configuration

## Environment Variables

Add your M-Pesa credentials to `.env`:

```bash
# Application Configuration
MPESA_DEFAULT_APP=c2b
MPESA_API_STATUS=sandbox

# Credentials
MPESA_CONSUMER_KEY=your-consumer-key-here
MPESA_CONSUMER_SECRET=your-consumer-secret-here
MPESA_SHORTCODE=174379
MPESA_PASSKEY=your-passkey-here

# Timeouts (optional)
MPESA_CONNECT_TIMEOUT=5
MPESA_TIMEOUT=15

# Security (optional)
MPESA_ALLOW_INSECURE_CALLBACKS=false

# Logging (optional)
MPESA_LOGGING=false
```

## Configuration Options

### API Status

Set `MPESA_API_STATUS` to control which M-Pesa environment to use:

- **`sandbox`** - For development and testing (default)
- **`live`** - For production (requires production credentials)

```bash
# Development
MPESA_API_STATUS=sandbox

# Production
MPESA_API_STATUS=live
```

### Multiple Applications

The package supports multiple M-Pesa applications (useful for different paybill/till numbers):

```php
// config/mpesa.php
return [
    'default' => env('MPESA_DEFAULT_APP', 'c2b'),
    
    'apps' => [
        'c2b' => [
            'status' => env('MPESA_API_STATUS'),
            'consumer_key' => env('MPESA_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
            'shortcode' => env('MPESA_SHORTCODE'),
            'passkey' => env('MPESA_PASSKEY'),
        ],
        
        'paybill' => [
            'status' => env('MPESA_PAYBILL_STATUS', 'sandbox'),
            'consumer_key' => env('MPESA_PAYBILL_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_PAYBILL_CONSUMER_SECRET'),
            'shortcode' => env('MPESA_PAYBILL_SHORTCODE'),
            'passkey' => env('MPESA_PAYBILL_PASSKEY'),
        ],
    ],
];
```

Use different apps:

```php
$c2b = MpesaSdk::instance('c2b');
$paybill = MpesaSdk::instance('paybill');
```

### Timeouts

Configure connection and request timeouts:

```bash
# Connection timeout (seconds)
MPESA_CONNECT_TIMEOUT=5

# Request timeout (seconds)
MPESA_TIMEOUT=15
```

### Callback Security

**Production:**
```bash
MPESA_ALLOW_INSECURE_CALLBACKS=false
```

All callback URLs must use HTTPS in production.

**Local Development:**
```bash
MPESA_API_STATUS=sandbox
MPESA_ALLOW_INSECURE_CALLBACKS=true
```

Allows HTTP callbacks in sandbox mode for local testing.

### Logging

Enable request/response logging for debugging:

```bash
MPESA_LOGGING=true
```

?> Sensitive fields (passwords, credentials) are automatically excluded from logs.

## Obtaining Credentials

1. **Register** at [Safaricom Developer Portal](https://developer.safaricom.co.ke/)
2. **Create an app** to get Consumer Key and Secret
3. **Get test credentials** from the portal for sandbox testing
4. **Apply for production** credentials when ready to go live

## Security Best Practices

- ✅ Never commit credentials to version control
- ✅ Use different credentials for sandbox and production
- ✅ Rotate credentials periodically
- ✅ Limit credential access to necessary personnel
- ✅ Use HTTPS callbacks in production
- ✅ Enable logging only in development

## Next Steps

- [Send an STK Push](stk-push.md)
- [Handle callbacks](callbacks.md)
- [Learn about security](security.md)
