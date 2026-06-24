# Response DTOs

Response Data Transfer Objects (DTOs) provide type-safe, IDE-friendly responses with helpful methods.

## Why Use DTOs?

**Without DTOs (array returns):**
```php
[$success, $data] = $mpesa->stkPush(...);

// ❌ No autocomplete
// ❌ No type safety
// ❌ Need to remember array keys
$checkoutId = $data['CheckoutRequestID'] ?? null;
```

**With DTOs:**
```php
$response = $mpesa->stkPush(..., returnDto: true);

// ✅ Full IDE autocomplete
// ✅ Type-safe
// ✅ Clear method names
$checkoutId = $response->getCheckoutRequestId();
```

## Available DTOs

### StkPushResponse

Returned from `stkPush()` method.

```php
use LaravelMpesa\Responses\StkPushResponse;

$response = $mpesa->stkPush(..., returnDto: true);

// Status checks
$response->isSuccessful(): bool
$response->isFailed(): bool

// Get data
$response->getMerchantRequestId(): ?string
$response->getCheckoutRequestId(): ?string
$response->getResponseCode(): ?string
$response->getResponseDescription(): ?string
$response->getCustomerMessage(): ?string
$response->getErrorMessage(): ?string

// Get raw response
$response->getRawResponse(): array

// JSON serializable
$json = $response->jsonSerialize(): array
```

### StkQueryResponse

Returned from `stkQuery()` method.

```php
use LaravelMpesa\Responses\StkQueryResponse;

$response = $mpesa->stkQuery($checkoutId, returnDto: true);

// Status checks
$response->isSuccessful(): bool
$response->isPaymentSuccessful(): bool  // Payment completed
$response->isPaymentPending(): bool     // Waiting or timed out
$response->isPaymentFailed(): bool      // Failed or cancelled

// Get data
$response->getMerchantRequestId(): ?string
$response->getCheckoutRequestId(): ?string
$response->getResultDescription(): ?string
```

**Payment Status Logic:**

| Result Code | Status | Description |
|-------------|--------|-------------|
| `0` | Successful | Payment completed |
| `1032`, `1037` | Pending | Cancelled by user or timeout |
| Others | Failed | Various failures |

```php
if ($response->isPaymentSuccessful()) {
    // Fulfill order
} elseif ($response->isPaymentPending()) {
    // Still waiting or timed out
} elseif ($response->isPaymentFailed()) {
    // Payment failed
}
```

### UrlRegistrationResponse

Returned from `registerUrls()` method.

```php
use LaravelMpesa\Responses\UrlRegistrationResponse;

$response = $mpesa->registerUrls(..., returnDto: true);

// Status checks
$response->isSuccessful(): bool
$response->isFailed(): bool

// Get data
$response->getOriginatorConversationId(): ?string
$response->getResponseDescription(): ?string
```

## Base Response Class

All DTOs extend `MpesaResponse` which provides:

```php
use LaravelMpesa\Responses\MpesaResponse;

// Common methods
$response->isSuccessful(): bool
$response->isFailed(): bool
$response->getErrorMessage(): ?string
$response->getRawResponse(): array
$response->jsonSerialize(): array
```

## Backward Compatibility

All methods support both return types:

```php
// Old code still works
[$success, $data] = $mpesa->stkPush(...);

// New code with DTOs
$response = $mpesa->stkPush(..., returnDto: true);
```

## JSON Serialization

DTOs can be directly returned in API responses:

```php
public function initiate(Request $request)
{
    $response = MpesaSdk::instance()->stkPush(
        receiver: $request->phone,
        amount: $request->amount,
        ref: 'ORDER-123',
        description: 'Payment',
        callbackUrl: route('callback'),
        returnDto: true
    );

    // Automatically serializes to JSON
    return response()->json($response);
}
```

Response:
```json
{
  "successful": true,
  "merchant_request_id": "29115-34620561-1",
  "checkout_request_id": "ws_CO_191220191020363925",
  "response_code": "0",
  "response_description": "Success. Request accepted for processing",
  "customer_message": "Success. Request accepted for processing",
  "error_message": null,
  "raw_response": { ... }
}
```

## Creating Custom DTOs

You can extend the base response for custom needs:

```php
use LaravelMpesa\Responses\MpesaResponse;

readonly class CustomResponse extends MpesaResponse
{
    public function __construct(
        bool $successful,
        array $rawResponse,
        ?string $errorMessage = null,
        public ?string $customField = null,
    ) {
        parent::__construct($successful, $rawResponse, $errorMessage);
    }

    public static function fromArray(bool $successful, array $data): self
    {
        return new self(
            successful: $successful,
            rawResponse: $data,
            errorMessage: $successful ? null : ($data['errorMessage'] ?? 'Unknown error'),
            customField: $data['CustomField'] ?? null,
        );
    }

    public function getCustomField(): ?string
    {
        return $this->customField;
    }
}
```

## Type Hints

Use type hints for better IDE support:

```php
use LaravelMpesa\Responses\StkPushResponse;
use LaravelMpesa\MpesaSdk;

class PaymentService
{
    public function __construct(
        private MpesaSdk $mpesa
    ) {}

    public function sendPayment(string $phone, int $amount): StkPushResponse
    {
        return $this->mpesa->stkPush(
            receiver: $phone,
            amount: $amount,
            ref: 'ORDER-123',
            description: 'Payment',
            callbackUrl: route('callback'),
            returnDto: true  // Returns StkPushResponse
        );
    }
}
```

## Testing with DTOs

```php
use LaravelMpesa\Responses\StkPushResponse;

public function test_payment_initiation()
{
    Http::fake([
        '*/mpesa/stkpush/*' => Http::response([
            'MerchantRequestID' => '123',
            'CheckoutRequestID' => 'ws_CO_123',
            'ResponseCode' => '0',
        ]),
    ]);

    $response = $this->mpesa->stkPush(..., returnDto: true);

    $this->assertInstanceOf(StkPushResponse::class, $response);
    $this->assertTrue($response->isSuccessful());
    $this->assertEquals('ws_CO_123', $response->getCheckoutRequestId());
}
```

## Next Steps

- [STK Push guide](stk-push.md)
- [STK Query guide](stk-query.md)
- [API Reference](api-reference.md)
