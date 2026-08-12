<?php

declare(strict_types=1);

namespace LaravelMpesa\DTOs\Responses;

final readonly class RegisterUrlResponse
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public ?string $originatorConversationId,
        public ?string $responseDescription,
        public array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromArray(array $payload): self
    {
        return new self(
            originatorConversationId: ResponseField::string(
                $payload['OriginatorConversationID'] ?? $payload['OriginatorCoversationID'] ?? null,
            ),
            responseDescription: ResponseField::string($payload['ResponseDescription'] ?? null),
            payload: $payload,
        );
    }
}
