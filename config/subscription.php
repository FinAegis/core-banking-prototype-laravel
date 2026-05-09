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
];
