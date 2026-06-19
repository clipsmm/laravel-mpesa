<?php

declare(strict_types=1);

namespace LaravelMpesa;

use Illuminate\Support\Arr;
use InvalidArgumentException;

class MpesaSdk
{
    protected string $app;

    protected array $config = [];

    /**
     * Create an SDK instance for a configured Mpesa application.
     */
    public function __construct(?string $app = null, array $opts = [])
    {
        $this->app = $app ?: (string) config('mpesa.default');
        $configured = config("mpesa.apps.{$this->app}");

        if (!is_array($configured)) {
            throw new InvalidArgumentException("Mpesa application [{$this->app}] is not configured.");
        }

        $this->config = array_merge($configured, $opts);
    }

    /**
     * Create a request manager for the selected configured Mpesa application.
     */
    public static function instance(?string $app = null): RequestManager
    {
        $app = $app ?? (string) config('mpesa.default');
        $baseConfig = Arr::except((array) config('mpesa'), ['apps']);
        $instanceConfig = config("mpesa.apps.{$app}");

        if (!is_array($instanceConfig)) {
            throw new InvalidArgumentException("Mpesa application [{$app}] is not configured.");
        }

        return new RequestManager(array_merge($baseConfig, $instanceConfig));
    }
}
