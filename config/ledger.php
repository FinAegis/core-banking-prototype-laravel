<?php

declare(strict_types=1);

return [
    'driver'                  => env('LEDGER_DRIVER', 'eloquent'),
    'auto_posting'            => (bool) env('LEDGER_AUTO_POSTING', true),
    'reconciliation_schedule' => env('LEDGER_RECONCILIATION_SCHEDULE', 'daily'),
    'tigerbeetle'             => [
        'addresses'  => env('TIGERBEETLE_ADDRESSES', '127.0.0.1:3001'),
        'cluster_id' => (int) env('TIGERBEETLE_CLUSTER_ID', 0),
        'timeout_ms' => (int) env('TIGERBEETLE_TIMEOUT', 5000),
    ],
    'default_currency' => env('LEDGER_DEFAULT_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Direct ledger funding (demo feature)
    |--------------------------------------------------------------------------
    |
    | Enables the demo-only endpoints that credit or debit internal account
    | balances directly, without an external payment rail: v1/v2 account
    | deposit/withdraw, account opening balances, the BIAN API, the mock
    | custodian transfer, transaction reversal, the web wallet fiat
    | deposit/withdraw pages and the ledger-moving GraphQL mutations.
    | Default true keeps the FinAegis demo unchanged; production wallet
    | deployments set LEDGER_DIRECT_FUNDING_ENABLED=false.
    |
    */
    'direct_funding_enabled' => (bool) env('LEDGER_DIRECT_FUNDING_ENABLED', true),
];
