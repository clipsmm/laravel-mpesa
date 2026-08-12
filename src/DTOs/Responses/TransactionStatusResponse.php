<?php

declare(strict_types=1);

namespace LaravelMpesa\DTOs\Responses;

final readonly class TransactionStatusResponse
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public ?string $originatorConversationId,
        public ?string $conversationId,
        public ?string $responseCode,
        public ?string $responseDescription,
        public array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            originatorConversationId: ResponseField::string($payload['OriginatorConversationID'] ?? null),
            conversationId: ResponseField::string($payload['ConversationID'] ?? null),
            responseCode: ResponseField::string($payload['ResponseCode'] ?? null),
            responseDescription: ResponseField::string($payload['ResponseDescription'] ?? null),
            payload: $payload,
        );
    }

    /**
     * Whether Daraja accepted the query for processing. The actual
     * transaction status still arrives asynchronously via the result URL.
     */
    public function accepted(): bool
    {
        return $this->responseCode === '0';
    }
}
