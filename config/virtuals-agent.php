<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Virtuals Agent Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Virtuals Protocol ACP (Agent Commerce Protocol)
    | integration. Enables autonomous AI agents from the Virtuals ecosystem
    | to hold spending limits, execute payments, and receive TrustCert
    | credentials within FinAegis.
    | See: https://virtuals.io/
    |
    */

    /*
    | Gates the /api/v1/virtuals-agents routes (404 while off). The routes
    | also need the VirtualsAgent module enabled (not in MODULES_DISABLED).
    | Secure by default: on only for APP_ENV=local, testing or demo, off for
    | every other value (production, staging, unset). An explicit
    | VIRTUALS_AGENT_ENABLED value always wins.
    */
    'enabled' => (bool) env('VIRTUALS_AGENT_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)),

    'acp_base_url' => env('VIRTUALS_ACP_BASE_URL', 'https://api.virtuals.io'),

    'acp_api_key' => env('VIRTUALS_ACP_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Spending Limits
    |--------------------------------------------------------------------------
    |
    | Default spending limits for Virtuals Agent payments.
    | All amounts in USD cents.
    |
    */

    'default_daily_limit' => (int) env('VIRTUALS_AGENT_DAILY_LIMIT', 50000), // $500 in cents

    'default_per_tx_limit' => (int) env('VIRTUALS_AGENT_PER_TX_LIMIT', 10000), // $100 in cents

    /*
    |--------------------------------------------------------------------------
    | Supported Chains
    |--------------------------------------------------------------------------
    |
    | Blockchain networks supported for Virtuals Agent operations.
    |
    */

    'supported_chains' => ['base', 'polygon', 'arbitrum', 'ethereum'],

    /*
    |--------------------------------------------------------------------------
    | Auto-Provision Card
    |--------------------------------------------------------------------------
    |
    | When enabled, a virtual card will be auto-provisioned for agents
    | during the onboarding process.
    |
    */

    'auto_provision_card' => (bool) env('VIRTUALS_AGENT_AUTO_PROVISION_CARD', false),

    /*
    |--------------------------------------------------------------------------
    | Spending Limit Caps
    |--------------------------------------------------------------------------
    |
    | Maximum allowed spending limits for agent onboarding.
    | Prevents misconfiguration of excessively high limits.
    |
    */

    'max_daily_limit' => (int) env('VIRTUALS_AGENT_MAX_DAILY_LIMIT', 10000000), // $100,000

    'max_per_tx_limit' => (int) env('VIRTUALS_AGENT_MAX_PER_TX_LIMIT', 1000000), // $10,000

    /*
    |--------------------------------------------------------------------------
    | Agent Token Tracking
    |--------------------------------------------------------------------------
    |
    | ERC-20 agent tokens to track for portfolio views.
    | Each entry: address (on Base), symbol, decimals.
    |
    */

    'tracked_tokens' => [
        ['address' => '0x44ff8620b8cA30902395A7bD3F2407e1A091BF73', 'symbol' => 'VIRTUAL', 'decimals' => 18],
    ],
];
