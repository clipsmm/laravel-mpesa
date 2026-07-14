<?php

declare(strict_types=1);

namespace LaravelMpesa\Http\Controllers\Callbacks;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use LaravelMpesa\DTOs\Callbacks\StkCallbackData;

class StkCallbackController
{
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

    protected function requestIpAllowed(Request $request): bool
    {
        $allowedIps = config('mpesa.callbacks.allowed_ips', []);

        if (!is_array($allowedIps) || $allowedIps === []) {
            return false;
        }

        $requestIp = (string) $request->ip();

        foreach ($allowedIps as $allowedIp) {
            if (!is_string($allowedIp)) {
                continue;
            }

            $allowedIp = trim($allowedIp);

            if ($allowedIp === '*' || $allowedIp === $requestIp || $this->ipMatchesCidr($requestIp, $allowedIp)) {
                return true;
            }
        }

        return false;
    }

    protected function ipMatchesCidr(string $ip, string $cidr): bool
    {
        if (!str_contains($cidr, '/')) {
            return false;
        }

        [$subnet, $bits] = explode('/', $cidr, 2);
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || !ctype_digit($bits)) {
            return false;
        }

        $bitsInt = (int) $bits;

        if ($bitsInt < 0 || $bitsInt > 32) {
            return false;
        }

        $mask = -1 << (32 - $bitsInt);

        return ($ipLong & $mask) === ($subnetLong & $mask);
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