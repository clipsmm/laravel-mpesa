<?php

declare(strict_types=1);

namespace LaravelMpesa\Http\Controllers\Callbacks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use LaravelMpesa\DTOs\Callbacks\C2bConfirmationData;
use LaravelMpesa\Http\Controllers\Callbacks\Concerns\ChecksCallbackIp;

class ConfirmationCallbackController
{
    use ChecksCallbackIp;

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->requestIpAllowed($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $confirmation = $this->confirmationFromRequest($request);

        $this->dispatchConfiguredEvent('c2b_confirmation_received', $confirmation);

        return $this->response($confirmation);
    }

    protected function confirmationFromRequest(Request $request): C2bConfirmationData
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return C2bConfirmationData::fromPayload($payload);
    }

    protected function response(C2bConfirmationData $confirmation): JsonResponse
    {
        return response()->json([
            'ResultCode' => 0,
            'ResultDesc' => 'Success',
        ]);
    }

    protected function dispatchConfiguredEvent(string $key, C2bConfirmationData $confirmation): void
    {
        $events = config('mpesa.callbacks.events', []);
        $event = is_array($events) ? ($events[$key] ?? null) : null;

        if (! is_string($event) || trim($event) === '' || ! class_exists($event)) {
            return;
        }

        Event::dispatch(new $event($confirmation));
    }
}
