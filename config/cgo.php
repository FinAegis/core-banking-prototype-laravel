<?php

return [

    /*
    |--------------------------------------------------------------------------
    | CGO (Continuous Growth Offering) Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains all configuration options for the CGO investment
    | feature. In production, ensure all payment addresses are properly
    | configured and tested before enabling.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Crypto Payment Addresses
    |--------------------------------------------------------------------------
    |
    | Configure cryptocurrency payment addresses for each supported currency.
    | 
    | IMPORTANT: In production, these should be addresses from a payment
    | processor like Coinbase Commerce or BitPay that can handle automatic
    | payment detection and confirmation.
    |
    | For testing, use testnet addresses:
    | - Bitcoin Testnet: addresses starting with 'tb1', 'm', or 'n'
    | - Ethereum Testnet: regular format but on Goerli/Sepolia network
    |
    */
    
    'crypto_addresses' => [
        'btc' => env('CGO_BTC_ADDRESS', ''),
        'eth' => env('CGO_ETH_ADDRESS', ''),
        'usdt' => env('CGO_USDT_ADDRESS', ''),
        'usdc' => env('CGO_USDC_ADDRESS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Production Crypto Enable Flag
    |--------------------------------------------------------------------------
    |
    | This flag must be explicitly set to true to enable crypto payments
    | in production. This prevents accidental enablement before proper
    | payment processor integration is complete.
    |
    */
    
    'production_crypto_enabled' => env('CGO_PRODUCTION_CRYPTO_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Bank Transfer Configuration
    |--------------------------------------------------------------------------
    |
    | Configure bank account details for wire transfer payments.
    |
    */
    
    'bank_details' => [
        'bank_name' => env('CGO_BANK_NAME', 'Example Bank Ltd.'),
        'account_name' => env('CGO_BANK_ACCOUNT_NAME', 'FinAegis CGO Holdings'),
        'account_number' => env('CGO_BANK_ACCOUNT_NUMBER', ''),
        'routing_number' => env('CGO_BANK_ROUTING_NUMBER', ''),
        'swift_code' => env('CGO_BANK_SWIFT_CODE', ''),
        'iban' => env('CGO_BANK_IBAN', ''),
        'address' => env('CGO_BANK_ADDRESS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Processing
    |--------------------------------------------------------------------------
    |
    | Configuration for payment processors and gateways.
    |
    */
    
    'payment_processors' => [
        'stripe' => [
            'enabled' => env('CGO_STRIPE_ENABLED', false),
            'public_key' => env('STRIPE_KEY'),
            'secret_key' => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        
        'coinbase_commerce' => [
            'enabled' => env('CGO_COINBASE_COMMERCE_ENABLED', false),
            'api_key' => env('COINBASE_COMMERCE_API_KEY'),
            'webhook_secret' => env('COINBASE_COMMERCE_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Investment Limits
    |--------------------------------------------------------------------------
    |
    | Configure minimum and maximum investment amounts and ownership limits.
    |
    */
    
    'limits' => [
        'minimum_investment' => env('CGO_MINIMUM_INVESTMENT', 100),
        'maximum_investment' => env('CGO_MAXIMUM_INVESTMENT', 1000000),
        'maximum_ownership_percentage' => env('CGO_MAX_OWNERSHIP_PERCENTAGE', 1.0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Investment Tiers
    |--------------------------------------------------------------------------
    |
    | Define investment tiers and their thresholds.
    |
    */
    
    'tiers' => [
        'bronze' => ['min' => 0, 'max' => 999],
        'silver' => ['min' => 1000, 'max' => 9999],
        'gold' => ['min' => 10000, 'max' => PHP_INT_MAX],
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for KYC/AML compliance requirements.
    |
    */
    
    'compliance' => [
        'kyc_required' => env('CGO_KYC_REQUIRED', true),
        'kyc_threshold' => env('CGO_KYC_THRESHOLD', 1000), // Amount that triggers KYC
        'aml_screening_enabled' => env('CGO_AML_SCREENING_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notification Settings
    |--------------------------------------------------------------------------
    |
    | Configure email notifications for investment events.
    |
    */
    
    'notifications' => [
        'send_investment_confirmation' => env('CGO_SEND_INVESTMENT_CONFIRMATION', true),
        'send_payment_received' => env('CGO_SEND_PAYMENT_RECEIVED', true),
        'send_admin_alerts' => env('CGO_SEND_ADMIN_ALERTS', true),
        'admin_email' => env('CGO_ADMIN_EMAIL', 'info@finaegis.org'),
    ],

];