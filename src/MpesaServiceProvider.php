<?php

declare(strict_types=1);

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

    /**
     * Publish or configure the Mpesa package configuration.
     */
    public function boot(): void
    {
        if (Str::contains($this->app->version(), 'Lumen')) {
            $this->app->configure('mpesa');
        } else {
            $this->publishes([
                __DIR__.'/../config/mpesa.php' => config_path('mpesa.php'),
            ]);
        }
    }

    /**
     * Register the Mpesa SDK with Laravel's service container.
     */
    public function register(): void
    {
        if (!Str::contains($this->app->version(), 'Lumen')) {
            $this->mergeConfigFrom(__DIR__.'/../config/mpesa.php', 'mpesa');
        }

        $this->app->singleton(MpesaSdk::class, fn () => new MpesaSdk());
    }

    /**
     * Return services provided by this deferred provider.
     */
    public function provides(): array
    {
        return [MpesaSdk::class];
    }
}
