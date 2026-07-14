<?php

declare(strict_types=1);

namespace LaravelMpesa\Commands;

use Illuminate\Console\Command;
use LaravelMpesa\MpesaSdk;
use Throwable;

class StkPushCommand extends Command
{
    protected $signature = 'mpesa:stk {--phone= : Customer phone number, for example 254712345678 or 0712345678} {--amount= : Amount to charge} {--ref= : Account reference} {--callback= : STK callback URL} {--description=Description : Payment description}';

    protected $description = 'Send an Mpesa STK push request.';

    public function handle(): int
    {
        $phone = $this->normalizePhone((string) $this->option('phone'));
        $amount = $this->parseAmount($this->option('amount'));
        $ref = trim((string) $this->option('ref'));
        $description = trim((string) $this->option('description')) ?: 'Description';
        $callbackUrl = trim((string) ($this->option('callback') ?: config('mpesa.stk_callback_url', '')));

        if ($phone === null) {
            $this->error('The --phone option must be a Kenyan Safaricom number such as 254712345678 or 0712345678.');

            return self::FAILURE;
        }

        if ($amount === null) {
            $this->error('The --amount option must be a positive whole number.');

            return self::FAILURE;
        }

        if ($ref === '') {
            $this->error('The --ref option is required.');

            return self::FAILURE;
        }

        if ($callbackUrl === '') {
            $this->error('The --callback option or MPESA_STK_CALLBACK_URL must be configured before sending an STK push.');

            return self::FAILURE;
        }

        try {
            [$successful, $response] = MpesaSdk::instance()->stkPush(
                $phone,
                $amount,
                $ref,
                $description,
                $callbackUrl
            );
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->line(json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]');

        if (!$successful) {
            $this->error('Mpesa STK push request failed.');

            return self::FAILURE;
        }

        $this->info('Mpesa STK push request sent.');

        return self::SUCCESS;
    }

    private function normalizePhone(string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', $phone) ?? '';

        if (preg_match('/^2547\d{8}$/', $phone) === 1) {
            return $phone;
        }

        if (preg_match('/^07\d{8}$/', $phone) === 1) {
            return '254'.substr($phone, 1);
        }

        if (preg_match('/^7\d{8}$/', $phone) === 1) {
            return '254'.$phone;
        }

        return null;
    }

    private function parseAmount(mixed $amount): ?int
    {
        if (!is_scalar($amount) || !preg_match('/^[1-9]\d*$/', trim((string) $amount))) {
            return null;
        }

        return (int) $amount;
    }
}