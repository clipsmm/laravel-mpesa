<?php

namespace LaravelMpesa;

use Illuminate\Support\Arr;

/**
 * Class MpesaSdk
 * @package LaravelMpesa
 */
class MpesaSdk
{
    protected $app;
    protected $config = [];


    public function __construct($app = null, array $opts = [])
    {
        $app  =  $app ? : config('mpesa.default');
        $this->app  = $app;
        $this->config =  config("mpesa.apps.{$app}");
    }

    /**
     * Get MpesaFacade Manager Instance
     *
     * @param string|null $app
     * @return RequestManager
     */
    public static function instance(string $app = null): RequestManager
    {
        $app  =  $app ?? config('mpesa.default');
        $baseConfig =  Arr::except(config("mpesa"), ['apps']);
        $instanceConfig = config("mpesa.apps.{$app}");
        $config = array_merge($baseConfig, $instanceConfig);

        return (new RequestManager($config));
    }
}
