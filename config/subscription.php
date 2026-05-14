<?php

/**
 * Plan B Slice 1 — subscription module config.
 *
 * Active version of the EU withdrawal-consent text shown on Stripe Web checkout.
 * Bumping the user-facing copy requires `consent_version + 1` so dispute lookups
 * retrieve the exact wording the user accepted.
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md (Q14)
 */

declare(strict_types=1);

return [
    /*
     * Active version of the EU withdrawal-consent text shown on Stripe Web checkout.
     * Increment when the user-facing copy changes; stored alongside each consent
     * row in subscription_consent_log so dispute lookups retrieve the exact
     * wording the user accepted.
     */
    'consent_version' => (int) env('SUBSCRIPTION_CONSENT_VERSION', 1),

    /*
     * Acceptable staleness window between consent.acceptedAt and request time.
     */
    'consent_max_age_seconds' => 300,

    /*
     * Outbox worker — backoff caps before a row is marked failed.
     */
    'outbox' => [
        'max_attempts'          => 5,
        'retry_backoff_seconds' => 30,
    ],

    /*
     * Versioned consent texts. The webhook handler reconstructs the snapshot
     * by looking up the version sent in Stripe metadata. New copy = new key.
     */
    'consent_texts' => [
        1 => 'I understand that my subscription begins immediately and I waive my 14-day right of withdrawal.',
    ],

    /*
     * Plan B Slice 2 — IAP (Apple App Store + Google Play) configuration.
     *
     * `product_ids` maps internal plan key → store product identifier. The
     * store product IDs must EXACTLY match what is configured in App Store
     * Connect and Google Play Console; mobile reads the same SKU values via
     * EXPO_PUBLIC_PRO_*_SKU. Any mismatch surfaces as ERR_SUB_001 at verify
     * time.
     *
     * `IAP_RECEIPT_PEPPER` is a one-way HMAC pepper for original_transaction_id
     * pseudonymisation (Backend-Q7 α). It CANNOT be rotated cleanly — once
     * rotated, previously-scrubbed rows become orphaned (raw IDs were nulled).
     */
    'iap' => [
        'receipt_pepper' => (string) env('IAP_RECEIPT_PEPPER', ''),

        // SECURITY: when true, the Apple JWS verifier accepts payloads without
        // validating the x5c certificate chain. Intended for staging only;
        // setting this in production allows any authenticated user to forge a
        // receipt with arbitrary originalTransactionId / expiresDate and
        // unlock Pro. Defaults to false so production fails closed until the
        // real chain-validation implementation lands (tracked follow-up).
        'apple_jws_verification_bypass' => (bool) env('APPLE_JWS_VERIFICATION_BYPASS', false),

        'apple' => [
            'bundle_id' => (string) env('APPLE_BUNDLE_ID', 'app.zelta'),
            // Apple App Store Server Notifications V2 are JWS-signed; the
            // verification key is Apple's certificate chain, not an env secret.
            // The verifier pins Apple Root CA G3 / WWDR G6 fingerprints.
            'product_ids' => [
                'monthly_pro' => (string) env('APPLE_PRODUCT_MONTHLY_PRO', 'zelta_pro_monthly'),
                'annual_pro'  => (string) env('APPLE_PRODUCT_ANNUAL_PRO', 'zelta_pro_annual'),
            ],
        ],

        'google' => [
            'package_name' => (string) env('GOOGLE_PACKAGE_NAME', 'app.zelta'),
            // Path to the service account JSON key file (recommended for
            // production — keep outside webroot).
            'service_account_path' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_PATH', null),
            // Alternative: raw or base64-encoded JSON key content (for
            // environments where file mounts are not practical).
            'service_account_json' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_JSON', null),
            // Audience claim in the Pub/Sub push JWT — verified on RTDN
            // delivery to /webhooks/google/play.
            'webhook_audience' => (string) env('GOOGLE_PLAY_WEBHOOK_AUDIENCE', ''),
            'product_ids'      => [
                'monthly_pro' => (string) env('GOOGLE_PRODUCT_MONTHLY_PRO', 'zelta_pro_monthly'),
                'annual_pro'  => (string) env('GOOGLE_PRODUCT_ANNUAL_PRO', 'zelta_pro_annual'),
            ],
        ],
    ],
];
