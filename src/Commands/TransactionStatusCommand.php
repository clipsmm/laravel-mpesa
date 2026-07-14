<?php

declare(strict_types=1);

namespace LaravelMpesa\Commands;

use Illuminate\Console\Command;
use LaravelMpesa\MpesaSdk;
use Throwable;

class TransactionStatusCommand extends Command
{
    protected $signature = 'mpesa:status {--receipt= : Mpesa receipt number} {--conversationId= : Provider conversation or originator conversation ID} {--result= : Result callback URL} {--timeout= : Timeout callback URL} {--remarks=Transaction status query : Query remarks} {--occasion=TransactionStatus : Query occasion}';

    protected $description = 'Query Mpesa transaction status.';

    public function handle(): int
    {
        $receipt = trim((string) $this->option('receipt'));
        $conversationId = trim((string) $this->option('conversationId'));
        $resultUrl = trim((string) ($this->option('result') ?: config('mpesa.transaction_status_result_url', '')));
        $timeoutUrl = trim((string) ($this->option('timeout') ?: config('mpesa.transaction_status_timeout_url', '')));
        $remarks = trim((string) $this->option('remarks')) ?: 'Transaction status query';
        $occasion = trim((string) $this->option('occasion')) ?: 'TransactionStatus';

        if (($receipt === '' && $conversationId === '') || ($receipt !== '' && $conversationId !== '')) {
            $this->error('Provide exactly one of --receipt or --conversationId.');

            return self::FAILURE;
        }

        if ($resultUrl === '') {
            $this->error('The --result option or MPESA_TRANSACTION_STATUS_RESULT_URL must be configured.');

            return self::FAILURE;
        }

        if ($timeoutUrl === '') {
            $this->error('The --timeout option or MPESA_TRANSACTION_STATUS_TIMEOUT_URL must be configured.');

            return self::FAILURE;
        }

        try {
            [$successful, $response] = MpesaSdk::instance()->transactionStatus(
                identifier: $receipt !== '' ? $receipt : $conversationId,
                resultUrl: $resultUrl,
                timeoutUrl: $timeoutUrl,
                identifierType: $receipt !== '' ? '4' : '1',
                remarks: $remarks,
                occasion: $occasion,
            );
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');

        if (!$successful) {
            $this->error('Mpesa transaction status query failed.');

            return self::FAILURE;
        }

        $this->info('Mpesa transaction status query sent.');

        return self::SUCCESS;
    }
}