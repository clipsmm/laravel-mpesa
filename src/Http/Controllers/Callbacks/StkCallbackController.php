<?php

declare(strict_types=1);

namespace LaravelMpesa\Http\Controllers\Callbacks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use LaravelMpesa\DTOs\Callbacks\StkCallbackData;
use LaravelMpesa\Http\Controllers\Callbacks\Concerns\ChecksCallbackIp;

class StkCallbackController
{
    use ChecksCallbackIp;

    public function __invoke(Request $request): JsonResponse
    {
        if (!$this->requestIpAllowed($request)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $callback = $this->callbackFromRequest($request);

        $this->dispatchConfiguredEvent('stk_received', $callback);
        $this->dispatchConfiguredEvent(
            $callback->succeeded() ? 'stk_succeeded' : 'stk_failed',
            $callback,
        );

        return $this->response($callback);
    }

    protected function callbackFromRequest(Request $request): StkCallbackData
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->all();

        return StkCallbackData::fromPayload($payload);
    }

    protected function response(StkCallbackData $callback): JsonResponse
    {
        return response()->json([
            'accepted' => true,
            'checkoutRequestId' => $callback->checkoutRequestId,
            'resultCode' => $callback->resultCode,
        ]);
    }

    protected function dispatchConfiguredEvent(string $key, StkCallbackData $callback): void
    {
        $events = config('mpesa.callbacks.events', []);
        $event = is_array($events) ? ($events[$key] ?? null) : null;

        if (!is_string($event) || trim($event) === '' || !class_exists($event)) {
            return;
        }

        Event::dispatch(new $event($callback));
    }
}