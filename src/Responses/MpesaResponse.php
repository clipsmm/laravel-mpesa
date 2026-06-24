<?php

declare(strict_types=1);

namespace LaravelMpesa\Responses;

use JsonSerializable;

/**
 * Base response class for M-Pesa API responses.
 */
readonly class MpesaResponse implements JsonSerializable
{
    public function __construct(
        public bool $successful,
        public array $rawResponse,
        public ?string $errorMessage = null,
    ) {}

    /**
     * Check if the request was successful.
     */
    public function isSuccessful(): bool
    {
        return $this->successful;
    }

    /**
     * Check if the request failed.
     */
    public function isFailed(): bool
    {
        return !$this->successful;
    }

    /**
     * Get the error message if the request failed.
     */
    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * Get the raw response array.
     */
    public function getRawResponse(): array
    {
        return $this->rawResponse;
    }

    public function jsonSerialize(): array
    {
        return [
            'successful' => $this->successful,
            'raw_response' => $this->rawResponse,
            'error_message' => $this->errorMessage,
        ];
    }
}
