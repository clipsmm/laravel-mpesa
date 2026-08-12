<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit\DTOs;

use LaravelMpesa\DTOs\Responses\StkPushResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StkPushResponseTest extends TestCase
{
    #[Test]
    public function test_parsesAcceptedResponse(): void
    {
        $response = StkPushResponse::fromArray([
            'MerchantRequestID' => '29115-34620561-1',
            'CheckoutRequestID' => 'ws_CO_191220191020363925',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Success. Request accepted for processing',
            'CustomerMessage' => 'Success. Request accepted for processing',
        ]);

        $this->assertTrue($response->accepted());
        $this->assertSame('29115-34620561-1', $response->merchantRequestId);
        $this->assertSame('ws_CO_191220191020363925', $response->checkoutRequestId);
        $this->assertSame('0', $response->responseCode);
    }

    #[Test]
    public function test_parsesRejectedResponse(): void
    {
        $response = StkPushResponse::fromArray([
            'requestId' => 'abc-123',
            'errorCode' => '400.002.02',
            'errorMessage' => 'Bad Request - Invalid PhoneNumber',
        ]);

        $this->assertFalse($response->accepted());
        $this->assertNull($response->responseCode);
        $this->assertNull($response->checkoutRequestId);
    }

    #[Test]
    public function test_retainsRawPayload(): void
    {
        $payload = ['ResponseCode' => '0', 'Extra' => 'value'];

        $response = StkPushResponse::fromArray($payload);

        $this->assertSame($payload, $response->payload);
    }
}
