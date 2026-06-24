<?php

declare(strict_types=1);

namespace LaravelMpesa\Responses;

/**
 * Response for C2B URL registration requests.
 */
readonly class UrlRegistrationResponse extends MpesaResponse
{
    public function __construct(
        bool $successful,
        array $rawResponse,
        ?string $errorMessage = null,
        public ?string $originatorConversationId = null,
        public ?string $responseCode = null,
        public ?string $responseDescription = null,
    ) {
        parent::__construct($successful, $rawResponse, $errorMessage);
    }

    /**
     * Create a URL registration response from a raw API response.
     */
    public static function fromArray(bool $successful, array $data): self
    {
        return new self(
            successful: $successful,
            rawResponse: $data,
            errorMessage: $successful ? null : ($data['errorMessage'] ?? $data['ResponseDescription'] ?? 'Unknown error'),
            originatorConversationId: $data['OriginatorConversationID'] ?? null,
            responseCode: isset($data['ResponseCode']) ? (string) $data['ResponseCode'] : null,
            responseDescription: $data['ResponseDescription'] ?? null,
        );
    }

    /**
     * Get the originator conversation ID.
     */
    public function getOriginatorConversationId(): ?string
    {
        return $this->originatorConversationId;
    }

    /**
     * Get the response description.
     */
    public function getResponseDescription(): ?string
    {
        return $this->responseDescription;
    }

    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'originator_conversation_id' => $this->originatorConversationId,
            'response_code' => $this->responseCode,
            'response_description' => $this->responseDescription,
        ]);
    }
}
