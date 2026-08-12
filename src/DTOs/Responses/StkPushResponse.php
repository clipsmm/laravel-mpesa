<?php

declare(strict_types=1);

namespace LaravelMpesa\DTOs\Responses;

final readonly class StkPushResponse
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public ?string $merchantRequestId,
        public ?string $checkoutRequestId,
        public ?string $responseCode,
        public ?string $responseDescription,
        public ?string $customerMessage,
        public array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            merchantRequestId: ResponseField::string($payload['MerchantRequestID'] ?? null),
            checkoutRequestId: ResponseField::string($payload['CheckoutRequestID'] ?? null),
            responseCode: ResponseField::string($payload['ResponseCode'] ?? null),
            responseDescription: ResponseField::string($payload['ResponseDescription'] ?? null),
            customerMessage: ResponseField::string($payload['CustomerMessage'] ?? null),
            payload: $payload,
        );
    }

    /**
     * Whether Daraja accepted the STK push request for processing.
     * This reflects the synchronous ResponseCode only; the final payment
     * outcome still arrives via the STK callback.
     */
    public function accepted(): bool
    {
        return $this->responseCode === '0';
    }
}
