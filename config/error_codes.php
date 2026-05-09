<?php

declare(strict_types=1);

/*
 * Centralised error-code registry for API responses.
 *
 * All API error codes match /^ERR_[A-Z]+_\d{3}$/ per Backend-Q15.3.
 * Add new codes here before referencing them in App\Support\ErrorResponse::make().
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md
 */

return [
    // Validation
    'ERR_VALIDATION_001' => [
        'http'    => 422,
        'message' => 'Idempotency-Key header is required for this endpoint.',
    ],
    'ERR_VALIDATION_002' => [
        'http'    => 422,
        'message' => 'Malformed amount field (must be integer string with explicit decimals + currency-or-asset).',
    ],

    // Subscription lifecycle (§1, deltas Q14, Q17)
    'ERR_SUB_002' => [
        'http'    => 409,
        'message' => 'Subscription conflict — another active subscription exists for this user.',
    ],
    'ERR_SUB_003' => [
        'http'    => 403,
        'message' => 'Trial already used on this card.',
    ],
    'ERR_SUB_004' => [
        'http'    => 422,
        'message' => 'Withdrawal consent payload is missing, malformed, or stale (>5 minutes old).',
    ],
    'ERR_SUB_005' => [
        'http'    => 409,
        'message' => 'A live Stripe Checkout session already exists for this user.',
    ],
    'ERR_SUB_006' => [
        'http'    => 422,
        'message' => 'Annual to monthly downgrade is not offered.',
    ],
    'ERR_SUB_007' => [
        'http'    => 409,
        'message' => 'Cross-source subscription conflict — another store already has an active subscription for this user.',
    ],
    'ERR_SUB_010' => [
        'http'    => 422,
        'message' => 'Cancel via the App Store or Google Play — IAP subscriptions are managed store-side.',
    ],
];
