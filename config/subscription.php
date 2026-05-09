<?php

declare(strict_types=1);

/*
 * Plan B Slice 1 — subscription module config.
 */

return [
    /*
     * Active version of the EU withdrawal-consent text shown on Stripe Web checkout.
     * Increment when the user-facing copy changes; stored alongside each consent
     * row in subscription_consent_log so dispute lookups retrieve the exact
     * wording the user accepted.
     */
    'consent_version' => env('SUBSCRIPTION_CONSENT_VERSION', 1),

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
];
