<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Agent Protocol Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the Agent Protocol implementation, including
    | DID verification, webhook endpoints, escrow settings, and fee structures.
    |
    */

    'enabled' => env('AGENT_PROTOCOL_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | DID (Decentralized Identifier) Settings
    |--------------------------------------------------------------------------
    */
    'did' => [
        'verification_enabled' => env('DID_VERIFICATION_ENABLED', false), // Set to true in production
        'signature_algorithm'  => env('DID_SIGNATURE_ALGO', 'RS256'),
        'cache_ttl'            => env('DID_CACHE_TTL', 3600), // 1 hour
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'internal_url'     => env('AGENT_WEBHOOK_INTERNAL_URL', 'http://localhost:8000/webhooks/agent-notifications'),
        'external_timeout' => env('AGENT_WEBHOOK_TIMEOUT', 10), // seconds
        'retry_attempts'   => env('AGENT_WEBHOOK_RETRIES', 3),
        'retry_delay'      => env('AGENT_WEBHOOK_RETRY_DELAY', 100), // milliseconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Escrow Settings
    |--------------------------------------------------------------------------
    */
    'escrow' => [
        'minimum_amount'       => env('ESCROW_MIN_AMOUNT', 10.00),
        'maximum_amount'       => env('ESCROW_MAX_AMOUNT', 1000000.00),
        'default_timeout'      => env('ESCROW_DEFAULT_TIMEOUT', 86400), // 24 hours in seconds
        'dispute_timeout'      => env('ESCROW_DISPUTE_TIMEOUT', 3600), // 1 hour
        'auto_release_enabled' => env('ESCROW_AUTO_RELEASE', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Processing
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'max_retries'           => env('PAYMENT_MAX_RETRIES', 3),
        'retry_delay'           => env('PAYMENT_RETRY_DELAY', 5), // seconds
        'split_payment_enabled' => env('SPLIT_PAYMENT_ENABLED', true),
        'max_split_recipients'  => env('MAX_SPLIT_RECIPIENTS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fee Structure
    |--------------------------------------------------------------------------
    */
    'fees' => [
        'standard_rate'       => env('FEE_STANDARD_RATE', 0.025), // 2.5%
        'minimum_fee'         => env('FEE_MINIMUM', 0.50),
        'maximum_fee'         => env('FEE_MAXIMUM', 100.00),
        'fee_collector_did'   => env('FEE_COLLECTOR_DID', 'did:agent:finaegis:fee-collector'),
        'exemption_threshold' => env('FEE_EXEMPTION_THRESHOLD', 1.00), // Transactions under this are exempt
    ],

    /*
    |--------------------------------------------------------------------------
    | System Agents
    |--------------------------------------------------------------------------
    */
    'system_agents' => [
        'admin_dids'   => explode(',', (string) env('SYSTEM_ADMIN_DIDS', 'did:agent:finaegis:admin-1,did:agent:finaegis:admin-2')),
        'system_did'   => env('SYSTEM_AGENT_DID', 'did:agent:finaegis:system'),
        'treasury_did' => env('TREASURY_AGENT_DID', 'did:agent:finaegis:treasury'),
        'reserve_did'  => env('RESERVE_AGENT_DID', 'did:agent:finaegis:reserve'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */
    'security' => [
        'rate_limiting' => [
            'enabled'                 => env('AGENT_RATE_LIMIT_ENABLED', true),
            'max_requests_per_minute' => env('AGENT_MAX_REQUESTS', 60),
            'max_payments_per_hour'   => env('AGENT_MAX_PAYMENTS_HOUR', 100),
        ],
        'transaction_limits' => [
            'daily_limit'              => env('AGENT_DAILY_LIMIT', 10000.00),
            'single_transaction_limit' => env('AGENT_SINGLE_LIMIT', 5000.00),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Monitoring & Logging
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'metrics_enabled' => env('AGENT_METRICS_ENABLED', true),
        'audit_logging'   => env('AGENT_AUDIT_LOGGING', true),
        'log_channel'     => env('AGENT_LOG_CHANNEL', 'agent_protocol'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Demo Mode Settings
    |--------------------------------------------------------------------------
    */
    'demo' => [
        'enabled'         => env('AGENT_DEMO_MODE', env('APP_ENV_MODE') === 'demo'),
        'mock_signatures' => env('AGENT_MOCK_SIGNATURES', env('APP_ENV') !== 'production'),
        'mock_webhooks'   => env('AGENT_MOCK_WEBHOOKS', env('APP_ENV') !== 'production'),
        'test_agent_dids' => [
            'did:agent:test:alice',
            'did:agent:test:bob',
            'did:agent:test:charlie',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Reputation System
    |--------------------------------------------------------------------------
    */
    'reputation' => [
        'cache_ttl'           => env('REPUTATION_CACHE_TTL', 300), // 5 minutes
        'initial_score'       => env('REPUTATION_INITIAL_SCORE', 50),
        'min_score'           => env('REPUTATION_MIN_SCORE', 0),
        'max_score'           => env('REPUTATION_MAX_SCORE', 100),
        'decay_enabled'       => env('REPUTATION_DECAY_ENABLED', true),
        'decay_inactive_days' => env('REPUTATION_DECAY_INACTIVE_DAYS', 30),
        'thresholds'          => [
            'excellent' => env('REPUTATION_THRESHOLD_EXCELLENT', 80),
            'good'      => env('REPUTATION_THRESHOLD_GOOD', 60),
            'fair'      => env('REPUTATION_THRESHOLD_FAIR', 40),
            'poor'      => env('REPUTATION_THRESHOLD_POOR', 20),
        ],
        'weights' => [
            'transaction_success' => env('REPUTATION_WEIGHT_SUCCESS', 5),
            'transaction_failure' => env('REPUTATION_WEIGHT_FAILURE', -10),
            'dispute_won'         => env('REPUTATION_WEIGHT_DISPUTE_WON', 3),
            'dispute_lost'        => env('REPUTATION_WEIGHT_DISPUTE_LOST', -15),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | KYC Verification
    |--------------------------------------------------------------------------
    */
    'kyc' => [
        'enabled'                  => env('AGENT_KYC_ENABLED', true),
        'inherit_from_linked_user' => env('AGENT_KYC_INHERIT', true),
        'levels'                   => [
            'basic' => [
                'daily_limit'   => env('KYC_BASIC_DAILY_LIMIT', 1000),
                'monthly_limit' => env('KYC_BASIC_MONTHLY_LIMIT', 5000),
                'max_single'    => env('KYC_BASIC_MAX_SINGLE', 500),
            ],
            'enhanced' => [
                'daily_limit'   => env('KYC_ENHANCED_DAILY_LIMIT', 10000),
                'monthly_limit' => env('KYC_ENHANCED_MONTHLY_LIMIT', 50000),
                'max_single'    => env('KYC_ENHANCED_MAX_SINGLE', 5000),
            ],
            'full' => [
                'daily_limit'   => null, // Unlimited
                'monthly_limit' => null,
                'max_single'    => null,
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Verification
    |--------------------------------------------------------------------------
    */
    'verification' => [
        'cache_ttl'              => env('VERIFICATION_CACHE_TTL', 2592000), // 30 days
        'multi_factor_threshold' => env('VERIFICATION_MF_THRESHOLD', 2), // Required factors
        'risk_thresholds'        => [
            'low'      => env('VERIFICATION_RISK_LOW', 20),
            'medium'   => env('VERIFICATION_RISK_MEDIUM', 50),
            'high'     => env('VERIFICATION_RISK_HIGH', 75),
            'critical' => env('VERIFICATION_RISK_CRITICAL', 90),
        ],
        'velocity_limits' => [
            'hourly_count' => env('VELOCITY_HOURLY_COUNT', 10),
            'daily_count'  => env('VELOCITY_DAILY_COUNT', 50),
            'daily_amount' => env('VELOCITY_DAILY_AMOUNT', 10000),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection
    |--------------------------------------------------------------------------
    */
    'fraud_detection' => [
        'enabled'        => env('FRAUD_DETECTION_ENABLED', true),
        'cache_ttl'      => env('FRAUD_CACHE_TTL', 2592000), // 30 days
        'min_reputation' => env('FRAUD_MIN_REPUTATION', 30),
        'risk_weights'   => [
            'velocity'       => env('FRAUD_WEIGHT_VELOCITY', 25),
            'amount_anomaly' => env('FRAUD_WEIGHT_AMOUNT', 20),
            'reputation'     => env('FRAUD_WEIGHT_REPUTATION', 15),
            'pattern'        => env('FRAUD_WEIGHT_PATTERN', 20),
            'time_of_day'    => env('FRAUD_WEIGHT_TIME', 10),
            'geographic'     => env('FRAUD_WEIGHT_GEO', 10),
        ],
        'suspicious_hours' => [
            'start' => env('FRAUD_SUSPICIOUS_HOUR_START', 2),
            'end'   => env('FRAUD_SUSPICIOUS_HOUR_END', 5),
        ],
        'structuring_threshold' => env('FRAUD_STRUCTURING_THRESHOLD', 1000),
        'large_transaction'     => env('FRAUD_LARGE_TRANSACTION', 50000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Encryption & Cryptography
    |--------------------------------------------------------------------------
    */
    'encryption' => [
        'default_cipher'    => env('AGENT_CIPHER', 'aes-256-gcm'),
        'key_rotation_days' => env('KEY_ROTATION_DAYS', 30),
        'key_cache_ttl'     => env('KEY_CACHE_TTL', 86400), // 24 hours
        'archive_cache_ttl' => env('KEY_ARCHIVE_TTL', 2592000), // 30 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Wallet Configuration
    |--------------------------------------------------------------------------
    */
    'wallet' => [
        'default_currency'        => env('AGENT_DEFAULT_CURRENCY', 'USD'),
        'supported_currencies'    => explode(',', env('AGENT_SUPPORTED_CURRENCIES', 'USD,EUR,GBP,BTC,ETH')),
        'exchange_rate_cache_ttl' => env('EXCHANGE_RATE_CACHE_TTL', 300), // 5 minutes
        'fee_rates'               => [
            'standard'   => env('WALLET_FEE_STANDARD', 0.025),
            'premium'    => env('WALLET_FEE_PREMIUM', 0.01),
            'enterprise' => env('WALLET_FEE_ENTERPRISE', 0.005),
        ],
        'minimum_balance' => env('WALLET_MIN_BALANCE', 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Registry
    |--------------------------------------------------------------------------
    */
    'registry' => [
        'cache_ttl'        => env('AGENT_REGISTRY_CACHE_TTL', 3600), // 1 hour
        'info_cache_ttl'   => env('AGENT_INFO_CACHE_TTL', 300), // 5 minutes
        'max_capabilities' => env('AGENT_MAX_CAPABILITIES', 50),
    ],

    /*
    |--------------------------------------------------------------------------
    | AML Screening
    |--------------------------------------------------------------------------
    */
    'aml' => [
        'enabled'              => env('AGENT_AML_ENABLED', true),
        'high_risk_countries'  => explode(',', env('AML_HIGH_RISK_COUNTRIES', 'KP,IR,SY,CU')),
        'large_transaction'    => env('AML_LARGE_TRANSACTION', 10000),
        'risk_score_threshold' => env('AML_RISK_THRESHOLD', 75),
    ],
];
