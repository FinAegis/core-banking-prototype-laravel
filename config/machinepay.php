<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Machine Payments Protocol (MPP) Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Machine Payments Protocol — a multi-rail HTTP 402
    | payment protocol supporting Stripe SPT, Tempo stablecoins, Lightning
    | Network, and traditional card payments for AI agent commerce.
    | See: https://paymentauth.org
    |
    */

    'enabled' => (bool) env('MPP_ENABLED', false),

    'version' => 1,

    // Subdomain prefix for protocol-specific routing (e.g. mpp.api.zelta.app)
    'subdomain' => env('MPP_SUBDOMAIN', 'mpp'),

    /*
    |--------------------------------------------------------------------------
    | Server Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for when FinAegis acts as a resource server,
    | issuing 402 challenges and accepting MPP payments.
    |
    */

    'server' => [
        'default_currency' => env('MPP_DEFAULT_CURRENCY', 'USD'),

        'supported_rails' => array_filter(
            explode(',', (string) env('MPP_SUPPORTED_RAILS', 'stripe,tempo,lightning,card')),
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Rail Configuration
    |--------------------------------------------------------------------------
    |
    | Per-rail settings for each supported payment method.
    |
    */

    'rails' => [
        'stripe' => [
            'api_key_id'     => env('MPP_STRIPE_API_KEY_ID'),
            'webhook_secret' => env('MPP_STRIPE_WEBHOOK_SECRET'),
        ],

        'tempo' => [
            'endpoint' => env('MPP_TEMPO_ENDPOINT', 'https://rpc.tempo.xyz'),
            'chain_id' => (int) env('MPP_TEMPO_CHAIN_ID', 42431),
        ],

        'lightning' => [
            'node_uri' => env('MPP_LIGHTNING_NODE_URI'),
        ],

        'card' => [
            'processor_id' => env('MPP_CARD_PROCESSOR_ID'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Challenge Settings
    |--------------------------------------------------------------------------
    |
    | Controls challenge generation and HMAC-SHA256 binding.
    | The HMAC key is used to sign challenges over 7 positional slots.
    |
    */

    'challenge' => [
        'hmac_key' => env('MPP_CHALLENGE_HMAC_KEY', ''),

        'default_expiry_seconds' => (int) env('MPP_CHALLENGE_EXPIRY', 300),

        'max_timeout_seconds' => (int) env('MPP_CHALLENGE_MAX_TIMEOUT', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Client Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for when FinAegis acts as a client (buyer),
    | paying external MPP-enabled APIs on behalf of AI agents.
    |
    */

    'client' => [
        'enabled' => (bool) env('MPP_CLIENT_ENABLED', false),

        'auto_pay' => (bool) env('MPP_CLIENT_AUTO_PAY', false),

        // Maximum auto-pay amount per request in cents ($1.00 = 100)
        'max_auto_pay_amount' => (int) env('MPP_CLIENT_MAX_AUTO_PAY', 100),

        // Preferred rail order when paying external APIs
        'preferred_rails' => ['stripe', 'tempo', 'lightning'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent Spending Limits
    |--------------------------------------------------------------------------
    |
    | Default spending limits for AI agents making MPP payments.
    | All amounts in the smallest currency unit (cents for USD).
    |
    */

    'agent_spending' => [
        // $50.00 daily limit
        'default_daily_limit' => (int) env('MPP_DAILY_LIMIT', 5000),

        // $1.00 per-transaction limit
        'default_per_transaction_limit' => (int) env('MPP_PER_TX_LIMIT', 100),

        // Require human approval above $10.00
        'require_approval_above' => (int) env('MPP_REQUIRE_APPROVAL_ABOVE', 1000),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Transport Binding
    |--------------------------------------------------------------------------
    |
    | Settings for the MCP (Model Context Protocol) transport binding.
    | MPP defines error code -32042 as the MCP equivalent of HTTP 402.
    |
    */

    'mcp' => [
        'enabled' => (bool) env('MPP_MCP_ENABLED', true),

        'error_code' => -32042,
    ],
];
