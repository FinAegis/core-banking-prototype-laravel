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
];
