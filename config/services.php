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
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mailchimp' => [
        'api_key' => env('MAILCHIMP_API_KEY'),
        'list_id' => env('MAILCHIMP_LIST_ID'),
    ],

    'ai' => [
        'llm_provider'       => env('AI_LLM_PROVIDER', 'openai'),
        'vector_db_provider' => env('AI_VECTOR_DB_PROVIDER', 'pinecone'),
        'auto_create_index'  => env('AI_AUTO_CREATE_INDEX', false),
    ],

    'openai' => [
        'api_key'     => env('OPENAI_API_KEY'),
        'model'       => env('OPENAI_MODEL', 'gpt-4'),
        'temperature' => env('OPENAI_TEMPERATURE', 0.7),
        'max_tokens'  => env('OPENAI_MAX_TOKENS', 2000),
    ],

    'claude' => [
        'api_key'     => env('CLAUDE_API_KEY'),
        'model'       => env('CLAUDE_MODEL', 'claude-3-opus-20240229'),
        'temperature' => env('CLAUDE_TEMPERATURE', 0.7),
        'max_tokens'  => env('CLAUDE_MAX_TOKENS', 4000),
    ],

    'pinecone' => [
        'api_key'     => env('PINECONE_API_KEY'),
        'environment' => env('PINECONE_ENVIRONMENT', 'us-east-1'),
        'index_name'  => env('PINECONE_INDEX_NAME', 'finaegis-ai'),
        'index_host'  => env('PINECONE_INDEX_HOST'),
    ],

    'coinbase_commerce' => [
        'api_key'        => env('COINBASE_COMMERCE_API_KEY'),
        'webhook_secret' => env('COINBASE_COMMERCE_WEBHOOK_SECRET'),
    ],

    'stripe' => [
        'key'                   => env('STRIPE_KEY'),
        'secret'                => env('STRIPE_SECRET'),
        'webhook_secret'        => env('STRIPE_WEBHOOK_SECRET'),
        'kyc_webhook_secret'    => env('STRIPE_KYC_WEBHOOK_SECRET'),
        'bridge_webhook_secret' => env('STRIPE_BRIDGE_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth Services Configuration
    |--------------------------------------------------------------------------
    */

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI', '/api/auth/social/google/callback'),
    ],

    'facebook' => [
        'client_id'     => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect'      => env('FACEBOOK_REDIRECT_URI', '/api/auth/social/facebook/callback'),
    ],

    'github' => [
        'client_id'     => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect'      => env('GITHUB_REDIRECT_URI', '/api/auth/social/github/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bank Services Configuration
    |--------------------------------------------------------------------------
    */

    'banks' => [
        'paysera' => [
            'enabled'       => env('BANK_PAYSERA_ENABLED', false),
            'client_id'     => env('BANK_PAYSERA_CLIENT_ID'),
            'client_secret' => env('BANK_PAYSERA_CLIENT_SECRET'),
            'base_url'      => env('BANK_PAYSERA_BASE_URL', 'https://bank.paysera.com/rest/v1'),
            'oauth_url'     => env('BANK_PAYSERA_OAUTH_URL', 'https://bank.paysera.com/oauth/v1'),
        ],

        'deutsche' => [
            'enabled'       => env('BANK_DEUTSCHE_ENABLED', false),
            'client_id'     => env('BANK_DEUTSCHE_CLIENT_ID'),
            'client_secret' => env('BANK_DEUTSCHE_CLIENT_SECRET'),
            'base_url'      => env('BANK_DEUTSCHE_BASE_URL', 'https://api.db.com/v2'),
        ],

        'santander' => [
            'enabled'       => env('BANK_SANTANDER_ENABLED', false),
            'client_id'     => env('BANK_SANTANDER_CLIENT_ID'),
            'client_secret' => env('BANK_SANTANDER_CLIENT_SECRET'),
            'base_url'      => env('BANK_SANTANDER_BASE_URL', 'https://api.santander.com/v2'),
        ],

        'revolut' => [
            'enabled'       => env('BANK_REVOLUT_ENABLED', false),
            'client_id'     => env('BANK_REVOLUT_CLIENT_ID'),
            'client_secret' => env('BANK_REVOLUT_CLIENT_SECRET'),
            'base_url'      => env('BANK_REVOLUT_BASE_URL', 'https://api.revolut.com/v1'),
        ],

        'wise' => [
            'enabled'  => env('BANK_WISE_ENABLED', false),
            'api_key'  => env('BANK_WISE_API_KEY'),
            'base_url' => env('BANK_WISE_BASE_URL', 'https://api.wise.com/v2'),
        ],

        'flutterwave' => [
            'enabled'        => env('FLUTTERWAVE_ENABLED', false),
            'secret_key'     => env('FLUTTERWAVE_SECRET_KEY'),
            'public_key'     => env('FLUTTERWAVE_PUBLIC_KEY'),
            'encryption_key' => env('FLUTTERWAVE_ENCRYPTION_KEY'),
            'environment'    => env('FLUTTERWAVE_ENVIRONMENT', 'sandbox'),
            'webhook_secret' => env('FLUTTERWAVE_WEBHOOK_SECRET'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | External Exchange Services Configuration
    |--------------------------------------------------------------------------
    */

    'binance' => [
        'api_key'    => env('BINANCE_API_KEY'),
        'api_secret' => env('BINANCE_API_SECRET'),
        'is_us'      => env('BINANCE_IS_US', false),
        'is_testnet' => env('BINANCE_IS_TESTNET', false),
    ],

    'kraken' => [
        'api_key'    => env('KRAKEN_API_KEY'),
        'api_secret' => env('KRAKEN_API_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging Configuration
    |--------------------------------------------------------------------------
    |
    | Firebase is used for push notifications to mobile devices.
    | The server_key is from Firebase Console > Project Settings > Cloud Messaging.
    | For HTTP v1 API, use the project_id and credentials file instead.
    |
    */

    'smileid' => [
        'partner_id'    => env('SMILEID_PARTNER_ID'),
        'api_key'       => env('SMILEID_API_KEY'),
        'signature_key' => env('SMILEID_SIGNATURE_KEY'),
        'environment'   => env('SMILEID_ENVIRONMENT', 'sandbox'),
        'callback_url'  => env('SMILEID_CALLBACK_URL'),
    ],

    'firebase' => [
        'project_id'  => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS', storage_path('firebase-credentials.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Chainalysis Sanctions Screening Configuration
    |--------------------------------------------------------------------------
    |
    | Chainalysis provides blockchain analytics and sanctions screening.
    | When enabled, it replaces the internal simulated sanctions screening
    | with real-time API checks against Chainalysis sanctions lists.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | GoPlus Security API Configuration
    |--------------------------------------------------------------------------
    |
    | GoPlus provides free blockchain address screening for sanctions, scams,
    | phishing, and malicious contracts. Works without credentials (lower
    | rate limits) or with app_key/app_secret for authenticated access.
    |
    | Get credentials at: https://console.gopluslabs.io
    |
    */

    'goplus' => [
        'app_key'    => env('GOPLUS_APP_KEY'),
        'app_secret' => env('GOPLUS_APP_SECRET'),
    ],

    'chainalysis' => [
        'api_key'        => env('CHAINALYSIS_API_KEY'),
        'base_url'       => env('CHAINALYSIS_BASE_URL', 'https://api.chainalysis.com/api/sanctions/v2'),
        'enabled'        => env('CHAINALYSIS_ENABLED', false),
        'timeout'        => env('CHAINALYSIS_TIMEOUT', 30),
        'retry_attempts' => env('CHAINALYSIS_RETRY_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | Ondato KYC/Identity Verification Configuration
    |--------------------------------------------------------------------------
    |
    | Ondato provides identity verification via mobile SDK flow.
    | Backend creates sessions, mobile SDK captures documents/selfie,
    | Ondato processes and notifies via webhooks.
    |
    */

    'ondato' => [
        'application_id'  => env('ONDATO_APPLICATION_ID'),
        'secret'          => env('ONDATO_SECRET'),
        'setup_id'        => env('ONDATO_SETUP_ID'),
        'sandbox'         => env('ONDATO_SANDBOX', true),
        'webhook_secret'  => env('ONDATO_WEBHOOK_SECRET'),
        'kyc_api_url'     => env('ONDATO_KYC_API_URL', 'https://sandbox-kycapi.ondato.com'),
        'verifid_api_url' => env('ONDATO_VERIFID_API_URL', 'https://verifid.ondato.com'),
    ],

    'helius' => [
        'api_key'        => env('HELIUS_API_KEY'),
        'webhook_id'     => env('HELIUS_WEBHOOK_ID'),
        'webhook_secret' => env('HELIUS_WEBHOOK_SECRET'),
        'rpc_url'        => env('SOLANA_RPC_URL', 'https://api.mainnet-beta.solana.com'),
    ],

    'alchemy' => [
        'notify_token' => env('ALCHEMY_NOTIFY_TOKEN'),
    ],

];
