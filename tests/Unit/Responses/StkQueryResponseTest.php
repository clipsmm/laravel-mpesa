<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use LaravelMpesa\Responses\StkQueryResponse;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StkQueryResponseTest extends TestCase
{
    #[Test]
    public function it_identifies_successful_payment(): void
    {
        $data = [
            'ResponseCode' => '0',
            'ResponseDescription' => 'The service request has been accepted successfully',
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResultCode' => '0',
            'ResultDesc' => 'The service request is processed successfully.',
        ];

        $response = StkQueryResponse::fromArray(true, $data);

        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isPaymentSuccessful());
        $this->assertFalse($response->isPaymentPending());
        $this->assertFalse($response->isPaymentFailed());
        $this->assertSame('The service request is processed successfully.', $response->getResultDescription());
    }

    #[Test]
    public function it_identifies_pending_payment(): void
    {
        $data = [
            'ResponseCode' => '0',
            'ResultCode' => '1032',
            'ResultDesc' => 'Request cancelled by user',
        ];

        $response = StkQueryResponse::fromArray(true, $data);

        $this->assertTrue($response->isPaymentPending());
        $this->assertFalse($response->isPaymentSuccessful());
        $this->assertFalse($response->isPaymentFailed());
    }

    #[Test]
    public function it_identifies_failed_payment(): void
    {
        $data = [
            'ResponseCode' => '0',
            'ResultCode' => '1',
            'ResultDesc' => 'The balance is insufficient for the transaction',
        ];

        $response = StkQueryResponse::fromArray(true, $data);

        $this->assertTrue($response->isPaymentFailed());
        $this->assertFalse($response->isPaymentSuccessful());
        $this->assertFalse($response->isPaymentPending());
        $this->assertSame('The balance is insufficient for the transaction', $response->getResultDescription());
    }

    #[Test]
    public function it_handles_cancelled_payment(): void
    {
        $data = [
            'ResponseCode' => '0',
            'ResultCode' => '1032',
            'ResultDesc' => 'Request cancelled by user',
        ];

        $response = StkQueryResponse::fromArray(true, $data);

        $this->assertTrue($response->isPaymentPending());
    }

    #[Test]
    public function it_serializes_payment_status(): void
    {
        $data = [
            'ResultCode' => '0',
            'ResultDesc' => 'Success',
        ];

        $response = StkQueryResponse::fromArray(true, $data);
        $json = $response->jsonSerialize();

        $this->assertTrue($json['payment_successful']);
        $this->assertFalse($json['payment_pending']);
        $this->assertFalse($json['payment_failed']);
    }
}
