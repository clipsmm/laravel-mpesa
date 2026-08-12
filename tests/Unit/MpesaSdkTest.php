<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Support\Facades\Http;
use LaravelMpesa\Mpesa;
use LaravelMpesa\MpesaSdk;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class MpesaSdkTest extends TestCase
{
    #[Test]
    public function test_constructorMergesSharedConfigWithApplicationConfig(): void
    {
        $sdk = new MpesaSdk('c2b');

        $this->assertSame('c2b', $sdk->app());
        $this->assertSame(2, $sdk->requestManager()->getConfig('connect_timeout'));
        $this->assertSame(5, $sdk->requestManager()->getConfig('timeout'));
        $this->assertSame('174379', $sdk->requestManager()->getConfig('shortcode'));
    }

    #[Test]
    public function test_instanceProducesEquivalentRequestManagerConfig(): void
    {
        $requestManager = MpesaSdk::instance('c2b');

        $this->assertSame(2, $requestManager->getConfig('connect_timeout'));
        $this->assertSame('test-key', $requestManager->getConfig('consumer_key'));
    }

    #[Test]
    public function test_constructorMergesRuntimeOptionsOverConfiguredValues(): void
    {
        $sdk = new MpesaSdk('c2b', ['shortcode' => '999999']);

        $this->assertSame('999999', $sdk->requestManager()->getConfig('shortcode'));
    }

    #[Test]
    public function test_facadeDelegatesToTheConfiguredApplication(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response(['access_token' => 'access-token', 'expires_in' => 3599]),
            '*/mpesa/stkpush/v1/processrequest' => Http::response(['ResponseCode' => '0']),
        ]);

        [$successful] = Mpesa::stkPush(
            '254712345678',
            100,
            'ORDER-1',
            'Test payment',
            'https://example.test/callback'
        );

        $this->assertTrue($successful);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/mpesa/stkpush/v1/processrequest')
            && $request['PhoneNumber'] === '254712345678');
    }
}
