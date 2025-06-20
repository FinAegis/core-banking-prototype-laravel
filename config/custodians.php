<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Custodian
    |--------------------------------------------------------------------------
    |
    | This option controls the default custodian connector that will be used
    | by the application. You can switch to a different custodian by changing
    | this value.
    |
    */
    
    'default' => env('CUSTODIAN_DEFAULT', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | Custodian Connectors
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the custodian connectors for your
    | application. Each custodian has its own configuration options.
    |
    */
    
    'connectors' => [
        
        'mock' => [
            'class' => \App\Domain\Custodian\Connectors\MockBankConnector::class,
            'enabled' => true,
            'name' => 'Mock Bank',
            'description' => 'Mock custodian for testing and development',
        ],
        
        'paysera' => [
            'class' => \App\Domain\Custodian\Connectors\PayseraConnector::class,
            'enabled' => env('PAYSERA_ENABLED', false),
            'name' => 'Paysera',
            'description' => 'Paysera bank integration for EUR and multi-currency accounts',
            'client_id' => env('PAYSERA_CLIENT_ID'),
            'client_secret' => env('PAYSERA_CLIENT_SECRET'),
            'environment' => env('PAYSERA_ENVIRONMENT', 'production'), // production or sandbox
            'webhook_secret' => env('PAYSERA_WEBHOOK_SECRET'),
        ],
        
        'santander' => [
            'class' => \App\Domain\Custodian\Connectors\SantanderConnector::class,
            'enabled' => env('SANTANDER_ENABLED', false),
            'name' => 'Santander',
            'description' => 'Santander bank integration for global banking services',
            'api_key' => env('SANTANDER_API_KEY'),
            'api_secret' => env('SANTANDER_API_SECRET'),
            'certificate' => env('SANTANDER_CERTIFICATE_PATH'),
            'environment' => env('SANTANDER_ENVIRONMENT', 'production'),
            'webhook_secret' => env('SANTANDER_WEBHOOK_SECRET'),
        ],
        
        'deutsche_bank' => [
            'class' => \App\Domain\Custodian\Connectors\DeutscheBankConnector::class,
            'enabled' => env('DEUTSCHE_BANK_ENABLED', false),
            'name' => 'Deutsche Bank',
            'description' => 'Deutsche Bank integration for corporate banking and SEPA payments',
            'client_id' => env('DEUTSCHE_BANK_CLIENT_ID'),
            'client_secret' => env('DEUTSCHE_BANK_CLIENT_SECRET'),
            'account_id' => env('DEUTSCHE_BANK_ACCOUNT_ID'),
            'environment' => env('DEUTSCHE_BANK_ENVIRONMENT', 'production'),
            'webhook_secret' => env('DEUTSCHE_BANK_WEBHOOK_SECRET'),
        ],
        
    ],

    /*
    |--------------------------------------------------------------------------
    | Custodian Webhooks
    |--------------------------------------------------------------------------
    |
    | Configure webhook endpoints for receiving real-time updates from
    | custodians about transaction status changes.
    |
    */
    
    'webhooks' => [
        'route_prefix' => 'webhooks/custodian',
        'middleware' => ['api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Account Mapping
    |--------------------------------------------------------------------------
    |
    | Configure how internal accounts are mapped to custodian accounts.
    | This allows for flexible account management across multiple custodians.
    |
    */
    
    'account_mapping' => [
        'strategy' => 'database', // database, config, or custom
        'cache_ttl' => 3600, // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Settings
    |--------------------------------------------------------------------------
    |
    | Configure transaction-related settings for custodian operations.
    |
    */
    
    'transactions' => [
        'timeout' => 30, // seconds
        'retry_attempts' => 3,
        'retry_delay' => 1000, // milliseconds
        'batch_size' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring and Logging
    |--------------------------------------------------------------------------
    |
    | Configure monitoring and logging for custodian operations.
    |
    */
    
    'monitoring' => [
        'log_requests' => env('CUSTODIAN_LOG_REQUESTS', true),
        'log_responses' => env('CUSTODIAN_LOG_RESPONSES', false),
        'alert_on_failure' => env('CUSTODIAN_ALERT_ON_FAILURE', true),
        'health_check_interval' => 300, // 5 minutes
    ],
];