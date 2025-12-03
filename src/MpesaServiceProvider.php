<?php

namespace LaravelMpesa;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class MpesaServiceProvider extends ServiceProvider implements DeferrableProvider
{

    /**
     * Package name.
     *
     * @var string
     */
    protected $package = '@clipsmm/laravel-mpesa';

    public function boot()
    {
        if (Str::contains($this->app->version(), 'Lumen')) {
            $this->app->configure('mpesa');
        } else {
            $this->publishes([
                __DIR__.'/../config/mpesa.php' => config_path('mpesa.php'),
            ]);
        }
    }

    public function register()
    {
        if (!Str::contains($this->app->version(), 'Lumen')) {
            $this->mergeConfigFrom(__DIR__.'/../config/mpesa.php', 'mpesa');
        }

        $this->app->bind('\LaravelMpesa\Facade\Mpesa', function () {
            return $this->app->make(MpesaSdk::class);
        });
    }

    public function provides()
    {
        return ['\LaravelMpesa\Facade\Mpesa'];
    }
}
