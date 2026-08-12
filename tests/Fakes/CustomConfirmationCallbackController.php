<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Fakes;

use Illuminate\Http\JsonResponse;
use LaravelMpesa\DTOs\Callbacks\C2bConfirmationData;
use LaravelMpesa\Http\Controllers\Callbacks\ConfirmationCallbackController;

final class CustomConfirmationCallbackController extends ConfirmationCallbackController
{
    protected function response(C2bConfirmationData $confirmation): JsonResponse
    {
        return response()->json([
            'ResultCode' => 0,
            'customController' => true,
            'transId' => $confirmation->transId,
        ]);
    }
}
