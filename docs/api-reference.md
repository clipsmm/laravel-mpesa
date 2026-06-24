# API Reference

Complete reference for all public methods.

## MpesaSdk

### `__construct(?string $app = null, array $opts = [])`

Create an SDK instance for a configured application.

```php
use LaravelMpesa\MpesaSdk;

// Use default app
$mpesa = new MpesaSdk();

// Specify app
$mpesa = new MpesaSdk('paybill');

// Override config
$mpesa = new MpesaSdk('c2b', [
    'timeout' => 30,
    'logging' => true,
]);
```

**Parameters:**
- `$app` - Application name from config (default: config value)
- `$opts` - Runtime config overrides

**Throws:** `InvalidArgumentException` if app not configured

### `static instance(?string $app = null): RequestManager`

Get a RequestManager instance (recommended).

```php
$manager = MpesaSdk::instance();
$manager = MpesaSdk::instance('paybill');
```

## RequestManager

### `authenticate(): bool`

Manually authenticate and cache OAuth token.

```php
$success = $manager->authenticate();
```

**Returns:** `true` on success, `false` on authentication failure  
**Throws:** `RuntimeException` on transport errors or malformed responses

?> Authentication is automatic - you rarely need to call this manually.

### `registerUrls(...)`

Register C2B validation and confirmation URLs.

```php
public function registerUrls(
    string $validationUrl,
    string $confirmationUrl,
    string $responseType = 'Cancelled',
    bool $returnDto = false,
): array|UrlRegistrationResponse
```

**Parameters:**
- `$validationUrl` - HTTPS URL for validation
- `$confirmationUrl` - HTTPS URL for confirmation
- `$responseType` - `'Cancelled'` or `'Completed'` (default: `'Cancelled'`)
- `$returnDto` - Return DTO instead of array (default: `false`)

**Example:**
```php
// Array return
[$success, $data] = $manager->registerUrls(
    'https://example.com/validate',
    'https://example.com/confirm'
);

// DTO return
$response = $manager->registerUrls(
    'https://example.com/validate',
    'https://example.com/confirm',
    returnDto: true
);
```

**Throws:**
- `InvalidArgumentException` - Invalid URL format or not HTTPS
- `RuntimeException` - Authentication or network failure

### `stkPush(...)`

Send STK Push payment request.

```php
public function stkPush(
    string $receiver,
    int $amount,
    string $ref,
    string $description,
    string $callbackUrl,
    string $transactionType = 'CustomerPayBillOnline',
    bool $returnDto = false,
): array|StkPushResponse
```

**Parameters:**
- `$receiver` - Phone number in `2547XXXXXXXX` format
- `$amount` - Amount in KES (1 to 150,000)
- `$ref` - Account reference (max 100 chars)
- `$description` - Transaction description (max 182 chars)
- `$callbackUrl` - HTTPS callback URL
- `$transactionType` - Transaction type (default: `'CustomerPayBillOnline'`)
- `$returnDto` - Return DTO instead of array (default: `false`)

**Example:**
```php
$response = $manager->stkPush(
    receiver: '254712345678',
    amount: 1000,
    ref: 'ORDER-123',
    description: 'Payment for order',
    callbackUrl: 'https://example.com/callback',
    returnDto: true
);
```

**Throws:**
- `InvalidArgumentException` - Invalid parameters
- `RuntimeException` - Authentication or network failure

### `stkQuery(...)`

Query STK Push payment status.

```php
public function stkQuery(
    string $checkoutRequestId,
    bool $returnDto = false,
): array|StkQueryResponse
```

**Parameters:**
- `$checkoutRequestId` - Checkout request ID from STK Push
- `$returnDto` - Return DTO instead of array (default: `false`)

**Example:**
```php
$response = $manager->stkQuery(
    'ws_CO_191220191020363925',
    returnDto: true
);

if ($response->isPaymentSuccessful()) {
    // Payment completed
}
```

**Throws:**
- `InvalidArgumentException` - Empty checkout request ID
- `RuntimeException` - Authentication or network failure

### `getEndpoint(string $url): string`

Resolve relative API path to full URL.

```php
$url = $manager->getEndpoint('/mpesa/stkpush/v1/processrequest');
// Returns: https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest
```

**Throws:** `InvalidArgumentException` - Absolute URLs or path traversal

### `isAuthenticated(): bool`

Check if cached token is still valid.

```php
if (!$manager->isAuthenticated()) {
    $manager->authenticate();
}
```

**Returns:** `true` if token valid (30-second buffer)

### `getConfig(string $key, mixed $default = null): mixed`

Get configuration value.

```php
$shortcode = $manager->getConfig('shortcode');
$timeout = $manager->getConfig('timeout', 15);
```

## Response DTOs

### StkPushResponse

```php
// Status
$response->isSuccessful(): bool
$response->isFailed(): bool

// Data
$response->getMerchantRequestId(): ?string
$response->getCheckoutRequestId(): ?string
$response->getResponseCode(): ?string
$response->getResponseDescription(): ?string
$response->getCustomerMessage(): ?string
$response->getErrorMessage(): ?string

// Raw
$response->getRawResponse(): array
$response->jsonSerialize(): array
```

### StkQueryResponse

```php
// Status
$response->isSuccessful(): bool
$response->isPaymentSuccessful(): bool
$response->isPaymentPending(): bool
$response->isPaymentFailed(): bool

// Data
$response->getResultDescription(): ?string
```

### UrlRegistrationResponse

```php
// Status
$response->isSuccessful(): bool
$response->isFailed(): bool

// Data
$response->getOriginatorConversationId(): ?string
$response->getResponseDescription(): ?string
```

## Callback Validation Trait

```php
use LaravelMpesa\Traits\ValidatesMpesaCallback;

class Controller {
    use ValidatesMpesaCallback;
    
    // Methods available:
    protected function validateStkCallback(Request $request): array
    protected function validateC2bCallback(Request $request): array
    protected function isStkCallbackSuccessful(array $callback): bool
    protected function extractStkPaymentDetails(array $callback): array
}
```

### `validateStkCallback(Request $request): array`

Validate and parse STK callback.

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

**Throws:** `InvalidArgumentException`

### `validateC2bCallback(Request $request): array`

Validate and parse C2B callback.

**Returns:**
```php
[
    'transaction_type' => string,
    'trans_id' => string,
    'trans_amount' => float,
    'msisdn' => string,
    // ... more fields
]
```

**Throws:** `InvalidArgumentException`

### `isStkCallbackSuccessful(array $callback): bool`

Check if payment succeeded.

### `extractStkPaymentDetails(array $callback): array`

Extract payment metadata.

**Returns:**
```php
[
    'amount' => ?float,
    'mpesa_receipt_number' => ?string,
    'transaction_date' => ?string,
    'phone_number' => ?string,
]
```

## Exceptions

### InvalidArgumentException

Thrown for:
- Invalid phone format
- Invalid amount
- Invalid URLs
- Missing configuration
- Invalid parameters

### RuntimeException

Thrown for:
- Authentication failures
- Network errors
- Malformed API responses
- Missing required config

## Configuration Reference

```php
// config/mpesa.php
return [
    'default' => 'c2b',
    'connect_timeout' => 5,
    'timeout' => 15,
    'allow_insecure_callbacks' => false,
    'logging' => false,
    
    'apps' => [
        'c2b' => [
            'status' => 'sandbox',  // or 'live'
            'consumer_key' => '...',
            'consumer_secret' => '...',
            'shortcode' => '...',
            'passkey' => '...',
        ],
    ],
];
```

## Type Unions

Methods support both return types for backward compatibility:

```php
// Returns array OR DTO depending on $returnDto parameter
array|StkPushResponse
array|StkQueryResponse
array|UrlRegistrationResponse
```

Use type hints appropriately:

```php
// If always using DTOs
public function send(): StkPushResponse
{
    return $mpesa->stkPush(..., returnDto: true);
}

// If supporting both
public function send(): array|StkPushResponse
{
    return $mpesa->stkPush(..., returnDto: $this->useDto);
}
```
