# Backend Handover: Plan B — Commercial Pricing (No Licence Path)

**Date**: 2026-05-08 (refactored 2026-05-08)
**Mobile version target**: 1.3.0+
**Status**: Specification — implementation not started
**Estimated effort**: ~7 engineer‑weeks (one mid‑senior backend engineer)
**Companion doc**: `finaegis-mobile/docs/MOBILE_HANDOVER_PLAN_B_COMMERCIAL.md`
**Stack**: PHP 8.4 / Laravel 12 / MySQL 8 / Redis / Pest / PHPStan Level 8

This document defines the backend work to make Zelta commercially viable **without** the EMI / MiCA licence. Layers transparent service fees, a freemium subscription, and B2B BaaS revenue on top of the existing non‑custodial architecture. Independent of the licence application track.

**Goal**: lift blended ARPU from ~€0.20/user/month to ~€3.30/user/month while staying clearly outside PSD2/MiCA (software/UX layer, not payment services).

---

## 0. Summary

| # | Feature | Domain | Effort | API surface |
|---|---|---|---|---|
| 1 | Subscription module + Stripe / Apple IAP / Google Play | new `Domain/Subscription` | 9d | 5 endpoints + 3 webhooks |
| 2 | Tier‑aware fee resolver (CQRS query handler) | `Domain/Wallet`, `Domain/Exchange`, `Domain/Ramp` | 7d | — (internal) |
| 3 | Universal quote endpoint with replay protection | new `Domain/Quote` | 3d | 1 endpoint |
| 4 | Revenue attribution + reconciliation + Filament dashboard | new `Domain/RevenueAnalytics` | 4d | — (admin only) |
| 5 | B2B BaaS commercial onboarding | extend `Domain/BaaS` | 5d | 3 admin endpoints |
| 6 | Card waitlist deposit (€5 refundable) | extend `Domain/Cards` | 3d | 2 endpoints |
| 7 | Entitlements query | new `Domain/Entitlements` | 2d | 1 endpoint |
| 8 | "Savings with Pro" calculator | extend `Domain/Subscription` | 1d | 1 endpoint |
| 9 | Transactions CSV/JSON export (Pro only) | extend `Domain/Wallet` | 2d | 2 endpoints |

**Total: 36 working days. Plan for 7 calendar weeks with code review, QA, and integration.**

---

## 0.1 Prerequisites (business)

These are not engineering tasks but block the engineering work. Do them in parallel:

- [ ] Stripe account with Stripe Tax enabled and EU VAT registration (founder, week 1)
- [ ] Stripe Bridge BD conversation: confirm marketplace / Connect platform fee mechanics (founder, week 1)
- [ ] Apple Developer Program account in legal-entity name + paid apps agreement signed + tax forms (founder, week 1)
- [ ] Google Play Console + Merchant Center linked to legal entity + tax forms (founder, week 1)
- [ ] Banking partner confirms incoming Stripe payouts to legal entity account (founder, week 2)
- [ ] Privacy policy + terms of service updated for paid features and EU consumer rights (legal, week 2)
- [ ] DPIA refresh — adding subscription, store identifiers, billing email is new processing under GDPR (legal, week 2)

---

## 0.2 Conventions

These apply to every section. Adherence is a hard merge gate.

### Money

- **API surface (request and response)**: decimal string with explicit currency, e.g. `{"amount": "4.99", "currency": "EUR"}`. Never a number literal — JSON loses precision.
- **Storage**: integer cents, e.g. `price_eur_cents INT UNSIGNED NOT NULL`. One column per (amount, currency).
- **Math**: `bcmath` exclusively — `bcadd`, `bcsub`, `bcmul`, `bcdiv`. Never `(float)` cast for money. Normalise inputs with `bcadd($val, '0', 4)` before math.
- **Currency**: pinned to `EUR` for v1.3.0. The `currency` field on every API surface MUST be present and MUST equal `EUR`. Any other value → `400 ERR_CUR_001`. Multi‑currency is v1.4+.
- **VAT**: Stripe Checkout uses Stripe Tax with **prices inclusive of VAT**. Apple / Google handle VAT inside their store cut (we receive net of VAT and store fee). Revenue events must record `gross_eur`, `vat_eur`, `provider_take_eur`, `net_eur` separately.

### Idempotency

- Every POST endpoint that has side effects accepts an `Idempotency-Key` HTTP header (matches existing `/pay`, `/pay/card`, wallet send convention — see `core-banking-prototype-laravel/CLAUDE.md`).
- Server must store `(user_id, idempotency_key, request_hash, response_body, expires_at)` and return the cached response on replay within 24 hours.
- Returning `409 ERR_IDEMPOTENCY_409` if the same key is presented with a different request body.
- Webhooks dedup by their store‑native event id: Stripe `event.id`, Apple `notificationUUID`, Google `messageId`. Stored in `processed_webhook_events` (TTL 30 days).

### Errors

- All error codes follow `ERR_<DOMAIN>_<NNN>` where `NNN` is a 3‑digit domain‑local id, **not** an HTTP status code (avoid the `ERR_QUOTE_410` confusion). Map to HTTP status in the controller.
- New domain prefixes used in this work: `ERR_SUB_*` (subscription), `ERR_QUO_*` (quote), `ERR_FEE_*` (fee resolver), `ERR_EXP_*` (export), `ERR_CUR_*` (currency), `ERR_IDEMPOTENCY_*` (idempotency cross‑cutting).
- Full list at the end of this document.

### Schema (MySQL 8)

- `BIGINT UNSIGNED` PKs unless explicitly UUID. UUID columns must be `CHAR(36)` and RFC 4122 v4 (per CLAUDE.md, MariaDB rejects non‑v4 UUIDs).
- Datetimes: `TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP` for created_at, `ON UPDATE CURRENT_TIMESTAMP` for updated_at. Store **UTC**.
- JSON: `JSON` (not `JSONB` — that's Postgres).
- Money columns: `INT UNSIGNED` for cents; `BIGINT UNSIGNED` only for ramp/swap notional volume above €21M.
- All multi‑column writes inside `DB::transaction()` with `lockForUpdate()` on read‑then‑write rows.

### Multi‑tenancy

- New tables introduced here are **global** (per‑user, not per‑tenant): `subscriptions`, `revenue_events`, `quotes`, `transaction_exports`, `processed_webhook_events`, `idempotency_keys`. They live on the default connection.
- Per CLAUDE.md: do not wrap flows that touch `UsesTenantConnection` models inside `DB::transaction()` — separate MySQL session causes self‑deadlock. Fee deduction touches `Domain/Wallet` (tenant) AND `revenue_events` (global). Use the saga pattern: write fee on tenant connection inside its own transaction, then dispatch a queued job that records the revenue event on the global connection. Add a regression test in `tests/MultiConnection/`.

### Security

- Authentication: Sanctum bearer tokens with abilities `['read', 'write', 'delete']` (matches existing test convention).
- Authorization: every endpoint declares required ability + tier guard explicitly (Pro endpoints check `entitlements.features.X` server‑side, never trust client).
- PII redaction in logs: receipts (Apple `signedTransactionInfo`, Google `purchaseToken`), Stripe customer email, payment method last4, tax id, full IP. Use `LogRedactor` middleware with an explicit allow‑list per channel.
- Webhook signature verification is **mandatory in non‑testing environments**. Per CLAUDE.md: gate test bypass with `app()->environment('local', 'testing')` — never `return true`.
- XML / external input parsed with `LIBXML_NONET`.

### CQRS alignment

- **Commands** (write): `StartSubscription`, `CancelSubscription`, `RecordSubscriptionRenewal`, `IssueRefund`, `RecordServiceFeeCharged`, `IssueQuote`, `ConsumeQuote`, `RequestTransactionExport`. Dispatched via the existing `app/Infrastructure/CommandBus`.
- **Queries** (read): `ResolveFeeTier`, `GetEntitlements`, `GetSavingsWithPro`, `GetSubscriptionState`. Dispatched via `app/Infrastructure/QueryBus`. The "fee resolver" is a Query handler — not a Service class.
- **Events** persisted via Spatie event sourcing v7.7+ in **domain‑specific** event tables: `subscription_events`, `quote_events`, `revenue_events_log`. Aggregates project to read models listed below.

### Versioning

- All endpoints land at `/api/v1/...`. Breaking shape changes go to `/v2`; additive changes (new optional fields) are allowed without a version bump. Mobile must tolerate unknown fields.

---

## 1. Subscription module — `Domain/Subscription`

### 1.1 Tiers

| Tier | Price (incl. VAT where applicable) | Trial | Payment sources |
|---|---|---|---|
| `free` | €0 | — | default |
| `pro_monthly` | €4.99 / mo | 7d (one per Apple/Google/Stripe account, lifetime) | Stripe Checkout · Apple IAP · Google Play Billing |
| `pro_annual` | €49 / yr | none | Stripe Checkout · Apple IAP · Google Play Billing |

### 1.2 Endpoints

All POST endpoints accept `Idempotency-Key` header.

#### `GET /api/v1/subscription/me`

Required ability: `read`.

```json
{
  "tier": "pro_monthly",
  "status": "active",
  "trialEndsAt": null,
  "currentPeriodEndsAt": "2026-06-08T12:00:00Z",
  "cancelAtPeriodEnd": false,
  "source": "stripe",
  "willDowngradeTo": null,
  "isPaused": false,
  "etag": "v1:7d3a1c..."
}
```

`status` ∈ `active | trialing | past_due | canceled | grace_period | paused | expired`.
`source` ∈ `stripe | apple_iap | google_play`.
`etag` is opaque; mobile sends it back as `If-None-Match` to get `304 Not Modified` if unchanged.

#### `POST /api/v1/subscription/checkout`

Required ability: `write`. Body:

```json
{ "plan": "pro_monthly", "trial": true, "returnUrl": "https://zelta.app/subscription/success", "currency": "EUR" }
```

Returns `200 OK` with `{ "checkoutUrl": "...", "sessionId": "cs_..." }`. The `returnUrl` MUST match the configured allow‑list (mobile uses a deep link `zelta://subscription/success`, web uses `https://zelta.app/subscription/success`).

#### `POST /api/v1/subscription/iap/verify`

Required ability: `write`. Body:

```json
{ "store": "apple", "receipt": "<base64>", "productId": "zelta_pro_monthly", "platform": "ios", "appVersion": "1.3.0" }
```

Server verifies the receipt against Apple StoreKit 2 / Google Play Developer API (never trust the client). Returns `200 OK` with the same shape as `GET /subscription/me`.

#### `POST /api/v1/subscription/cancel`

Required ability: `write`. Body: `{ "reason": "<optional free text, max 500 chars>" }`. Sets `cancelAtPeriodEnd=true` (no proration). Returns `GET /subscription/me` shape.

For Apple / Google subscriptions: cancellation is performed at the store, not by us. This endpoint returns `409 ERR_SUB_010` with a deep link to `itms-apps://apps.apple.com/account/subscriptions` or `https://play.google.com/store/account/subscriptions` — mobile renders a "Manage in App Store" CTA.

#### `GET /api/v1/me/entitlements`

Required ability: `read`. Single source of truth for feature flags + fee tier. Mobile calls on login, on resume, and on every WebSocket `entitlements.changed`.

```json
{
  "version": 1,
  "tier": "pro_monthly",
  "features": {
    "hardwareWallet": true,
    "multiDeviceSync": true,
    "advancedAnalytics": true,
    "prioritySupport": true,
    "cardWaitlistFrontOfQueue": true,
    "txExport": true,
    "subscriptionsEnabled": true
  },
  "feeTier": {
    "txFlatEur": "0.05",
    "swapMarginBps": 20,
    "rampMarginBps": 50,
    "currency": "EUR"
  },
  "limits": {
    "swapsPerDay": null,
    "rampsPerMonth": null,
    "quotesPerMinute": 60
  },
  "etag": "v1:9a2f..."
}
```

> Free tier returns the same shape with `features.*: false` (except `subscriptionsEnabled`) and `feeTier: { txFlatEur: "0.20", swapMarginBps: 50, rampMarginBps: 100, currency: "EUR" }`.

`version` is the schema version. Increment when shape changes; mobile tolerates unknown fields and falls back to free tier if `version` is unknown.

### 1.3 Webhooks

All webhooks dedup by their store‑native id stored in `processed_webhook_events`.

#### `POST /webhooks/stripe/subscriptions`

Verify `Stripe-Signature` header against `STRIPE_WEBHOOK_SECRET`. Handle:
- `customer.subscription.created`, `.updated`, `.deleted`, `.trial_will_end`, `.paused`, `.resumed`
- `invoice.payment_failed`, `invoice.payment_succeeded`
- `charge.refunded`, `charge.dispute.created`

#### `POST /webhooks/apple/notifications`

App Store Server Notifications V2. Verify JWS chain to Apple's root cert. Handle: `SUBSCRIBED`, `DID_RENEW`, `DID_CHANGE_RENEWAL_STATUS`, `DID_FAIL_TO_RENEW`, `EXPIRED`, `GRACE_PERIOD_EXPIRED`, `REFUND`, `REFUND_DECLINED`, `REVOKE`, `CONSUMPTION_REQUEST`.

#### `POST /webhooks/google/play`

Real‑time Developer Notifications via Pub/Sub. Verify by re‑fetching the subscription via Google Play Developer API. Handle: `SUBSCRIPTION_PURCHASED`, `.RENEWED`, `.CANCELED`, `.EXPIRED`, `.IN_GRACE_PERIOD`, `.PAUSED`, `.RESTARTED`, `.REVOKED`, `.REFUNDED`.

### 1.4 Refund propagation

Refunds always result in **immediate** entitlement downgrade — not period‑end. Reasoning: regulator‑safe, store‑policy‑safe, and matches user expectation.

```
Webhook(refund) → IssueRefund command
  → Subscription aggregate: status := 'expired', cancel_at_period_end := false
  → Emit RefundIssued event
  → Project: subscriptions read model + revenue_events (negative entry)
  → Broadcast subscription.changed + entitlements.changed on private-user.{userId}
  → Send email "We've issued a refund and your Pro access has ended"
```

Mobile entitlement cache is invalidated by the broadcast and the next call to a Pro‑gated endpoint returns `403 ERR_SUB_005`.

### 1.5 Multi‑store conflict policy

A user can only have **one active subscription** at a time. Policy:

1. **First sub wins for billing.** If a user already has an active sub on store A and starts checkout on store B, the checkout session is allowed to *complete* but the verify call returns `409 ERR_SUB_002` with `existingSource` populated, instructing mobile to:
   - Refund the new sub via the new store (mobile shows a flow + deep link to manage at the new store)
   - Keep the original sub
2. **DB enforcement**: serialise via Redis lock `lock:subscription:{userId}` (TTL 30s) on every write path. Inside the lock, check for active sub before insert. If two writes race, the loser gets `ERR_SUB_002`.
3. The unique partial index `uniq_subscriptions_active_per_user` is the last line of defence — never the primary mechanism (DB errors are user‑hostile).

### 1.6 Trial abuse prevention

Trial eligibility is gated by:
1. `users.has_used_trial = false`, AND
2. **For Stripe**: no historical `subscriptions` row for this user with `trial_started_at IS NOT NULL`.
3. **For Apple**: trust Apple's `intro_period` flag — Apple enforces one‑intro‑per‑Apple‑account at the store level (cross‑user under the same Apple ID).
4. **For Google**: same as Apple — trust Google's `paymentState` and intro flag.

If `subscription/checkout` is called with `trial: true` for an ineligible user, return `403 ERR_SUB_003` with `eligibleAfter: null` (lifetime block) so mobile falls back to a paid CTA.

### 1.7 Database schema (MySQL 8)

```sql
CREATE TABLE subscriptions (
    id                          CHAR(36) PRIMARY KEY,
    user_id                     CHAR(36) NOT NULL,
    tier                        VARCHAR(32) NOT NULL,
    status                      VARCHAR(32) NOT NULL,
    source                      VARCHAR(32) NOT NULL,
    stripe_subscription_id      VARCHAR(255) NULL,
    stripe_customer_id          VARCHAR(255) NULL,
    apple_original_tx_id        VARCHAR(255) NULL,
    google_purchase_token       TEXT NULL,
    google_purchase_token_hash  CHAR(64) NULL,             -- sha256, indexable
    trial_started_at            TIMESTAMP NULL,
    trial_ends_at               TIMESTAMP NULL,
    current_period_starts_at    TIMESTAMP NULL,
    current_period_ends_at      TIMESTAMP NULL,
    cancel_at_period_end        BOOLEAN NOT NULL DEFAULT FALSE,
    paused_at                   TIMESTAMP NULL,
    canceled_at                 TIMESTAMP NULL,
    expired_at                  TIMESTAMP NULL,
    refunded_at                 TIMESTAMP NULL,
    last_event_id               CHAR(36) NULL,             -- last applied subscription_events.id
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_subscriptions_user (user_id),
    UNIQUE KEY uniq_subscriptions_stripe (stripe_subscription_id),
    UNIQUE KEY uniq_subscriptions_apple (apple_original_tx_id),
    UNIQUE KEY uniq_subscriptions_google_hash (google_purchase_token_hash),
    KEY idx_subscriptions_period_end (current_period_ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Partial unique active sub via a generated column trick (MySQL 8 supports functional indexes)
ALTER TABLE subscriptions
    ADD COLUMN active_user_id CHAR(36)
        AS (CASE WHEN status IN ('active','trialing','past_due','grace_period','paused') THEN user_id END)
        STORED,
    ADD UNIQUE KEY uniq_subscriptions_active_per_user (active_user_id);

CREATE TABLE subscription_events (
    id              CHAR(36) PRIMARY KEY,
    aggregate_uuid  CHAR(36) NOT NULL,
    aggregate_version INT UNSIGNED NOT NULL,
    event_class     VARCHAR(255) NOT NULL,
    event_payload   JSON NOT NULL,
    metadata        JSON NOT NULL,
    created_at      TIMESTAMP(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    KEY idx_se_aggregate (aggregate_uuid, aggregate_version),
    UNIQUE KEY uniq_se_aggregate_version (aggregate_uuid, aggregate_version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE processed_webhook_events (
    provider        VARCHAR(32) NOT NULL,                  -- stripe | apple | google
    event_id        VARCHAR(255) NOT NULL,
    received_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at      TIMESTAMP NOT NULL,
    PRIMARY KEY (provider, event_id),
    KEY idx_pwe_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE idempotency_keys (
    user_id         CHAR(36) NOT NULL,
    idempotency_key VARCHAR(255) NOT NULL,
    request_hash    CHAR(64) NOT NULL,
    response_status INT UNSIGNED NOT NULL,
    response_body   JSON NOT NULL,
    expires_at      TIMESTAMP NOT NULL,
    PRIMARY KEY (user_id, idempotency_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

UUIDs MUST be RFC 4122 v4 — generate via `Str::uuid()` (Laravel) or `Uuid::uuid4()` (ramsey). Never hash‑derived UUIDs.

### 1.8 Domain events (Spatie)

`subscription_events` stores events for the `Subscription` aggregate. Events:

- `SubscriptionStarted { user_id, tier, source, started_at, trial_until, store_identifiers }`
- `SubscriptionRenewed { user_id, period_start, period_end, gross_eur_cents, vat_eur_cents, provider_take_eur_cents, net_eur_cents }`
- `SubscriptionCanceled { user_id, at, reason, cancel_at_period_end }`
- `SubscriptionExpired { user_id, at, reason }`
- `SubscriptionTrialStarted { user_id, until }`
- `SubscriptionTrialEnded { user_id, at, converted_to_paid }`
- `SubscriptionPaymentFailed { user_id, attempt, next_retry_at }`
- `SubscriptionPaused { user_id, at, until }`
- `SubscriptionResumed { user_id, at }`
- `RefundIssued { user_id, gross_eur_cents, reason, occurred_at }`

Projectors fan out into the `subscriptions` read model and into `revenue_events` (§4).

### 1.9 WebSocket broadcasts

Broadcast both events together to avoid the race where mobile sees `subscription.changed` but reads stale `entitlements`:

- `subscription.changed` on `private-user.{userId}` → payload = `GET /subscription/me` body
- `entitlements.changed` on `private-user.{userId}` → payload = `GET /me/entitlements` body

Both fired transactionally after every state change. Mobile receives both and updates its caches.

---

## 2. Tier‑aware fee resolver (Query handler)

Implemented as a CQRS query handler under `app/Infrastructure/QueryBus`, not a Service.

### 2.1 Query

```php
namespace App\Domain\Subscription\Queries;

final readonly class ResolveFeeTier
{
    public function __construct(public string $userId) {}
}

namespace App\Domain\Subscription\Queries\Handlers;

final class ResolveFeeTierHandler
{
    public function __invoke(ResolveFeeTier $q): FeeTier
    {
        // Read from cached entitlements (5min TTL, busted on subscription.changed)
        // Returns tx_flat_eur_cents, swap_margin_bps, ramp_margin_bps
    }
}
```

### 2.2 Where it plugs in

| Action | Existing domain | Insertion point |
|---|---|---|
| Outgoing tx | `Domain/Wallet/Actions/PrepareTransaction` | After upstream gas estimation, before returning the prepare payload to the client |
| Token swap | `Domain/Exchange/QuoteAggregator` | After upstream Uniswap V3 / Curve quote, before returning |
| Fiat ramp | `Domain/Ramp/SessionFactory` | After upstream Stripe Bridge / Onramper quote, before returning |

All three call `ResolveFeeTier` then **emit a `ServiceFeeCharged` event** at the point the fee is actually deducted (which may be after the user signs and submits, not at quote time).

### 2.3 Fee accounting

```
ServiceFeeCharged {
  user_id,
  source: tx | swap | ramp,
  reference_id,                      -- tx_hash | swap_id | ramp_session_id
  gross_eur_cents,
  vat_eur_cents,                     -- 0 for B2C — Stripe Tax handles this on subscription, fees are commercial software (B2B-style, EU reverse charge inapplicable)
  external_provider,                 -- 'stripe_bridge' | 'uniswap_v3' | 'curve' | null
  external_provider_take_eur_cents,
  net_eur_cents,                     -- gross - vat - external_take
  user_tier_at_time,                 -- snapshot for audit
  occurred_at
}
```

Stored in `revenue_events_log` (event-sourced) and projected to `revenue_events` (read model — §4).

### 2.4 Collection mechanism per fee type

| Fee type | Mechanism | Concurrency |
|---|---|---|
| Tx flat fee | Deducted from stablecoin transfer amount in `PrepareTransaction`; user signs the post‑fee amount; settled on chain confirmation | Saga: prepare on tenant connection (with `lockForUpdate` on the wallet balance row), dispatch `RecordServiceFeeCharged` job to global connection. **Never wrap both in one `DB::transaction`** (multi-connection deadlock per CLAUDE.md). |
| Swap margin | Layered into quote; user signs post-margin amount | Same saga as tx fee |
| Ramp margin | Quoted to user before checkout. **Implementation**: Stripe Connect with `application_fee_amount` if Bridge supports it; otherwise a separate Stripe charge for the margin against the user's saved card, paying Bridge the net | Settled when Stripe Bridge fires the success webhook |

> **Decision required week 1**: confirm with Stripe Bridge BD whether they support Stripe Connect platform fees. If yes, much simpler. If no, implement the dual-charge fallback. Spec assumes the worst case.

---

## 3. Universal quote endpoint — `Domain/Quote`

A new bounded context — quotes are stateful, replay‑protected, and used across multiple domains.

### 3.1 `POST /api/v1/quote`

Required ability: `write` (because it reserves upstream rates). Accepts `Idempotency-Key`.

**Request**:

```json
{
  "kind": "send",
  "amount": "100.00",
  "currency": "EUR",
  "from": { "asset": "USDC", "network": "polygon" },
  "to":   { "asset": "EUR",  "network": "sepa" }
}
```

`kind` ∈ `send | swap | ramp_buy | ramp_sell`.

**Response** `200 OK`:

```json
{
  "quoteId": "qt_01HQ...",
  "expiresAt": "2026-05-08T12:00:30Z",
  "currency": "EUR",
  "breakdown": {
    "userPays":       { "amount": "101.50", "currency": "EUR" },
    "networkFee":     { "amount": "0.10",   "currency": "EUR", "label": "Network fee" },
    "providerFee":    { "amount": "0.40",   "currency": "EUR", "label": "Bridge fee", "provider": "stripe_bridge" },
    "serviceFee":     { "amount": "1.00",   "currency": "EUR", "label": "Service fee", "tier": "free" },
    "vat":            { "amount": "0.00",   "currency": "EUR", "label": "VAT" },
    "userReceives":   { "amount": "100.00", "currency": "USDC", "network": "polygon" }
  },
  "feeTier": "free",
  "saveWithPro":    { "amount": "0.50", "currency": "EUR", "label": "You'd save €0.50 with Pro" }
}
```

- `expiresAt`: 30s for swaps, 60s for ramp, 5 min for sends (gas estimation drift is slow).
- `saveWithPro` omitted if user is already Pro.

### 3.2 Replay protection

Server stores the full quote payload server-side keyed by `quoteId`:

```sql
CREATE TABLE quotes (
    id                CHAR(36) PRIMARY KEY,
    user_id           CHAR(36) NOT NULL,
    user_tier         VARCHAR(32) NOT NULL,
    kind              VARCHAR(16) NOT NULL,
    request_payload   JSON NOT NULL,
    response_payload  JSON NOT NULL,
    expires_at        TIMESTAMP NOT NULL,
    consumed_at       TIMESTAMP NULL,
    consumed_by       VARCHAR(255) NULL,                   -- tx_hash | swap_id | ramp_session_id
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_quotes_user_expires (user_id, expires_at),
    KEY idx_quotes_consumed (consumed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 3.3 Submission contract

Every consumer endpoint accepts a `quoteId`:

```
POST /api/v1/wallet/transactions/submit  { quoteId, signedUserOp }
POST /api/v1/exchange/swap/execute        { quoteId, signature }
POST /api/v1/ramp/session/create          { quoteId }
```

Server validates inside one transaction:
1. `quotes.id = quoteId AND quotes.user_id = currentUserId` → else `404 ERR_QUO_001`
2. `consumed_at IS NULL` → else `409 ERR_QUO_002` (replay)
3. `expires_at > NOW()` → else `410 ERR_QUO_003` (expired)
4. The signed payload's effective parameters match the stored `request_payload + response_payload` (e.g. signed amount equals `breakdown.userReceives.amount` or `userPays.amount` per kind) → else `409 ERR_QUO_004`
5. Mark `consumed_at = NOW()`, `consumed_by = <ref>` with `lockForUpdate()` on the row to prevent racing consumes

### 3.4 Rate limiting

- 60 quotes / minute / user (matches `entitlements.limits.quotesPerMinute`)
- 600 quotes / minute / IP (anonymous DoS protection)
- Returns `429 ERR_QUO_005` with `Retry-After` header
- Implementation: Redis sliding window using `Cache::add($key, 0, $ttl)` + `Cache::increment()` (per CLAUDE.md — never read‑then‑write counters)

### 3.5 Authorization gating

Quote requests are gated by upstream pre‑conditions:
- `kind: ramp_buy | ramp_sell` requires KYC level ≥ Basic → else `403 ERR_QUO_006` with `requiresKycLevel` populated
- `kind: send` requires destination address screening passed (existing GoPlus / Chainalysis check) → else `403 ERR_QUO_007`
- `kind: swap` requires source asset balance ≥ amount → else `400 ERR_QUO_008`

These are advisory: mobile can fetch the quote anyway by passing `?dryRun=true` (returns the breakdown without reserving anything — useful for pricing previews).

---

## 4. Revenue attribution + reconciliation

### 4.1 Read model

```sql
CREATE TABLE revenue_events (
    id                          CHAR(36) PRIMARY KEY,
    user_id                     CHAR(36) NOT NULL,
    user_id_hash                CHAR(64) NOT NULL,                -- for post-deletion analytics
    cohort_month                DATE NOT NULL,
    source                      VARCHAR(32) NOT NULL,             -- tx_fee | swap_margin | ramp_margin | subscription_initial | subscription_renewal | kyc_margin | baas | refund
    sign                        TINYINT NOT NULL DEFAULT 1,       -- 1 for revenue, -1 for refund
    gross_eur_cents             INT UNSIGNED NOT NULL,
    vat_eur_cents               INT UNSIGNED NOT NULL DEFAULT 0,
    provider_take_eur_cents     INT UNSIGNED NOT NULL DEFAULT 0,
    net_eur_cents               INT UNSIGNED NOT NULL,
    provider                    VARCHAR(32) NULL,                  -- stripe | apple | google | stripe_bridge | uniswap_v3 | curve
    reference_id                VARCHAR(255) NULL,
    occurred_at                 TIMESTAMP NOT NULL,
    created_at                  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_revenue_user_cohort (user_id, cohort_month),
    KEY idx_revenue_source_month (source, occurred_at),
    KEY idx_revenue_provider_month (provider, occurred_at),
    KEY idx_revenue_user_hash (user_id_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

`user_id_hash = sha256(user_id || HASH_PEPPER)` — survives GDPR deletion (see §4.4).

### 4.2 Projectors

Project from these events into `revenue_events`:
- `ServiceFeeCharged` → `tx_fee | swap_margin | ramp_margin`
- `SubscriptionStarted` → `subscription_initial`
- `SubscriptionRenewed` → `subscription_renewal`
- `RefundIssued` → `refund` with `sign = -1`
- `KycCompleted` → `kyc_margin`
- `BaasInvoiceIssued` → `baas`

### 4.3 Filament dashboard

Under `Admin → Revenue`. Required widgets:
- **ARPU per cohort, per month** — line chart
- **Revenue split by source** — stacked area
- **Top 100 users by net revenue** — table
- **Free → Pro conversion rate by cohort** — funnel
- **Provider take rate** — % of GMV lost to Stripe / Apple / Google / DEX
- **Reconciliation variance** — see §4.4
- **Refund rate (30‑day rolling)** — line, alert if >2%

### 4.4 Reconciliation (P0 for prod)

Monthly job `php artisan revenue:reconcile {month}`:
1. Pull Stripe payouts via Stripe Reporting API
2. Pull Apple settlement reports (downloaded via App Store Connect API)
3. Pull Google Play earnings reports (downloaded via Google Play Console API)
4. For each provider, sum `revenue_events` where `provider=X AND occurred_at BETWEEN ...`
5. Diff against actual cash received (after store cut + VAT)
6. Write to `revenue_reconciliations` table with `expected_net_cents`, `actual_net_cents`, `variance_cents`
7. If `|variance| > €50` or `>0.5%` of expected: PagerDuty alert to founder

```sql
CREATE TABLE revenue_reconciliations (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    period_month        DATE NOT NULL,
    provider            VARCHAR(32) NOT NULL,
    expected_net_cents  BIGINT NOT NULL,
    actual_net_cents    BIGINT NOT NULL,
    variance_cents      BIGINT NOT NULL,
    notes               TEXT NULL,
    reconciled_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_period_provider (period_month, provider)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 4.5 GDPR — pseudonymisation on account deletion

Event sourcing means we cannot delete events. Policy:

When a user invokes their right to erasure (Art. 17 GDPR):
1. `users.email`, `users.name`, billing addresses, etc. are deleted/pseudonymised in the `users` table.
2. `revenue_events.user_id` is replaced with `NULL`; `user_id_hash` is **kept** for cohort analytics (irreversible — sha256 with secret pepper).
3. `subscription_events.event_payload` is rewritten to remove identifying fields (the aggregate stays, semantics preserved). This is the one allowed "rewriting history" — log every such operation in `gdpr_erasure_log`.
4. Document this in the privacy policy (Article 14 transparency). Legal owner: founder.

### 4.6 PII / access controls in the dashboard

- The Filament Revenue dashboard does **not** show user emails or names — only `user_id_hash` short prefix and aggregate metrics.
- Drilling into a specific user's revenue is gated by an explicit "Investigate user" button that requires re-authentication and is logged for audit.

---

## 5. B2B BaaS commercial onboarding — extends `Domain/BaaS`

### 5.1 Background

`config/baas.php` defines tiers (Starter €99, Growth €499, Enterprise €1,999). Missing: commercial pipeline + contract management.

### 5.2 Endpoints (admin‑only, `role:admin` middleware)

#### `POST /api/admin/baas/tenants`

```json
{
  "companyName": "Acme Neobank Ltd",
  "tier": "enterprise",
  "customMonthlyPriceEurCents": 249900,
  "currency": "EUR",
  "billingEmail": "ap@acme.com",
  "salesContactUserId": "<uuid>",
  "contractDocumentId": "<uuid>",
  "noticePeriodDays": 90,
  "slaTier": "premium",
  "jurisdiction": "LT"
}
```

Returns the tenant id + a one‑time setup link sent to billing email.

#### `GET /api/admin/baas/tenants/{id}`

Tenant detail with: status, contract on file, current month usage, historical revenue, last invoice, churn risk score (basic: `last_login_days_ago`, `mtd_api_calls_ratio_to_30d_avg`).

#### `POST /api/admin/baas/tenants/{id}/contract`

Upload signed PDF contract. Stored encrypted at rest; metadata indexed for search; SHA-256 logged for non-repudiation.

### 5.3 Contract template — required fields

Backend validates contract metadata includes:
- `noticePeriodDays` (default 90 for Enterprise, 30 for Growth)
- `slaTier` ∈ `standard | premium | bespoke`
- `jurisdiction` (ISO 3166-1 alpha-2)
- `liabilityCapEurCents` (default: 12 × monthly fee)
- `dataProcessorAddendumSigned` (required for tenants in EU/UK)
- `effectiveAt`, `endsAt` (or `null` for evergreen)

These are not contract terms (legal authors them) — they're metadata for filtering and renewal tracking.

### 5.4 Schema additions

```sql
ALTER TABLE baas_tenants
    ADD COLUMN custom_monthly_price_eur_cents INT UNSIGNED NULL,
    ADD COLUMN currency CHAR(3) NOT NULL DEFAULT 'EUR',
    ADD COLUMN billing_email VARCHAR(255) NULL,
    ADD COLUMN sales_contact_user_id CHAR(36) NULL,
    ADD COLUMN contract_document_id CHAR(36) NULL,
    ADD COLUMN sales_status VARCHAR(32) NOT NULL DEFAULT 'prospecting',
    ADD COLUMN notice_period_days INT UNSIGNED NULL,
    ADD COLUMN sla_tier VARCHAR(32) NULL,
    ADD COLUMN jurisdiction CHAR(2) NULL,
    ADD COLUMN liability_cap_eur_cents BIGINT UNSIGNED NULL,
    ADD COLUMN signed_at TIMESTAMP NULL,
    ADD COLUMN effective_at TIMESTAMP NULL,
    ADD COLUMN ends_at TIMESTAMP NULL,
    ADD COLUMN churned_at TIMESTAMP NULL;
```

### 5.5 Filament admin panel

Under `Admin → BaaS → Tenants`. Pipeline view: prospecting → demo → contract → signed → live → churned. State changes emit `BaasTenantStatusChanged` event (projects to `revenue_events` if relevant).

---

## 6. Card waitlist deposit — extends `Domain/Cards`

### 6.1 Endpoints (auth required)

#### `POST /api/v1/cards/waitlist/deposit`

Accepts `Idempotency-Key`. Body: `{ "currency": "EUR" }`.

Returns `{ "checkoutUrl": "...", "sessionId": "cs_..." }`.

#### `GET /api/v1/cards/waitlist/deposit/status`

```json
{ "deposited": true, "amountEurCents": 500, "depositedAt": "2026-05-08T12:00:00Z", "refundableAfter": "2027-11-08T00:00:00Z" }
```

### 6.2 Schema additions

```sql
ALTER TABLE card_waitlist
    ADD COLUMN deposit_paid BOOLEAN NOT NULL DEFAULT FALSE,
    ADD COLUMN deposit_amount_eur_cents INT UNSIGNED NULL,
    ADD COLUMN deposit_payment_id VARCHAR(255) NULL,
    ADD COLUMN deposit_paid_at TIMESTAMP NULL,
    ADD COLUMN deposit_refunded_at TIMESTAMP NULL,
    ADD COLUMN deposit_refund_reason VARCHAR(64) NULL;
```

### 6.3 Position drift handling

Existing `position INTEGER` becomes stale when users leave / refund. Fix:
- Keep `joined_at` as the canonical ordering field
- Compute `position` on read: `ROW_NUMBER() OVER (ORDER BY deposit_paid DESC, joined_at ASC)` (deposit holders first)
- The `position` column becomes a denormalised cache, recomputed nightly by `CardWaitlistRecomputeJob`

### 6.4 Refund triggers

| Trigger | Refund window | Mechanism |
|---|---|---|
| Card programme launches and user activates | Next billing cycle (≤30d) | Manual ops with confirmation email |
| 18 months elapsed and cards not live | T+18m, automatic | Scheduled `cards:refund-stale-deposits` (daily) |
| User closes their account | T+7d (pending no fraud signals) | Triggered by `UserAccountClosed` event |
| User explicitly requests refund pre-launch | T+7d | Self-serve request via mobile, admin approval |

All refunds emit `CardWaitlistDepositRefunded` event (negative `revenue_event` if it was previously recognised).

---

## 7. Entitlements query — `Domain/Entitlements`

Already specified in §1.2 (`GET /api/v1/me/entitlements`). One additional implementation note:

- The handler reads from a Redis cache keyed by `user_id`, TTL 5 minutes
- Cache busted on `subscription.changed` event firing (the projector for the subscription read model writes the new entitlements snapshot to cache on commit — sub-millisecond hit on the WebSocket broadcast)
- Mobile passes `If-None-Match: <etag>` to skip body when unchanged

---

## 8. "Savings with Pro" calculator — extends `Domain/Subscription`

### 8.1 `GET /api/v1/me/savings-with-pro`

```json
{
  "last30DaysFreeFeesEurCents": 840,
  "estimatedProFeesEurCents":   210,
  "monthlySavingsEurCents":     630,
  "breakEvenAtVolumeMet":       true,
  "currency":                   "EUR",
  "sampleSize":                 12,
  "calculatedAt":               "2026-05-08T12:00:00Z"
}
```

For Pro users: returns `204 No Content`.
For users with `sampleSize < 5`: `breakEvenAtVolumeMet: false` (mobile hides banner).

### 8.2 Calculation

Read last 30 days of `revenue_events` for this user:
1. `freeFees = SUM(gross_eur_cents) WHERE source IN ('tx_fee','swap_margin','ramp_margin')`
2. `proFees = re-rate the same volumes at Pro tier`:
   - `tx_count × 5`
   - `swap_volume_cents × 20 / 10000`
   - `ramp_volume_cents × 50 / 10000`
3. `monthlySavings = max(0, freeFees - proFees)`
4. `breakEvenAtVolumeMet = monthlySavings >= 499 AND sampleSize >= 5`

All math via `bcmath`; never `(float)`.

### 8.3 Caching

Per-user, TTL 1 hour. Bust on:
- New `ServiceFeeCharged` event for the user (projector pushes to cache)
- `subscription.changed` (Pro user → returns 204)

---

## 9. Transactions export — extends `Domain/Wallet`

### 9.1 Endpoints (Pro-only — `entitlements.features.txExport` enforced server-side)

#### `POST /api/v1/wallet/transactions/export`

Accepts `Idempotency-Key`. Body:

```json
{
  "format": "csv",
  "from":   "2026-01-01",
  "to":     "2026-05-08",
  "networks": ["polygon", "base", "solana"],
  "assets": ["USDC", "EUR"],
  "includeFees": true,
  "language": "en"
}
```

`format` ∈ `csv | json`. `language` for CSV column headers: `en | lt`.

Errors:
- `403 ERR_SUB_005` — not Pro
- `429 ERR_EXP_001` — rate limited (1 export / hour, max 3 / day)
- `400 ERR_EXP_002` — invalid filter (range >5y, end before start, unknown network)

Response `202 Accepted`:
```json
{ "exportId": "exp_01HQ...", "status": "queued", "estimatedSecondsToComplete": 45 }
```

#### `GET /api/v1/wallet/transactions/export/{exportId}`

Returns `{exportId, status, progressPercent}` while `processing`; on `ready`:

```json
{
  "exportId": "exp_01HQ...",
  "status": "ready",
  "downloadUrl": "https://exports.zelta.app/...?signature=...",
  "expiresAt": "2026-05-09T12:00:00Z",
  "rowCount": 1247,
  "fileSizeBytes": 184320
}
```

`downloadUrl` is a pre-signed S3 URL valid 24h. After expiry the file is purged.

### 9.2 Implementation

- Laravel Horizon job `ExportTransactionsJob` queued with low priority (`exports` queue)
- Stream rows to S3 via `League\Csv` (CSV) or chunked JSON encoder — never load full set into memory
- On completion: send email + push notification with download link
- Stored at `s3://zelta-exports/{userId}/{exportId}.{format}` with SSE-S3 server-side encryption
- S3 lifecycle policy auto-purges after 30 days (legal retention is in the underlying tx records, not the export file)

### 9.3 CSV schema

```csv
timestamp,network,direction,asset,amount,counterparty,tx_hash,service_fee_eur,provider_fee_eur,network_fee_eur,note
2026-04-15T13:42:11Z,polygon,outgoing,USDC,100.000000,0x742d...,0x9a3f...,0.20,0.00,0.0023,Sent to Alice
```

JSON variant uses the same data with proper types (numbers as numbers, no string formatting).

### 9.4 Schema

```sql
CREATE TABLE transaction_exports (
    id              CHAR(36) PRIMARY KEY,
    user_id         CHAR(36) NOT NULL,
    format          VARCHAR(8) NOT NULL,
    filter          JSON NOT NULL,
    status          VARCHAR(16) NOT NULL DEFAULT 'queued',
    progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
    s3_key          VARCHAR(255) NULL,
    row_count       INT UNSIGNED NULL,
    file_size_bytes BIGINT UNSIGNED NULL,
    error_code      VARCHAR(32) NULL,
    requested_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    completed_at    TIMESTAMP NULL,
    expires_at      TIMESTAMP NULL,
    KEY idx_exports_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### 9.5 Domain events

- `TransactionExportRequested { user_id, export_id, format, filter, requested_at }`
- `TransactionExportCompleted { user_id, export_id, row_count, file_size_bytes, completed_at }`
- `TransactionExportFailed    { user_id, export_id, error_code, failed_at }`

---

## 10. Configuration

### 10.1 `config/subscription.php`

```php
return [
    'plans' => [
        'pro_monthly' => [
            'price_eur_cents' => 499,
            'stripe_price_id' => env('STRIPE_PRICE_PRO_MONTHLY'),
            'apple_product_id' => 'zelta_pro_monthly',
            'google_product_id' => 'zelta_pro_monthly',
            'trial_days' => 7,
        ],
        'pro_annual' => [
            'price_eur_cents' => 4900,
            'stripe_price_id' => env('STRIPE_PRICE_PRO_ANNUAL'),
            'apple_product_id' => 'zelta_pro_annual',
            'google_product_id' => 'zelta_pro_annual',
            'trial_days' => 0,
        ],
    ],
    'webhook_secrets' => [
        'stripe' => env('STRIPE_WEBHOOK_SECRET'),
        'apple_root_cert' => env('APPLE_ROOT_CERT_PATH'),
        'google_pubsub_audience' => env('GOOGLE_PUBSUB_AUDIENCE'),
    ],
    'allowed_return_urls' => [
        'https://zelta.app/subscription/success',
        'https://zelta.app/subscription/cancel',
        'zelta://subscription/success',
        'zelta://subscription/cancel',
    ],
];
```

### 10.2 `config/fees.php`

```php
return [
    'tiers' => [
        'free' => [
            'tx_flat_eur_cents' => 20,
            'swap_margin_bps' => 50,
            'ramp_margin_bps' => 100,
        ],
        'pro_monthly' => [
            'tx_flat_eur_cents' => 5,
            'swap_margin_bps' => 20,
            'ramp_margin_bps' => 50,
        ],
        'pro_annual' => [
            'tx_flat_eur_cents' => 5,
            'swap_margin_bps' => 20,
            'ramp_margin_bps' => 50,
        ],
    ],
    'currency' => 'EUR',
];
```

### 10.3 Environment variables

```
STRIPE_PRICE_PRO_MONTHLY=price_...
STRIPE_PRICE_PRO_ANNUAL=price_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_TAX_ENABLED=true
APPLE_ROOT_CERT_PATH=/secrets/AppleRootCA-G3.cer
APPLE_BUNDLE_ID=com.zelta.wallet
APPLE_ENVIRONMENT=production
GOOGLE_PUBSUB_AUDIENCE=//pubsub.googleapis.com/projects/...
GOOGLE_PLAY_PACKAGE_NAME=com.zelta.wallet
GOOGLE_PLAY_SERVICE_ACCOUNT_JSON=/secrets/play-service-account.json
S3_EXPORTS_BUCKET=zelta-exports
HASH_PEPPER=<64-byte-secret>
SUBSCRIPTIONS_ENABLED=false
FEES_ENABLED=false
QUOTE_REQUIRED=false
```

---

## 11. Test strategy

### 11.1 Unit (Pest)

- `ResolveFeeTierHandlerTest` — every tier × every fee type; cache hit + miss
- `SubscriptionAggregateTest` — every state transition (start, trial, renew, cancel, expire, refund, pause, resume)
- `EntitlementsServiceTest` — cache invalidation on every relevant event
- `QuoteAggregateTest` — issue, consume, expire, double-consume rejected
- `SavingsCalculatorTest` — multiple usage profiles, Pro user 204, low-sample case
- All fee math tests use `bcmath` assertions, not float equality

### 11.2 Integration

- `StripeWebhookTest` — signature verification, replay dedup via `processed_webhook_events`
- `AppleNotificationTest` — JWS verification against fixture chain, every notification type
- `GooglePlayNotificationTest` — purchase token re-verification
- `QuoteEndpointTest` — every kind, free vs pro, expiry, replay rejected, mismatch rejected, rate limit
- `RevenueEventProjectionTest` — projection from each event type produces correct read-model row
- `IdempotencyTest` — same key + body → cached response; same key + different body → 409
- `MultiStoreConflictTest` — concurrent Apple + Stripe purchase → loser gets `ERR_SUB_002`
- `RefundPropagationTest` — refund webhook → immediate downgrade + entitlements broadcast
- `ReconciliationJobTest` — synthetic Stripe report vs revenue_events → variance correctly computed

### 11.3 Concurrency / multi-connection (`tests/MultiConnection/`)

- `FeeDeductionMultiConnectionTest` — wallet write on tenant connection + revenue event on global connection without deadlock (regression for the saga pattern)
- `QuoteConsumeRaceTest` — two concurrent submits with same quoteId → one wins, one gets `ERR_QUO_002`
- `SubscriptionRaceTest` — two concurrent IAP verify calls → exactly one active subscription, the other gets `ERR_SUB_002`

### 11.4 E2E (Behat)

- `Free user sends tx → quote shows €0.20 fee → tx submits with quote ID → revenue_event recorded`
- `User starts trial via web Stripe Checkout → entitlements update → fees drop`
- `Trial ends without payment → tier reverts to free → entitlements broadcast`
- `Apple subscription cancelation webhook → tier downgrades at period end`
- `User triggers GDPR erasure → revenue_events.user_id nulled, user_id_hash retained`

### 11.5 Fixtures

- Stripe Checkout test mode prices (`STRIPE_PRICE_PRO_MONTHLY=price_test_...`)
- Apple sandbox subscriptions provided by mobile team
- Google Play licence test track
- Pre-recorded webhook payload fixtures under `tests/Fixtures/Webhooks/{stripe,apple,google}/`

---

## 12. Rollout plan + KPIs

### 12.1 Build sequence

| Week | Milestone |
|---|---|
| 1 | Conventions implemented (idempotency middleware, error code registry, multi-connection saga helper); subscription module skeleton + Stripe webhook |
| 2 | IAP verification (Apple + Google), entitlements query |
| 3 | Fee resolver (Wallet + Exchange + Ramp); revenue events projector |
| 4 | Universal quote endpoint + replay protection + rate limit |
| 5 | Reconciliation job + Filament dashboard |
| 6 | BaaS commercial onboarding, card deposit, transactions export |
| 7 | QA hardening, sandbox burn-in, store review submissions, docs |

### 12.2 Feature flags

- `SUBSCRIPTIONS_ENABLED` — gate subscription module entirely (off by default)
- `SUBSCRIPTIONS_TRIAL_ENABLED` — kill switch for trials if abuse detected
- `FEES_ENABLED` — fee resolver returns zero when off (allows shipping code without charging)
- `QUOTE_REQUIRED` — when on, `submit` rejects without `quoteId`. Ship off; flip on after mobile 1.3.0 is at majority adoption

### 12.3 Migration sequence

1. **Day 0**: deploy with all flags **off**. Existing flows unchanged. Run `revenue:reconcile` against zero data to prove plumbing works.
2. **Day +7**: enable `SUBSCRIPTIONS_ENABLED` for internal users. End-to-end test on staging.
3. **Day +14**: enable `FEES_ENABLED` for internal users. Validate fees flow into `revenue_events`.
4. **Day +21**: mobile 1.3.0 in TestFlight / Play Internal. Enable `QUOTE_REQUIRED` for internal cohort.
5. **Day +28**: ramp public 5% → 25% → 100% over 5 days, monitoring revenue dashboard.

### 12.4 KPIs (measurable, dashboard-tracked)

A rollout is **successful** when, 30 days post-100%:
- Free → Pro trial conversion ≥ 4% of MAU on the home screen banner
- Trial → paid conversion ≥ 30%
- Quote success rate ≥ 99% (rejection / expiry / mismatch combined ≤ 1%)
- Revenue reconciliation variance ≤ 0.5% per provider per month
- ARPU on the rolled-out cohort ≥ €1.50 (year-1 target, climbs to €3.30 by year-2)
- Pro churn ≤ 5% / month
- Refund rate ≤ 2% / month
- Crash rate (mobile 1.3.0) ≤ 0.5%

If two of the above miss for 14 days running: pause rollout, root-cause via dashboard, hotfix.

---

## 13. Acceptance criteria

A feature is **done** when ALL of:

- [ ] Pest unit tests cover domain logic at ≥ 90% line coverage on touched files; no new entries in `phpstan-baseline*.neon` for the touched code
- [ ] Behat tests cover the user-visible flow end-to-end
- [ ] Multi-connection regression test added under `tests/MultiConnection/` for any flow touching both tenant and global tables
- [ ] All fee math uses `bcmath`; PHPStan numeric-string types pass
- [ ] All POST endpoints accept `Idempotency-Key`; tested for replay
- [ ] Webhook endpoints verify signatures and dedup by store-native id
- [ ] Filament dashboard shows the data the feature produces
- [ ] Feature flag defaults to off
- [ ] OpenAPI spec updated under `docs/04-API/`
- [ ] Mobile team reviewed the API contract section and signed off
- [ ] Logs do not contain card numbers, full IAP receipts, Stripe customer emails, Google purchase tokens, or PII payloads (verified by `LogRedactor` middleware test)
- [ ] Privacy policy + ToS updated by legal where applicable
- [ ] Reconciliation pipeline produces a row for each provider for the rollout month
- [ ] Postman / Insomnia collection updated under `docs/04-API/collections/`

---

## 14. Risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| 1 | Stripe Bridge does not support Stripe Connect platform fees | Medium | High | Dual-charge fallback (§2.4). Confirm by week 1. |
| 2 | Apple rejects v1.3.0 over EULA / disclosure compliance | Medium | High | Use `commit-commands:commit` only after manual review of paywall against Apple's checklist. Allow buffer in §12.1. |
| 3 | Multi-store conflict policy frustrates users | Low | Medium | Mobile UX deep-links the user to manage at the original store. Monitor `ERR_SUB_002` rate. |
| 4 | Revenue reconciliation variance spikes | Medium | Medium | Alert at 0.5%, founder reviews monthly. Most common cause: VAT misclassification. |
| 5 | Quote endpoint upstream rate limits hit (Uniswap RPC, Stripe Bridge) | Medium | Medium | Per-user 60 quotes/min cap; cache identical quote requests for 3s; backoff on upstream 429. |
| 6 | Trial abuse via burner Apple/Google accounts | Medium | Low | Trust store-side intro flag (Apple/Google enforce). Burner volume is small. |
| 7 | GDPR erasure breaks event-sourcing audit | Low | High | Pseudonymisation policy (§4.5) — keep event but remove PII. Validated with legal. |
| 8 | Multi-connection deadlock in production | Medium | High | Saga pattern enforced; regression test in `tests/MultiConnection/`. |
| 9 | Refund bypass — refund issued at store but our webhook lost | Low | Medium | Daily reconcile job catches mismatch; alert on variance. |
| 10 | Stripe Tax misclassifies Zelta product | Low | High | Stripe Tax dashboard review monthly; reverse charge for B2B BaaS only. |

---

## 15. Out of scope (explicit)

- Multi-currency on subscriptions or fees — v1.4
- Family / team subscriptions — v1.4
- Promotional discounts / coupon codes — manual via Stripe dashboard for now
- Self-serve refunds — admin-only via Filament for now
- Tax handling beyond Stripe Tax automatic mode — accountant's call quarterly
- Public pricing page on web (`zelta.app/pricing`) — separate ticket; impacts SEO and B2B leads but doesn't block 1.3.0
- A/B price discovery infrastructure — v1.4
- Custodial wallet opt-in — requires the licence (Plan A territory, separate handover)

---

## Appendix A — API contract summary (for mobile)

| Method | Path | Idempotent? | Auth | Notes |
|---|---|---|---|---|
| GET | `/api/v1/subscription/me` | yes (GET) | Sanctum `read` | Supports `If-None-Match` |
| POST | `/api/v1/subscription/checkout` | via `Idempotency-Key` | Sanctum `write` | Web only |
| POST | `/api/v1/subscription/iap/verify` | via `Idempotency-Key` | Sanctum `write` | iOS / Android |
| POST | `/api/v1/subscription/cancel` | yes | Sanctum `write` | Stripe-only; Apple/Google return `ERR_SUB_010` with deep link |
| GET | `/api/v1/me/entitlements` | yes (GET) | Sanctum `read` | Supports `If-None-Match` |
| GET | `/api/v1/me/savings-with-pro` | yes (GET) | Sanctum `read` | Returns 204 for Pro users |
| POST | `/api/v1/quote` | via `Idempotency-Key` | Sanctum `write` | Rate-limited 60/min/user |
| POST | `/api/v1/cards/waitlist/deposit` | via `Idempotency-Key` | Sanctum `write` | — |
| GET | `/api/v1/cards/waitlist/deposit/status` | yes (GET) | Sanctum `read` | — |
| POST | `/api/v1/wallet/transactions/export` | via `Idempotency-Key` | Sanctum `write` | Pro-gated |
| GET | `/api/v1/wallet/transactions/export/{id}` | yes (GET) | Sanctum `read` | Pro-gated |

WebSocket on `private-user.{userId}`: `subscription.changed` AND `entitlements.changed` (always paired, transactional).

## Appendix B — Error codes added

| Code | HTTP | Meaning |
|---|---|---|
| `ERR_IDEMPOTENCY_409` | 409 | Same idempotency key, different request body |
| `ERR_CUR_001` | 400 | Currency must be EUR in v1.3.x |
| `ERR_SUB_001` | 422 | Invalid receipt |
| `ERR_SUB_002` | 409 | Already subscribed via different store |
| `ERR_SUB_003` | 403 | Trial already used |
| `ERR_SUB_004` | 422 | Plan not configured |
| `ERR_SUB_005` | 403 | Pro feature accessed by free user |
| `ERR_SUB_010` | 409 | Cancellation must occur at originating store (Apple/Google) |
| `ERR_QUO_001` | 404 | Quote not found |
| `ERR_QUO_002` | 409 | Quote already consumed |
| `ERR_QUO_003` | 410 | Quote expired |
| `ERR_QUO_004` | 409 | Submitted payload does not match quote |
| `ERR_QUO_005` | 429 | Quote rate limit exceeded |
| `ERR_QUO_006` | 403 | KYC level insufficient for ramp |
| `ERR_QUO_007` | 403 | Destination address blocked |
| `ERR_QUO_008` | 400 | Insufficient balance for swap source |
| `ERR_FEE_001` | 500 | Fee tier could not be resolved |
| `ERR_EXP_001` | 429 | Export rate limit exceeded |
| `ERR_EXP_002` | 400 | Invalid export filter |
| `ERR_EXP_003` | 500 | Export job failed |

## Appendix C — Cross-references

- Wallet send conventions (idempotency, camelCase, Privy auth bridge): `core-banking-prototype-laravel/CLAUDE.md` "Wallet Send (v7.12.0+)"
- Multi-connection deadlock pattern: `core-banking-prototype-laravel/CLAUDE.md` CI/CD table + `tests/MultiConnection/`
- Existing handover style we're matching: `docs/BACKEND_HANDOVER_CARDS_KYC_RAMP.md`
- Mobile companion doc: `finaegis-mobile/docs/MOBILE_HANDOVER_PLAN_B_COMMERCIAL.md`
