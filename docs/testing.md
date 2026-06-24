# Testing

Test your M-Pesa integration without making real API calls.

## Testing Philosophy

The package uses Laravel's HTTP fakes - **no real M-Pesa API calls** are made during tests.

## Setup

```php
use Illuminate\Support\Facades\Http;
use LaravelMpesa\MpesaSdk;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Fake all HTTP requests
        Http::fake();
    }
}
```

## Testing STK Push

```php
public function test_stk_push_initiates_payment()
{
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3599,
        ]),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
        ]),
    ]);

    $response = MpesaSdk::instance()->stkPush(
        receiver: '254712345678',
        amount: 1000,
        ref: 'ORDER-123',
        description: 'Test payment',
        callbackUrl: 'https://example.com/callback',
        returnDto: true
    );

    $this->assertTrue($response->isSuccessful());
    $this->assertEquals('ws_CO_191220191020363925', $response->getCheckoutRequestId());
}
```

## Testing STK Query

```php
public function test_stk_query_checks_payment_status()
{
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3599,
        ]),
        '*/mpesa/stkpushquery/v1/query' => Http::response([
            'ResponseCode' => '0',
            'ResultCode' => '0',
            'ResultDesc' => 'The service request is processed successfully.',
        ]),
    ]);

    $response = MpesaSdk::instance()->stkQuery(
        'ws_CO_191220191020363925',
        returnDto: true
    );

    $this->assertTrue($response->isPaymentSuccessful());
}
```

## Testing Callbacks

```php
public function test_successful_callback_completes_payment()
{
    $payment = Payment::factory()->create([
        'checkout_request_id' => 'ws_CO_123',
        'status' => 'pending',
    ]);

    $response = $this->postJson('/api/mpesa/stk/callback', [
        'Body' => [
            'stkCallback' => [
                'MerchantRequestID' => '123',
                'CheckoutRequestID' => 'ws_CO_123',
                'ResultCode' => 0,
                'ResultDesc' => 'Success',
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 1000],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'ABC123'],
                        ['Name' => 'PhoneNumber', 'Value' => 254712345678],
                    ],
                ],
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['ResultCode' => 0]);
    
    $payment->refresh();
    $this->assertEquals('completed', $payment->status);
    $this->assertEquals('ABC123', $payment->mpesa_receipt_number);
}
```

## Testing Validation

```php
public function test_stk_push_validates_phone_number()
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('2547XXXXXXXX');

    MpesaSdk::instance()->stkPush(
        receiver: '0712345678',  // Invalid format
        amount: 1000,
        ref: 'ORDER-123',
        description: 'Test',
        callbackUrl: 'https://example.com/callback'
    );
}

public function test_stk_push_validates_amount()
{
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('greater than zero');

    MpesaSdk::instance()->stkPush(
        receiver: '254712345678',
        amount: 0,  // Invalid amount
        ref: 'ORDER-123',
        description: 'Test',
        callbackUrl: 'https://example.com/callback'
    );
}
```

## Testing Error Scenarios

```php
public function test_handles_authentication_failure()
{
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([], 401),
    ]);

    $manager = MpesaSdk::instance();
    
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Unable to authenticate');
    
    $manager->stkPush(
        receiver: '254712345678',
        amount: 1000,
        ref: 'ORDER-123',
        description: 'Test',
        callbackUrl: 'https://example.com/callback'
    );
}

public function test_handles_mpesa_rejection()
{
    Http::fake([
        '*/oauth/v1/generate*' => Http::response([
            'access_token' => 'test-token',
            'expires_in' => 3599,
        ]),
        '*/mpesa/stkpush/v1/processrequest' => Http::response([
            'errorMessage' => 'Bad Request - Invalid ShortCode',
        ], 400),
    ]);

    $response = MpesaSdk::instance()->stkPush(
        receiver: '254712345678',
        amount: 1000,
        ref: 'ORDER-123',
        description: 'Test',
        callbackUrl: 'https://example.com/callback',
        returnDto: true
    );

    $this->assertFalse($response->isSuccessful());
    $this->assertStringContainsString('Invalid ShortCode', $response->getErrorMessage());
}
```

## Testing with Factory

```php
// database/factories/PaymentFactory.php
class PaymentFactory extends Factory
{
    public function definition()
    {
        return [
            'checkout_request_id' => 'ws_CO_' . $this->faker->numerify('####################'),
            'merchant_request_id' => $this->faker->numerify('##########'),
            'phone_number' => '2547' . $this->faker->numerify('########'),
            'amount' => $this->faker->numberBetween(100, 10000),
            'status' => 'pending',
        ];
    }

    public function completed()
    {
        return $this->state([
            'status' => 'completed',
            'mpesa_receipt_number' => 'TEST' . $this->faker->numerify('########'),
        ]);
    }
}

// Usage
$payment = Payment::factory()->completed()->create();
```

## Assertions

### HTTP Assertions

```php
Http::assertSent(function ($request) {
    return $request->url() === 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest'
        && $request['Amount'] === 1000
        && $request['PhoneNumber'] === '254712345678';
});

Http::assertSentCount(2);  // Auth + STK push

Http::assertNotSent(function ($request) {
    return str_contains($request->url(), 'example.com');
});
```

### Response Assertions

```php
$response->assertOk();
$response->assertStatus(200);
$response->assertJson(['ResultCode' => 0]);
$response->assertJsonStructure([
    'ResultCode',
    'ResultDesc',
]);
```

### Database Assertions

```php
$this->assertDatabaseHas('payments', [
    'checkout_request_id' => 'ws_CO_123',
    'status' => 'completed',
]);

$this->assertDatabaseCount('payments', 1);
```

## Feature Testing

```php
public function test_complete_payment_flow()
{
    Http::fake();
    
    $user = User::factory()->create();
    
    // Initiate payment
    $response = $this->actingAs($user)
        ->postJson('/api/payments/initiate', [
            'phone' => '254712345678',
            'amount' => 1000,
        ]);
    
    $response->assertOk();
    $checkoutId = $response->json('checkout_request_id');
    
    $this->assertDatabaseHas('payments', [
        'user_id' => $user->id,
        'checkout_request_id' => $checkoutId,
        'status' => 'pending',
    ]);
    
    // Simulate callback
    $this->postJson('/api/mpesa/callback', [
        'Body' => [
            'stkCallback' => [
                'CheckoutRequestID' => $checkoutId,
                'ResultCode' => 0,
                'CallbackMetadata' => [
                    'Item' => [
                        ['Name' => 'Amount', 'Value' => 1000],
                        ['Name' => 'MpesaReceiptNumber', 'Value' => 'TEST123'],
                    ],
                ],
            ],
        ],
    ]);
    
    $this->assertDatabaseHas('payments', [
        'checkout_request_id' => $checkoutId,
        'status' => 'completed',
    ]);
}
```

## Running Tests

```bash
# Run all tests
composer test

# Run specific test
vendor/bin/phpunit --filter test_stk_push_initiates_payment

# Run with coverage
composer test:coverage

# Run tests in parallel
vendor/bin/phpunit --parallel
```

## Continuous Integration

Tests run automatically in GitHub Actions:

```yaml
# .github/workflows/tests.yml
- name: Run tests
  run: composer test
```

## Best Practices

✅ **DO:**
- Use HTTP fakes for all tests
- Test both success and failure scenarios
- Test validation rules
- Test idempotency
- Use factories for test data
- Test callback processing
- Test database state changes

❌ **DON'T:**
- Don't make real M-Pesa API calls
- Don't hardcode test data
- Don't skip error cases
- Don't test only happy paths

## Next Steps

- [Error handling](error-handling.md)
- [Security guide](security.md)
- [API Reference](api-reference.md)
