<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit\DTOs;

use LaravelMpesa\DTOs\Callbacks\StkCallbackData;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StkCallbackDataTest extends TestCase
{
    #[Test]
    public function test_parsesSuccessfulCallbackMetadata(): void
    {
        $callback = StkCallbackData::fromPayload([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'ff3e-4fa4-abc0-8eb3aa92c0d998768',
                    'CheckoutRequestID' => 'ws_CO_14072026222032600702997218',
                    'ResultCode' => 0,
                    'ResultDesc' => 'The service request is processed successfully.',
                    'CallbackMetadata' => [
                        'Item' => [
                            ['Name' => 'Amount', 'Value' => 5],
                            ['Name' => 'MpesaReceiptNumber', 'Value' => 'UGEHJB6GMF'],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertTrue($callback->succeeded());
        $this->assertSame('ff3e-4fa4-abc0-8eb3aa92c0d998768', $callback->merchantRequestId);
        $this->assertSame(5, $callback->metadataValue('Amount'));
        $this->assertSame('UGEHJB6GMF', $callback->metadataValue('MpesaReceiptNumber'));
        $this->assertNull($callback->metadataValue('Missing'));
    }

    #[Test]
    public function test_parsesFailedCallbackWithoutMetadata(): void
    {
        $callback = StkCallbackData::fromPayload([
            'Body' => [
                'stkCallback' => [
                    'MerchantRequestID' => 'abc',
                    'CheckoutRequestID' => 'ws_CO_1',
                    'ResultCode' => 1032,
                    'ResultDesc' => 'Request Cancelled by user.',
                ],
            ],
        ]);

        $this->assertFalse($callback->succeeded());
        $this->assertSame(1032, $callback->resultCode);
        $this->assertSame([], $callback->metadata);
    }

    #[Test]
    public function test_toleratesMalformedPayload(): void
    {
        $callback = StkCallbackData::fromPayload([]);

        $this->assertFalse($callback->succeeded());
        $this->assertSame('', $callback->merchantRequestId);
        $this->assertSame('', $callback->checkoutRequestId);
        $this->assertSame(-1, $callback->resultCode);
        $this->assertSame([], $callback->metadata);
    }
}
