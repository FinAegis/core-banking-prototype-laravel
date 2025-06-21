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
    | Resilience Configuration
    |--------------------------------------------------------------------------
    |
    | Configure circuit breaker and retry settings for custodian operations.
    |
    */
    
    'resilience' => [
        'circuit_breaker' => [
            'failure_threshold' => env('CUSTODIAN_CB_FAILURE_THRESHOLD', 5),
            'success_threshold' => env('CUSTODIAN_CB_SUCCESS_THRESHOLD', 2),
            'timeout' => env('CUSTODIAN_CB_TIMEOUT', 60), // seconds
            'failure_rate_threshold' => env('CUSTODIAN_CB_FAILURE_RATE', 0.5), // 50%
            'sample_size' => env('CUSTODIAN_CB_SAMPLE_SIZE', 10),
        ],
        
        'retry' => [
            'max_attempts' => env('CUSTODIAN_RETRY_MAX_ATTEMPTS', 3),
            'initial_delay_ms' => env('CUSTODIAN_RETRY_INITIAL_DELAY', 200),
            'max_delay_ms' => env('CUSTODIAN_RETRY_MAX_DELAY', 5000),
            'multiplier' => env('CUSTODIAN_RETRY_MULTIPLIER', 2.5),
            'jitter' => env('CUSTODIAN_RETRY_JITTER', true),
        ],
        
        'fallback' => [
            'cache_ttl' => env('CUSTODIAN_FALLBACK_CACHE_TTL', 300), // seconds
            'enable_queuing' => env('CUSTODIAN_FALLBACK_QUEUE', true),
            'enable_alternative_routing' => env('CUSTODIAN_FALLBACK_ROUTING', true),
        ],
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

    /*
    |--------------------------------------------------------------------------
    | Transfer Routing Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how transfers are routed between different custodians.
    |
    */
    
    'routing_strategy' => [
        'primary' => 'same_custodian', // same_custodian, fastest, cheapest, balanced
        'fallback' => 'fastest',
        'max_bridge_fee' => 500, // Maximum fee in cents for bridge transfers
    ],

    /*
    |--------------------------------------------------------------------------
    | Settlement Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how inter-custodian settlements are processed.
    |
    */
    
    'settlement' => [
        'type' => env('SETTLEMENT_TYPE', 'net'), // realtime, batch, net
        'batch_interval_minutes' => env('SETTLEMENT_BATCH_INTERVAL', 60),
        'min_settlement_amount' => env('SETTLEMENT_MIN_AMOUNT', 10000), // $100.00 in cents
        'settlement_accounts' => [
            'paysera' => [
                'USD' => env('PAYSERA_SETTLEMENT_USD'),
                'EUR' => env('PAYSERA_SETTLEMENT_EUR'),
            ],
            'deutsche_bank' => [
                'USD' => env('DEUTSCHE_BANK_SETTLEMENT_USD'),
                'EUR' => env('DEUTSCHE_BANK_SETTLEMENT_EUR'),
            ],
            'santander' => [
                'USD' => env('SANTANDER_SETTLEMENT_USD'),
                'EUR' => env('SANTANDER_SETTLEMENT_EUR'),
            ],
        ],
    ],
];