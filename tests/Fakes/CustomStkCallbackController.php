<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests\Fakes;

use Illuminate\Http\JsonResponse;
use LaravelMpesa\DTOs\Callbacks\StkCallbackData;
use LaravelMpesa\Http\Controllers\Callbacks\StkCallbackController;

final class CustomStkCallbackController extends StkCallbackController
{
    protected function response(StkCallbackData $callback): JsonResponse
    {
        return response()->json([
            'accepted' => true,
            'customController' => true,
            'checkoutRequestId' => $callback->checkoutRequestId,
        ]);
    }
}