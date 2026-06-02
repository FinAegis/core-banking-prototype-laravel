<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | KYC Purpose Routing
    |--------------------------------------------------------------------------
    |
    | Maps a KycPurpose value to the provider name that should handle it.
    | KycProviderRouter::resolve(KycPurpose) reads from this config.
    |
    | Per docs/BACKEND_HANDOVER_BRIDGE_RAMP.md §7.5, purposes are partitioned
    | — Ondato data and Bridge data must not be conflated. A user who
    | completes TRUSTCERT KYC under Ondato does NOT automatically pass RAMP
    | KYC under Bridge, and vice versa.
    */

    'routing' => [
        'trustcert' => env('KYC_TRUSTCERT_PROVIDER', 'ondato'),
        'ramp'      => env('KYC_RAMP_PROVIDER', 'bridge'),
        'cards'     => env('KYC_CARDS_PROVIDER', 'bridge'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configuration
    |--------------------------------------------------------------------------
    |
    | Ondato config remains in config/services.php (existing layout). Bridge
    | gets its own block here. New providers register their config under
    | this `providers` key.
    */

    'providers' => [
        'ondato' => [
            // Existing Ondato credentials are read from config('services.ondato.*')
            // by OndatoService. OndatoKycProvider is a thin adapter; it does not
            // duplicate the config surface.
        ],

        'bridge' => [
            'api_key' => env('BRIDGE_API_KEY'),
            // Legacy shared-secret HMAC scheme (kept for tests / older tenants).
            'webhook_secret' => env('BRIDGE_WEBHOOK_SECRET'),
            // Current "Bridge by Stripe" platform: per-endpoint RSA public key
            // (PEM, or base64-encoded PEM) used to verify X-Webhook-Signature.
            // This is NOT a secret — it is the public half Bridge exposes in
            // the dashboard webhook detail view.
            'webhook_public_key' => env('BRIDGE_WEBHOOK_PUBLIC_KEY', ''),
            'base_url'           => env('BRIDGE_API_BASE_URL', 'https://api.bridge.xyz'),
            // Default per-customer developer fee (Free tier). Pro upgrade
            // PATCHes the customer to 0. See ADR-0006.
            'default_developer_fee_bps' => (int) env('BRIDGE_DEFAULT_DEV_FEE_BPS', 75),
        ],
    ],
];
