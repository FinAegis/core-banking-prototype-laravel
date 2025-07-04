<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    
    'mailchimp' => [
        'api_key' => env('MAILCHIMP_API_KEY'),
        'list_id' => env('MAILCHIMP_LIST_ID'),
    ],
    
    'coinbase_commerce' => [
        'api_key' => env('COINBASE_COMMERCE_API_KEY'),
        'webhook_secret' => env('COINBASE_COMMERCE_WEBHOOK_SECRET'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | OAuth Services Configuration
    |--------------------------------------------------------------------------
    */
    
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/api/auth/social/google/callback'),
    ],
    
    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect' => env('FACEBOOK_REDIRECT_URI', '/api/auth/social/facebook/callback'),
    ],
    
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', '/api/auth/social/github/callback'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Bank Services Configuration
    |--------------------------------------------------------------------------
    */
    
    'banks' => [
        'paysera' => [
            'enabled' => env('BANK_PAYSERA_ENABLED', true),
            'client_id' => env('BANK_PAYSERA_CLIENT_ID'),
            'client_secret' => env('BANK_PAYSERA_CLIENT_SECRET'),
            'base_url' => env('BANK_PAYSERA_BASE_URL', 'https://bank.paysera.com/rest/v1'),
            'oauth_url' => env('BANK_PAYSERA_OAUTH_URL', 'https://bank.paysera.com/oauth/v1'),
        ],
        
        'deutsche' => [
            'enabled' => env('BANK_DEUTSCHE_ENABLED', true),
            'client_id' => env('BANK_DEUTSCHE_CLIENT_ID'),
            'client_secret' => env('BANK_DEUTSCHE_CLIENT_SECRET'),
            'base_url' => env('BANK_DEUTSCHE_BASE_URL', 'https://api.db.com/v2'),
        ],
        
        'santander' => [
            'enabled' => env('BANK_SANTANDER_ENABLED', true),
            'client_id' => env('BANK_SANTANDER_CLIENT_ID'),
            'client_secret' => env('BANK_SANTANDER_CLIENT_SECRET'),
            'base_url' => env('BANK_SANTANDER_BASE_URL', 'https://api.santander.com/v2'),
        ],
        
        'revolut' => [
            'enabled' => env('BANK_REVOLUT_ENABLED', false),
            'client_id' => env('BANK_REVOLUT_CLIENT_ID'),
            'client_secret' => env('BANK_REVOLUT_CLIENT_SECRET'),
            'base_url' => env('BANK_REVOLUT_BASE_URL', 'https://api.revolut.com/v1'),
        ],
        
        'wise' => [
            'enabled' => env('BANK_WISE_ENABLED', false),
            'api_key' => env('BANK_WISE_API_KEY'),
            'base_url' => env('BANK_WISE_BASE_URL', 'https://api.wise.com/v2'),
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | External Exchange Services Configuration
    |--------------------------------------------------------------------------
    */
    
    'binance' => [
        'api_key' => env('BINANCE_API_KEY'),
        'api_secret' => env('BINANCE_API_SECRET'),
        'is_us' => env('BINANCE_IS_US', false),
        'is_testnet' => env('BINANCE_IS_TESTNET', false),
    ],
    
    'kraken' => [
        'api_key' => env('KRAKEN_API_KEY'),
        'api_secret' => env('KRAKEN_API_SECRET'),
    ],

];
