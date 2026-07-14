<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Unit;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use LaravelMpesa\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class TransactionStatusCommandTest extends TestCase
{
    #[Test]
    public function test_sendsStatusQueryByReceipt(): void
    {
        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/transactionstatus/v1/query' => Http::response([
                'ResponseCode' => '0',
            ]),
        ]);

        $this->artisan('mpesa:status', [
            '--receipt' => 'UGEHJB6GMF',
            '--result' => 'https://example.test/status/result',
            '--timeout' => 'https://example.test/status/timeout',
        ])->assertExitCode(Command::SUCCESS);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/mpesa/transactionstatus/v1/query')
            && $request['TransactionID'] === 'UGEHJB6GMF'
            && $request['IdentifierType'] === '4');
    }

    #[Test]
    public function test_sendsStatusQueryByConversationId(): void
    {
        config()->set('mpesa.transaction_status_result_url', 'https://example.test/status/result');
        config()->set('mpesa.transaction_status_timeout_url', 'https://example.test/status/timeout');

        Http::fake([
            '*/oauth/v1/generate*' => Http::response([
                'access_token' => 'access-token',
                'expires_in' => 3599,
            ]),
            '*/mpesa/transactionstatus/v1/query' => Http::response([
                'ResponseCode' => '0',
            ]),
        ]);

        $this->artisan('mpesa:status', [
            '--conversationId' => 'AG_20260714_12345',
        ])->assertExitCode(Command::SUCCESS);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), '/mpesa/transactionstatus/v1/query')
            && $request['TransactionID'] === 'AG_20260714_12345'
            && $request['IdentifierType'] === '1'
            && $request['ResultURL'] === 'https://example.test/status/result'
            && $request['QueueTimeOutURL'] === 'https://example.test/status/timeout');
    }

    #[Test]
    public function test_requiresExactlyOneTransactionIdentifier(): void
    {
        $this->artisan('mpesa:status')
            ->expectsOutput('Provide exactly one of --receipt or --conversationId.')
            ->assertExitCode(Command::FAILURE);

        $this->artisan('mpesa:status', [
            '--receipt' => 'UGEHJB6GMF',
            '--conversationId' => 'AG_20260714_12345',
        ])
            ->expectsOutput('Provide exactly one of --receipt or --conversationId.')
            ->assertExitCode(Command::FAILURE);

        Http::assertNothingSent();
    }

    #[Test]
    public function test_requiresConfiguredStatusCallbackUrls(): void
    {
        $this->artisan('mpesa:status', [
            '--receipt' => 'UGEHJB6GMF',
        ])
            ->expectsOutput('The --result option or MPESA_TRANSACTION_STATUS_RESULT_URL must be configured.')
            ->assertExitCode(Command::FAILURE);

        Http::assertNothingSent();
    }
}