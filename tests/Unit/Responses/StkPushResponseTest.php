<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use LaravelMpesa\Responses\StkPushResponse;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StkPushResponseTest extends TestCase
{
    #[Test]
    public function it_creates_successful_response_from_array(): void
    {
        $data = [
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ];

        $response = StkPushResponse::fromArray(true, $data);

        $this->assertTrue($response->isSuccessful());
        $this->assertFalse($response->isFailed());
        $this->assertSame('29115-34620561-1', $response->getMerchantRequestId());
        $this->assertSame('ws_CO_191220191020363925', $response->getCheckoutRequestId());
        $this->assertSame('0', $response->getResponseCode());
        $this->assertSame('Success. Request accepted for processing', $response->getResponseDescription());
        $this->assertSame('Success. Request accepted for processing', $response->getCustomerMessage());
        $this->assertNull($response->getErrorMessage());
    }

    #[Test]
    public function it_creates_failed_response_from_array(): void
    {
        $data = [
            'requestId' => '1234-5678',
            'errorCode' => '400.002.02',
            'errorMessage' => 'Bad Request - Invalid ShortCode',
        ];

        $response = StkPushResponse::fromArray(false, $data);

        $this->assertFalse($response->isSuccessful());
        $this->assertTrue($response->isFailed());
        $this->assertSame('Bad Request - Invalid ShortCode', $response->getErrorMessage());
        $this->assertNull($response->getCheckoutRequestId());
    }

    #[Test]
    public function it_serializes_to_json(): void
    {
        $data = [
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success',
        ];

        $response = StkPushResponse::fromArray(true, $data);
        $json = $response->jsonSerialize();

        $this->assertTrue($json['successful']);
        $this->assertSame('29115-34620561-1', $json['merchant_request_id']);
        $this->assertSame('ws_CO_191220191020363925', $json['checkout_request_id']);
        $this->assertSame($data, $json['raw_response']);
    }

    #[Test]
    public function it_handles_missing_optional_fields(): void
    {
        $response = StkPushResponse::fromArray(true, []);

        $this->assertTrue($response->isSuccessful());
        $this->assertNull($response->getMerchantRequestId());
        $this->assertNull($response->getCheckoutRequestId());
        $this->assertNull($response->getResponseCode());
    }
}
