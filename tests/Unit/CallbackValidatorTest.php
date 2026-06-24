<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Http\Request;
use InvalidArgumentException;
use LaravelMpesa\Tests\TestCase;
use LaravelMpesa\Traits\ValidatesMpesaCallback;
use PHPUnit\Framework\Attributes\Test;

class CallbackValidatorTest extends TestCase
{
    use ValidatesMpesaCallback;

    #[Test]
    public function it_validates_successful_stk_callback(): void
    {
        $request = Request::create('/callback', 'POST', [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_191220191020363925',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 1.00],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'NLJ7RT61SV'],
                            ['Name' => 'TransactionDate', 'Value' => 20191219102115],
                            ['Name' => 'PhoneNumber', 'Value' => 254708374149],
                        ],
                    ],
                ],
            ],
        ]);

        $callback = $this->validateStkCallback($request);

        $this->assertSame('29115-34620561-1', $callback['merchant_request_id']);
        $this->assertSame('ws_CO_191220191020363925', $callback['checkout_request_id']);
        $this->assertSame(0, $callback['result_code']);
        $this->assertTrue($this->isStkCallbackSuccessful($callback));

        $details = $this->extractStkPaymentDetails($callback);
        $this->assertSame(1.00, $details['amount']);
        $this->assertSame('NLJ7RT61SV', $details['mpesa_receipt_number']);
    }

    #[Test]
    public function it_validates_failed_stk_callback(): void
    {
        $request = Request::create('/callback', 'POST', [
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => '29115-34620561-1',
                    'CheckoutRequestID' => 'ws_CO_191220191020363925',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request cancelled by user',
                ],
            ],
        ]);

        $callback = $this->validateStkCallback($request);

        $this->assertSame(1032, $callback['result_code']);
        $this->assertFalse($this->isStkCallbackSuccessful($callback));
    }

    #[Test]
    public function it_validates_c2b_callback(): void
    {
        $request = Request::create('/callback', 'POST', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RKTQDM7W6S',
            'TransTime' => '20191122063845',
            'TransAmount' => '10',
            'BusinessShortCode' => '600638',
            'BillRefNumber' => 'account',
            'InvoiceNumber' => '',
            'OrgAccountBalance' => '49197.00',
            'ThirdPartyTransID' => '',
            'MSISDN' => '254708374149',
            'FirstName' => 'John',
            'MiddleName' => 'Doe',
            'LastName' => '',
        ]);

        $callback = $this->validateC2bCallback($request);

        $this->assertSame('Pay Bill', $callback['transaction_type']);
        $this->assertSame('RKTQDM7W6S', $callback['trans_id']);
        $this->assertSame(10.0, $callback['trans_amount']);
        $this->assertSame('254708374149', $callback['msisdn']);
    }

    #[Test]
    public function it_rejects_invalid_stk_callback_structure(): void
    {
        $request = Request::create('/callback', 'POST', ['invalid' => 'data']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid STK callback payload structure');

        $this->validateStkCallback($request);
    }

    #[Test]
    public function it_rejects_missing_c2b_required_fields(): void
    {
        $request = Request::create('/callback', 'POST', [
            'TransactionType' => 'Pay Bill',
            'TransID' => 'RKTQDM7W6S',
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing required C2B callback field');

        $this->validateC2bCallback($request);
    }
}
