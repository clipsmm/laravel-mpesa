<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LaravelMpesa\MpesaSdk;
use LaravelMpesa\RequestManager;
use LaravelMpesa\Responses\StkPushResponse;
use LaravelMpesa\Responses\StkQueryResponse;
use LaravelMpesa\Responses\UrlRegistrationResponse;
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

    #[Test]
    public function it_returns_stk_push_response_dto_when_requested(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_191220191020363925',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success. Request accepted for processing',
                'CustomerMessage' => 'Success. Request accepted for processing',
            ]),
        ]);

        $response = MpesaSdk::instance()->stkPush(
            '254712345678',
            100,
            'ORDER-1',
            'Test payment',
            'https://example.test/callback',
            returnDto: true,
        );

        $this->assertInstanceOf(StkPushResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('ws_CO_191220191020363925', $response->getCheckoutRequestId());
        $this->assertSame('29115-34620561-1', $response->getMerchantRequestId());
    }

    #[Test]
    public function it_queries_stk_push_status(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpushquery/v1/query' => Http::response([
                'ResponseCode' => '0',
                'ResponseDescription' => 'The service request has been accepted successfully',
                'MerchantRequestID' => '29115-34620561-1',
                'CheckoutRequestID' => 'ws_CO_191220191020363925',
                'ResultCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.',
            ]),
        ]);

        [$successful, $response] = MpesaSdk::instance()->stkQuery('ws_CO_191220191020363925');

        $this->assertTrue($successful);
        $this->assertSame('0', $response['ResultCode']);
        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request['CheckoutRequestID'] === 'ws_CO_191220191020363925'
            && isset($request['Password'])
            && isset($request['Timestamp']));
    }

    #[Test]
    public function it_returns_stk_query_response_dto_when_requested(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpushquery/v1/query' => Http::response([
                'ResponseCode' => '0',
                'ResultCode' => '0',
                'ResultDesc' => 'The service request is processed successfully.',
            ]),
        ]);

        $response = MpesaSdk::instance()->stkQuery(
            'ws_CO_191220191020363925',
            returnDto: true,
        );

        $this->assertInstanceOf(StkQueryResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertTrue($response->isPaymentSuccessful());
        $this->assertFalse($response->isPaymentPending());
    }

    #[Test]
    public function it_validates_checkout_request_id_for_stk_query(): void
    {
        $manager = MpesaSdk::instance();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Checkout request ID cannot be empty');
        $manager->stkQuery('');
    }

    #[Test]
    public function it_returns_url_registration_response_dto_when_requested(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/c2b/v2/registerurl' => Http::response([
                'OriginatorConversationID' => 'AG_20191219_00005797af5d7d75f652',
                'ResponseCode' => '0',
                'ResponseDescription' => 'Success',
            ]),
        ]);

        $response = MpesaSdk::instance()->registerUrls(
            'https://example.test/validate',
            'https://example.test/confirm',
            returnDto: true,
        );

        $this->assertInstanceOf(UrlRegistrationResponse::class, $response);
        $this->assertTrue($response->isSuccessful());
        $this->assertSame('AG_20191219_00005797af5d7d75f652', $response->getOriginatorConversationId());
    }

    #[Test]
    public function it_logs_requests_and_responses_when_enabled(): void
    {
        Log::spy();

        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
            ]),
        ]);

        $config = config('mpesa.apps.c2b');
        $config['logging'] = true;
        $manager = new RequestManager($config);

        $manager->stkPush(
            '254712345678',
            100,
            'ORDER-1',
            'Test payment',
            'https://example.test/callback'
        );

        // Should log request and response for the STK push
        Log::shouldHaveReceived('info')
            ->with('Mpesa API Request', \Mockery::on(function ($arg) {
                return is_array($arg) && isset($arg['url']) && isset($arg['payload']);
            }))
            ->once();

        Log::shouldHaveReceived('info')
            ->with('Mpesa API Response', \Mockery::on(function ($arg) {
                return is_array($arg) && isset($arg['successful']) && isset($arg['data']);
            }))
            ->once();
    }
}

