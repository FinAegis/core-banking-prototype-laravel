# Plan B v1.3.0 — Slice 2: IAP (Apple App Store + Google Play)

**Date:** 2026-05-10
**Author:** Backend Architect
**Status:** Spec — revised with grilling pass corrections (Revision 2, 2026-05-11)
**Slice predecessor:** Slice 1 (Cashier Stripe subscription path) — merged to `main` as commit `957ea3d8` via PR #1037
**Estimated implementation effort:** 6–7 engineer-days
**Mobile target:** Zelta v1.3.0

---

## 1. Working directory and authorisation

You are in a git worktree branched off `main` (which already includes slice 1 from PR #1037).

- Create branch: `feat/plan-b-slice-2-iap`
- Worktree base: `origin/main` (post-slice-1)
- Do NOT modify any files outside `app/Domain/Subscription/`, `routes/api.php`, `config/`, and `database/migrations/` for the implementation phase.
- Do NOT modify `bootstrap/app.php` or `composer.json` unless a new vendor package is introduced — coordinate with the controller before adding dependencies.
- Commit + push + open PR against `main` titled `feat(subscription): slice 2 — IAP (Apple + Google Play)`.
- Do NOT merge; the human reviewer will.
- After all commits are clean (php-cs-fixer, PHPStan L8, Pest pass), report your status using the format in §13.

---

## 2. Situation summary

### What slice 1 delivered

Slice 1 (PR #1037) delivered the **Stripe-only** subscription path:

- `POST /api/v1/subscription/checkout` — creates a Stripe Setup-mode Checkout session (with trial-abuse fingerprint gate via `TrialFingerprintService`)
- `POST /api/v1/subscription/change-plan`, `POST /api/v1/subscription/cancel`, `POST /api/v1/subscription/reactivate` — Cashier-backed lifecycle endpoints
- `POST /webhooks/stripe/subscriptions` — Stripe webhook handler writing to `revenue_outbox_events` and `processed_webhook_events`
- `SubscriptionProjection` — unified read model with stubs for the IAP join (`hasActiveProSubscription(source: 'iap')` returns `false` always in slice 1 but the call sites are already wired)
- Database tables: `processed_webhook_events`, `trial_card_fingerprints`, `subscription_consent_log`, `revenue_outbox_events`, `revenue_events`

### What slice 2 adds

Slice 2 wires **Apple App Store** and **Google Play** native IAP into the same projection slice 1 designed. The single most important endpoint is `POST /api/v1/subscription/iap/verify` — without it, neither store will approve the app for digital-goods purchases.

At a high level, slice 2 adds:

1. **`POST /api/v1/subscription/iap/verify`** — the mobile-P0 endpoint that validates a store receipt/token server-side and returns the same `GET /subscription/me` shape.
2. **`POST /webhooks/apple/notifications`** — App Store Server Notifications V2 receiver.
3. **`POST /webhooks/google/play`** — Google Play Real-Time Developer Notifications (RTDN) receiver.
4. **`iap_subscriptions` and `iap_receipts` tables** — custom event-sourced storage for the IAP path (rationale: Apple/Google notifications arrive partial/out-of-order; internal event replay is genuinely useful here, per Backend-Q1 decision).
5. **`SubscriptionProjection` IAP join** — fills in `hasActiveProSubscription(source: 'iap')` so the existing Stripe-path conflict gate (which already calls this) correctly rejects cross-store conflicts.
6. **IAP product ID → internal plan key config** — equivalent of `config/services.stripe.subscription_prices` for the store paths.
7. **`iap_receipts` pseudonymisation columns** (Backend-Q7 α) — `original_transaction_id_hash`, `scrubbed_at`, `scrubbed_renewal_count` — and the corresponding GdprController erasure walk extension.

### Where slice 2 plugs in

The projection design from slice 1 was deliberately forward-compatible. Two concrete seams:

- `SubscriptionProjection::hasActiveProSubscription($user, source: 'iap')` — returns `false` in slice 1, returns a real DB read in slice 2. No call-site changes required in slice 2.
- `SubscriptionProjection::for($user)` — slice 1 reads only Cashier's `subscriptions` table. Slice 2 adds a `LEFT JOIN`-style union: if `iap_subscriptions` has an active row and Cashier does not (or `iap_subscriptions` has a more recently activated row), the IAP source wins. The returned wire shape is identical — `source` field will now carry `apple_iap` or `google_play` where applicable.

---

## 3. Read these BEFORE writing code (in order)

These files are the canonical source of truth. Read them in this order; the later files override the earlier ones on any point of conflict.

1. **`docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md`** — architectural baseline for all slice 2 decisions. Key sections:
   - `## Subscription architecture baseline (Backend grilling, Q1)` — the Backend-Q1 (γ) hybrid decision: Cashier for Stripe, custom for IAP, unified projection. IAP uses `iap_subscriptions` + `iap_subscription_events`.
   - `## IAP receipt pseudonymisation (Backend grilling, Q7)` — the Backend-Q7 (α) decision: pseudonymise, not delete. Full `iap_receipts` schema additions, erasure walk ordering, `IAP_RECEIPT_PEPPER` rotation caveat, webhook hash-fallback logic.
   - `### Q6 — Multi-store conflict (cause-distinguishing)` — the five `ERR_SUB_002` `kind` values (`two_stores_active`, `different_zelta_user`, `family_sharing_unsupported`, `stale_receipt`) and their distinct recovery paths.
   - Search for "IAP", "Apple", "Play", "iap_receipts", "ERR_SUB_002", "ERR_SUB_010" throughout.

2. **`docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §1** — original subscription surface area. §1.2 defines the `POST /api/v1/subscription/iap/verify` request/response shape, the webhook endpoint URLs and event lists (§1.3), and §1.5's multi-store conflict policy (superseded by deltas Q6 for `ERR_SUB_002` detail).

3. **`docs/adr/0002-revenue-projection-dual-upstream.md`** — IAP subscription fees are off-chain PSP events, so they travel the outbox-saga path: IAP webhook handler writes to `revenue_outbox_events` + `processed_webhook_events` in one transaction; `ProjectRevenueOutbox` worker fans them into `revenue_events`. Same infrastructure as slice 1's Stripe path.

4. **`docs/adr/0003-pricing-bounded-context.md`** — IAP product IDs (`zelta_pro_monthly`, `zelta_pro_annual`) are the store-native analogue of Stripe's `STRIPE_PRICE_*` env keys. Slice 2 needs an equivalent config-keyed map (`config/subscription.php` IAP product IDs → internal plan keys). The `Domain/Pricing` bounded context owns `ResolveFeeTier`; slice 2 reads tier via the same projection path.

5. **`docs/adr/0004-money-on-the-wire.md`** — Apple's `signedTransactionInfo` returns prices in `price` + `currency` fields (currency-amount in the storefront's currency). Google Play's `SubscriptionPurchase` returns `priceAmountMicros` (6 implicit decimals). Both must be mapped to the `Money` VO (`amount`, `decimals`, `currency`) before storage in `revenue_outbox_events`. Never store raw micros without the `decimals: 6` annotation.

6. **`app/Domain/Subscription/Projections/SubscriptionProjection.php`** — understand the current shape. Slice 2 adds the `hasActiveProSubscription(source: 'iap')` implementation and the `for()` union logic. The existing Stripe path inside `for()` is unchanged; the IAP path is additive.

7. **`app/Domain/Subscription/Services/SubscriptionService.php`** — slice 1's service entry point. Slice 2 adds an `IapSubscriptionService` in a sibling file (`app/Domain/Subscription/Iap/IapSubscriptionService.php`) with the same response-shape convention (return `['code' => '...']` for errors, `['success' => [...]]` for ok). Do not add IAP logic into the existing `SubscriptionService` — keep the source-specific code isolated per the Backend-Q1 rationale.

8. **`app/Domain/Subscription/Webhooks/SubscriptionWebhookController.php`** — the Stripe webhook handler is the structural template for the Apple and Google webhook controllers. Mirror the dedup + outbox write transaction pattern exactly.

   > **Exception to "mirror exactly":** The Stripe handler returns HTTP 500 on `Throwable` in its `catch` block. Apple ASN V2 and Google RTDN handlers MUST return 200 even on processing errors (log the exception; never propagate a 5xx to the store). Stripe permits 500-retry semantics because Stripe will retry on 5xx; App Store and Play Store treat any non-2xx as a failed delivery and retry indefinitely, which can cause duplicate-processing storms. Acceptance criterion §9 (line 912) makes this explicit.

9. **`config/error_codes.php`** — the current registered codes after the companion `fix(plan-b): error code registry` hotfix PR: `ERR_SUB_001` through `ERR_SUB_007` and `ERR_SUB_010` are taken. `ERR_SUB_001` + `ERR_CUR_001` + `ERR_QUO_002` were added by that companion hotfix; `ERR_SUB_010` HTTP code was corrected from 422 → 409 there too. `ERR_SUB_008` and `ERR_SUB_009` remain free. Use `ERR_SUB_008` for family-sharing receipt rejection and `ERR_SUB_009` for stale/expired receipt (see §5).

10. **`CLAUDE.md`** — project conventions. Especially: `assert()` as auth guard (don't), webhook auth bypass pattern (`app()->environment('local', 'testing')` gated), `Idempotency-Key` is a header not a body field, BCMath for all money arithmetic.

---

## 4. Existing repo state (foundations slice 2 builds on)

The following is already in `main` after slice 1. Do not re-create any of it.

### Tables (created by slice 1 migrations)

| Table | Purpose |
|---|---|
| `processed_webhook_events` | Dedup by `(provider, event_id)`. Slice 2 reuses provider values `apple` and `google`. |
| `revenue_outbox_events` | Off-chain outbox. `source_type` ENUM already includes `apple_iap` and `google_play` in the docblock. |
| `revenue_events` | Unified revenue read model. |
| `trial_card_fingerprints` | Stripe trial-abuse prevention (no IAP equivalent — Apple/Google enforce at store level). |
| `subscription_consent_log` | `subscription_id` FK is nullable — slice 2 writes a row referencing `iap_subscriptions.id` after IAP subscription creation. |
| Cashier's `subscriptions` + `subscription_items` | Stripe-only; do not touch. |

### Services / classes

| Class | Location | Status |
|---|---|---|
| `SubscriptionProjection` | `app/Domain/Subscription/Projections/SubscriptionProjection.php` | Exists; IAP join is a stub (`return false`) |
| `SubscriptionService` | `app/Domain/Subscription/Services/SubscriptionService.php` | Exists; Stripe-only |
| `TrialFingerprintService` | `app/Domain/Subscription/Services/TrialFingerprintService.php` | Exists; Stripe-only (Apple/Google enforce trial at store level) |
| `ConsentLogWriter` | `app/Domain/Subscription/Services/ConsentLogWriter.php` | Exists; reuse for IAP path |
| `SubscriptionWebhookController` | `app/Domain/Subscription/Webhooks/SubscriptionWebhookController.php` | Exists; Stripe-only |
| `ProjectRevenueOutbox` (job) | `app/Domain/Subscription/Jobs/ProjectRevenueOutbox.php` | Exists; already processes any `source_type` |
| `RevenueOutboxEvent` model | `app/Domain/Subscription/Models/RevenueOutboxEvent.php` | Exists |
| `ProcessedWebhookEvent` model | `app/Domain/Subscription/Models/ProcessedWebhookEvent.php` | Exists |

### Config

| Key | File | Status |
|---|---|---|
| `services.stripe.subscription_prices` | `config/services.php` | Exists; maps `monthly_pro`/`annual_pro` to Stripe price IDs |
| `subscription.consent_texts` | `config/subscription.php` | Exists |
| IAP product IDs | — | **Does not exist yet; slice 2 must add** |

### Routes (registered in `routes/api.php`)

Slice 1 registered:
- `POST /api/v1/subscription/checkout`
- `POST /api/v1/subscription/cancel`
- `POST /api/v1/subscription/change-plan`
- `POST /api/v1/subscription/reactivate`
- `GET /api/v1/subscription/me`
- `POST /webhooks/stripe/subscriptions`

Slice 2 must register:
- `POST /api/v1/subscription/iap/verify`
- `POST /webhooks/apple/notifications`
- `POST /webhooks/google/play`

---

## 5. Slice 2 scope — BUILD this

### 5.1 Endpoint: `POST /api/v1/subscription/iap/verify`

**Auth:** Sanctum bearer token, ability `write`.
**Idempotency-Key header:** required. Returns `ERR_VALIDATION_001` if missing. Cached response returned within 24h on replay.

**Request body:**

Mobile sends the fields below. The wire shape is confirmed by mobile-dev (expo-iap 4.2.3 / Expo SDK 54, iOS 15.1+ minimum — StoreKit 2 always; no legacy receipt blob path):

```json
{
  "platform": "apple_iap",
  "receipt": "<JWS signed transaction string>",
  "originalTransactionId": "<StoreKit 2 stable id>",
  "productId": "zelta_pro_monthly",
  "appVersion": "1.3.0",
  "currency": "EUR"
}
```

For Google Play:

```json
{
  "platform": "google_play",
  "receipt": "<purchaseToken verbatim>",
  "productId": "zelta_pro_monthly",
  "appVersion": "1.3.0",
  "currency": "EUR"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `platform` | `"apple_iap"` \| `"google_play"` | yes | Determines which verification path runs. Replaces `store` + `platform` split from earlier draft — mobile uses this single field. |
| `receipt` | string | yes | Apple: JWS-signed transaction string from StoreKit 2 (starts with `ey`). Google: raw `purchaseToken`. |
| `originalTransactionId` | string | Apple only | StoreKit 2 stable transaction id — used as the iOS-side stable PK for `iap_subscriptions` (see §5.2). Not sent for Google. |
| `productId` | string | yes | Must be in the IAP product ID config map (see §5.6). Allowed values: `zelta_pro_monthly`, `zelta_pro_annual`. Unknown product ID → `ERR_SUB_001`. |
| `appVersion` | string | yes | Logged for diagnostics; not validated. |
| `currency` | `"EUR"` | yes | Must be `"EUR"` in v1.3.0 per ADR-0004 / `ERR_CUR_001`. |
| `withdrawalConsent` | object | no | Optional in v1.3.0 — not populated by native mobile. Required from v1.3.1 (see §8.6). |

**Success response (200):** Same shape as `GET /api/v1/subscription/me` (the `SubscriptionProjection::for($user)` array serialised). Plus one extra field on first-verification:

```json
{
  "tier": "pro",
  "status": "active",
  "source": "apple_iap",
  "plan": "monthly_pro",
  "currentPeriodEnd": "2026-06-10T12:00:00Z",
  "trialEndsAt": null,
  "cancelledAtPeriodEnd": false,
  "pausedUntil": null,
  "reactivated": false
}
```

`reactivated: true` is returned when the inbound receipt's `originalTransactionId` matches an `iap_subscriptions` row with `cancelled_at_period_end = true` that has not yet expired (re-subscribe within grace period, per deltas Q6 reactivation note).

**Error codes (HTTP status in parentheses):**

| Code | HTTP | Condition |
|---|---|---|
| `ERR_SUB_001` | 422 | Receipt verification failed (Apple/Google rejected the receipt or `productId` unknown in config) |
| `ERR_SUB_002` | 409 | Multi-store conflict: user already has an active subscription via another source. Body includes `existingSubscription.source` and `conflict.kind` (see §5.3). |
| `ERR_SUB_003` | 403 | Trial already used — returned when `productId` maps to a trial-eligible plan and `users.has_used_trial = true` (Apple/Google also enforce at store level, but we gate server-side too). |
| `ERR_SUB_008` | 409 | Family Sharing receipt: `appAccountToken` / `obfuscatedAccountId` does not match the authenticated user. User should manage subscription from the original purchaser account. |
| `ERR_SUB_009` | 422 | Receipt is expired or its subscription has already been cancelled/expired before this call. |
| `ERR_VALIDATION_001` | 422 | Missing `Idempotency-Key` header. |
| `ERR_VALIDATION_002` | 422 | Malformed money field or platform/store mismatch. |
| `ERR_CUR_001` | 400 | `currency` is not `"EUR"`. |

**Processing sequence (within a DB transaction):**

1. Validate request shape.
2. Acquire Redis lock `lock:subscription:{userId}` (TTL 30s) — matches slice 1's pattern.
3. Run `ProcessedWebhookEvent` dedup on a synthetic event ID derived from `(platform, productId, originalTransactionId_or_purchaseToken)` — prevents double-processing a receipt submitted twice before the async verification completes. For Apple, use the mobile-provided `originalTransactionId` field as the stable part of the dedup key. For Google, use the raw `purchaseToken` (backend will obtain the stable `play_subscription_resource_id` from the Play Developer API in step 4).
4. Verify receipt against the store: Apple path — JWS local verification using Apple's certificate chain (see §8.1 — DECIDED: StoreKit 2 JWS, no legacy `verifyReceipt` call). Google path — call Play Developer API `purchases.subscriptionsv2.get` with the `purchaseToken` (see §8.7 — DECIDED: Play Developer API introspection).
5. Check multi-store conflict via `SubscriptionProjection::hasActiveProSubscription($user, source: 'stripe')` and `hasActiveProSubscription($user, source: opposing_iap_store)`. If conflict: return `ERR_SUB_002` with the `conflict.kind` field per §5.3.
6. Check `appAccountToken` (Apple) / `obfuscatedAccountId` (Google) matches `user->id`. If mismatch: return `ERR_SUB_008`.
7. Check if receipt maps to an existing `iap_subscriptions` row. Apple: look up by `original_transaction_id` (from the mobile-provided `originalTransactionId` field — the StoreKit 2 stable id). Google: look up by `play_subscription_resource_id` (extracted from the Play Developer API response). If existing row is `cancelled_at_period_end = true` and not yet expired: reactivate (clear `cancelled_at_period_end`, return `reactivated: true`). If existing row is fully expired: return `ERR_SUB_009`.
8. Create `iap_subscriptions` row and `iap_receipts` row in the same transaction.
9. Write `subscription_consent_log` row (if consent payload present in request; note: withdrawal-consent flow for IAP is mobile-side pre-purchase; see §8 open question).
10. Write `revenue_outbox_events` row (event kind `iap_subscription_initial`).
11. Refresh `SubscriptionProjection` (in-process; the `for($user)` call always reads fresh from DB).
12. Broadcast `subscription.changed` + `entitlements.changed` on `private-user.{userId}` (matches slice 1's WebSocket broadcast pattern).
13. Return `200` with the projection shape.

All of steps 8–12 are inside a single `DB::transaction()`. The lock is released after the transaction commits.

### 5.2 Tables

#### `iap_subscriptions`

```sql
CREATE TABLE iap_subscriptions (
    id                              CHAR(36) PRIMARY KEY,
    user_id                         CHAR(36) NOT NULL,
    store                           ENUM('apple', 'google') NOT NULL,
    tier                            VARCHAR(32) NOT NULL,
    status                          VARCHAR(32) NOT NULL,
    original_transaction_id         VARCHAR(255) NULL,
    play_subscription_resource_id   VARCHAR(255) NULL,
    google_purchase_token_hash      CHAR(64) NULL,
    apple_app_account_token         CHAR(36) NULL,
    google_obfuscated_account_id    VARCHAR(255) NULL,
    trial_started_at                TIMESTAMP NULL,
    trial_ends_at                   TIMESTAMP NULL,
    current_period_starts_at        TIMESTAMP NULL,
    current_period_ends_at          TIMESTAMP NULL,
    cancel_at_period_end            BOOLEAN NOT NULL DEFAULT FALSE,
    paused_at                       TIMESTAMP NULL,
    paused_until                    TIMESTAMP NULL,
    cancelled_at                    TIMESTAMP NULL,
    expired_at                      TIMESTAMP NULL,
    refunded_at                     TIMESTAMP NULL,
    last_notification_type          VARCHAR(64) NULL,
    last_event_id                   CHAR(36) NULL,
    created_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_iap_sub_user (user_id),
    UNIQUE KEY uniq_iap_sub_apple (original_transaction_id),
    UNIQUE KEY uniq_iap_sub_play_resource (play_subscription_resource_id),
    KEY idx_iap_sub_google_hash (google_purchase_token_hash),
    KEY idx_iap_sub_period_end (current_period_ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Partial unique active sub via generated column (mirrors Cashier table pattern from §1.7)
ALTER TABLE iap_subscriptions
    ADD COLUMN active_user_id CHAR(36)
        AS (CASE WHEN status IN ('active','trialing','past_due','grace_period','paused')
                 THEN user_id END)
        STORED,
    ADD UNIQUE KEY uniq_iap_active_per_user (active_user_id);
```

> **One-active-sub invariant:** The unique key is single-column `(active_user_id)`, not `(store, active_user_id)`. The `(store, active_user_id)` composite would permit one Apple sub AND one Google sub simultaneously for the same user, violating the commercial spec §1.5 ("A user can only have one active subscription at a time") and the conflict gate in §5.3. The single-column key matches the `subscriptions` table's single-active invariant; a cross-store conflict surfaces as `ERR_SUB_002` at `/iap/verify` time.

Notes:
- `original_transaction_id`: Apple stable PK. The StoreKit 2 `originalTransactionId` sent by mobile is the stable identifier across renewals, cancellations, and resubscriptions. Mobile supplies this directly in the `originalTransactionId` request field. The `UNIQUE` constraint prevents double-insert.
- `play_subscription_resource_id`: Google stable PK. **NOT** derived from `orderId` — see §8.7 (DECIDED). Backend calls Play Developer API `purchases.subscriptionsv2.get` on the raw `purchaseToken`; the canonical subscription resource id returned from that response is stored here. This is stable across the `linkedPurchaseToken` chain (renewal/cancel/resubscribe). The `UNIQUE` constraint is on this column.
- `google_purchase_token_hash`: SHA-256 of the most recent `purchaseToken` (updated on each renewal notification). Same pattern as `subscriptions.google_purchase_token_hash` from the commercial spec §1.7; indexable without storing the full token.
- `paused_until` lives here only — Apple's pause feature has no Stripe equivalent (Backend-Q1 #3).
- `status` ∈ `active | trialing | past_due | grace_period | paused | cancelled | expired | refunded`.

#### `iap_subscription_events` (Spatie-style event store)

```sql
CREATE TABLE iap_subscription_events (
    id              CHAR(36) PRIMARY KEY,
    aggregate_uuid  CHAR(36) NOT NULL,
    aggregate_version INT UNSIGNED NOT NULL,
    event_class     VARCHAR(255) NOT NULL,
    event_payload   JSON NOT NULL,
    metadata        JSON NOT NULL,
    created_at      TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_ise_aggregate (aggregate_uuid, aggregate_version),
    UNIQUE KEY uniq_ise_version (aggregate_uuid, aggregate_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Rationale (Backend-Q1): Apple/Google Server Notifications arrive partial/out-of-order; having an event replay capability covers out-of-order notification processing and provides an audit trail for subscription state reconstruction.

#### `iap_receipts`

```sql
CREATE TABLE iap_receipts (
    id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id                     CHAR(36) NULL,
    iap_subscription_id         CHAR(36) NOT NULL,
    store                       ENUM('apple', 'google') NOT NULL,
    original_transaction_id     VARCHAR(255) NULL,
    original_transaction_id_hash CHAR(64) NULL,
    apple_app_account_token     CHAR(36) NULL,
    google_obfuscated_account_id VARCHAR(255) NULL,
    product_id                  VARCHAR(255) NOT NULL,
    transaction_id              VARCHAR(255) NULL,
    receipt_blob                TEXT NULL,
    tier                        VARCHAR(32) NOT NULL,
    amount_smallest_unit        BIGINT NOT NULL,
    amount_decimals             TINYINT UNSIGNED NOT NULL,
    amount_currency             VARCHAR(8) NOT NULL DEFAULT 'EUR',
    period_starts_at            TIMESTAMP NULL,
    period_ends_at              TIMESTAMP NULL,
    environment                 ENUM('sandbox', 'production') NOT NULL DEFAULT 'production',
    scrubbed_at                 TIMESTAMP NULL,
    scrubbed_renewal_count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_iap_receipts_original_tx (original_transaction_id),
    KEY idx_iap_receipts_user (user_id),
    KEY idx_iap_receipts_scrubbed_hash (original_transaction_id_hash),
    KEY idx_iap_receipts_sub (iap_subscription_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Notes:
- `original_transaction_id` is NULL after erasure pseudonymisation (Backend-Q7 α). The `UNIQUE` constraint only applies to non-null values — MySQL unique indexes skip NULL values, so pseudonymised rows don't conflict. For Apple rows this is the StoreKit 2 `originalTransactionId`. For Google rows this field is unused (NULL); the stable lookup key is `google_purchase_token_hash` pointing back to the `iap_subscriptions.play_subscription_resource_id`.
- `original_transaction_id_hash = HMAC-SHA256(original_transaction_id, IAP_RECEIPT_PEPPER)`. NULL until pseudonymisation; raw `original_transaction_id` is the lookup key before that.
- `scrubbed_renewal_count`: incremented each time a RENEWAL webhook arrives for a scrubbed receipt (ops alerting at first occurrence). See §5.9. Column type is `SMALLINT UNSIGNED` (capacity 0–65535), tightened from `INT` used in the deltas Q7 source DDL — capacity is more than sufficient for renewal counts; deviation is deliberate.
- `receipt_blob`: the raw JWS string (Apple) or `purchaseToken` (Google). Stored for audit, nulled at erasure. Never expose in API responses.
- Money fields use the ADR-0004 triple: `amount_smallest_unit` (integer string via BIGINT), `amount_decimals`, `amount_currency`. Apple `signedTransactionInfo.price`: store in storefront currency with `decimals: 2`, convert to EUR at projection time. Google `priceAmountMicros`: `decimals: 6`.

### 5.3 Multi-store conflict gate

Per deltas Q6, `ERR_SUB_002` carries a `conflict.kind` field distinguishing five causes:

```json
{
  "code": "ERR_SUB_002",
  "conflict": {
    "kind": "two_stores_active",
    "existingSubscription": {
      "source": "stripe_web",
      "tier": "pro",
      "currentPeriodEnd": "2026-07-01T00:00:00Z"
    },
    "attemptedSource": "apple_iap"
  }
}
```

| `kind` | Condition | Recovery |
|---|---|---|
| `two_stores_active` | User has an active sub on another store | Mobile: offer to cancel the other store sub first, then retry IAP |
| `different_zelta_user` | `appAccountToken`/`obfuscatedAccountId` maps to a different Zelta `user_id` | Mobile: show error, suggest contacting support |
| `family_sharing_unsupported` | Receipt belongs to a family-member's Apple ID, not the authenticated user | Return `ERR_SUB_008` instead (see §5.1). The `family_sharing_unsupported` `kind` in `ERR_SUB_002` is reserved for webhook-time detection. |
| `stale_receipt` | Receipt's `originalTransactionId` exists but sub is fully expired | Return `ERR_SUB_009` instead |
| `same_store_duplicate` | Duplicate verify call for an already-active subscription from same store | Return `200` with `reactivated: false` (idempotent, per deltas Q6) |

The `SubscriptionProjection::hasActiveProSubscription($user, string $source)` implementation slice 2 provides:
- `$source === 'apple'`: query `iap_subscriptions WHERE user_id = ? AND store = 'apple' AND status IN ('active','trialing','past_due','grace_period','paused')`
- `$source === 'google'`: same query with `store = 'google'`
- `$source === 'iap'` (the generic form used by slice 1's Stripe path): `OR` of both apple and google

Slice 1's existing calls `hasActiveProSubscription($user, source: 'iap')` automatically become accurate once slice 2 implements this.

### 5.4 Webhook receiver: Apple (App Store Server Notifications V2)

**Route:** `POST /webhooks/apple/notifications` (no Sanctum auth, no CSRF — webhook from Apple's infrastructure).

**Signature verification:** Apple delivers a signed JSON Web Signature (JWS) in the request body. Verify the JWS chain against Apple's root CA certificate (`AppleRootCA-G3.cer`) using a JWT library. In `local`/`testing` environments with an empty verification key, fall through to JSON decode (matches the CLAUDE.md bypass pattern).

**Dedup key:** `notificationUUID` (present in the outer decoded `responseBodyV2DecodedPayload`, NOT inside `data.signedTransactionInfo` — that nested JWS contains transaction details only). Store as `(provider: 'apple', event_id: notificationUUID)` in `processed_webhook_events`.

**Handled notification types** (from `App Store Server Notifications V2` event list — commercial spec §1.3):

| Notification type | Subtype | Action |
|---|---|---|
| `SUBSCRIBED` | `INITIAL_BUY` | Create `iap_subscriptions` row (if not already from `/iap/verify`); write `iap_receipts` row; write outbox `iap_subscription_initial`. |
| `SUBSCRIBED` | `RESUBSCRIBE` | Reactivate existing subscription (clear `cancelled_at_period_end`). |
| `DID_RENEW` | — | Update `current_period_ends_at`; write outbox `iap_subscription_renewal`. |
| `DID_CHANGE_RENEWAL_STATUS` | `AUTO_RENEW_DISABLED` | Set `cancel_at_period_end = true`. |
| `DID_CHANGE_RENEWAL_STATUS` | `AUTO_RENEW_ENABLED` | Clear `cancel_at_period_end`. |
| `DID_FAIL_TO_RENEW` | — | Set `status = past_due`; cue dispatch is slice-4 territory (stub a log for now). |
| `GRACE_PERIOD_EXPIRED` | — | Set `status = expired`; broadcast `entitlements.changed`. |
| `EXPIRED` | `VOLUNTARY` / `BILLING_RETRY` / `BILLING_RETRY_VOLUNTARY` | Set `status = expired`; broadcast. |
| `REFUND` | — | Set `status = refunded`; write outbox negative `iap_refund`; broadcast. |
| `REFUND_DECLINED` | — | Log + restore `status = active` if previously `refunded`. |
| `REVOKE` | — | Family Sharing revoked. Set `status = expired`; broadcast. Write `cues` row `family_sharing_unsupported` (slice-4 stub for now). |
| `CONSUMPTION_REQUEST` | — | Log; no state change required in v1.3.0. |
| `DID_CHANGE_RENEWAL_PREF` | — | Update `tier` if product ID changed; log. |
| `PRICE_INCREASE` | — | Log; no state change in v1.3.0. |

All state mutations happen inside a DB transaction that atomically writes the `processed_webhook_events` dedup row and any `iap_subscriptions` / `revenue_outbox_events` row.

**Pattern:** Mirror `SubscriptionWebhookController::handle()` exactly — dedup check + lock + dispatch + outbox write in one transaction. **Exception:** Unlike the Stripe handler, this controller MUST return HTTP 200 even on `Throwable` — catch all exceptions, log them, and return 200. App Store treats any non-2xx as a failed delivery and retries indefinitely. See §3 callout and §9 acceptance criterion.

### 5.5 Webhook receiver: Google Play (Real-Time Developer Notifications)

**Route:** `POST /webhooks/google/play`.

**Auth:** Google sends the notification as a Pub/Sub push message to a registered HTTPS endpoint. The push message is authenticated via a JWT bearer token from Google's `accounts.google.com` domain. Verify using Google's JSON public keys endpoint (`https://www.googleapis.com/oauth2/v3/certs`). In `local`/`testing` with empty key: JSON decode only.

**Dedup key:** Pub/Sub `messageId` (present in the outer `message.messageId` field). Store as `(provider: 'google', event_id: messageId)`.

**Processing:**
Google's RTDN message body carries a `subscriptionNotification.purchaseToken` and `subscriptionNotification.notificationType` (integer 1–13). Because the Pub/Sub message contains only the token, the handler must **re-fetch the subscription state** via the Google Play Developer API (`purchases.subscriptionsv2.get`) before applying any state mutation. This is different from Apple's self-contained JWS approach.

**Handled notification types** (integer codes from Play Billing Library):

| Code | Name | Action |
|---|---|---|
| 1 | `SUBSCRIPTION_RECOVERED` | Reactivate; write outbox renewal. |
| 2 | `SUBSCRIPTION_RENEWED` | Update `current_period_ends_at`; write outbox renewal. |
| 3 | `SUBSCRIPTION_CANCELED` | Set `cancel_at_period_end = true`. |
| 4 | `SUBSCRIPTION_PURCHASED` | Create row (if not already); write outbox initial. |
| 5 | `SUBSCRIPTION_ON_HOLD` | Set `status = past_due`. |
| 6 | `SUBSCRIPTION_IN_GRACE_PERIOD` | Keep `status = active` (grace period — user still has access). |
| 7 | `SUBSCRIPTION_RESTARTED` | Clear `cancel_at_period_end`; reactivate. |
| 8 | `SUBSCRIPTION_PRICE_CHANGE_CONFIRMED` | Log. |
| 9 | `SUBSCRIPTION_DEFERRED` | Update `current_period_ends_at`. |
| 10 | `SUBSCRIPTION_PAUSED` | Set `status = paused`, `paused_until`. |
| 11 | `SUBSCRIPTION_PAUSE_SCHEDULE_CHANGED` | Update `paused_until`. |
| 12 | `SUBSCRIPTION_REVOKED` | Set `status = expired`; broadcast. |
| 13 | `SUBSCRIPTION_EXPIRED` | Set `status = expired`; broadcast; write outbox (zero amount, for reconciliation). |

After fetching subscription details from Google Play API via `purchases.subscriptionsv2.get`, extract the subscription resource id and store it as `iap_subscriptions.play_subscription_resource_id`. Write an `iap_receipts` row with `receipt_blob = $purchaseToken` and `google_purchase_token_hash = SHA256(purchaseToken, IAP_RECEIPT_PEPPER)`. The raw `purchaseToken` IS stored in `receipt_blob` for audit (matching §5.2 notes and Backend-Q7 α); on erasure the row's `receipt_blob` is nulled but `google_purchase_token_hash` is retained for post-erasure webhook matching. Update `play_subscription_resource_id` on the `iap_subscriptions` row if a `linkedPurchaseToken` chain yields a new canonical resource id.

**Pattern:** Mirror `SubscriptionWebhookController::handle()` exactly — dedup check + lock + dispatch + outbox write in one transaction. **Exception:** Unlike the Stripe handler, this controller MUST return HTTP 200 even on `Throwable` — catch all exceptions, log them, and return 200. Google Play treats any non-2xx as a failed delivery and retries indefinitely. See §3 callout and §9 acceptance criterion.

### 5.6 IAP product ID config

Add to `config/subscription.php`:

```php
'iap' => [
    'apple' => [
        'bundle_id' => env('APPLE_BUNDLE_ID', 'app.zelta'),
        'product_ids' => [
            'monthly_pro' => env('APPLE_PRODUCT_MONTHLY_PRO', 'zelta_pro_monthly'),
            'annual_pro'  => env('APPLE_PRODUCT_ANNUAL_PRO', 'zelta_pro_annual'),
        ],
    ],
    'google' => [
        'package_name' => env('GOOGLE_PACKAGE_NAME', 'app.zelta'),
        'product_ids' => [
            'monthly_pro' => env('GOOGLE_PRODUCT_MONTHLY_PRO', 'zelta_pro_monthly'),
            'annual_pro'  => env('GOOGLE_PRODUCT_ANNUAL_PRO', 'zelta_pro_annual'),
        ],
    ],
],
```

`IapSubscriptionService::planForProductId(string $store, string $productId): ?string` maps a store product ID to an internal plan key (`monthly_pro` / `annual_pro`). Returns `null` for unknown product IDs (→ `ERR_SUB_001`).

### 5.7 Services and value objects

#### `IapSubscriptionService` (`app/Domain/Subscription/Iap/IapSubscriptionService.php`)

Responsibilities:
- `verify(User $user, array $input): array` — implements the `/iap/verify` processing sequence (§5.1)
- `planForProductId(string $store, string $productId): ?string`
- `enqueueRevenueOutbox(string $store, string $notificationId, string $eventKind, array $payload): RevenueOutboxEvent` — wraps `SubscriptionService::enqueueRevenueOutbox()` with IAP-specific `source_type`

Returns the same `['code' => '...']` / `['success' => [...]]` convention as `SubscriptionService`.

#### `AppleReceiptVerifier` (`app/Domain/Subscription/Iap/AppleReceiptVerifier.php`)

Single responsibility: take the raw receipt or JWS string and return a typed `AppleVerifiedTransaction` value object (or throw on verification failure). Delegates to whichever API version is decided in §8.

#### `GooglePlayReceiptVerifier` (`app/Domain/Subscription/Iap/GooglePlayReceiptVerifier.php`)

Single responsibility: take the `purchaseToken` + `productId` and return a typed `GoogleVerifiedSubscription` value object by calling Play Developer API `purchases.subscriptionsv2.get` server-side. The response provides the canonical subscription record including `linkedPurchaseToken` chains and the stable subscription resource id used as `play_subscription_resource_id` in `iap_subscriptions`.

Requires a Google Cloud service account credential. Recommended env key: `GOOGLE_PLAY_SERVICE_ACCOUNT_PATH` (filesystem path to the JSON key file — preferred for production; 12-factor keeps credentials in files, not env values). `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` (raw or base64-encoded JSON content) is also supported for environments where file mounts are not practical. See §15 for setup instructions.

PHP library: `google/apiclient` with `Google\Service\AndroidPublisher`. Check if `google/apiclient` is already in `composer.json` before adding it as a new dependency; it is used widely across Google-integrated Laravel projects.

#### `IapReceiptPseudonymiser` (`app/Domain/Subscription/Iap/IapReceiptPseudonymiser.php`)

Called from `GdprController::eraseUser()` during the Article 17 erasure walk (Backend-Q7 α). See §5.9.

### 5.8 `SubscriptionProjection` IAP join

Fill in `hasActiveProSubscription($user, string $source)` (currently returns `false` for `'iap'`, `'apple'`, `'google'`):

```
For source === 'iap': return true if iap_subscriptions has any row WHERE user_id = ? AND status IN ('active','trialing','past_due','grace_period','paused')
For source === 'apple': same query filtered to store = 'apple'
For source === 'google': same query filtered to store = 'google'
For source === 'stripe': unchanged from slice 1
```

Extend `for(User $user): array` to include IAP rows. The union rule: if both Cashier and IAP have active rows (should be prevented by the conflict gate but defensive code is required), prefer the row with the more recent `current_period_ends_at`. The `source` field in the returned array will be `apple_iap` or `google_play` when the IAP path is authoritative.

### 5.9 Erasure semantics (Backend-Q7 α)

`IapReceiptPseudonymiser::pseudonymise(User $user, string $requestId): void` implements the erasure walk for IAP receipts per Backend-Q7 α:

For each `iap_receipts` row owned by `$user`:
1. Compute `original_transaction_id_hash = HMAC-SHA256(original_transaction_id, env('IAP_RECEIPT_PEPPER'))`
2. Set `user_id = NULL`, `original_transaction_id = NULL`, `receipt_blob = NULL`, `apple_app_account_token = NULL`, `google_obfuscated_account_id = NULL`
3. Populate `original_transaction_id_hash` with the computed hash
4. Set `scrubbed_at = NOW()`

Write an audit row to `gdpr_erasure_log` per existing GDPR controller patterns: `(row_id, user_id, table='iap_receipts', scrubbed_at, request_id)`.

**Webhook post-erasure lookup logic** (implement in both `AppleWebhookController` and `GooglePlayWebhookController`):

1. Try to find `iap_receipts` by `original_transaction_id` (fast path — normal case)
2. If not found: compute `HMAC-SHA256(inbound_id, IAP_RECEIPT_PEPPER)` and look up by `original_transaction_id_hash`
3. On REFUND match via hash: insert `closed_account_refunds` row with `source = 'apple_iap'` / `'google_play'`; no user notification
4. On RENEWAL match via hash at `scrubbed_renewal_count == 0`: drop notification + alert ops (`Log::warning('iap.webhook.stale_renewal_post_erasure', ...)`) + increment `scrubbed_renewal_count`
5. On RENEWAL with `scrubbed_renewal_count >= 3`: page ops via the existing alerting channel

**Erasure ordering** (full sequence from Backend-Q7; slice 2 provides steps b and d — other steps are other slices):

a. Cancel Stripe subscription via Cashier `cancel()` [Slice 1 path — already present or wired]
b. Cancel IAP subscriptions via store APIs (`POST /inApps/v1/subscriptions/{originalTransactionId}` for Apple; `purchases.subscriptions.cancel` for Google) — MUST complete before pseudonymisation
c. Pro-rata refund attempts (store-discretion-bound)
d. Pseudonymise `iap_receipts` rows [this slice]
e. (Other steps are other domains)

**`IAP_RECEIPT_PEPPER` — one-way rotation caveat** (per Backend-Q7): unlike `TRIAL_FINGERPRINT_PEPPER`, this pepper cannot be rotated cleanly — once rotated, previously-scrubbed rows' `original_transaction_id_hash` values become orphaned. Add a code comment in `.env.production.example`:

```
# IAP_RECEIPT_PEPPER: ONE-WAY ROTATION CAVEAT — previously-scrubbed receipts
# cannot be re-hashed after rotation (raw ID was nulled). Rotate only on
# confirmed compromise; expect manual ops resolution for webhook matches.
IAP_RECEIPT_PEPPER=
```

### 5.10 Revenue projection (ADR-0002 off-chain path)

IAP events travel the **outbox saga** path (ADR-0002 §β). The `ProjectRevenueOutbox` worker already processes any `source_type`; slice 2 only needs to write outbox rows correctly.

**Event kinds for IAP:**

| `event_kind` | When | `source_type` |
|---|---|---|
| `iap_subscription_initial` | First purchase verified | `apple_iap` / `google_play` |
| `iap_subscription_renewal` | DID_RENEW / SUBSCRIPTION_RENEWED | same |
| `iap_refund` | REFUND / SUBSCRIPTION_REVOKED | same (negative amount per ADR-0004) |

Money in the outbox `payload` must use the ADR-0004 triple: `{"amount": "499", "decimals": 2, "currency": "EUR"}`.

For Apple `signedTransactionInfo.price`: Apple reports in the storefront currency, not necessarily EUR. For v1.3.0 (EUR-only), convert at the exchange rate stored on the `price_quotes` table or, if not available, use the ECB reference rate for the day. Store the original storefront currency alongside for reconciliation.

For Google `priceAmountMicros`: divide by 10^6 then convert to EUR cents. Store the micro amount with `decimals: 6` and the `priceCurrencyCode` in the outbox payload for reconciliation; the worker converts to EUR at projection time.

**Open question:** See §8 — the EUR conversion source for Apple/Google prices at subscription-creation time may need controller input.

### 5.11 Error codes

All ERR codes used in this slice are registered upstream — see the `fix(plan-b): error code registry` companion PR.

- `ERR_SUB_001`, `ERR_CUR_001`, `ERR_QUO_002` — added by that companion hotfix PR (already registered post #1037+hotfix).
- `ERR_SUB_010` HTTP code corrected from 422 → 409 by the same hotfix.
- `ERR_SUB_008` and `ERR_SUB_009` — slice 2 registers these as part of its own implementation work (they remain free in `config/error_codes.php` until slice 2 merges):
  ```php
  'ERR_SUB_008' => ['http' => 409, 'description' => 'Family Sharing receipt — purchase was made by a different Apple ID. Manage subscription from the original purchasing account.'],
  'ERR_SUB_009' => ['http' => 422, 'description' => 'Receipt is expired or subscription has already ended.'],
  ```

### 5.12 Filament admin (read-only IAP subscription view)

Matches the `TrialCardFingerprintResource` pattern from slice 1. Add a read-only `IapSubscriptionResource` under `app/Filament/Admin/Resources/` displaying:
- User lookup (by email)
- `store`, `tier`, `status`, `current_period_ends_at`, `cancel_at_period_end`, `paused_until`, `scrubbed_at` (from `iap_receipts` pivot)
- No edit actions — IAP subscription state is managed by store webhooks, not admin

Admin module gating: `$adminModule = 'Subscription'` (matches slice 1's Filament resource module). Apply `RespectsModuleVisibility` trait.

### 5.13 Cron schedules

No net-new cron required in slice 2. Defer:
- RENEWAL webhook dedup alert escalation → handled by `scrubbed_renewal_count` + log + ops (no cron needed in v1.3.0)
- `iap_receipts` 10-year purge cron → explicitly deferred to v1.4+ per Backend-Q7 retention note

---

## 6. Slice 2 — out of scope

The following are explicitly deferred and must NOT be implemented in slice 2:

| Item | Deferred to |
|---|---|
| Cue dispatch for IAP payment failures, trial ending, grace period started | Slice 4 (Backend-Q8 cue dispatch architecture) |
| IAP plan change / downgrade via backend endpoint | IAP plan changes happen store-side; backend is notified via `DID_CHANGE_RENEWAL_PREF` webhook. A `/change-plan` endpoint for IAP is not needed. |
| `family_sharing_unsupported` cue | Slice 4 |
| Multi-currency (non-EUR) storefront pricing | v1.4+ |
| `iap_receipts` 10-year purge cron | v1.4+ |
| Apple Promotional Offers / Offer Codes | v1.3.1+ |
| Google Play Introductory Pricing (server-side offer redemption) | v1.3.1+ |
| Entitlements schema v2 changes | v1.3.1+ |
| Savings-with-Pro calculator IAP sourcing | Slice 3 (savings endpoint, §8 of the commercial spec) |
| `vendor_data_deletions` table for Apple/Google account deletion requests | Slice 5 (Backend-Q6 vendor deletion orchestration) |

---

## 7. Mobile contract

Mobile is on **expo-iap 4.2.3 + Expo SDK 54, iOS 15.1+ minimum** → StoreKit 2 is always used. The wire shapes below are confirmed by mobile-dev.

> **Wire field name:** All response shapes below use `currentPeriodEnd` (matching slice 1's `SubscriptionProjection.php` reality). The deltas Q6.1 document and commercial spec §1.2 used `currentPeriodEndsAt` — treat those references as out-of-date. A future deltas amendment should align the docs. Do not use `currentPeriodEndsAt` in any new code.

### `POST /api/v1/subscription/iap/verify`

**Request (iOS — Apple IAP):**

```json
POST /api/v1/subscription/iap/verify
Authorization: Bearer <sanctum-token>
Content-Type: application/json
Idempotency-Key: <uuid-v4-generated-by-mobile>

{
  "platform": "apple_iap",
  "receipt": "<JWS signed transaction string from StoreKit 2>",
  "originalTransactionId": "<StoreKit 2 stable transaction id>",
  "productId": "zelta_pro_monthly",
  "appVersion": "1.3.0",
  "currency": "EUR"
}
```

**Request (Android — Google Play):**

```json
POST /api/v1/subscription/iap/verify
Authorization: Bearer <sanctum-token>
Content-Type: application/json
Idempotency-Key: <uuid-v4-generated-by-mobile>

{
  "platform": "google_play",
  "receipt": "<purchaseToken verbatim>",
  "productId": "zelta_pro_monthly",
  "appVersion": "1.3.0",
  "currency": "EUR"
}
```

**Product ID values** (confirmed — must match App Store Connect + Google Play Console exactly):
- `zelta_pro_monthly`
- `zelta_pro_annual`

Mobile reads these via Expo env vars: `EXPO_PUBLIC_PRO_MONTHLY_SKU` and `EXPO_PUBLIC_PRO_ANNUAL_SKU`. These must equal what is configured in ASC and Play Console — see §15.

**Success (200):**

```json
{
  "tier": "pro",
  "status": "active",
  "source": "apple_iap",
  "plan": "monthly_pro",
  "currentPeriodEnd": "2026-06-10T12:00:00Z",
  "trialEndsAt": null,
  "cancelledAtPeriodEnd": false,
  "pausedUntil": null,
  "reactivated": false
}
```

**Error (409 — conflict):**

```json
{
  "code": "ERR_SUB_002",
  "conflict": {
    "kind": "two_stores_active",
    "existingSubscription": {
      "source": "stripe_web",
      "tier": "pro",
      "currentPeriodEnd": "2026-07-01T00:00:00Z"
    },
    "attemptedSource": "apple_iap"
  }
}
```

**Error (409 — family sharing):**

```json
{
  "code": "ERR_SUB_008",
  "message": "This purchase was made by a different Apple ID. Please manage your subscription in the App Store app."
}
```

**Error (422 — expired receipt):**

```json
{
  "code": "ERR_SUB_009",
  "message": "Your App Store subscription has already expired. Please subscribe again."
}
```

**Error (403 — trial used):**

```json
{
  "code": "ERR_SUB_003",
  "eligibleAfter": null
}
```

### `GET /api/v1/subscription/me` (free-tier shape, unchanged from slice 1)

```json
{
  "tier": "free",
  "status": null,
  "source": null,
  "plan": null,
  "currentPeriodEnd": null,
  "trialEndsAt": null,
  "cancelledAtPeriodEnd": false,
  "pausedUntil": null
}
```

### Mobile must handle these error codes from `POST /iap/verify`

| Code | Mobile response |
|---|---|
| `ERR_SUB_001` | Show generic "subscription failed" dialog; offer retry or contacting support |
| `ERR_SUB_002` | Show conflict dialog with CTA to cancel existing subscription; `existingSubscription.source` determines which deep link to show |
| `ERR_SUB_003` | Show "trial already used" copy; offer non-trial Pro CTA |
| `ERR_SUB_008` | Show "manage in App Store / Google Play" deep link; user must switch accounts |
| `ERR_SUB_009` | Show "subscription expired" copy; offer re-subscribe CTA |
| `ERR_VALIDATION_001` | Programming error on mobile — always include `Idempotency-Key`; never show to user |
| `ERR_CUR_001` | Programming error — `currency` must be `"EUR"` |

### Cancellation (`POST /api/v1/subscription/cancel`)

When `source` is `apple_iap` or `google_play`, this endpoint returns `ERR_SUB_010` (409) with deep-link management URLs — already wired in slice 1's `SubscriptionService::cancel()`. Mobile should route the user to the store's subscription management screen.

### Money-safety: `finishTransaction` timing (mobile-confirmed)

Mobile's `purchaseSubscriptionAsync` → `reconciler.verifyPendingPurchase` chain calls `finishTransaction()` only on `POST /iap/verify` 2xx. Any backend non-2xx response (webhook signature failure, transient error, validation rejection) leaves the purchase as "pending" on the device — mobile retries the verify call automatically. This means:

- **Slice 2 does NOT need backend-side retry queues for the `/iap/verify` path itself.** Mobile's retry loop is the reliability mechanism for the verify step.
- Post-verify side-effects — entitlements projection write, `revenue_outbox_events` — still require durable delivery per ADR-0002. That is slice 4's territory.
- Backend should return a clear non-2xx status (not a silent 200 with an error body) on any rejection so mobile knows the purchase is still pending.

---

## 8. Open design decisions

The following decisions require explicit controller input **before implementation starts**. The default recommendation is listed; the implementation agent should ask for confirmation and not guess.

---

### 8.1 Apple StoreKit 2 local JWS verification vs server-side `verifyReceipt` (deprecated)

**STATUS: DECIDED — StoreKit 2 JWS local verification.**

Mobile-dev confirmed: expo-iap 4.2.3 + Expo SDK 54, iOS 15.1+ minimum → StoreKit 2 is always in effect. Mobile sends a JWS-signed transaction string. The legacy `SKPaymentQueue` receipt blob path (`appStoreReceiptURL`, base64 blob) is **not used and must not be implemented**.

**Decision:** (α) StoreKit 2 JWS local verification — verify the JWS chain in PHP using Apple WWDR G6 intermediate cert + Apple Root CA G3. Fast (no network call on the hot verify path). Apple's recommended path for new implementations.

**PHP library recommendation:** `readdle/app-store-server-api` — actively maintained, supports StoreKit 2 JWS verification natively. Alternatives if needed: `kobotn/app-store-server-library-php` (also StoreKit 2 aware), or roll-your-own with `web-token/jwt-framework` (handles the JWS parsing with the WWDR G6/G3 cert chain). Do not use `firebase/php-jwt` alone — it lacks Apple-cert-chain validation out of the box.

The deprecated `POST https://buy.itunes.apple.com/verifyReceipt` endpoint must NOT be called anywhere in the implementation.

---

### 8.2 Google Play: Subscriptions v3 API + RTDN vs legacy in-app billing

**Context:** Google deprecated the v2 `purchases.subscriptions` endpoint in June 2022. The v3 `purchases.subscriptionsv2.get` endpoint is the current standard.

**Options:**
- **(α) Play Billing v3 `purchases.subscriptionsv2.get` + RTDN push:** Recommended. Requires a Google service account with `Android Publisher` API read access. RTDN delivers events within seconds. The webhook handler re-fetches subscription state from v3 API after receiving the Pub/Sub notification.
- **(β) Legacy v2 `purchases.subscriptions.get`:** Still works. Simpler response schema. Will eventually be sunset.

**Recommendation:** (α). v3 is the production standard; implementing v2 now requires a migration later.

**Open question — needs controller input:** The RTDN Pub/Sub subscription must be registered in the Google Play Console with the backend's `POST /webhooks/google/play` URL. This requires the production/staging URL to be known. Does the founder already have a Play Console merchant account linked? (See §15 coordination items.)

---

### 8.3 Receipt persistence: separate `iap_receipts` table vs fold into `iap_subscriptions`

**Context:** The commercial spec (§1.7) and Backend-Q7 both reference an `iap_receipts` table distinct from `iap_subscriptions`. The receipts table holds the raw JWS/token and is the target of erasure pseudonymisation.

**Decision:** Keep `iap_receipts` separate. Rationale: erasure pseudonymisation nulls personal data in `iap_receipts` but preserves the transactional row in `iap_subscriptions` (tier, amount, dates needed for tax retention). One-to-many: a subscription can have multiple receipts over its lifetime (initial + renewals). Merging them would require either denormalisation or a self-join on renewals.

**This is a decided default; no controller input needed.** Listed here for transparency.

---

### 8.4 Unified entitlements projection: read-time join vs projection-time merge

**Context:** `SubscriptionProjection::for($user)` can either (α) query both Cashier tables and `iap_subscriptions` at read time and pick the winner, or (β) maintain a separate `entitlements` materialised table that is updated on every write event.

**Recommendation:** (α) read-time join for v1.3.0. The query is a simple `LEFT JOIN` or two sequential reads with a comparison; at this traffic scale it is faster to implement and maintains only one source of truth per source. A cached version (5-min TTL, busted on `subscription.changed` WebSocket event) is sufficient.

**(β) materialised projection is deferred to v1.4+** if profiling reveals `GET /me/entitlements` latency becomes a concern.

---

### 8.5 IAP webhook authentication: env keys vs `webhook_endpoints` table

**Context:** Alchemy/Helius use the `webhook_endpoints` table (managed by `AlchemyWebhookManager`) for per-webhook signing keys. Stripe's subscription webhook uses `STRIPE_WEBHOOK_SECRET` env key. Apple notifications don't have a per-endpoint secret — they use JWS signing with Apple's certificate chain (no env key needed). Google's Pub/Sub push messages carry a bearer JWT from Google.

**Decision:**
- **Apple:** No env key for the webhook secret — verification is via Apple's certificate chain (bundled or fetched). Store the WWDR G6 cert URL or its SHA-256 fingerprint in config for pinning.
- **Google:** Store `GOOGLE_PLAY_WEBHOOK_AUDIENCE` env key (the audience claim in the push JWT) in `.env.production.example`. The verification key is fetched from `https://www.googleapis.com/oauth2/v3/certs`.
- Neither store uses the `webhook_endpoints` table — only the Alchemy/Helius pattern requires per-endpoint managed keys.

**Add to `.env.production.example` and `.env.zelta.example`:**
```
APPLE_BUNDLE_ID=app.zelta
APPLE_PRODUCT_MONTHLY_PRO=zelta_pro_monthly
APPLE_PRODUCT_ANNUAL_PRO=zelta_pro_annual
GOOGLE_PACKAGE_NAME=app.zelta
GOOGLE_PRODUCT_MONTHLY_PRO=zelta_pro_monthly
GOOGLE_PRODUCT_ANNUAL_PRO=zelta_pro_annual
# Recommended for production: path to the service account JSON key file (keep outside webroot)
GOOGLE_PLAY_SERVICE_ACCOUNT_PATH=
# Alternative: raw or base64-encoded JSON key content (use if file mount not available)
GOOGLE_PLAY_SERVICE_ACCOUNT_JSON=
GOOGLE_PLAY_WEBHOOK_AUDIENCE=https://api.zelta.app
IAP_RECEIPT_PEPPER=
```

---

### 8.6 Withdrawal consent for IAP purchases

**STATUS: DECIDED — optional in v1.3.0; required from v1.3.1.**

Mobile-dev confirmed (per Q14 of the review deltas): native IAP does not need a withdrawal-consent line in the `iap/verify` call for v1.3.0. Apple's standard refund flow (`reportaproblem.apple.com`) covers the EU 14-day right of withdrawal, and App Review has accepted this approach. The `withdrawalConsent` field is **OPTIONAL** on `POST /iap/verify` in v1.3.0 — mobile does not populate it.

Slice 1's web Stripe Checkout consent flow (the `subscription_consent_log` writer + 5-min staleness window from `ERR_SUB_004`) is unchanged and flows through the checkout path, NOT through `/iap/verify`.

In v1.3.1, mobile's mid-flight Q10 paywall expansion will ship a unified consent UX and start populating `withdrawalConsent` in `iap/verify`. At that point flip it to required. The backend field must be wired and stored even in v1.3.0 so the v1.3.1 mobile update is a non-breaking change.

**Implementation in v1.3.0:**
- Accept `withdrawalConsent` as an optional request field (same object shape as slice 1's checkout path).
- If present: write `subscription_consent_log` row linking to `iap_subscriptions.id` (the FK is already nullable — see §4).
- If absent: skip the `subscription_consent_log` write; no warning, no error.
- Log a `DEBUG` entry when absent so it is visible in staging but does not pollute production logs.

---

### 8.7 Google Play stable subscription identifier

**STATUS: DECIDED — Play Developer API `purchases.subscriptionsv2.get` introspection.**

Mobile-dev confirmed: do NOT rely on `orderId` base-prefix stripping. Sandbox `orderId` values can be null or non-conformant on mock paths, making option (α) unreliable across environments.

**Decision:** Backend calls Play Developer API `purchases.subscriptionsv2.get` on the raw `purchaseToken` server-side. That response returns the canonical subscription record including `linkedPurchaseToken` chains across renewal/cancel/resubscribe. The subscription resource id from that response is the stable PK for `iap_subscriptions`, stored in the new `play_subscription_resource_id VARCHAR(255)` column (see §5.2 schema).

Mobile sends `purchaseToken` verbatim — backend owns the introspection step.

**Backend implications:**
- New PHP dependency: `google/apiclient` with `Google\Service\AndroidPublisher`. Check `composer.json` before adding — it may already be present.
- New env requirement: Google Cloud Service Account credentials. Two env options are supported (see §5.7 `GooglePlayReceiptVerifier` and §15):
  - `GOOGLE_PLAY_SERVICE_ACCOUNT_PATH` — filesystem path to the JSON key file (recommended for production).
  - `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON` — raw or base64-encoded JSON key content (for environments without file mount capability).
- Schema: `iap_subscriptions` has `play_subscription_resource_id` as the stable Google PK (replaces the `google_order_id_base` column from the earlier (α) option). The `UNIQUE KEY uniq_iap_sub_play_resource (play_subscription_resource_id)` prevents double-insert.

The discarded `orderId`-based approach and the `google_order_id_base` column do not appear anywhere in the final schema.

---

## 9. Acceptance criteria

An implementation is complete when every item below passes.

### API endpoint

- [ ] `POST /api/v1/subscription/iap/verify` returns `200` with the subscription shape for a valid Apple JWS receipt
- [ ] Same endpoint returns `200` for a valid Google `purchaseToken`
- [ ] Returns `ERR_SUB_001` for a receipt that fails Apple/Google server verification
- [ ] Returns `ERR_SUB_002` with `conflict.kind = 'two_stores_active'` when user has an active Stripe subscription and attempts IAP verify
- [ ] Returns `ERR_SUB_008` when `appAccountToken` / `obfuscatedAccountId` does not match authenticated user
- [ ] Returns `ERR_SUB_009` for a receipt whose subscription is already expired
- [ ] Returns `200` with `reactivated: true` for a receipt matching a `cancelled_at_period_end = true` subscription within grace period
- [ ] `Idempotency-Key` replay within 24h returns the cached response without re-verifying with the store
- [ ] Missing `Idempotency-Key` header returns `ERR_VALIDATION_001`

### `SubscriptionProjection`

- [ ] `hasActiveProSubscription($user, 'iap')` returns `true` after a successful `/iap/verify` creates an `iap_subscriptions` row
- [ ] `hasActiveProSubscription($user, 'iap')` returns `false` for a user with only a Stripe subscription
- [ ] `for($user)` returns `source: 'apple_iap'` / `'google_play'` for IAP-subscribed users
- [ ] `for($user)` returns `source: 'stripe_web'` for Stripe-subscribed users (unchanged from slice 1)
- [ ] Existing slice 1 test suite continues to pass with no regressions

### Webhooks

- [ ] `POST /webhooks/apple/notifications` with a valid JWS `SUBSCRIBED` notification creates/updates `iap_subscriptions` and writes `revenue_outbox_events`
- [ ] Same endpoint deduplicates on `notificationUUID` — second delivery of same UUID is a no-op
- [ ] `POST /webhooks/google/play` with a valid Pub/Sub `SUBSCRIPTION_PURCHASED` (type 4) notification creates/updates `iap_subscriptions` and writes `revenue_outbox_events`
- [ ] Same endpoint deduplicates on `messageId`
- [ ] `DID_RENEW` (Apple) and `SUBSCRIPTION_RENEWED` (Google) correctly update `current_period_ends_at` and write outbox renewal row
- [ ] `REFUND` (Apple) sets `status = refunded`, writes negative-amount outbox row, broadcasts `entitlements.changed`
- [ ] Both webhook handlers return `200` to the store even on processing errors (log and return 200; never 500 to store webhooks)
- [ ] Signature verification bypass works in `local`/`testing` environment with empty keys (per CLAUDE.md pattern)

### Erasure (Backend-Q7 α)

- [ ] `IapReceiptPseudonymiser::pseudonymise()` nulls `user_id`, `original_transaction_id`, `receipt_blob`, `apple_app_account_token` / `google_obfuscated_account_id`; populates `original_transaction_id_hash`; sets `scrubbed_at`
- [ ] Post-erasure `DID_RENEW` webhook with the scrubbed receipt's original transaction ID: logs warning, increments `scrubbed_renewal_count`, returns 200 (does not update subscription)
- [ ] Post-erasure REFUND webhook: inserts `closed_account_refunds` row with `source = 'apple_iap'` / `'google_play'`
- [ ] `gdpr_erasure_log` audit row written for each pseudonymised receipt
- [ ] `iap_receipts` webhook lookup uses raw `original_transaction_id` first; falls back to hash lookup for scrubbed rows

### Revenue projection

- [ ] `revenue_outbox_events` row written with correct `source_type` (`apple_iap` or `google_play`) on initial purchase
- [ ] `revenue_outbox_events` row written on renewal
- [ ] Negative-amount row written on refund (ADR-0004 sign-prefix convention)
- [ ] `ProjectRevenueOutbox` worker successfully fans outbox rows into `revenue_events` (existing worker; no changes needed — just verify the `source_type` passes through cleanly)

### Config and env

- [ ] `config/subscription.php` `iap.apple.product_ids` maps `zelta_pro_monthly` → `monthly_pro`
- [ ] `config/subscription.php` `iap.google.product_ids` maps `zelta_pro_monthly` → `monthly_pro`
- [ ] `IAP_RECEIPT_PEPPER`, `APPLE_BUNDLE_ID`, `GOOGLE_PACKAGE_NAME`, `GOOGLE_PLAY_SERVICE_ACCOUNT_PATH`, `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON`, `GOOGLE_PLAY_WEBHOOK_AUDIENCE` are documented in `.env.production.example` and `.env.zelta.example`
- [ ] Code comment in `.env.production.example` explicitly states `IAP_RECEIPT_PEPPER` one-way rotation caveat

### Error code registry

- [ ] `config/error_codes.php` contains `ERR_SUB_008` and `ERR_SUB_009`

### Quality gates

- [ ] `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php` — zero violations
- [ ] `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` — zero new errors (level 8)
- [ ] `./vendor/bin/pest --parallel --stop-on-failure` — all existing + new tests pass
- [ ] `./vendor/bin/phpcs --standard=PSR12 app/Domain/Subscription/` — zero violations

---

## 10. Conventions

All conventions from CLAUDE.md apply. Reproduced here for convenience:

### PHP file header

```php
<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;
```

### Import order

`App\Domain` → `App\Http` → `App\Models` → `Illuminate` → Third-party

### Money

- API wire: `{"amount": "499", "decimals": 2, "currency": "EUR"}` (ADR-0004)
- Storage: integer columns; `iap_receipts.amount_smallest_unit BIGINT`
- Math: `bcmath` exclusively. Never `(float)` cast. Normalize with `bcadd($val, '0', 4)`.
- Apple storefront currency → EUR: use `bcmul` or `bcdiv` with the rate as a `numeric-string`. Never `(float)($priceAmountMicros / 1_000_000)`.

### DB transactions

- Wrap `iap_subscriptions` + `iap_receipts` + `processed_webhook_events` + `revenue_outbox_events` multi-table writes in one `DB::transaction()`
- Both `iap_subscriptions` and these webhook tables are **global** connection — no cross-connection issue, so `DB::transaction()` is safe here (unlike the tenant-connection guard in CLAUDE.md)
- Use `lockForUpdate()` on the `iap_subscriptions` row when updating status (prevents concurrent webhook race conditions)

### Multi-connection rule

IAP tables are all on the global connection. No `UsesTenantConnection` models are touched by any IAP handler. The CLAUDE.md multi-connection rule does not apply here. Add a comment in the webhook controllers to make this explicit.

### Cashier conventions

Do not call Cashier methods (`$user->subscription('default')`, `$user->newSubscription()`) from the IAP path. IAP is the custom path; Cashier is the Stripe path. The two paths are kept isolated per Backend-Q1 (γ).

### Wire contract

camelCase JSON keys throughout (`currentPeriodEnd`, `cancelledAtPeriodEnd`, `pausedUntil`). The `reactivated` key is the only addition from slice 1's shape.

### Sanctum abilities

All tests: `Sanctum::actingAs($user, ['read', 'write', 'delete'])`.

### Webhook auth bypass

```php
if (app()->environment('local', 'testing') && $appleKey === '') {
    // decode unsigned for testing — never `return true`
}
```

### `assert()` as auth guard

Do NOT use `assert($user instanceof User)` as an auth guard. Use `if (! $user instanceof User) { return response()->json(['code' => 'unauthenticated'], 401); }`.

### Bypass flag tests

Every new `account_flags` bypass introduced in slice 2 must have a matching feature test in `tests/Feature/AccountProvisioning/Bypasses/` asserting both sides.

---

## 11. Quality gates (run in order)

```bash
# 1. Code style
./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php

# 2. Static analysis
XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G

# 3. Full test suite
./vendor/bin/pest --parallel --stop-on-failure

# 4. PHPCS (must match CI's v4.0.1)
./vendor/bin/phpcs --standard=PSR12 app/Domain/Subscription/
```

All four must pass clean before creating the PR.

---

## 12. Workflow expectations

Suggested commit topology (each commit is atomic + passing quality gates):

```
feat(iap): add iap_subscriptions, iap_subscription_events, iap_receipts migrations
feat(iap): add IapSubscriptionService + AppleReceiptVerifier + GooglePlayReceiptVerifier
feat(iap): POST /api/v1/subscription/iap/verify endpoint + error codes ERR_SUB_008/009
feat(iap): fill in SubscriptionProjection IAP join (hasActiveProSubscription + for union)
feat(iap): POST /webhooks/apple/notifications — App Store Server Notifications V2 handler
feat(iap): POST /webhooks/google/play — Google Play RTDN handler
feat(iap): IapReceiptPseudonymiser + erasure walk integration (Backend-Q7 α)
feat(iap): Filament IapSubscriptionResource (read-only)
feat(iap): IAP env vars in .env.production.example + .env.zelta.example
test(iap): feature tests for /iap/verify, webhook handlers, pseudonymiser
```

Each commit title ends with:
```
Co-Authored-By: Claude <noreply@anthropic.com>
```

---

## 13. Status reporting format

When returning your status to the controller, include:

```
Status: DONE | DONE_WITH_CONCERNS | BLOCKED
Branch: feat/plan-b-slice-2-iap
PR URL: <url>
Quality gates:
  php-cs-fixer: PASS | FAIL
  PHPStan L8:   PASS | FAIL (N errors)
  Pest:         PASS | FAIL (N failed / N total)
  PHPCS:        PASS | FAIL
Files changed: <list>
New migrations: <list>
Open questions unresolved: <any item from §8 not yet answered by controller>
Concerns: <any design compromise made under time pressure>
```

---

## 14. Estimated effort

**6–7 engineer-days.** (Revised from 5–7d — Play Developer API client integration adds scope vs the original `orderId`-parsing assumption; see §8.7.)

Breakdown:

| Task | Days |
|---|---|
| `iap_subscriptions` + `iap_receipts` + `iap_subscription_events` migrations | 0.25 |
| `IapSubscriptionService` + `AppleReceiptVerifier` + `GooglePlayReceiptVerifier` service layer | 1.25 |
| `POST /api/v1/subscription/iap/verify` controller + request validation + idempotency | 0.75 |
| `SubscriptionProjection` IAP join + `for()` union | 0.5 |
| Apple webhook handler (`AppleNotificationsWebhookController`) + JWS verification | 1.0 |
| Google Play webhook handler (`GooglePlayWebhookController`) + Pub/Sub JWT auth + Play Developer API re-fetch | 1.25 |
| `IapReceiptPseudonymiser` + erasure walk integration + `GdprController` extension | 0.75 |
| Config additions (`config/subscription.php`, `.env.production.example`, `.env.zelta.example`) + error codes | 0.25 |
| Filament `IapSubscriptionResource` (read-only) | 0.25 |
| Feature tests (coverage of §9 acceptance criteria) | 1.0 |

**Total: ~7.25d, rounded to 6–7d.** The main variability is in the `google/apiclient` + `AndroidPublisher` integration and getting Google Play service account credentials provisioned for staging.

Comparison to slice 1: slice 1 was sized 3–5d and covered one PSP. Slice 2 covers two store SDKs with distinct verification protocols, a server-side Play Developer API introspection step for Google, plus the erasure pseudonymisation layer, explaining the larger estimate.

---

## 15. Coordination items

These are actions the human controller must complete **before or during** implementation. They are not engineering tasks.

### Before implementation starts — open items now resolved (mobile-dev confirmed)

The three original open questions from §8 are now closed:

- [x] **§8.1 — StoreKit 2 JWS confirmed.** expo-iap 4.2.3 / Expo SDK 54 / iOS 15.1+. No legacy receipt blob.
- [x] **§8.6 — Withdrawal consent optional in v1.3.0.** Required from v1.3.1 when mobile ships unified consent UX.
- [x] **§8.7 — Google stable id via Play Developer API `subscriptionsv2.get`.** Not `orderId` base-prefix.

### Before implementation starts (founder must action)

- [ ] **App Store Connect — product IDs:** Set up in-app purchase subscription products with EXACTLY these IDs: `zelta_pro_monthly` and `zelta_pro_annual`. These must match `config/subscription.php` (`APPLE_PRODUCT_MONTHLY_PRO` / `APPLE_PRODUCT_ANNUAL_PRO`) and match what mobile reads from `EXPO_PUBLIC_PRO_MONTHLY_SKU` / `EXPO_PUBLIC_PRO_ANNUAL_SKU`. Any mismatch causes `ERR_SUB_001` on the first verify call.
- [ ] **App Store Server Notifications V2:** Register `https://zelta.app/webhooks/apple/notifications` in App Store Connect → App Information → App Store Server Notifications (V2 endpoint). Use the sandbox URL `https://staging.zelta.app/webhooks/apple/notifications` for the sandbox environment.
- [ ] **Google Play Console — product IDs:** Register subscription products with EXACTLY the same IDs: `zelta_pro_monthly` and `zelta_pro_annual`. Must match `GOOGLE_PRODUCT_MONTHLY_PRO` / `GOOGLE_PRODUCT_ANNUAL_PRO` and `EXPO_PUBLIC_PRO_*_SKU`.
- [ ] **Google Play RTDN — Pub/Sub setup:** Create a Google Cloud Pub/Sub topic + push subscription pointing to `https://zelta.app/webhooks/google/play`. Set `GOOGLE_PLAY_WEBHOOK_AUDIENCE=https://api.zelta.app` (the expected `aud` claim in Google's push JWT).
- [ ] **Google Play service account:** Create a service account in Google Cloud Console with `Android Publisher` API read access. Grant the service account access in Play Console (Setup → API access → Grant access). Download the JSON key file. Recommended deployment: store the file at a path outside the webroot and set `GOOGLE_PLAY_SERVICE_ACCOUNT_PATH=/path/to/key.json`. Alternatively, base64-encode the JSON and set `GOOGLE_PLAY_SERVICE_ACCOUNT_JSON=<base64>`.
- [ ] **Mobile env match-up:** Confirm `EXPO_PUBLIC_PRO_MONTHLY_SKU=zelta_pro_monthly` and `EXPO_PUBLIC_PRO_ANNUAL_SKU=zelta_pro_annual` are set in the mobile Expo project and match ASC + Play Console exactly. Any discrepancy will cause receipt verification to return `ERR_SUB_001`.

### Env vars to add (controller actions after merge)

Set in production and staging environments:

```
APPLE_BUNDLE_ID=app.zelta
APPLE_PRODUCT_MONTHLY_PRO=zelta_pro_monthly
APPLE_PRODUCT_ANNUAL_PRO=zelta_pro_annual
GOOGLE_PACKAGE_NAME=app.zelta
GOOGLE_PRODUCT_MONTHLY_PRO=zelta_pro_monthly
GOOGLE_PRODUCT_ANNUAL_PRO=zelta_pro_annual
# Recommended: path to the service account JSON key file
GOOGLE_PLAY_SERVICE_ACCOUNT_PATH=
# Alternative: raw or base64-encoded JSON key content (use if file mount not available)
GOOGLE_PLAY_SERVICE_ACCOUNT_JSON=
GOOGLE_PLAY_WEBHOOK_AUDIENCE=https://api.zelta.app
IAP_RECEIPT_PEPPER=
```

`IAP_RECEIPT_PEPPER` must be generated with `php artisan key:generate --show` or `openssl rand -base64 32`. Document the generated value securely — it cannot be rotated without manual ops work (§5.9).

### After merge — before going live

- [ ] Run `php artisan migrate` in staging then production (3 new tables: `iap_subscriptions`, `iap_subscription_events`, `iap_receipts`)
- [ ] Configure staging App Store Server Notification URL and test with Sandbox receipt
- [ ] Configure staging Google Play RTDN push subscription and test with test purchaseToken
- [ ] Verify `GET /api/v1/subscription/me` returns the IAP source for a test user who purchases via the App Store sandbox
- [ ] **Mobile smoke test:** Once `/iap/verify` is live in staging, mobile runs a real end-to-end subscribe on the next preview-debug build. This is the post-implementation smoke milestone — confirm with mobile-dev team before sign-off.

### Next step

Once this spec merges, the implementation agent uses this file as the direct input prompt. The agent should start at §2 (situation summary) and §3 (read these before writing code), then implement the scope from §5, validate against §9, and report via §13.

---

*Document traceability:*
- `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md` — Backend-Q1 (architecture decision γ), Backend-Q7 (erasure α), Q6 (multi-store conflict kinds)
- `docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §1 — original subscription spec surface area
- `docs/adr/0002-revenue-projection-dual-upstream.md` — outbox-saga path for IAP revenue
- `docs/adr/0003-pricing-bounded-context.md` — IAP product ID config equivalent of Stripe price keys
- `docs/adr/0004-money-on-the-wire.md` — Money VO shape for IAP amounts
