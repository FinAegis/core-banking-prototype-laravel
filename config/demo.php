<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Demo Environment Features
    |--------------------------------------------------------------------------
    |
    | Feature flags for the demo environment (APP_ENV=demo).
    | These control which demo behaviors are enabled.
    |
    */

    'features' => [
        'instant_deposits'     => env('DEMO_INSTANT_DEPOSITS', true),
        'skip_kyc'             => env('DEMO_SKIP_KYC', true),
        'mock_external_apis'   => env('DEMO_MOCK_EXTERNAL_APIS', true),
        'fixed_exchange_rates' => env('DEMO_FIXED_EXCHANGE_RATES', true),
        'auto_approve'         => env('DEMO_AUTO_APPROVE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Indicators
    |--------------------------------------------------------------------------
    |
    | Visual indicators shown when running in demo environment.
    |
    */

    'ui' => [
        'show_banner'    => env('DEMO_SHOW_BANNER', true),
        'banner_text'    => env('DEMO_BANNER_TEXT', 'Demo Environment - No real transactions'),
        'show_watermark' => env('DEMO_SHOW_WATERMARK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Domain-Specific Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for each business domain in demo mode.
    |
    */

    'domains' => [
        'exchange' => [
            'spread_percentage'    => 0.1,
            'liquidity_multiplier' => 10,
            'default_rates'        => [
                'EUR/USD' => 1.10,
                'GBP/USD' => 1.27,
                'GCU/USD' => 1.00,
                'BTC/USD' => 45000.00,
                'ETH/USD' => 2500.00,
            ],
        ],

        'lending' => [
            'auto_approve_threshold' => 10000, // $100.00
            'default_credit_score'   => 750,
            'default_interest_rate'  => 5.5,
            'approval_rate'          => 80, // percentage
        ],

        'stablecoin' => [
            'collateral_ratio'      => 1.5,
            'liquidation_threshold' => 1.2,
            'stability_fee'         => 2.5, // annual percentage
        ],

        'wallet' => [
            'testnets' => [
                'bitcoin'  => 'testnet',
                'ethereum' => 'sepolia',
                'polygon'  => 'mumbai',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Users
    |--------------------------------------------------------------------------
    |
    | Pre-configured demo user accounts.
    |
    */

    'users' => [
        'accounts' => [
            'demo.user@gcu.global'      => 'Regular User',
            'demo.business@gcu.global'  => 'Business User',
            'demo.investor@gcu.global'  => 'Investor',
            'demo.argentina@gcu.global' => 'High-Inflation Country User',
            'demo.nomad@gcu.global'     => 'Digital Nomad',
        ],
        'default_password' => env('DEMO_USER_PASSWORD', 'demo123'),
        'default_balance'  => 100000, // $1,000.00
        'kyc_verified'     => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Operational Limits
    |--------------------------------------------------------------------------
    |
    | Safety limits for demo environment operations.
    |
    */

    'limits' => [
        'max_transaction_amount' => 100000, // $1,000.00
        'max_accounts_per_user'  => 5,
        'max_daily_transactions' => 50,
        'data_retention_days'    => env('DEMO_DATA_RETENTION_DAYS', 7),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | API rate limits specific to demo environment.
    |
    */

    'rate_limits' => [
        'api_per_minute'        => 60,
        'deposits_per_hour'     => 10,
        'withdrawals_per_hour'  => 5,
        'transactions_per_hour' => 20,
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Cleanup
    |--------------------------------------------------------------------------
    |
    | Automatic cleanup of old demo data.
    |
    */

    'cleanup' => [
        'enabled'        => env('DEMO_CLEANUP_ENABLED', true),
        'retention_days' => env('DEMO_CLEANUP_RETENTION_DAYS', 1),
        'schedule_time'  => '03:00',
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Mode
    |--------------------------------------------------------------------------
    |
    | Use real sandbox APIs instead of mocks (for integration testing).
    |
    */

    'sandbox' => [
        'enabled' => env('SANDBOX_MODE', false),
        'apis'    => [
            'stripe'     => env('STRIPE_SANDBOX_URL', 'https://api.stripe.com'),
            'paysera'    => env('PAYSERA_SANDBOX_URL', 'https://sandbox.paysera.com'),
            'santander'  => env('SANTANDER_SANDBOX_URL', 'https://sandbox.santander.com'),
            'blockchain' => env('BLOCKCHAIN_SANDBOX_URL', 'https://testnet.blockchain.info'),
        ],
    ],
];
