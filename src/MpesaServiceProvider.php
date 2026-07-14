<?php

declare(strict_types=1);

namespace LaravelMpesa;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LaravelMpesa\Commands\TransactionStatusCommand;
use LaravelMpesa\Commands\StkPushCommand;

class MpesaServiceProvider extends ServiceProvider
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
            if ((bool) config('mpesa.callbacks.enabled', true)) {
                $this->loadRoutesFrom(__DIR__.'/../routes/callbacks.php');
            }

            $this->publishes([
                __DIR__.'/../config/mpesa.php' => config_path('mpesa.php'),
            ]);

            if ($this->app->runningInConsole()) {
                $this->commands([
                    StkPushCommand::class,
                    TransactionStatusCommand::class,
                ]);
            }
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
}
