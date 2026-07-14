<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class StkPushCommandTest extends TestCase
{
    #[Test]
    public function test_sendsStkPushFromConsoleCommand(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
                'CheckoutRequestID' => 'ws_CO_123',
            ]),
        ]);

        $this->artisan('mpesa:stk', [
            '--phone' => '0712345678',
            '--amount' => '100',
            '--ref' => 'RENT-1001',
            '--callback' => 'https://example.test/mpesa/stk/callback',
            '--description' => 'Rent payment',
        ])->assertExitCode(Command::SUCCESS);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/mpesa/stkpush/v1/processrequest')
            && $request['PhoneNumber'] === '254712345678'
            && $request['Amount'] === 100
            && $request['AccountReference'] === 'RENT-1001'
            && $request['TransactionDesc'] === 'Rent payment'
            && $request['CallBackURL'] === 'https://example.test/mpesa/stk/callback');
    }

    #[Test]
    public function test_defaultsDescriptionWhenOmitted(): void
    {
        config()->set('mpesa.stk_callback_url', 'https://example.test/mpesa/stk/callback');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
            ]),
        ]);

        $this->artisan('mpesa:stk', [
            '--phone' => '254712345678',
            '--amount' => '100',
            '--ref' => 'RENT-1001',
        ])->assertExitCode(Command::SUCCESS);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/mpesa/stkpush/v1/processrequest')
            && $request['AccountReference'] === 'RENT-1001'
            && $request['TransactionDesc'] === 'Description'
            && $request['Remark'] === 'Description');
    }

    #[Test]
    public function test_requiresConfiguredStkCallbackUrl(): void
    {
        config()->set('mpesa.stk_callback_url', null);

        $this->artisan('mpesa:stk', [
            '--phone' => '254712345678',
            '--amount' => '100',
            '--ref' => 'RENT-1001',
        ])
            ->expectsOutput('The --callback option or MPESA_STK_CALLBACK_URL must be configured before sending an STK push.')
            ->assertExitCode(Command::FAILURE);

        Http::assertNothingSent();
    }

    #[Test]
    public function test_fallsBackToConfiguredStkCallbackUrl(): void
    {
        config()->set('mpesa.stk_callback_url', 'https://example.test/mpesa/stk/callback');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/stkpush/v1/processrequest' => Http::response([
                'ResponseCode' => '0',
            ]),
        ]);

        $this->artisan('mpesa:stk', [
            '--phone' => '254712345678',
            '--amount' => '100',
            '--ref' => 'RENT-1001',
        ])->assertExitCode(Command::SUCCESS);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/mpesa/stkpush/v1/processrequest')
            && $request['CallBackURL'] === 'https://example.test/mpesa/stk/callback');
    }
}