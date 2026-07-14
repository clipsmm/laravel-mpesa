<?php

use LaravelMpesa\Events\Callbacks\StkCallbackFailed;
use LaravelMpesa\Events\Callbacks\StkCallbackReceived;
use LaravelMpesa\Events\Callbacks\StkCallbackSucceeded;
use LaravelMpesa\Http\Controllers\Callbacks\StkCallbackController;

$defaultCallbackAllowedIps = app()->environment(['local', 'testing'])
    ? ['*']
    : [
        '196.201.212.69',
        '196.201.212.74',
        '196.201.212.127',
        '196.201.212.129',
        '196.201.212.136',
        '196.201.212.138',
        '196.201.213.44',
        '196.201.213.114',
        '196.201.214.200',
        '196.201.214.206',
        '196.201.214.207',
        '196.201.214.208',
    ];

return [
    'default' => env('MPESA_DEFAULT_APP', 'c2b'),
    'connect_timeout' => (int) env('MPESA_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('MPESA_TIMEOUT', 15),
    'allow_insecure_callbacks' => (bool) env('MPESA_ALLOW_INSECURE_CALLBACKS', false),
    'stk_callback_url' => env('MPESA_STK_CALLBACK_URL'),
    'transaction_status_result_url' => env('MPESA_TRANSACTION_STATUS_RESULT_URL'),
    'transaction_status_timeout_url' => env('MPESA_TRANSACTION_STATUS_TIMEOUT_URL'),
    'callbacks' => [
        'enabled' => (bool) env('MPESA_CALLBACK_ROUTES_ENABLED', true),
        'middleware' => ['api'],
        'path_prefix' => '/signal/ingress',
        'allowed_ips' => array_values(array_filter(array_map(
            static fn(string $ip): string => trim($ip),
            explode(',', (string) env('MPESA_CALLBACK_ALLOWED_IPS', implode(',', $defaultCallbackAllowedIps))),
        ))),
        'routes' => [
            'stk' => null,
        ],
        'controllers' => [
            'stk' => StkCallbackController::class,
        ],
        'events' => [
            'stk_received' => StkCallbackReceived::class,
            'stk_succeeded' => StkCallbackSucceeded::class,
            'stk_failed' => StkCallbackFailed::class,
        ],
    ],
    'apps' => [
        'c2b' => [
            'status' => env('MPESA_API_STATUS'),
            'consumer_key' => env('MPESA_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
            'shortcode' => env('MPESA_SHORTCODE'),
            'passkey' => env('MPESA_PASSKEY'),
            'initiator_name' => env('MPESA_INITIATOR_NAME'),
            'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),
        ],
        'b2c' => [
            'status' => env('MPESA_API_STATUS'),
            'consumer_key' => env('MPESA_B2C_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_B2C_CONSUMER_SECRET'),
            'shortcode' => env('MPESA_B2C_SHORTCODE'),
            'passkey' => env('MPESA_B2C_PASSKEY'),
            'initiator_name' => env('MPESA_B2C_INITIATOR_NAME'),
            'initiator_password' => env('MPESA_B2C_INITIATOR_PASSWORD'),
        ],
        'b2b' => [
            'status' => env('MPESA_API_STATUS'),
            'consumer_key' => env('MPESA_CONSUMER_KEY'),
            'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
            'shortcode' => env('MPESA_SHORTCODE'),
            'passkey' => env('MPESA_PASSKEY'),
            'initiator_name' => env('MPESA_INITIATOR_NAME'),
            'initiator_password' => env('MPESA_INITIATOR_PASSWORD'),
        ]
    ],
];
