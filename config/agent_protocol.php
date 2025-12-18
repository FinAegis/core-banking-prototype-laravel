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
    | Authentication Settings
    |--------------------------------------------------------------------------
    */
    'authentication' => [
        'session_ttl_hours'   => env('AGENT_SESSION_TTL_HOURS', 24),
        'api_key_length'      => env('AGENT_API_KEY_LENGTH', 64),
        'nonce_ttl_minutes'   => env('AGENT_NONCE_TTL_MINUTES', 5),
        'max_custom_fee_rate' => env('AGENT_MAX_CUSTOM_FEE_RATE', 0.10), // 10%
        'scopes'              => [
            // Payment scopes
            'payments:read'   => 'View payment transactions and history',
            'payments:create' => 'Create new payment transactions',
            'payments:cancel' => 'Cancel pending payment transactions',
            'payments:*'      => 'Full access to payment operations',

            // Wallet scopes
            'wallet:read'     => 'View wallet balance and transactions',
            'wallet:transfer' => 'Transfer funds between wallets',
            'wallet:withdraw' => 'Withdraw funds from wallet',
            'wallet:*'        => 'Full access to wallet operations',

            // Escrow scopes
            'escrow:read'    => 'View escrow transactions',
            'escrow:create'  => 'Create new escrow transactions',
            'escrow:release' => 'Release escrow funds',
            'escrow:dispute' => 'Dispute escrow transactions',
            'escrow:*'       => 'Full access to escrow operations',

            // Messaging scopes
            'messages:read'  => 'View A2A messages and conversations',
            'messages:send'  => 'Send A2A messages to other agents',
            'messages:write' => 'Create and manage A2A messages',
            'messages:*'     => 'Full access to messaging operations',

            // Agent management scopes
            'agent:read'     => 'View agent profile and capabilities',
            'agent:update'   => 'Update agent profile',
            'agent:keys'     => 'Manage API keys',
            'agent:sessions' => 'Manage sessions',
            'agent:*'        => 'Full access to agent management',

            // Reputation scopes
            'reputation:read'   => 'View reputation scores',
            'reputation:review' => 'Submit reviews and ratings',
            'reputation:*'      => 'Full access to reputation system',

            // Compliance scopes
            'compliance:read'   => 'View compliance status',
            'compliance:verify' => 'Submit verification documents',
            'compliance:*'      => 'Full access to compliance operations',

            // Administrative scopes (restricted)
            'admin:read'   => 'View administrative data',
            'admin:manage' => 'Manage agents and transactions',
            'admin:*'      => 'Full administrative access',

            // Universal scope
            '*' => 'Full access to all operations',
        ],
        'default_scopes' => [
            'payments:read',
            'wallet:read',
            'agent:read',
            'reputation:read',
            'messages:read',
        ],
    ],

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
        'minimum_amount'          => env('ESCROW_MIN_AMOUNT', 10.00),
        'maximum_amount'          => env('ESCROW_MAX_AMOUNT', 1000000.00),
        'default_expiration_days' => env('ESCROW_EXPIRATION_DAYS', 30),
        'default_timeout'         => env('ESCROW_DEFAULT_TIMEOUT', 86400), // 24 hours in seconds
        'dispute_timeout'         => env('ESCROW_DISPUTE_TIMEOUT', 3600), // 1 hour
        'auto_release_enabled'    => env('ESCROW_AUTO_RELEASE', true),
        'voting_threshold'        => env('ESCROW_VOTING_THRESHOLD', 10000.00), // Amount below which voting is used
        'resolution_methods'      => [
            'automated'   => env('ESCROW_RESOLUTION_AUTOMATED', true),
            'voting'      => env('ESCROW_RESOLUTION_VOTING', true),
            'arbitration' => env('ESCROW_RESOLUTION_ARBITRATION', true),
        ],
        'types' => [
            'standard'    => 'Standard escrow with basic release conditions',
            'milestone'   => 'Milestone-based escrow with phased releases',
            'timed'       => 'Time-based escrow with automatic release',
            'conditional' => 'Conditional escrow with complex criteria',
        ],
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
            'low'      => env('VERIFICATION_RISK_LOW', 30),
            'medium'   => env('VERIFICATION_RISK_MEDIUM', 60),
            'high'     => env('VERIFICATION_RISK_HIGH', 80),
            'critical' => env('VERIFICATION_RISK_CRITICAL', 95),
        ],
        'risk_weights' => [
            'signature'    => env('VERIFICATION_WEIGHT_SIGNATURE', 25),
            'agent'        => env('VERIFICATION_WEIGHT_AGENT', 15),
            'limits'       => env('VERIFICATION_WEIGHT_LIMITS', 10),
            'velocity'     => env('VERIFICATION_WEIGHT_VELOCITY', 15),
            'fraud'        => env('VERIFICATION_WEIGHT_FRAUD', 20),
            'compliance'   => env('VERIFICATION_WEIGHT_COMPLIANCE', 10),
            'encryption'   => env('VERIFICATION_WEIGHT_ENCRYPTION', 3),
            'multi_factor' => env('VERIFICATION_WEIGHT_MFA', 2),
        ],
        'velocity_limits' => [
            'hourly'  => ['amount' => env('VELOCITY_HOURLY_AMOUNT', 10000), 'count' => env('VELOCITY_HOURLY_COUNT', 10)],
            'daily'   => ['amount' => env('VELOCITY_DAILY_AMOUNT', 50000), 'count' => env('VELOCITY_DAILY_COUNT', 50)],
            'weekly'  => ['amount' => env('VELOCITY_WEEKLY_AMOUNT', 200000), 'count' => env('VELOCITY_WEEKLY_COUNT', 200)],
            'monthly' => ['amount' => env('VELOCITY_MONTHLY_AMOUNT', 500000), 'count' => env('VELOCITY_MONTHLY_COUNT', 500)],
        ],
        'default_limits' => [
            'single_transaction' => env('DEFAULT_SINGLE_TX_LIMIT', 10000),
            'daily'              => env('DEFAULT_DAILY_LIMIT', 50000),
            'monthly'            => env('DEFAULT_MONTHLY_LIMIT', 200000),
        ],
        'levels' => [
            'basic'    => ['signature', 'agent'],
            'standard' => ['signature', 'agent', 'limits', 'velocity'],
            'enhanced' => ['signature', 'agent', 'limits', 'velocity', 'fraud', 'compliance'],
            'maximum'  => ['signature', 'agent', 'limits', 'velocity', 'fraud', 'compliance', 'encryption', 'multi_factor'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection
    |--------------------------------------------------------------------------
    */
    'fraud_detection' => [
        'enabled'         => env('FRAUD_DETECTION_ENABLED', true),
        'cache_ttl'       => env('FRAUD_CACHE_TTL', 2592000), // 30 days
        'min_reputation'  => env('FRAUD_MIN_REPUTATION', 30),
        'risk_thresholds' => [
            'low'      => env('FRAUD_RISK_THRESHOLD_LOW', 30),
            'medium'   => env('FRAUD_RISK_THRESHOLD_MEDIUM', 60),
            'high'     => env('FRAUD_RISK_THRESHOLD_HIGH', 80),
            'critical' => env('FRAUD_RISK_THRESHOLD_CRITICAL', 95),
        ],
        'risk_weights' => [
            'velocity'       => env('FRAUD_WEIGHT_VELOCITY', 25),
            'amount_anomaly' => env('FRAUD_WEIGHT_AMOUNT', 20),
            'reputation'     => env('FRAUD_WEIGHT_REPUTATION', 15),
            'pattern'        => env('FRAUD_WEIGHT_PATTERN', 20),
            'time_of_day'    => env('FRAUD_WEIGHT_TIME', 10),
            'geographic'     => env('FRAUD_WEIGHT_GEO', 10),
        ],
        'velocity_limits' => [
            'hourly_count' => env('FRAUD_VELOCITY_HOURLY_COUNT', 10),
            'daily_count'  => env('FRAUD_VELOCITY_DAILY_COUNT', 50),
        ],
        'pattern_scores' => [
            'round_amount'  => env('FRAUD_PATTERN_ROUND_AMOUNT', 20),
            'structuring'   => env('FRAUD_PATTERN_STRUCTURING', 40),
            'sequential'    => env('FRAUD_PATTERN_SEQUENTIAL', 30),
            'suspicious_ua' => env('FRAUD_PATTERN_SUSPICIOUS_UA', 25),
        ],
        'suspicious_hours' => [
            'start' => env('FRAUD_SUSPICIOUS_HOUR_START', 2),
            'end'   => env('FRAUD_SUSPICIOUS_HOUR_END', 5),
        ],
        'structuring_threshold'    => env('FRAUD_STRUCTURING_THRESHOLD', 1000),
        'structuring_count'        => env('FRAUD_STRUCTURING_COUNT', 5),
        'small_transaction_amount' => env('FRAUD_SMALL_TX_AMOUNT', 100),
        'large_transaction'        => env('FRAUD_LARGE_TRANSACTION', 50000),
        'suspicious_user_agents'   => explode(',', (string) env('FRAUD_SUSPICIOUS_USER_AGENTS', 'bot,crawler,spider,scraper,curl,wget,python')),
        'scoring_adjustments'      => [
            'z_score_multiplier'         => env('FRAUD_Z_SCORE_MULTIPLIER', 20),
            'z_score_anomaly_threshold'  => env('FRAUD_Z_SCORE_THRESHOLD', 3),
            'unknown_agent_risk'         => env('FRAUD_UNKNOWN_AGENT_RISK', 80),
            'default_reputation'         => env('FRAUD_DEFAULT_REPUTATION', 50),
            'new_country_score'          => env('FRAUD_GEO_NEW_COUNTRY', 40),
            'impossible_travel_score'    => env('FRAUD_GEO_IMPOSSIBLE_TRAVEL', 60),
            'unusual_time_score'         => env('FRAUD_TIME_UNUSUAL', 30),
            'night_time_score'           => env('FRAUD_TIME_NIGHT', 20),
            'weekend_no_history_score'   => env('FRAUD_TIME_WEEKEND', 15),
            'typical_activity_min_count' => env('FRAUD_TYPICAL_ACTIVITY_MIN', 2),
        ],
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
        'supported_currencies'    => explode(',', (string) env('AGENT_SUPPORTED_CURRENCIES', 'USD,EUR,GBP,JPY,CHF,CAD,AUD,NZD,BTC,ETH')),
        'exchange_rate_cache_ttl' => env('EXCHANGE_RATE_CACHE_TTL', 300), // 5 minutes
        'fee_rates'               => [
            'standard'   => env('WALLET_FEE_STANDARD', 0.025),
            'premium'    => env('WALLET_FEE_PREMIUM', 0.01),
            'enterprise' => env('WALLET_FEE_ENTERPRISE', 0.005),
        ],
        'transaction_fees' => [
            'domestic'      => env('WALLET_FEE_DOMESTIC', 0.01),       // 1%
            'international' => env('WALLET_FEE_INTERNATIONAL', 0.025), // 2.5%
            'crypto'        => env('WALLET_FEE_CRYPTO', 0.005),        // 0.5%
            'escrow'        => env('WALLET_FEE_ESCROW', 0.02),         // 2%
        ],
        'crypto_currencies' => explode(',', (string) env('WALLET_CRYPTO_CURRENCIES', 'BTC,ETH,USDT')),
        'minimum_balance'   => env('WALLET_MIN_BALANCE', 0),
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
        'high_risk_countries'  => explode(',', (string) env('AML_HIGH_RISK_COUNTRIES', 'KP,IR,SY,CU')),
        'large_transaction'    => env('AML_LARGE_TRANSACTION', 10000),
        'risk_score_threshold' => env('AML_RISK_THRESHOLD', 75),
    ],
];
