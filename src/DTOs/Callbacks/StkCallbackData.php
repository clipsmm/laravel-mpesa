<?php

declare(strict_types=1);

namespace LaravelMpesa\DTOs\Callbacks;

final readonly class StkCallbackData
{
    /**
     * @param array<string, mixed> $metadata
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public string $merchantRequestId,
        public string $checkoutRequestId,
        public int $resultCode,
        public string $resultDescription,
        public array $metadata,
        public array $payload,
    ) {}

    /** @param array<string, mixed> $payload */
    public static function fromPayload(array $payload): self
    {
        $body = $payload['Body'] ?? [];
        $callback = is_array($body) ? ($body['stkCallback'] ?? []) : [];
        $callback = is_array($callback) ? $callback : [];

        return new self(
            merchantRequestId: (string) ($callback['MerchantRequestID'] ?? ''),
            checkoutRequestId: (string) ($callback['CheckoutRequestID'] ?? ''),
            resultCode: (int) ($callback['ResultCode'] ?? -1),
            resultDescription: (string) ($callback['ResultDesc'] ?? ''),
            metadata: self::metadataFromCallback($callback),
            payload: $payload,
        );
    }

    public function succeeded(): bool
    {
        return $this->resultCode === 0;
    }

    public function metadataValue(string $name): mixed
    {
        return $this->metadata[$name] ?? null;
    }

    /**
     * @param array<string, mixed> $callback
     * @return array<string, mixed>
     */
    private static function metadataFromCallback(array $callback): array
    {
        $callbackMetadata = $callback['CallbackMetadata'] ?? [];
        $items = is_array($callbackMetadata) ? ($callbackMetadata['Item'] ?? []) : [];

        if (!is_array($items)) {
            return [];
        }

        $metadata = [];

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item['Name'])) {
                continue;
            }

            $metadata[(string) $item['Name']] = $item['Value'] ?? null;
        }

        return $metadata;
    }
}