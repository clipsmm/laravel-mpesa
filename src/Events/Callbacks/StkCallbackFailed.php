<?php

declare(strict_types=1);

namespace LaravelMpesa\Events\Callbacks;

use LaravelMpesa\DTOs\Callbacks\StkCallbackData;

class StkCallbackFailed
{
    public function __construct(
        public readonly StkCallbackData $callback,
    ) {}
}