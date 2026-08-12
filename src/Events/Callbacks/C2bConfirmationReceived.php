<?php

declare(strict_types=1);

namespace LaravelMpesa\Events\Callbacks;

use LaravelMpesa\DTOs\Callbacks\C2bConfirmationData;

class C2bConfirmationReceived
{
    public function __construct(
        public readonly C2bConfirmationData $confirmation,
    ) {}
}
