<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo Mode Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file controls the demo mode features of the application.
    | When demo mode is enabled, external API calls are bypassed and simulated
    | responses are returned for testing and demonstration purposes.
    |
    */

    'mode' => env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Demo Features
    |--------------------------------------------------------------------------
    |
    | These settings control specific demo features that can be enabled or
    | disabled independently. This allows for fine-grained control over
    | which parts of the system use demo functionality.
    |
    */

    'features' => [
        'instant_deposits'          => env('DEMO_INSTANT_DEPOSITS', true),
        'skip_kyc'                  => env('DEMO_SKIP_KYC', true),
        'mock_banks'                => env('DEMO_MOCK_BANKS', true),
        'fake_blockchain'           => env('DEMO_FAKE_BLOCKCHAIN', true),
        'fixed_exchange_rates'      => env('DEMO_FIXED_EXCHANGE_RATES', true),
        'auto_approve_transactions' => env('DEMO_AUTO_APPROVE_TRANSACTIONS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sandbox Configuration
    |--------------------------------------------------------------------------
    |
    | Sandbox mode uses real external APIs but in test/sandbox environments.
    | This is useful for integration testing with actual payment providers
    | while still using test credentials and fake money.
    |
    */

    'sandbox' => [
        'enabled'           => env('DEMO_SANDBOX_ENABLED', false),
        'stripe_test_mode'  => env('STRIPE_TEST_MODE', true),
        'bank_sandbox_urls' => [
            'paysera'      => env('PAYSERA_SANDBOX_URL', 'https://sandbox.paysera.com'),
            'santander'    => env('SANTANDER_SANDBOX_URL', 'https://sandbox.santander.com'),
            'deutschebank' => env('DEUTSCHEBANK_SANDBOX_URL', 'https://simulator-api.db.com'),
        ],
        'blockchain_testnets' => [
            'bitcoin'  => env('BITCOIN_TESTNET', 'testnet'),
            'ethereum' => env('ETHEREUM_TESTNET', 'sepolia'),
            'polygon'  => env('POLYGON_TESTNET', 'mumbai'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Data Configuration
    |--------------------------------------------------------------------------
    |
    | These settings control the behavior of demo data generation and
    | the values used for simulated transactions and operations.
    |
    */

    'demo_data' => [
        'default_deposit_amount' => env('DEMO_DEFAULT_DEPOSIT_AMOUNT', 10000), // $100.00
        'default_currency'       => env('DEMO_DEFAULT_CURRENCY', 'USD'),
        'processing_delay'       => env('DEMO_PROCESSING_DELAY', 0), // seconds
        'success_rate'           => env('DEMO_SUCCESS_RATE', 100), // percentage
        'exchange_rates'         => [
            'EUR/USD' => 1.10,
            'GBP/USD' => 1.27,
            'USD/EUR' => 0.91,
            'USD/GBP' => 0.79,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Mode Indicators
    |--------------------------------------------------------------------------
    |
    | Visual indicators and messages to show when demo mode is active.
    |
    */

    'indicators' => [
        'show_banner'    => env('DEMO_SHOW_BANNER', true),
        'banner_message' => env('DEMO_BANNER_MESSAGE', 'Demo Mode - No real transactions'),
        'banner_color'   => env('DEMO_BANNER_COLOR', 'warning'),
        'watermark'      => env('DEMO_WATERMARK', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo User Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for demo user accounts and their default configurations.
    |
    */

    'demo_users' => [
        'auto_create'     => env('DEMO_AUTO_CREATE_USERS', true),
        'default_balance' => env('DEMO_DEFAULT_BALANCE', 100000), // $1,000.00
        'kyc_status'      => env('DEMO_KYC_STATUS', 'verified'),
        'account_types'   => ['personal', 'business'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Security settings specific to demo mode to prevent accidental
    | production usage and ensure data isolation.
    |
    */

    'security' => [
        'enforce_demo_database' => env('DEMO_ENFORCE_DB', true),
        'disable_external_apis' => env('DEMO_DISABLE_EXTERNAL_APIS', true),
        'data_retention_days'   => env('DEMO_DATA_RETENTION_DAYS', 7),
        'max_demo_accounts'     => env('DEMO_MAX_ACCOUNTS', 1000),
        'rate_limiting'         => [
            'deposits_per_hour'     => 10,
            'withdrawals_per_hour'  => 5,
            'transactions_per_hour' => 20,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Data Cleanup Settings
    |--------------------------------------------------------------------------
    |
    | Automatic cleanup of demo data to prevent database bloat.
    |
    */

    'cleanup' => [
        'enabled'        => env('DEMO_CLEANUP_ENABLED', true),
        'retention_days' => env('DEMO_CLEANUP_RETENTION_DAYS', 1),
        'time'           => env('DEMO_CLEANUP_TIME', '03:00'),
    ],
];
