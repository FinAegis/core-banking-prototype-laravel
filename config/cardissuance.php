<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Card Issuance Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the virtual card issuance system for tap-to-pay
    | functionality via Apple Pay and Google Pay.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Default Card Issuer
    |--------------------------------------------------------------------------
    |
    | The card issuer provider to use. Supported: "demo", "marqeta", "lithic",
    | "stripe_issuing", "fincard"
    |
    */

    'default_issuer' => env('CARD_ISSUER', 'demo'),

    /*
    |--------------------------------------------------------------------------
    | Card providers (provider-neutral card programme)
    |--------------------------------------------------------------------------
    |
    | Which providers new cardholders may get. The provider is stored on the
    | cardholder and read from there afterwards, so changing the default never
    | moves an existing user. While the Lepo pilot is on, only the listed users
    | (uuids or e-mails, comma-separated) are offered Lepo cards.
    |
    */
    'providers' => [
        // Opt-in: an enabled provider without credentials would break cards
        // (and fails ops:verify-env in production). Zelta: CARD_PROVIDERS_ENABLED=lepo.
        'enabled'          => env('CARD_PROVIDERS_ENABLED', ''),
        'default'          => env('CARD_PROVIDER_DEFAULT', 'lepo'),
        'selection'        => env('CARD_PROVIDER_SELECTION', 'default'),
        'lepo_pilot_only'  => (bool) env('CARD_PROVIDER_LEPO_PILOT_ONLY', true),
        'lepo_pilot_users' => env('CARD_PROVIDER_LEPO_PILOT_USERS', ''),
        // The first network is the one the app offers by default.
        'lepo_deposit_networks' => env('LEPO_DEPOSIT_NETWORKS', 'arbitrum,polygon'),
        // How the app runs the identity check: hosted_url (our page with the
        // SumSub web SDK, opened in the app's browser) or sumsub_sdk (a token
        // for the native SumSub SDK).
        'lepo_kyc_method' => env('CARD_LEPO_KYC_METHOD', 'hosted_url'),
        // Where the identity-check page sends the user when the check is sent.
        'kyc_return_url'   => env('CARD_KYC_RETURN_URL', 'zelta://cards/kyc-done'),
        'kyc_link_minutes' => (int) env('CARD_KYC_LINK_MINUTES', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Issuer Configurations
    |--------------------------------------------------------------------------
    */

    'issuers' => [
        'demo' => [
            'driver' => 'demo',
        ],

        'marqeta' => [
            'driver'             => 'marqeta',
            'base_url'           => env('MARQETA_BASE_URL', 'https://sandbox-api.marqeta.com/v3'),
            'application_token'  => env('MARQETA_APPLICATION_TOKEN'),
            'admin_access_token' => env('MARQETA_ADMIN_ACCESS_TOKEN'),
            'webhook_secret'     => env('MARQETA_WEBHOOK_SECRET'),
            'hmac_secret'        => env('MARQETA_HMAC_SECRET'),
            'webhook_username'   => env('MARQETA_WEBHOOK_USERNAME'),
            'webhook_password'   => env('MARQETA_WEBHOOK_PASSWORD'),
        ],

        'lithic' => [
            'driver'         => 'lithic',
            'base_url'       => env('LITHIC_BASE_URL', 'https://sandbox.lithic.com/v1'),
            'api_key'        => env('LITHIC_API_KEY'),
            'webhook_secret' => env('LITHIC_WEBHOOK_SECRET'),
        ],

        'stripe_issuing' => [
            'driver'         => 'stripe_issuing',
            'api_key'        => env('STRIPE_SECRET'),
            'webhook_secret' => env('STRIPE_ISSUING_WEBHOOK_SECRET'),
        ],

        // FinCard (FinHub BFF, finhub.cloud) — prefunded stored-value virtual
        // cards. Auth is a session-JWT login (no request signing on our side;
        // FinHub's BFF signs to its upstream). Inbound webhooks are RSA-signed
        // and verified with `webhook_public_key`.
        // @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md
        'fincard' => [
            'driver'               => 'fincard',
            'base_url'             => env('FINCARD_BASE_URL', 'https://sandbox.finhub.cloud/api/v2.1/fincard/virtual'),
            'tenant_id'            => env('FINCARD_TENANT_ID'),
            'org_id'               => env('FINCARD_ORG_ID'),
            'user_id'              => env('FINCARD_USER_ID'),
            'username'             => env('FINCARD_USERNAME'),
            'password'             => env('FINCARD_PASSWORD'),
            'forwarded_from'       => env('FINCARD_FORWARDED_FROM', 'zelta'),
            'webhook_public_key'   => env('FINCARD_WEBHOOK_PUBLIC_KEY', ''),
            'default_card_type_id' => env('FINCARD_DEFAULT_CARD_TYPE_ID'),
            'default_coin_key'     => env('FINCARD_DEFAULT_COIN_KEY', 'USDT_TRC20'),

            // DEV/QA ONLY — enables POST /v1/cards/dev/simulate-deposit, a
            // money-crediting test affordance for the funding UI. The route is
            // registered in non-production ONLY; this flag is a second gate.
            // Never set in production.
            'dev_simulate_enabled' => (bool) env('FINCARD_DEV_SIMULATE_ENABLED', false),
        ],

        // Lepo Finance — virtual cards issued by Interlace, identity checks by
        // SumSub, funding from USDC/USDT deposits. A company API key in the
        // `x-api-key` header; every user-scoped call names the Lepo user id.
        // Inbound webhooks carry a lowercase hex HMAC-SHA256 of the raw body
        // (header X-LepoFinance-Signature) keyed by `webhook_secret`.
        // Tier and card-type ids are partner data: env only, never in code.
        'lepo' => [
            'driver'               => 'lepo',
            'base_url'             => env('LEPO_BASE_URL', 'https://api.lepofinance.com/api/v2'),
            'api_key'              => env('LEPO_API_KEY'),
            'webhook_secret'       => env('LEPO_WEBHOOK_SECRET', ''),
            'card_type_id_virtual' => env('LEPO_CARD_TYPE_ID_VIRTUAL'),
            'tier_id'              => env('LEPO_TIER_ID'),
            'timeout_seconds'      => (int) env('LEPO_TIMEOUT_SECONDS', 15),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | JIT Funding Configuration
    |--------------------------------------------------------------------------
    |
    | Just-in-Time funding settings for real-time card authorization.
    |
    */

    'jit_funding' => [
        // Maximum latency budget for authorization (milliseconds)
        'latency_budget_ms' => env('CARD_AUTH_LATENCY_BUDGET', 2000),

        // Default stablecoin for funding card transactions
        'default_token' => env('CARD_FUNDING_TOKEN', 'USDC'),

        // Supported tokens for card funding
        'supported_tokens' => ['USDC', 'USDT', 'DAI'],

        // Whether to allow partial approvals
        'allow_partial_approval' => env('CARD_ALLOW_PARTIAL', false),

        // Per-card rate limit: maximum JIT authorization attempts per 60-second window.
        // Prevents a compromised card token from flooding the authorization pipeline.
        'max_auth_per_minute' => (int) env('CARD_JIT_MAX_AUTH_PER_MINUTE', 10),

        // Live issuers only (CARD_ISSUER other than demo): approved amounts
        // are held against the cardholder's balance for this long, so later
        // authorizations cannot spend the same funds.
        'hold_ttl_seconds' => (int) env('CARD_JIT_HOLD_TTL_SECONDS', 604800),
    ],

    /*
    |--------------------------------------------------------------------------
    | Card Limits
    |--------------------------------------------------------------------------
    */

    'limits' => [
        // Maximum cards per user
        'max_cards_per_user' => env('CARD_MAX_PER_USER', 5),

        // Default daily spending limit (in USD cents)
        'default_daily_limit' => env('CARD_DAILY_LIMIT', 500000), // $5,000

        // Default per-transaction limit (in USD cents)
        'default_transaction_limit' => env('CARD_TRANSACTION_LIMIT', 100000), // $1,000
    ],

    /*
    |--------------------------------------------------------------------------
    | Apple Pay / Google Pay Configuration
    |--------------------------------------------------------------------------
    */

    'wallet_provisioning' => [
        'apple_pay' => [
            'enabled'          => env('APPLE_PAY_ENABLED', true),
            'certificate_path' => env('APPLE_PAY_CERTIFICATE_PATH'),
            'key_path'         => env('APPLE_PAY_KEY_PATH'),
            'merchant_id'      => env('APPLE_PAY_MERCHANT_ID'),
        ],

        'google_pay' => [
            'enabled'          => env('GOOGLE_PAY_ENABLED', true),
            'wallet_issuer_id' => env('GOOGLE_PAY_WALLET_ISSUER_ID'),
            'backend_url'      => env('GOOGLE_PAY_BACKEND_URL'),
        ],
    ],
];
