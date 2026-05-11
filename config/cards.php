<?php

/**
 * Plan B Slice 5 — card waitlist deposit configuration.
 *
 * This file is intentionally separate from config/cardissuance.php (the
 * Marqeta / Lithic / Stripe Issuing card issuance layer) because the
 * waitlist deposit is a pre-issuance Stripe Checkout flow with its own
 * webhook secret + return-URL allow-list.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-5-card-waitlist-deposit-design.md §15
 */

declare(strict_types=1);

return [
    /*
     * Refundable card waitlist deposit — flat €5.00 = 500 EUR cents in v1.3.0.
     * The value matches the quote (kind: card_waitlist_deposit) feeBreakdown
     * issued by Pricing slice 3. Storing the amount server-side prevents
     * tampering: the WaitlistDepositService verifies the quote's breakdown
     * matches this constant before creating the Checkout session.
     */
    'deposit_amount_cents' => (int) env('CARD_DEPOSIT_AMOUNT_CENTS', 500),
    'deposit_decimals'     => 2,
    'deposit_currency'     => env('CARD_DEPOSIT_CURRENCY', 'EUR'),

    /*
     * Time window the user has to complete the Stripe Checkout session.
     * After this, cards:purge-expired-deposits marks the row as expired.
     * Stripe's own session expiry is 24 hours by default; we mirror that
     * here so our cleanup cron matches.
     */
    'session_ttl_hours' => 24,

    /*
     * Refund window: paid_at + 18 months. After this, deposits become
     * eligible for the eighteen_month_auto refund cron (future slice).
     * Per Q9.2 this is FROZEN at the moment paid_at is set — never
     * recalculated based on the current policy.
     */
    'refund_eligible_after_months' => 18,

    /*
     * Estimated settlement delay (Stripe → user's card) shown in the
     * cancel-deposit response. Per Q9.5 the user-facing copy is
     * "5–10 business days"; the API returns the upper bound as
     * estimatedSettlementDays so mobile can present a range.
     */
    'refund_estimated_settlement_days' => 10,

    /*
     * Allow-list of valid Checkout success / cancel return URLs. The first
     * entry is the default when the client doesn't supply one. Any URL
     * outside this list returns ERR_CARDS_005 (422).
     */
    'allowed_return_urls' => array_values(array_filter([
        env('CARD_DEPOSIT_DEFAULT_SUCCESS_URL', 'zelta://cards/waitlist/deposit/success'),
        env('CARD_DEPOSIT_DEFAULT_CANCEL_URL', 'zelta://cards/waitlist/deposit/cancel'),
        // Web fallback (browser-based Checkout completion).
        rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/cards/waitlist/deposit/success',
        rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/cards/waitlist/deposit/cancel',
    ])),

    /*
     * Stripe webhook signing secret for POST /webhooks/stripe/cards. Distinct
     * from STRIPE_WEBHOOK_SECRET (CGO) and STRIPE_SUBSCRIPTION_WEBHOOK_SECRET
     * (slice 1) so each can rotate independently.
     */
    'stripe_webhook_secret' => env('STRIPE_CARDS_WEBHOOK_SECRET'),

    /*
     * Daily 24-hour-expired-session purge cap. Hitting this cap logs a
     * warning (indicates a backlog accumulating faster than the cron
     * drains it).
     */
    'purge_per_run_cap' => 1000,
];
