<?php

declare(strict_types=1);

namespace LaravelMpesa\Events\Callbacks;

use LaravelMpesa\DTOs\Callbacks\StkCallbackData;

class StkCallbackSucceeded
{
    public function __construct(
        public readonly StkCallbackData $callback,
    ) {}
}