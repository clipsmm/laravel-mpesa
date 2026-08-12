<?php

declare(strict_types=1);

namespace LaravelMpesa\Http\Controllers\Callbacks\Concerns;

use Illuminate\Http\Request;

trait ChecksCallbackIp
{
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
}
