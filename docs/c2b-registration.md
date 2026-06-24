# C2B Registration

Register validation and confirmation URLs for Customer to Business (C2B) payments.

## Overview

C2B registration tells M-Pesa where to send payment notifications when customers:
- Send money to your Paybill number
- Send money to your Till number

## Basic Usage

```php
use LaravelMpesa\MpesaSdk;

$response = MpesaSdk::instance()->registerUrls(
    validationUrl: 'https://example.com/api/mpesa/validation',
    confirmationUrl: 'https://example.com/api/mpesa/confirmation',
    returnDto: true
);

if ($response->isSuccessful()) {
    // URLs registered successfully
}
```

## Parameters

### `validationUrl` (required)

HTTPS URL where M-Pesa will call to validate payment before processing.

```php
validationUrl: 'https://example.com/api/mpesa/validation'
```

Return `ResultCode: 0` to accept or `ResultCode: 1` to reject.

### `confirmationUrl` (required)

HTTPS URL where M-Pesa will send payment confirmation.

```php
confirmationUrl: 'https://example.com/api/mpesa/confirmation'
```

### `responseType` (optional)

How to handle payments when validation fails. Default: `'Cancelled'`

```php
// Cancel payment if validation URL fails
responseType: 'Cancelled'

// Complete payment even if validation fails
responseType: 'Completed'
```

## Complete Example

```php
Route::post('mpesa/c2b/register', function () {
    $response = MpesaSdk::instance()->registerUrls(
        validationUrl: route('mpesa.validation'),
        confirmationUrl: route('mpesa.confirmation'),
        responseType: 'Cancelled',
        returnDto: true
    );

    if ($response->isSuccessful()) {
        return response()->json([
            'message' => 'URLs registered successfully',
            'conversation_id' => $response->getOriginatorConversationId(),
        ]);
    }

    return response()->json([
        'error' => $response->getErrorMessage(),
    ], 400);
});
```

## When to Register

Register URLs:
- Once during initial setup
- After changing callback URLs
- After shortcode changes
- Periodically to ensure registration is active

!> Registration expires after some time. Re-register periodically.

## Testing

In sandbox, use ngrok or similar to expose local development:

```bash
# Expose local server
ngrok http 8000

# Use ngrok URL in registration
https://abc123.ngrok.io/api/mpesa/validation
```

## Next Steps

- [Handle C2B callbacks](callbacks.md)
- [Callback validation guide](callbacks.md#c2b-callbacks)
- [Security best practices](security.md)
