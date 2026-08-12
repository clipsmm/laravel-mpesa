<?php

declare(strict_types=1);

namespace LaravelMpesa\DTOs\Callbacks;

final readonly class C2bConfirmationData
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $transactionType,
        public string $transId,
        public string $transTime,
        public float $transAmount,
        public string $businessShortCode,
        public string $billRefNumber,
        public string $invoiceNumber,
        public ?float $orgAccountBalance,
        public string $thirdPartyTransId,
        public string $msisdn,
        public string $firstName,
        public string $middleName,
        public string $lastName,
        public array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        return new self(
            transactionType: (string) ($payload['TransactionType'] ?? ''),
            transId: (string) ($payload['TransID'] ?? ''),
            transTime: (string) ($payload['TransTime'] ?? ''),
            transAmount: (float) ($payload['TransAmount'] ?? 0),
            businessShortCode: (string) ($payload['BusinessShortCode'] ?? ''),
            billRefNumber: (string) ($payload['BillRefNumber'] ?? ''),
            invoiceNumber: (string) ($payload['InvoiceNumber'] ?? ''),
            orgAccountBalance: self::floatOrNull($payload['OrgAccountBalance'] ?? null),
            thirdPartyTransId: (string) ($payload['ThirdPartyTransID'] ?? ''),
            msisdn: (string) ($payload['MSISDN'] ?? ''),
            firstName: (string) ($payload['FirstName'] ?? ''),
            middleName: (string) ($payload['MiddleName'] ?? ''),
            lastName: (string) ($payload['LastName'] ?? ''),
            payload: $payload,
        );
    }

    private static function floatOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
