<?php

/**
 * Plan B Commercial v1.3.0 error code registry.
 *
 * Every code emitted by Plan B endpoints MUST be registered here. Codes
 * must match `/^ERR_[A-Z]+_\d{3}$/` (enforced by a contract test). HTTP
 * status is mapped at this level, never inferred from the code suffix
 * (avoids the `ERR_QUOTE_410` confusion called out in the original §0.2).
 *
 * Use App\Support\ErrorResponse::make($code) to emit.
 *
 * @see docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md §0.2 (Errors)
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md Q15.3
 *
 * @return array<string, array{http: int, description: string}>
 */

declare(strict_types=1);

return [
    // ─── Cross-cutting (validation, idempotency, currency) ─────────────────
    'ERR_VALIDATION_001'  => ['http' => 422, 'description' => 'Idempotency-Key header is required for this endpoint.'],
    'ERR_VALIDATION_002'  => ['http' => 422, 'description' => 'Money field is malformed.'],
    'ERR_VALIDATION_003'  => ['http' => 422, 'description' => 'Idempotency-Key has invalid format.'],
    'ERR_IDEMPOTENCY_409' => ['http' => 409, 'description' => 'Same Idempotency-Key presented with a different request body.'],
    'ERR_CUR_001'         => ['http' => 400, 'description' => 'Currency must be EUR in v1.3.x.'],

    // ─── Subscription (deltas Q14, Q17) ────────────────────────────────────
    'ERR_SUB_001' => ['http' => 422, 'description' => 'Invalid receipt.'],
    'ERR_SUB_002' => ['http' => 409, 'description' => 'Already subscribed via different store.'],
    'ERR_SUB_003' => ['http' => 403, 'description' => 'Trial already used.'],
    'ERR_SUB_004' => ['http' => 422, 'description' => 'Withdrawal consent missing or stale.'],
    'ERR_SUB_005' => ['http' => 409, 'description' => 'Live incomplete checkout session for user; recovery URL provided.'],
    'ERR_SUB_006' => ['http' => 422, 'description' => 'Annual to monthly downgrade is not offered.'],
    'ERR_SUB_007' => ['http' => 409, 'description' => 'Cross-source subscription conflict — another store already has an active subscription for this user.'],
    // Slice 2 — IAP family-sharing + stale-receipt cases are emitted as
    // ERR_SUB_002 with a `conflict.kind` discriminator (family_sharing_unsupported,
    // stale_receipt) so mobile can switch on a single field. The dedicated
    // ERR_SUB_008 / ERR_SUB_009 codes were dropped 2026-05-14 after the mobile
    // contract review (subscriptionConflict.ts:14 enum is the source of truth).
    'ERR_SUB_010' => ['http' => 409, 'description' => 'Cancellation must occur at originating store (Apple/Google).'],

    // ─── Quote / Pricing (deltas Q2, Q3 — codes stay ERR_QUOTE_* per Backend-Q4) ──
    // ERR_QUOTE_* codes: used at redemption time (consumed by submit/checkout/deposit).
    // ERR_QUO_*   codes: used at quote issuance / lookup time.
    'ERR_QUOTE_001' => ['http' => 410, 'description' => 'Quote expired.'],
    'ERR_QUOTE_002' => ['http' => 409, 'description' => 'Submitted payload does not match quoted userOp hash.'],
    // Registered in companion hotfix PR fix(plan-b): error code registry #1041
    'ERR_QUO_002' => ['http' => 409, 'description' => 'Quote already consumed.'],
    // Registered in slice 3 (Pricing quote endpoint)
    'ERR_QUO_001' => ['http' => 404, 'description' => 'Quote not found.'],
    'ERR_QUO_005' => ['http' => 429, 'description' => 'Quote rate limit exceeded. Try again shortly.'],
    'ERR_QUO_006' => ['http' => 403, 'description' => 'KYC level insufficient for ramp operations.'],
    'ERR_QUO_007' => ['http' => 403, 'description' => 'Destination address is blocked.'],
    'ERR_QUO_008' => ['http' => 400, 'description' => 'Insufficient balance for swap source asset.'],

    // ─── Fee resolver ──────────────────────────────────────────────────────
    'ERR_FEE_001' => ['http' => 500, 'description' => 'Fee tier could not be resolved.'],

    // ─── Exports (deltas Q13) ─────────────────────────────────────────────
    'ERR_EXP_001' => ['http' => 429, 'description' => 'Export rate limit exceeded.'],
    'ERR_EXP_002' => ['http' => 410, 'description' => 'Export artifact has been purged.'],
    'ERR_EXP_003' => ['http' => 500, 'description' => 'Export job failed.'],

    // ─── Cue queue (slice 4) ──────────────────────────────────────────────
    'ERR_CUE_001' => ['http' => 404, 'description' => 'Cue not found or does not belong to the authenticated user.'],

    // ─── Card waitlist deposit (slice 5) ───────────────────────────────────
    'ERR_CARDS_001' => ['http' => 404, 'description' => 'User is not on the card waitlist.'],
    'ERR_CARDS_002' => ['http' => 409, 'description' => 'User already has an active card waitlist deposit.'],
    'ERR_CARDS_003' => ['http' => 404, 'description' => 'No active card waitlist deposit found to cancel.'],
    'ERR_CARDS_004' => ['http' => 409, 'description' => 'Deposit state conflict — already shipped or cancellation already in progress.'],
    'ERR_CARDS_005' => ['http' => 422, 'description' => 'Invalid return URL — not in the configured allow-list.'],
    'ERR_CARDS_006' => ['http' => 409, 'description' => 'Quote kind is not card_waitlist_deposit.'],
];
