<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Visa CLI Integration Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the Visa CLI payment integration.
    | Visa CLI enables AI agents and developers to make programmatic
    | Visa card payments without managing API keys directly.
    | See: https://visacli.sh/
    |
    */

    'enabled' => (bool) env('VISACLI_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | The driver to use for Visa CLI operations. Supported: "demo", "process".
    | "demo" uses a cache-based mock for development and testing.
    | "process" calls the actual visa-cli binary via Symfony Process.
    |
    */

    'driver' => env('VISACLI_DRIVER', 'demo'),

    /*
    |--------------------------------------------------------------------------
    | Binary Configuration
    |--------------------------------------------------------------------------
    |
    | Path to the visa-cli binary and API base URL for the process driver.
    |
    */

    'binary_path' => env('VISACLI_BINARY_PATH', 'visa-cli'),

    'api' => [
        'base_url' => env('VISACLI_API_BASE_URL', 'https://api.visacli.sh'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | GitHub token for Visa CLI authentication and session management.
    |
    */

    'auth' => [
        'github_token'  => env('VISACLI_GITHUB_TOKEN'),
        'session_token' => env('VISACLI_SESSION_TOKEN'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Spending Limits
    |--------------------------------------------------------------------------
    |
    | Default spending limits for Visa CLI payments.
    | All amounts in USD cents.
    |
    */

    'spending_limits' => [
        // $100.00 daily limit
        'daily' => (int) env('VISACLI_DAILY_LIMIT', 10000),

        // $10.00 per-transaction limit
        'per_tx' => (int) env('VISACLI_PER_TX_LIMIT', 1000),

        // Enable auto-pay for agent-initiated payments
        'auto_pay' => (bool) env('VISACLI_AUTO_PAY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    |
    | Secret for verifying inbound webhook signatures from Visa CLI.
    |
    */

    'webhook' => [
        'secret' => env('VISACLI_WEBHOOK_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts
    |--------------------------------------------------------------------------
    |
    | Timeout settings for Visa CLI operations in seconds.
    |
    */

    'timeouts' => [
        'payment'    => (int) env('VISACLI_PAYMENT_TIMEOUT', 30),
        'enrollment' => (int) env('VISACLI_ENROLLMENT_TIMEOUT', 60),
        'status'     => (int) env('VISACLI_STATUS_TIMEOUT', 10),
    ],
];
