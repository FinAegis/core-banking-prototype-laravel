<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Card Issuance Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the virtual card issuance system for tap-to-pay
    | functionality via Apple Pay and Google Pay.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Card Issuer
    |--------------------------------------------------------------------------
    |
    | The card issuer provider to use. Supported: "demo", "marqeta", "lithic",
    | "stripe_issuing"
    |
    */

    'default_issuer' => env('CARD_ISSUER', 'demo'),

    /*
    |--------------------------------------------------------------------------
    | Issuer Configurations
    |--------------------------------------------------------------------------
    */

    'issuers' => [
        'demo' => [
            'driver' => 'demo',
        ],

        'marqeta' => [
            'driver'             => 'marqeta',
            'base_url'           => env('MARQETA_BASE_URL', 'https://sandbox-api.marqeta.com/v3'),
            'application_token'  => env('MARQETA_APPLICATION_TOKEN'),
            'admin_access_token' => env('MARQETA_ADMIN_ACCESS_TOKEN'),
            'webhook_secret'     => env('MARQETA_WEBHOOK_SECRET'),
            'webhook_username'   => env('MARQETA_WEBHOOK_USERNAME'),
            'webhook_password'   => env('MARQETA_WEBHOOK_PASSWORD'),
        ],

        'lithic' => [
            'driver'         => 'lithic',
            'base_url'       => env('LITHIC_BASE_URL', 'https://sandbox.lithic.com/v1'),
            'api_key'        => env('LITHIC_API_KEY'),
            'webhook_secret' => env('LITHIC_WEBHOOK_SECRET'),
        ],

        'stripe_issuing' => [
            'driver'         => 'stripe_issuing',
            'api_key'        => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_ISSUING_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JIT Funding Configuration
    |--------------------------------------------------------------------------
    |
    | Just-in-Time funding settings for real-time card authorization.
    |
    */

    'jit_funding' => [
        // Maximum latency budget for authorization (milliseconds)
        'latency_budget_ms' => env('CARD_AUTH_LATENCY_BUDGET', 2000),

        // Default stablecoin for funding card transactions
        'default_token' => env('CARD_FUNDING_TOKEN', 'USDC'),

        // Supported tokens for card funding
        'supported_tokens' => ['USDC', 'USDT', 'DAI'],

        // Whether to allow partial approvals
        'allow_partial_approval' => env('CARD_ALLOW_PARTIAL', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Card Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // Maximum cards per user
        'max_cards_per_user' => env('CARD_MAX_PER_USER', 5),

        // Default daily spending limit (in USD cents)
        'default_daily_limit' => env('CARD_DAILY_LIMIT', 500000), // $5,000

        // Default per-transaction limit (in USD cents)
        'default_transaction_limit' => env('CARD_TRANSACTION_LIMIT', 100000), // $1,000
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple Pay / Google Pay Configuration
    |--------------------------------------------------------------------------
    */

    'wallet_provisioning' => [
        'apple_pay' => [
            'enabled'          => env('APPLE_PAY_ENABLED', true),
            'certificate_path' => env('APPLE_PAY_CERTIFICATE_PATH'),
            'key_path'         => env('APPLE_PAY_KEY_PATH'),
            'merchant_id'      => env('APPLE_PAY_MERCHANT_ID'),
        ],

        'google_pay' => [
            'enabled'          => env('GOOGLE_PAY_ENABLED', true),
            'wallet_issuer_id' => env('GOOGLE_PAY_WALLET_ISSUER_ID'),
            'backend_url'      => env('GOOGLE_PAY_BACKEND_URL'),
        ],
    ],
];
