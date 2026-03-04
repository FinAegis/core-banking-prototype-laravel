<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Default Ramp Provider
    |--------------------------------------------------------------------------
    */

    'default_provider' => env('RAMP_PROVIDER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    */

    'providers' => [
        'mock' => [
            'driver'  => 'mock',
            'enabled' => true,
        ],

        'moonpay' => [
            'driver'      => 'moonpay',
            'api_key'     => env('MOONPAY_API_KEY'),
            'secret_key'  => env('MOONPAY_SECRET_KEY'),
            'webhook_key' => env('MOONPAY_WEBHOOK_KEY'),
            'base_url'    => env('MOONPAY_BASE_URL', 'https://api.moonpay.com'),
            'enabled'     => (bool) env('MOONPAY_ENABLED', false),
        ],

        'transak' => [
            'driver'      => 'transak',
            'api_key'     => env('TRANSAK_API_KEY'),
            'api_secret'  => env('TRANSAK_API_SECRET'),
            'webhook_key' => env('TRANSAK_WEBHOOK_KEY'),
            'base_url'    => env('TRANSAK_BASE_URL', 'https://api.transak.com'),
            'enabled'     => (bool) env('TRANSAK_ENABLED', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Supported Currencies
    |--------------------------------------------------------------------------
    */

    'supported_fiat'   => ['USD', 'EUR', 'GBP'],
    'supported_crypto' => ['USDC', 'USDT', 'ETH', 'BTC'],

    /*
    |--------------------------------------------------------------------------
    | Transaction Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        'min_fiat_amount' => (float) env('RAMP_MIN_AMOUNT', 10.00),
        'max_fiat_amount' => (float) env('RAMP_MAX_AMOUNT', 10000.00),
        'daily_limit'     => (float) env('RAMP_DAILY_LIMIT', 50000.00),
    ],
];
