<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit\DTOs;

use LaravelMpesa\DTOs\Responses\RegisterUrlResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RegisterUrlResponseTest extends TestCase
{
    #[Test]
    public function test_parsesConversationIdField(): void
    {
        $response = RegisterUrlResponse::fromArray([
            'OriginatorConversationID' => '7619-37765134-1',
            'ResponseDescription' => 'success',
        ]);

        $this->assertSame('7619-37765134-1', $response->originatorConversationId);
        $this->assertSame('success', $response->responseDescription);
    }

    #[Test]
    public function test_fallsBackToMisspelledConversationIdField(): void
    {
        $response = RegisterUrlResponse::fromArray([
            'OriginatorCoversationID' => '7619-37765134-1',
            'ResponseDescription' => 'success',
        ]);

        $this->assertSame('7619-37765134-1', $response->originatorConversationId);
    }
}
