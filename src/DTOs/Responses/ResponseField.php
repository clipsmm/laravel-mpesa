<?php

declare(strict_types=1);

namespace LaravelMpesa\DTOs\Responses;

final class ResponseField
{
    public static function string(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
