<?php

declare(strict_types=1);

namespace LaravelMpesa\Traits;

use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Trait for validating M-Pesa callback payloads.
 */
trait ValidatesMpesaCallback
{
    /**
     * Validate and parse an STK Push callback payload.
     *
     * @throws InvalidArgumentException
     */
    protected function validateStkCallback(Request $request): array
    {
        $data = $request->all();

        if (!isset($data['Body']['stkCallback'])) {
            throw new InvalidArgumentException('Invalid STK callback payload structure.');
        }

        $callback = $data['Body']['stkCallback'];

        if (!isset($callback['MerchantRequestID'], $callback['CheckoutRequestID'], $callback['ResultCode'])) {
            throw new InvalidArgumentException('Missing required STK callback fields.');
        }

        return [
            'merchant_request_id' => $callback['MerchantRequestID'],
            'checkout_request_id' => $callback['CheckoutRequestID'],
            'result_code' => (int) $callback['ResultCode'],
            'result_desc' => $callback['ResultDesc'] ?? null,
            'callback_metadata' => $this->parseCallbackMetadata($callback['CallbackMetadata'] ?? []),
        ];
    }

    /**
     * Validate and parse a C2B confirmation callback payload.
     *
     * @throws InvalidArgumentException
     */
    protected function validateC2bCallback(Request $request): array
    {
        $data = $request->all();

        $required = ['TransactionType', 'TransID', 'TransTime', 'TransAmount', 'BusinessShortCode', 'BillRefNumber', 'MSISDN'];

        foreach ($required as $field) {
            if (!isset($data[$field])) {
                throw new InvalidArgumentException("Missing required C2B callback field: {$field}");
            }
        }

        return [
            'transaction_type' => $data['TransactionType'],
            'trans_id' => $data['TransID'],
            'trans_time' => $data['TransTime'],
            'trans_amount' => (float) $data['TransAmount'],
            'business_short_code' => $data['BusinessShortCode'],
            'bill_ref_number' => $data['BillRefNumber'],
            'invoice_number' => $data['InvoiceNumber'] ?? null,
            'org_account_balance' => isset($data['OrgAccountBalance']) ? (float) $data['OrgAccountBalance'] : null,
            'third_party_trans_id' => $data['ThirdPartyTransID'] ?? null,
            'msisdn' => $data['MSISDN'],
            'first_name' => $data['FirstName'] ?? null,
            'middle_name' => $data['MiddleName'] ?? null,
            'last_name' => $data['LastName'] ?? null,
        ];
    }

    /**
     * Check if an STK callback indicates a successful payment.
     */
    protected function isStkCallbackSuccessful(array $callback): bool
    {
        return isset($callback['result_code']) && $callback['result_code'] === 0;
    }

    /**
     * Extract amount and phone number from STK callback metadata.
     */
    protected function extractStkPaymentDetails(array $callback): array
    {
        $metadata = $callback['callback_metadata'] ?? [];

        return [
            'amount' => $metadata['Amount'] ?? null,
            'mpesa_receipt_number' => $metadata['MpesaReceiptNumber'] ?? null,
            'transaction_date' => $metadata['TransactionDate'] ?? null,
            'phone_number' => $metadata['PhoneNumber'] ?? null,
        ];
    }

    /**
     * Parse callback metadata into a more usable format.
     */
    private function parseCallbackMetadata(array $metadata): array
    {
        if (!isset($metadata['Item']) || !is_array($metadata['Item'])) {
            return [];
        }

        $parsed = [];

        foreach ($metadata['Item'] as $item) {
            if (isset($item['Name'], $item['Value'])) {
                $parsed[$item['Name']] = $item['Value'];
            }
        }

        return $parsed;
    }
}
