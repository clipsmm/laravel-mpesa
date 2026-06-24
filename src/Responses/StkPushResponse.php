<?php

declare(strict_types=1);

namespace LaravelMpesa\Responses;

/**
 * Response for STK Push (Lipa Na M-Pesa) requests.
 */
readonly class StkPushResponse extends MpesaResponse
{
    public function __construct(
        bool $successful,
        array $rawResponse,
        ?string $errorMessage = null,
        public ?string $merchantRequestId = null,
        public ?string $checkoutRequestId = null,
        public ?string $responseCode = null,
        public ?string $responseDescription = null,
        public ?string $customerMessage = null,
    ) {
        parent::__construct($successful, $rawResponse, $errorMessage);
    }

    /**
     * Create an STK Push response from a raw API response.
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
            customerMessage: $data['CustomerMessage'] ?? null,
        );
    }

    /**
     * Get the merchant request ID for tracking.
     */
    public function getMerchantRequestId(): ?string
    {
        return $this->merchantRequestId;
    }

    /**
     * Get the checkout request ID for querying payment status.
     */
    public function getCheckoutRequestId(): ?string
    {
        return $this->checkoutRequestId;
    }

    /**
     * Get the response code from M-Pesa.
     */
    public function getResponseCode(): ?string
    {
        return $this->responseCode;
    }

    /**
     * Get the response description.
     */
    public function getResponseDescription(): ?string
    {
        return $this->responseDescription;
    }

    /**
     * Get the customer-facing message.
     */
    public function getCustomerMessage(): ?string
    {
        return $this->customerMessage;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'merchant_request_id' => $this->merchantRequestId,
            'checkout_request_id' => $this->checkoutRequestId,
            'response_code' => $this->responseCode,
            'response_description' => $this->responseDescription,
            'customer_message' => $this->customerMessage,
        ]);
    }
}
