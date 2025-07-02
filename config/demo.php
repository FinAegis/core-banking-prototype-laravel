<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo Environment Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration specific to the demo environment.
    | The demo environment should behave like production but with certain
    | features enabled or disabled for demonstration purposes.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Production-like Settings
    |--------------------------------------------------------------------------
    |
    | These settings ensure the demo environment behaves like production
    |
    */
    'debug' => false,
    'debug_blacklist' => [
        '_ENV' => [
            'APP_KEY',
            'DB_PASSWORD',
            'REDIS_PASSWORD',
            'MAIL_PASSWORD',
            'PUSHER_APP_KEY',
            'PUSHER_APP_SECRET',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo-specific Features
    |--------------------------------------------------------------------------
    |
    | Settings that are specific to the demo environment
    |
    */
    'features' => [
        // Allow demo users to reset data
        'allow_data_reset' => env('DEMO_ALLOW_DATA_RESET', false),
        
        // Show demo banner
        'show_demo_banner' => env('DEMO_SHOW_BANNER', true),
        
        // Demo user credentials (if applicable)
        'demo_users' => [
            'customer' => [
                'email' => 'demo@finaegis.com',
                'password' => 'demo123',
            ],
            'business' => [
                'email' => 'business@finaegis.com',
                'password' => 'demo123',
            ],
            'admin' => [
                'email' => 'admin@finaegis.com',
                'password' => 'demo123',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Demo environment may have different rate limits
    |
    */
    'rate_limits' => [
        'api' => env('DEMO_API_RATE_LIMIT', 60),
        'transactions' => env('DEMO_TRANSACTION_RATE_LIMIT', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Restrictions
    |--------------------------------------------------------------------------
    |
    | Certain features might be restricted in demo
    |
    */
    'restrictions' => [
        // Maximum transaction amount in cents
        'max_transaction_amount' => env('DEMO_MAX_TRANSACTION', 100000), // €1,000
        
        // Maximum accounts per user
        'max_accounts_per_user' => env('DEMO_MAX_ACCOUNTS', 5),
        
        // Disable real bank connections
        'disable_real_banks' => env('DEMO_DISABLE_REAL_BANKS', true),
    ],
];