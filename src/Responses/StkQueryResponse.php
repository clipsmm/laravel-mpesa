<?php

declare(strict_types=1);

namespace LaravelMpesa\Responses;

/**
 * Response for STK Query requests to check payment status.
 */
readonly class StkQueryResponse extends MpesaResponse
{
    public function __construct(
        bool $successful,
        array $rawResponse,
        ?string $errorMessage = null,
        public ?string $merchantRequestId = null,
        public ?string $checkoutRequestId = null,
        public ?string $responseCode = null,
        public ?string $responseDescription = null,
        public ?string $resultCode = null,
        public ?string $resultDesc = null,
    ) {
        parent::__construct($successful, $rawResponse, $errorMessage);
    }

    /**
     * Create an STK Query response from a raw API response.
     */
    public static function fromArray(bool $successful, array $data): self
    {
        return new self(
            successful: $successful,
            rawResponse: $data,
            errorMessage: $successful ? null : ($data['errorMessage'] ?? $data['ResponseDescription'] ?? 'Unknown error'),
            merchantRequestId: $data['MerchantRequestID'] ?? null,
            checkoutRequestId: $data['CheckoutRequestID'] ?? null,
            responseCode: isset($data['ResponseCode']) ? (string) $data['ResponseCode'] : null,
            responseDescription: $data['ResponseDescription'] ?? null,
            resultCode: isset($data['ResultCode']) ? (string) $data['ResultCode'] : null,
            resultDesc: $data['ResultDesc'] ?? null,
        );
    }

    /**
     * Check if the payment was successful.
     */
    public function isPaymentSuccessful(): bool
    {
        return $this->resultCode === '0';
    }

    /**
     * Check if the payment is still pending.
     */
    public function isPaymentPending(): bool
    {
        // 1032 is the code for pending/timeout
        return in_array($this->resultCode, ['1032', '1037'], true);
    }

    /**
     * Check if the payment was cancelled or failed.
     */
    public function isPaymentFailed(): bool
    {
        return $this->resultCode !== null 
            && $this->resultCode !== '0' 
            && !$this->isPaymentPending();
    }

    /**
     * Get the result description from M-Pesa.
     */
    public function getResultDescription(): ?string
    {
        return $this->resultDesc;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'merchant_request_id' => $this->merchantRequestId,
            'checkout_request_id' => $this->checkoutRequestId,
            'response_code' => $this->responseCode,
            'response_description' => $this->responseDescription,
            'result_code' => $this->resultCode,
            'result_desc' => $this->resultDesc,
            'payment_successful' => $this->isPaymentSuccessful(),
            'payment_pending' => $this->isPaymentPending(),
            'payment_failed' => $this->isPaymentFailed(),
        ]);
    }
}
