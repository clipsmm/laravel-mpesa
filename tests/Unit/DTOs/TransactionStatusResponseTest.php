<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit\DTOs;

use LaravelMpesa\DTOs\Responses\TransactionStatusResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TransactionStatusResponseTest extends TestCase
{
    #[Test]
    public function test_parsesAcceptedResponse(): void
    {
        $response = TransactionStatusResponse::fromArray([
            'OriginatorConversationID' => '5118-111210482-1',
            'ConversationID' => 'AG_20191219_00005797af5d7d75f652',
            'ResponseCode' => '0',
            'ResponseDescription' => 'Accept the service request successfully.',
        ]);

        $this->assertTrue($response->accepted());
        $this->assertSame('5118-111210482-1', $response->originatorConversationId);
        $this->assertSame('AG_20191219_00005797af5d7d75f652', $response->conversationId);
    }

    #[Test]
    public function test_parsesRejectedResponse(): void
    {
        $response = TransactionStatusResponse::fromArray([
            'requestId' => 'abc-123',
            'errorCode' => '404.001.03',
            'errorMessage' => 'Invalid Access Token',
        ]);

        $this->assertFalse($response->accepted());
        $this->assertNull($response->conversationId);
    }
}
