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
    | custodian transfer, transaction reversal, the simulated web wallet
    | deposits (simulate, Open Banking, bank/manual/crypto pages), the web
    | wallet withdrawals and the ledger-moving GraphQL mutations.
    | Payment-backed web deposits do not depend on it: card deposits use
    | web_card_deposits_enabled below and Paysera uses PAYSERA_ENABLED. Their
    | simulated demo gateways still need this flag.
    | Secure by default: on only for APP_ENV=local, testing or demo; every
    | other value (production, staging, unset) turns it off. An explicit
    | LEDGER_DIRECT_FUNDING_ENABLED value always wins, so a production-mode
    | demo can opt back in with LEDGER_DIRECT_FUNDING_ENABLED=true.
    |
    */
    'direct_funding_enabled' => (bool) env('LEDGER_DIRECT_FUNDING_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)),

    /*
    |--------------------------------------------------------------------------
    | Web wallet card deposits (Stripe / HyperSwitch)
    |--------------------------------------------------------------------------
    |
    | GET/POST /wallet/deposit/card, GET /wallet/deposit/confirm and the saved
    | payment-method routes. A card payment backs these deposits, so they
    | have their own switch instead of LEDGER_DIRECT_FUNDING_ENABLED.
    | Unset, it is on for APP_ENV=local, testing or demo, and elsewhere only
    | with HYPERSWITCH_ENABLED=true (the card rail opt-in). Plain Stripe card
    | deposits need WEB_CARD_DEPOSITS_ENABLED=true, because the Stripe keys
    | also serve subscriptions and KYC payments. The simulated demo gateway
    | (APP_ENV=demo) also needs direct_funding_enabled.
    |
    */
    'web_card_deposits_enabled' => (bool) env(
        'WEB_CARD_DEPOSITS_ENABLED',
        in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true) || (bool) env('HYPERSWITCH_ENABLED', false),
    ),
];
