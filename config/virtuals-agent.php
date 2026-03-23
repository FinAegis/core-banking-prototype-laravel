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

    'enabled' => (bool) env('VIRTUALS_AGENT_ENABLED', false),

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
];
