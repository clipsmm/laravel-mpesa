<?php

declare(strict_types=1);

namespace LaravelMpesa\Tests;

use LaravelMpesa\MpesaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [MpesaServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('mpesa.default', 'c2b');
        $app['config']->set('mpesa.connect_timeout', 2);
        $app['config']->set('mpesa.timeout', 5);
        $app['config']->set('mpesa.allow_insecure_callbacks', false);
        $app['config']->set('mpesa.apps.c2b', [
            'status' => 'sandbox',
            'consumer_key' => 'test-key',
            'consumer_secret' => 'test-secret',
            'shortcode' => '174379',
            'passkey' => 'test-passkey',
            'initiator_name' => 'test-initiator',
            'initiator_password' => 'test-security-credential',
        ]);
    }
}
