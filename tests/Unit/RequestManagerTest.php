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
    public function it_authenticates_with_http_basic_credentials(): void
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
    public function it_rejects_absolute_or_traversing_api_paths(): void
    {
        $manager = MpesaSdk::instance();

        $this->expectException(\InvalidArgumentException::class);
        $manager->getEndpoint('https://attacker.example/oauth');
    }

    #[Test]
    public function it_requires_https_callback_urls(): void
    {
        $manager = MpesaSdk::instance();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('callback URL must use HTTPS');
        $manager->registerUrls('http://example.test/validate', 'https://example.test/confirm');
    }

    #[Test]
    public function it_validates_stk_inputs_before_authentication(): void
    {
        $manager = MpesaSdk::instance();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('2547XXXXXXXX');
        $manager->stkPush('0712345678', 100, 'ORDER-1', 'Test', 'https://example.test/callback');
    }

    #[Test]
    public function it_sends_an_authenticated_stk_push(): void
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
    public function configuration_does_not_fall_back_to_arbitrary_environment_keys(): void
    {
        $manager = new RequestManager([]);

        $this->assertSame('fallback', $manager->getConfig('PATH', 'fallback'));
    }
}
