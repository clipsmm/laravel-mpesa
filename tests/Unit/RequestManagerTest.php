<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Support\Facades\Http;
use LaravelMpesa\MpesaSdk;
use LaravelMpesa\RequestManager;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class RequestManagerTest extends TestCase
{
    #[Test]
    public function test_authenticatesWithHttpBasicCredentials(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
        ]);

        $manager = MpesaSdk::instance();

        $this->assertTrue($manager->authenticate());
        $this->assertTrue($manager->isAuthenticated());
        Http::assertSent(fn ($request) => $request->hasHeader(
            'Authorization',
            'Basic ' . base64_encode('test-key:test-secret')
        ));
    }

    #[Test]
    public function test_rejectsAbsoluteOrTraversingApiPaths(): void
    {
        $manager = MpesaSdk::instance();

        $this->expectException(\InvalidArgumentException::class);
        $manager->getEndpoint('https://attacker.example/oauth');
    }

    #[Test]
    public function test_requiresHttpsCallbackUrls(): void
    {
        $manager = MpesaSdk::instance();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('callback URL must use HTTPS');
        $manager->registerUrls('http://example.test/validate', 'https://example.test/confirm');
    }

    #[Test]
    public function test_validatesStkInputsBeforeAuthentication(): void
    {
        $manager = MpesaSdk::instance();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2547XXXXXXXX');
        $manager->stkPush('0712345678', 100, 'ORDER-1', 'Test', 'https://example.test/callback');
    }

    #[Test]
    public function test_sendsAuthenticatedStkPush(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
            ]),
        ]);

        [$successful, $response] = MpesaSdk::instance()->stkPush(
            '254712345678',
            100,
            'ORDER-1',
            'Test payment',
            'https://example.test/callback'
        );

        $this->assertTrue($successful);
        $this->assertSame('0', $response['ResponseCode']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['PhoneNumber'] === '254712345678'
            && $request['Amount'] === 100);
    }

    #[Test]
    public function test_sendsAuthenticatedTransactionStatusQuery(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/transactionstatus/v1/query' => Http::response([
                'ResponseCode' => '0',
                'ConversationID' => 'AG_123',
            ]),
        ]);

        [$successful, $response] = MpesaSdk::instance()->transactionStatus(
            'UGEHJB6GMF',
            'https://example.test/status/result',
            'https://example.test/status/timeout'
        );

        $this->assertTrue($successful);
        $this->assertSame('0', $response['ResponseCode']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/mpesa/transactionstatus/v1/query')
            && $request['CommandID'] === 'TransactionStatusQuery'
            && $request['TransactionID'] === 'UGEHJB6GMF'
            && $request['IdentifierType'] === '4'
            && $request['Initiator'] === 'test-initiator'
            && $request['SecurityCredential'] === 'test-security-credential'
            && $request['ResultURL'] === 'https://example.test/status/result'
            && $request['QueueTimeOutURL'] === 'https://example.test/status/timeout');
    }

    #[Test]
    public function test_configurationDoesNotFallBackToArbitraryEnvironmentKeys(): void
    {
        $manager = new RequestManager([]);

        $this->assertSame('fallback', $manager->getConfig('PATH', 'fallback'));
    }
}
