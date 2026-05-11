# Plan B v1.3.0 — Slice 5: Card Waitlist Deposit

**Date:** 2026-05-10
**Author:** Backend Architect
**Status:** Spec — implementation not started
**Slice predecessors:**
  - Slice 1 (Cashier Stripe subscription path) — merged to `main` via PR #1037
  - Slice 2 (Apple/Google IAP path) — spec at PR #1038
  - Slice 3 (Pricing quote endpoint) — spec at PR #1039
  - Slice 4 (Cue queue infrastructure) — spec at PR #1040
**Estimated implementation effort:** 3 engineer-days
**Mobile target:** Zelta v1.3.0
**Mobile priority:** P3 (after P1 slice 3 and P2 subscription slices)

---

## 1. Working directory and authorisation

You are in a git worktree branched off `main` (which includes slices 1–4 once each is merged — confirm slice 3 is merged before starting, since this slice calls `QuoteService::redeem()`).

- Create branch: `feat/plan-b-slice-5-card-waitlist-deposit`
- Worktree base: `origin/main` (post-slice-3 is the hard prerequisite)
- Implement ONLY inside `app/Domain/CardIssuance/`, `routes/api.php`, `database/migrations/`, `config/error_codes.php` (if new codes needed), and `routes/console.php` (new cron append only).
- Do NOT modify `bootstrap/app.php` or `composer.json` (no new vendor dependencies — all Stripe plumbing is already provided by slice 1's Cashier setup).
- Do NOT modify `app/Domain/Subscription/` or `app/Domain/Pricing/` — consume them via constructor-injected services only.
- Commit + push + open PR against `main` titled `feat(cards): slice 5 — Card waitlist deposit (Stripe Checkout)`.
- Do NOT merge; the human reviewer will.
- After all commits are clean (php-cs-fixer, PHPStan L8, Pest pass), report your status using the format in §13.

---

## 2. Situation summary

### What earlier slices delivered (slice 5 builds on)

**Slice 1 (PR #1037)** delivered the Stripe subscription path. Directly reused by slice 5:
- `processed_webhook_events` table — webhook dedup (provider + event_id PRIMARY KEY). Slice 5 registers `metadata.flow = 'card_waitlist_deposit'` Checkout completions with provider `'stripe_cards'`.
- `revenue_outbox_events` table — off-chain outbox saga per ADR-0002. Slice 5 writes a `card_waitlist_deposit` outbox row on Checkout completion in the same transaction as the dedup row.
- `App\Domain\Subscription\Webhooks\SubscriptionWebhookController` — the pattern for signature verification, dedup write, and outbox dispatch. Slice 5 creates a parallel controller (`CardWaitlistWebhookController`) for `checkout.session.completed` events tagged with `metadata.flow = 'card_waitlist_deposit'`.
- `App\Http\Middleware\IdempotencyKey` — registered as `idempotency.required`. Slice 5 applies this to both POST endpoints.
- `App\Support\ErrorResponse::make($code)` — uniform error emission.

**Slice 3 (PR #1039)** delivered the pricing quote endpoint. Directly consumed by slice 5:
- `App\Domain\Pricing\Services\QuoteService::redeem()` — validates and atomically consumes a `price_quotes` row.
- `price_quotes` table — stores quotes keyed by `quoteId`; slice 5 reads `kind = 'card_waitlist_deposit'` quotes here.
- `App\Domain\Pricing\ValueObjects\Money` — Money VO used for `depositAmount` wire fields.

**Existing `app/Domain/CardIssuance/`** — the CardIssuance domain contains the existing `CardWaitlist` model (`card_waitlist` table). The table currently tracks `user_id`, `position`, `joined_at`, `notified_at`, `converted`. Slice 5 does NOT alter `card_waitlist` — it creates a separate `card_waitlist_deposits` table that holds deposit-specific state, keeping the two concerns cleanly separated (waitlist membership versus deposit payment).

### What slice 5 adds

Slice 5 is the **deposit payment layer** on top of the existing free waitlist join. It adds:

1. `POST /api/v1/cards/waitlist/deposit` — starts a Stripe Checkout session for the €5 refundable deposit. Requires a `quoteId` from slice 3's `POST /api/v1/pricing/quote?kind=card_waitlist_deposit`.
2. `POST /api/v1/cards/waitlist/deposit/cancel` — cancels the user's in-flight deposit by requesting a Stripe refund and transitioning the deposit record to `cancellation_requested`.
3. `GET /api/v1/cards/waitlist/entry` — returns the user's current waitlist state covering both free-tier membership and deposit payment status.
4. `card_waitlist_deposits` migration — dedicated table tracking deposit lifecycle.
5. `App\Domain\CardIssuance\Services\WaitlistDepositService` — orchestrates the three endpoints.
6. `CardWaitlistWebhookController` — handles `checkout.session.completed` for the card deposit flow.
7. `cards:purge-expired-deposits` artisan command — daily cron for 24-hour uncompleted Checkout session cleanup.

**Revenue boundary:** slice 5 writes a `revenue_outbox_events` row (source `card_waitlist_deposit`) only on confirmed Checkout completion (not on Checkout session creation). This aligns with ADR-0002's off-chain outbox saga: PSP webhook arrival is the trigger. Slice 5 does NOT write to `revenue_events` directly.

**Mobile URL change:** mobile currently calls a legacy free-join endpoint. The swap is a two-line URL change on their side (see §15 coordination items). The legacy endpoint is not removed in slice 5 — it continues to serve free-tier joins; the deposit endpoints are additive.

---

## 3. Read these BEFORE writing code (in order)

1. **`docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md` — Q9 section** ("Card waitlist deposit refund mechanics"): Q9.1 (`POST /deposit/cancel` endpoint + state machine), Q9.2 (updated `GET /deposit/status` shape with frozen `refundEligibleAfter`), Q9.3 (CHECK-constrained UPDATE for race avoidance between cancel and ship), Q9.4 (account closure: never block on Stripe API failure), Q9.5 (refund copy "5–10 business days"). Also search for "closed_account_refunds" — Backend-Q7 extends that table with a `source` ENUM covering `stripe_card_deposit` that slice 5 must acknowledge.

2. **`docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §6** ("Card waitlist deposit"): primary source for the deposit business logic. §6.1 (endpoints + request shapes), §6.2 (schema additions to `card_waitlist`), §6.3 (position drift handling via `ROW_NUMBER() OVER (ORDER BY deposit_paid DESC, joined_at ASC)`), §6.4 (four refund triggers and their windows). Note: §6's schema uses `ALTER TABLE card_waitlist ADD COLUMN deposit_*` — slice 5 diverges from this design by using a separate `card_waitlist_deposits` table (see §5.2 for rationale). Also read §3 (Pricing) for the quote integration contract, and Appendix B (error codes registered so far).

3. **`docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §0.2** — conventions (money, idempotency, errors, schema, multi-tenancy, security). All apply.

4. **`docs/adr/0002-revenue-projection-dual-upstream.md`** — the outbox saga. Card deposit revenue goes through the off-chain path (β): Stripe Checkout completion webhook → `revenue_outbox_events` → `ProjectRevenueOutbox` worker → `revenue_events`. This ADR establishes that PSP-webhook-triggered outbox writes must be in the same `DB::transaction()` as the `processed_webhook_events` dedup row.

5. **`docs/adr/0004-money-on-the-wire.md`** — every Money field on the wire is a `(amount, decimals, denomination)` triple. `depositAmount` in `GET /cards/waitlist/entry` response uses `{"amount": "500", "decimals": 2, "currency": "EUR"}` for €5.00.

6. **`docs/superpowers/specs/2026-05-10-slice-3-pricing-design.md` §5.3–§5.7** — the quote-as-input contract. Particularly: the `kind: "card_waitlist_deposit"` quote shape (flat €5 EUR, no upstream call needed), `QuoteService::redeem()` signature and the four validation steps (404, 409 consumed, 410 expired, 409 payload mismatch), and the quote expiry policy for fiat-only kinds (5-minute TTL per §5.5).

7. **`app/Domain/Subscription/Webhooks/SubscriptionWebhookController.php`** — the pattern for signature verification + dedup + outbox write. Slice 5's `CardWaitlistWebhookController` mirrors this: verify `Stripe-Signature`, atomic dedup INSERT, conditional outbox write in the same transaction.

8. **`app/Domain/CardIssuance/Models/CardWaitlist.php`** — the existing waitlist model. Slice 5 creates a companion model `CardWaitlistDeposit` for `card_waitlist_deposits`. Note: `CardWaitlist` uses `HasUuids` and `$table = 'card_waitlist'` — follow the same pattern.

9. **`config/error_codes.php`** — what is already registered (no `ERR_CARDS_*` codes exist). Slice 5 must register new codes; see §5.7 for the list.

10. **`CLAUDE.md`** — project conventions. Key points: BCMath for all money math, multi-connection rule (all new tables are global-connection — no `UsesTenantConnection`), `assert()` never as auth guard, `if (! $user instanceof User) return 401`, camelCase wire contract, PHPStan Level 8.

---

## 4. Existing repo state (foundations slice 5 builds on)

Do NOT re-create any of the following; they exist in `main` after slices 1–3.

### Tables available on `main`

| Table | Created by | Slice 5 usage |
|---|---|---|
| `processed_webhook_events` | Slice 1 migration | Dedup for card deposit Checkout completion webhook (provider `'stripe_cards'`) |
| `revenue_outbox_events` | Slice 1 migration | Outbox row written on Checkout completion |
| `revenue_events` | Slice 1 migration | Written by `ProjectRevenueOutbox` worker (not by slice 5 directly) |
| `idempotency_keys` | Slice 1 migration | `idempotency.required` middleware writes here for POST endpoints |
| `price_quotes` | Slice 3 migration | Slice 5 reads and redeems `kind = 'card_waitlist_deposit'` quotes |
| `card_waitlist` | Existing domain | Read-only in slice 5 (position, joined_at) |

### Services available on `main`

| Service | Created by | Slice 5 usage |
|---|---|---|
| `App\Domain\Pricing\Services\QuoteService` | Slice 3 | `QuoteService::redeem($quoteId, $userId, 'card_waitlist_deposit')` |
| `App\Domain\Pricing\ValueObjects\Money` | PR #1033 (foundations) | Serialise deposit amount on wire |
| `App\Http\Middleware\IdempotencyKey` | PR #1033 (foundations) | Applied to both POST deposit endpoints |
| `App\Support\ErrorResponse` | PR #1033 (foundations) | `ErrorResponse::make($code)` for error responses |

### Error codes already registered (`config/error_codes.php`)

| Code | HTTP | Note |
|---|---|---|
| `ERR_QUOTE_001` | 410 | Quote expired — emitted by `QuoteService::redeem()` |
| `ERR_QUO_002` | 409 | Quote already consumed — emitted by `QuoteService::redeem()` |
| `ERR_QUOTE_002` | 409 | Submitted payload does not match quote — emitted by `QuoteService::redeem()` |
| `ERR_FEE_001` | 500 | Fee tier could not be resolved |
| `ERR_VALIDATION_001` | 422 | Idempotency-Key header required |
| `ERR_VALIDATION_002` | 422 | Money field malformed |
| `ERR_VALIDATION_003` | 422 | Idempotency-Key invalid format |
| `ERR_IDEMPOTENCY_409` | 409 | Same key, different request body |
| `ERR_CUR_001` | 400 | Currency must be EUR |

No `ERR_CARDS_*` codes exist — slice 5 registers them; see §5.7.

---

## 5. Slice 5 scope — BUILD this

### 5.1 Endpoint: `POST /api/v1/cards/waitlist/deposit`

**Auth:** Sanctum bearer token, ability `write`.

**Middleware:** `idempotency.required` (header `Idempotency-Key` required).

**Purpose:** Creates a Stripe Checkout session for the €5 refundable card waitlist deposit. The client must first call `POST /api/v1/pricing/quote` with `kind: "card_waitlist_deposit"` and pass the returned `quoteId` here to lock the deposit price.

#### Request body

```json
{
  "quoteId": "qte_01HQ...",
  "withdrawalConsent": {
    "consentText": "I consent to the immediate provision of digital services...",
    "consentedAt": "2026-05-10T14:00:00Z",
    "version": "1.0"
  },
  "successUrl": "zelta://cards/waitlist/deposit/success",
  "cancelUrl": "zelta://cards/waitlist/deposit/cancel"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `quoteId` | string | yes | Must be an unexpired, unconsumed `price_quotes` row with `kind = 'card_waitlist_deposit'` belonging to the authenticated user. |
| `withdrawalConsent` | object | optional in v1.3.0; required v1.3.1+ | EU consumer right: 14-day cooling-off period waiver for immediate digital service provision. When absent in v1.3.0, backend proceeds but logs a warning. See `subscription_consent_log` pattern from slice 1. |
| `successUrl` | string | optional | Deep link or URL to redirect to on Checkout success. Validated against an allow-list in `config('cards.allowed_return_urls')`. Defaults to `config('cards.allowed_return_urls')[0]`. |
| `cancelUrl` | string | optional | Deep link or URL on Checkout cancellation. Same allow-list validation as `successUrl`. |

**Invariants (checked before creating the Checkout session):**

1. `quoteId` resolves to a valid, unexpired, unconsumed `price_quotes` row belonging to `$user->id` with `kind = 'card_waitlist_deposit'` — else errors per §5.7.
2. The user does not already have an active (`status` IN `pending_payment`, `paid`, `cancellation_requested`) deposit in `card_waitlist_deposits` — else `ERR_CARDS_002` (409). (Per-user single-active-deposit invariant — see OD-3.)
3. The user exists in `card_waitlist` (i.e., they have joined the waitlist, even as a free-tier member) — else `ERR_CARDS_001` (404). If they have not joined, mobile should call the legacy free-join endpoint first; deposit presupposes waitlist membership.

**Flow:**

```
WaitlistDepositService::startDeposit($user, $quoteId, $consentPayload)
  → QuoteService::redeem($quoteId, $user->id, 'card_waitlist_deposit')
      (validates + locks quote; throws on expired/consumed/mismatch)
  → Retrieve deposit amount from quote response_payload feeBreakdown
  → Check user has a card_waitlist row → ERR_CARDS_001 if absent
  → Check no active deposit exists → ERR_CARDS_002 if exists
  → Create Stripe Checkout session (mode: 'payment', one-time €5.00)
      metadata: { flow: 'card_waitlist_deposit', userId: ..., quoteId: ... }
  → DB::transaction():
      INSERT INTO card_waitlist_deposits (id, user_id, quote_id, status='pending_payment',
          deposit_amount_cents=500, deposit_decimals=2, deposit_currency='EUR',
          stripe_checkout_session_id, refund_eligible_after = NOW() + 18 months, ...)
      (consent log write if withdrawalConsent present, mirroring slice 1 pattern)
  → return { checkoutUrl, sessionId, depositId }
```

**Note on multi-connection safety:** `card_waitlist_deposits` is a global-connection table (per §0.2 — new Plan B tables are global). `card_waitlist` is also global (it is not a tenant-isolated model; `CardWaitlist` does not use `UsesTenantConnection`). No cross-connection write; `DB::transaction()` is safe here.

#### Response (200 OK)

```json
{
  "depositId": "dep_01HQ...",
  "checkoutUrl": "https://checkout.stripe.com/c/pay/cs_...",
  "sessionId": "cs_...",
  "depositAmount": {
    "amount": "500",
    "decimals": 2,
    "currency": "EUR"
  },
  "expiresAt": "2026-05-11T14:00:00Z"
}
```

`depositId` is the `card_waitlist_deposits.id` UUID. `expiresAt` is 24 hours from now (the Stripe Checkout session expiry — see §5.3 cron).

### 5.2 Endpoint: `POST /api/v1/cards/waitlist/deposit/cancel`

**Auth:** Sanctum bearer token, ability `write`.

**Middleware:** `idempotency.required`.

**Purpose:** Cancels the user's current deposit and initiates a Stripe refund. Mobile previously used a legacy endpoint path `/cards/waitlist/{id}/cancel` (per mobile service spec); backend's canonical path is `/cards/waitlist/deposit/cancel` singular per Q9.1 (per-user, not per-deposit-id — one active deposit per user).

**Request body:** None (no body — cancel is identified by the authenticated user's active deposit).

**Flow:**

```
WaitlistDepositService::cancelDeposit($user)
  → Look up card_waitlist_deposits WHERE user_id = $user->id AND status IN ('pending_payment', 'paid')
  → If none found → ERR_CARDS_003 (404 no active deposit)
  → Q9.3 atomic state transition (CHECK-constrained UPDATE):
      UPDATE card_waitlist_deposits
         SET status = 'cancellation_requested', cancelled_at = NOW()
       WHERE id = ? AND status IN ('pending_payment', 'paid')
      → If affected_rows = 0: deposit is already shipped or already cancelling → ERR_CARDS_004 (409 conflict)
  → Initiate Stripe refund on the captured payment intent:
      If status was 'pending_payment' (Checkout not yet completed):
        → Cancel the Checkout session (Stripe Checkout session expiry or explicit expire call)
      If status was 'paid' (Checkout completed):
        → stripe->refunds->create(['payment_intent' => $paymentIntentId])
  → On Stripe API success: record $stripeRefundId in deposit row
  → On Stripe API failure: mark deposit status = 'refund_pending_manual', page ops (per Q9.4 pattern — never block user-facing flow on Stripe failure)
  → return { status, refundedAt?, estimatedSettlementDays: 10 }
```

**Per Q9.3 race avoidance:** the atomic UPDATE against `status IN ('pending_payment', 'paid')` means whichever side commits first wins. If the `card.shipped` handler fires concurrently, it will `UPDATE ... WHERE status = 'paid'` which returns `affected_rows = 0` (cancel won). If cancel fires after ship, cancel's UPDATE returns `affected_rows = 0` (ship won). No compensating saga needed — the constraint is structural.

**Per Q9.4:** account-closure flow (GDPR §4.5) must also call this logic but must NOT block on Stripe API failure. The closure flow captures the user email + deposit IDs before erasure, initiates refund, and on Stripe failure marks `refund_pending_manual` and pages ops — proceeding with erasure regardless. This hook into `WaitlistDepositService::cancelDeposit()` is documented here but implemented as part of the GDPR erasure flow (a separate concern; flag to the account-closure implementer).

#### Response (200 OK)

```json
{
  "depositId": "dep_01HQ...",
  "status": "cancellation_requested",
  "refundedAt": null,
  "estimatedSettlementDays": 10,
  "message": "Refund initiated. Funds will return to your card in 5–10 business days."
}
```

Per Q9.5: user-facing copy uses "5–10 business days", never "within 7 days".

### 5.3 Endpoint: `GET /api/v1/cards/waitlist/entry`

**Auth:** Sanctum bearer token, ability `read`.

**Purpose:** Returns the user's current waitlist state. Null-safe: returns a `tier: "none"` shape for users not on the waitlist. Covers both the free-tier join state and the deposit payment state.

#### Response shapes

**User not on waitlist:**

```json
{
  "tier": "none",
  "depositStatus": null,
  "depositAmount": null,
  "queuePosition": null,
  "joinedAt": null,
  "refundEligibleAfter": null,
  "refundedReason": null,
  "refundedAt": null,
  "quoteId": null
}
```

**User on waitlist, free-tier (no deposit):**

```json
{
  "tier": "free",
  "depositStatus": "none",
  "depositAmount": null,
  "queuePosition": 843,
  "joinedAt": "2026-03-15T10:00:00Z",
  "refundEligibleAfter": null,
  "refundedReason": null,
  "refundedAt": null,
  "quoteId": null
}
```

**User on waitlist, deposit pending payment:**

```json
{
  "tier": "deposit",
  "depositStatus": "pending_payment",
  "depositAmount": { "amount": "500", "decimals": 2, "currency": "EUR" },
  "queuePosition": 312,
  "joinedAt": "2026-03-15T10:00:00Z",
  "refundEligibleAfter": null,
  "refundedReason": null,
  "refundedAt": null,
  "quoteId": "qte_01HQ..."
}
```

**User on waitlist, deposit paid:**

```json
{
  "tier": "deposit",
  "depositStatus": "paid",
  "depositAmount": { "amount": "500", "decimals": 2, "currency": "EUR" },
  "queuePosition": 312,
  "joinedAt": "2026-03-15T10:00:00Z",
  "refundEligibleAfter": "2027-11-08T00:00:00Z",
  "refundedReason": null,
  "refundedAt": null,
  "quoteId": null
}
```

**User with cancellation requested or refunded:**

```json
{
  "tier": "free",
  "depositStatus": "refunded",
  "depositAmount": { "amount": "500", "decimals": 2, "currency": "EUR" },
  "queuePosition": 1204,
  "joinedAt": "2026-03-15T10:00:00Z",
  "refundEligibleAfter": "2027-11-08T00:00:00Z",
  "refundedReason": "user_cancelled",
  "refundedAt": "2026-05-15T09:00:00Z",
  "quoteId": null
}
```

**Field notes:**

- `tier`: `"none"` | `"free"` | `"deposit"`. After a refund, tier reverts to `"free"` (the user stays on the waitlist, just loses their deposit priority).
- `depositStatus`: mirrors the `card_waitlist_deposits.status` enum. `"none"` when no deposit record exists for the user.
- `queuePosition`: computed on-read via `ROW_NUMBER() OVER (ORDER BY deposit_paid DESC, joined_at ASC)` — deposit holders first, then by join date. Per commercial §6.3, the `position` column in `card_waitlist` is a nightly-recomputed cache; `GET /entry` computes live for freshness on the endpoint that users care about.
- `refundEligibleAfter`: frozen at the moment the deposit was marked `paid` (paidAt + 18 months). Never recalculated based on current policy. Per Q9.2.
- `quoteId`: populated only when there is a live active `price_quotes` row linked to a `pending_payment` deposit (useful for mobile to show the locked price). Null once paid or once the quote expires.

### 5.4 Webhook handler: `POST /webhooks/stripe/cards`

**Purpose:** Handles `checkout.session.completed` Stripe webhook events where `metadata.flow = 'card_waitlist_deposit'`. Deduplicates via `processed_webhook_events`, transitions the deposit to `paid`, and writes a `revenue_outbox_events` row.

**Route registration:** `POST /webhooks/stripe/cards` — a separate route from `/webhooks/stripe/subscriptions` (slice 1). Both are Stripe webhooks but target different processing logic. Register in `routes/api.php` with no CSRF, no Sanctum, no session middleware (mirrors slice 1's webhook route group).

**Controller:** `App\Domain\CardIssuance\Webhooks\CardWaitlistWebhookController`

**Stripe events handled:**

| Event type | Condition | Action |
|---|---|---|
| `checkout.session.completed` | `metadata.flow = 'card_waitlist_deposit'` AND `payment_status = 'paid'` | Transition deposit → `paid`; write `revenue_outbox_events` |
| `checkout.session.completed` | `metadata.flow = 'card_waitlist_deposit'` AND `payment_status != 'paid'` | Log warning; no state change (payment not captured) |
| `checkout.session.expired` | `metadata.flow = 'card_waitlist_deposit'` | Transition deposit → `expired`; no refund needed (no payment captured) |
| `charge.refunded` | Payment intent matches a `card_waitlist_deposits.stripe_payment_intent_id` | Transition deposit → `refunded`; write negative `revenue_outbox_events` |

**Dedup + outbox transaction (mirroring slice 1 pattern):**

```
receive POST /webhooks/stripe/cards
  → verify Stripe-Signature (STRIPE_CARDS_WEBHOOK_SECRET env var)
    on failure: return 400
  → extract event_id, event_type, metadata
  → DB::transaction():
      INSERT INTO processed_webhook_events (provider='stripe_cards', event_id, ...)
        ON DUPLICATE KEY: skip (already processed) → return 200
      match event_type:
        'checkout.session.completed' + paid:
          UPDATE card_waitlist_deposits SET status='paid', paid_at=NOW(),
              stripe_payment_intent_id=..., refund_eligible_after=(NOW() + 18mo)
            WHERE stripe_checkout_session_id = ? AND status = 'pending_payment'
          INSERT INTO revenue_outbox_events (source_type='stripe_cards',
              source_event_id=event_id, payload={...})
        'checkout.session.expired':
          UPDATE card_waitlist_deposits SET status='expired'
            WHERE stripe_checkout_session_id = ? AND status = 'pending_payment'
        'charge.refunded':
          UPDATE card_waitlist_deposits SET status='refunded', refunded_at=NOW(),
              refund_reason='user_cancelled', stripe_refund_id=...
            WHERE stripe_payment_intent_id = ? AND status IN ('paid','cancellation_requested')
          INSERT INTO revenue_outbox_events (source_type='stripe_cards',
              source_event_id=event_id, payload={sign:-1, ...}) -- negative revenue
  → return 200
```

**Signature verification env var:** `STRIPE_CARDS_WEBHOOK_SECRET` — separate from `STRIPE_WEBHOOK_SECRET` (subscriptions). Allows independent secret rotation per webhook endpoint. Bypass with `app()->environment('local', 'testing')` only — never `return true`.

### 5.5 Tables: `card_waitlist_deposits`

#### Rationale for a separate table

Commercial §6.2 specifies `ALTER TABLE card_waitlist ADD COLUMN deposit_*`. Slice 5 diverges with a separate `card_waitlist_deposits` table for three reasons:

1. **Clean separation of concerns:** waitlist membership (`card_waitlist`) and deposit payment lifecycle (`card_waitlist_deposits`) are different bounded contexts. A user can be on the waitlist without a deposit; a deposit can go through multiple lifecycle states that would add 7+ sparse columns to `card_waitlist`.
2. **Multiple deposit attempts:** if a user's Checkout session expires before payment, a new deposit record is needed. An ALTER approach requires nullable columns and careful handling of which column set is "current" — a separate table with a clear `status` enum is cleaner.
3. **Audit trail:** a separate table naturally retains historical deposit attempts. This aids reconciliation per ADR-0002 §4.4.

A JOIN view `v_waitlist_with_deposit` can expose the combined shape for position queries if needed — but for slice 5, position is computed live.

#### DDL

```sql
CREATE TABLE card_waitlist_deposits (
    id                          CHAR(36)        NOT NULL,          -- RFC 4122 v4 UUID
    user_id                     CHAR(36)        NOT NULL,          -- FK → users.id
    quote_id                    CHAR(36)        NOT NULL,          -- FK → price_quotes.id
    status                      ENUM(
                                  'pending_payment',
                                  'paid',
                                  'cancellation_requested',
                                  'refunded',
                                  'refund_pending_manual',
                                  'expired',
                                  'card_shipped'
                                )               NOT NULL DEFAULT 'pending_payment',
    deposit_amount_cents        INT UNSIGNED    NOT NULL DEFAULT 500,  -- always 500 for v1.3.0 (€5.00)
    deposit_decimals            TINYINT UNSIGNED NOT NULL DEFAULT 2,
    deposit_currency            CHAR(3)         NOT NULL DEFAULT 'EUR',
    stripe_checkout_session_id  VARCHAR(255)    NULL,
    stripe_payment_intent_id    VARCHAR(255)    NULL,
    stripe_refund_id            VARCHAR(255)    NULL,
    refund_eligible_after       TIMESTAMP       NULL,              -- paidAt + 18 months; frozen at deposit time
    paid_at                     TIMESTAMP       NULL,
    cancelled_at                TIMESTAMP       NULL,
    refunded_at                 TIMESTAMP       NULL,
    refunded_reason             ENUM(
                                  'user_cancelled',
                                  'card_shipped',
                                  'eighteen_month_auto',
                                  'account_closure'
                                )               NULL,
    expired_at                  TIMESTAMP       NULL,
    shipped_at                  TIMESTAMP       NULL,
    withdrawal_consent_log_id   CHAR(36)        NULL,              -- FK → subscription_consent_log.id (if consent given)
    created_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                  TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_cwd_user_status         (user_id, status),
    KEY idx_cwd_checkout_session    (stripe_checkout_session_id),
    KEY idx_cwd_payment_intent      (stripe_payment_intent_id),
    KEY idx_cwd_paid_at             (paid_at),
    KEY idx_cwd_refund_eligible     (refund_eligible_after),
    UNIQUE KEY uniq_cwd_quote       (quote_id)                     -- one deposit per quote (a quote can only be consumed once)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Column notes:**

- `id`: RFC 4122 v4 UUID via `Str::uuid()`. Per CLAUDE.md convention.
- `status`: covers the full lifecycle including `card_shipped` (set by a future card-issuance slice, not slice 5 — slice 5 only handles pending/paid/cancel/refund/expired states).
- `deposit_amount_cents` + `deposit_decimals` + `deposit_currency`: the explicit triple per ADR-0004 DB storage guidance ("variable-currency tables use explicit triple"). Although €5 is the only value in v1.3.0, storing the triple preserves flexibility and matches the ADR's guidance.
- `refund_eligible_after`: frozen at the moment `paid_at` is set (paidAt + 18 months). Per Q9.2 this is never recalculated.
- `withdrawal_consent_log_id`: references `subscription_consent_log` (created by slice 1) if the user provided withdrawal consent. Nullable because consent is optional in v1.3.0.
- `uniq_cwd_quote`: enforces that a given `price_quotes` row can only be used for one deposit. This mirrors the `price_quotes.consumed_at` constraint but adds a DB-level backstop at the deposit layer.

#### Active deposit invariant

Per-user, only one deposit may be in an "active" state (`pending_payment`, `paid`, `cancellation_requested`). Enforced at the application level in `WaitlistDepositService::startDeposit()` before INSERT. A partial unique index is an option but the application-level check plus the single-active-deposit check is sufficient for v1.3.0 (see OD-3).

### 5.6 Services

#### `App\Domain\CardIssuance\Services\WaitlistDepositService`

The main orchestrating service. Constructor-injected into the controller and the webhook controller.

**Entry points:**

- `startDeposit(User $user, string $quoteId, ?array $consentPayload): array` — validates prerequisites, calls `QuoteService::redeem()`, creates the Stripe Checkout session, inserts the deposit record. Returns the response array `{ depositId, checkoutUrl, sessionId, depositAmount, expiresAt }`.
- `cancelDeposit(User $user): array` — finds the active deposit, performs the Q9.3 atomic state transition, initiates the Stripe refund (or marks `refund_pending_manual` on failure). Returns the response array.
- `entryFor(User $user): array` — assembles the `GET /entry` response by joining `card_waitlist` + latest `card_waitlist_deposits` row for the user, computing queue position on-read.

**Dependencies (constructor-injected):**

- `App\Domain\Pricing\Services\QuoteService` — for quote redemption.
- `Stripe\StripeClient` — for Checkout session creation and refund initiation. Reuse the bound `StripeClient` from slice 1 (already bound in `AppServiceProvider`).

**Financial arithmetic:** all amount math (deposit EUR cents, refund amounts) uses `bcmath`. The deposit is a flat €5 (500 cents) so no arithmetic is needed in v1.3.0, but the pattern must be correct for future deposit amounts.

#### `App\Domain\CardIssuance\Webhooks\CardWaitlistWebhookController`

Handles `POST /webhooks/stripe/cards`. Constructor-injected `WaitlistDepositService`. Verifies signature, deduplicates, dispatches to service methods. See §5.4 for the full flow.

### 5.7 Error codes (new — register in `config/error_codes.php`)

No `ERR_CARDS_*` codes exist in the current registry. Appendix B of the commercial spec does not define `ERR_CARDS_*` codes — slice 5 introduces them. The following codes are proposed; flag them in the PR for controller review before merging:

| Code | HTTP | Condition | Notes |
|---|---|---|---|
| `ERR_CARDS_001` | 404 | User is not on the card waitlist (no `card_waitlist` row) | Must join the waitlist before depositing |
| `ERR_CARDS_002` | 409 | User already has an active deposit (`pending_payment` or `paid`) | Per-user single-active-deposit invariant (OD-3) |
| `ERR_CARDS_003` | 404 | No active deposit found to cancel | Cancel called but user has no active deposit |
| `ERR_CARDS_004` | 409 | Deposit state conflict — already shipped or cancellation already in progress | Q9.3 `affected_rows = 0` path |
| `ERR_CARDS_005` | 422 | Invalid `successUrl` or `cancelUrl` — not in allow-list | |
| `ERR_CARDS_006` | 409 | Quote `kind` is not `card_waitlist_deposit` | Wrong quote kind passed to the deposit endpoint |

**Note on quote-related errors:** `ERR_QUOTE_001` (expired), `ERR_QUO_002` (consumed), and `ERR_QUOTE_002` (payload mismatch) are already registered and are emitted by `QuoteService::redeem()`. The controller propagates them directly — no re-wrapping.

### 5.8 Cron: `cards:purge-expired-deposits`

**Purpose:** Daily cleanup of Stripe Checkout sessions that were created but never completed within 24 hours. Stripe auto-expires sessions after 24h by default; this command syncs the `card_waitlist_deposits` status to `expired` and releases the associated quote redemption.

**Command:** `php artisan cards:purge-expired-deposits`

**Schedule:** daily at 03:30 UTC (append to `routes/console.php` — never modify existing schedule lines).

**Logic:**

1. Query `card_waitlist_deposits WHERE status = 'pending_payment' AND created_at < NOW() - INTERVAL 24 HOUR`.
2. For each: verify with Stripe whether the session actually expired (or completed — the webhook may have been delayed).
3. If expired: set `status = 'expired'`, `expired_at = NOW()`. The associated `price_quotes` row's `consumed_at` was set on `startDeposit()` — if the deposit never completed, unset `consumed_at` (set to `NULL`) so the user can attempt a new deposit with a new quote. (See OD-4 for the open design decision on quote release.)
4. If completed (webhook was delayed): call the same state transition logic as the webhook handler.
5. Emit metric `cards.deposits.purged` per record.
6. Cap at 1,000 records per run with a warning log if the cap is hit (indicates a backlog).

---

## 6. Out of scope

The following are explicitly excluded from slice 5 and deferred to a later slice or version:

- **Card issuance itself** — the actual virtual/physical card provisioning after a user's deposit is paid. This is handled by the existing `CardIssuance` domain (separate bounded context); slice 5 only advances the waitlist state to `paid`. The `card_shipped` status enum value is reserved for the card-issuance slice.
- **Waitlist conversion flow** — when a deposit-paid user's card is ready and they are moved through to card activation. This is v1.4 or a separate slice.
- **18-month automatic refund** — commercial §6.4 documents a `cards:refund-stale-deposits` cron. Slice 5 only implements the `cards:purge-expired-deposits` cron (24h session cleanup). The 18-month refund cron is a future slice — it requires policy sign-off on refund trigger conditions.
- **Self-serve refund request (mobile-initiated pre-launch)** — the fourth refund trigger from §6.4 ("user explicitly requests refund pre-launch via mobile, admin approval"). Slice 5 implements user-initiated cancel (immediate Stripe refund initiation), but the "admin approval" workflow is deferred to the Filament admin panel work.
- **Filament admin panel for deposits** — the `Admin → Cards → Deposits` panel showing deposit status, refund management, and `refund_pending_manual` queue. This is a separate Filament resource; not slice 5 scope.
- **GDPR erasure hook** — the `UserAccountClosed` event listener that calls `WaitlistDepositService::cancelDeposit()` per Q9.4. The service method is designed to be callable from the erasure flow, but wiring the listener is the erasure flow implementer's responsibility.
- **Position drift recomputation cron** (`CardWaitlistRecomputeJob`) — §6.3 describes a nightly job to recompute the denormalised `position` column. Slice 5 does not implement this; it computes position live on `GET /entry`. The nightly recompute is deferred.
- **Multi-currency deposits** — v1.4. v1.3.0 pins to EUR per §0.2 convention.

---

## 7. Mobile contract (precise JSON shapes)

All wire fields use camelCase. All Money fields use the ADR-0004 triple `(amount, decimals, currency-or-asset)`.

### 7.1 `POST /api/v1/cards/waitlist/deposit` — request

```json
{
  "quoteId": "qte_01HQ...",
  "withdrawalConsent": {
    "consentText": "I consent to the immediate provision of digital services and acknowledge that I waive my 14-day right of withdrawal.",
    "consentedAt": "2026-05-10T14:00:00Z",
    "version": "1.0"
  },
  "successUrl": "zelta://cards/waitlist/deposit/success",
  "cancelUrl": "zelta://cards/waitlist/deposit/cancel"
}
```

**Minimal valid request (v1.3.0):**

```json
{
  "quoteId": "qte_01HQ..."
}
```

### 7.2 `POST /api/v1/cards/waitlist/deposit` — response (200)

```json
{
  "depositId": "dep_01HQ...",
  "checkoutUrl": "https://checkout.stripe.com/c/pay/cs_test_...",
  "sessionId": "cs_test_...",
  "depositAmount": {
    "amount": "500",
    "decimals": 2,
    "currency": "EUR"
  },
  "expiresAt": "2026-05-11T14:00:00Z"
}
```

### 7.3 `POST /api/v1/cards/waitlist/deposit` — error responses

| Scenario | HTTP | Body |
|---|---|---|
| Missing `Idempotency-Key` header | 422 | `{"code": "ERR_VALIDATION_001", "message": "..."}` |
| `quoteId` not found or belongs to another user | 404 | `{"code": "ERR_QUO_001", "message": "..."}` |
| Quote expired | 410 | `{"code": "ERR_QUOTE_001", "message": "..."}` |
| Quote already consumed | 409 | `{"code": "ERR_QUO_002", "message": "..."}` |
| Quote payload mismatch | 409 | `{"code": "ERR_QUOTE_002", "message": "..."}` |
| Wrong quote kind (not `card_waitlist_deposit`) | 409 | `{"code": "ERR_CARDS_006", "message": "..."}` |
| User not on waitlist | 404 | `{"code": "ERR_CARDS_001", "message": "..."}` |
| User already has active deposit | 409 | `{"code": "ERR_CARDS_002", "message": "..."}` |
| Invalid `successUrl` or `cancelUrl` | 422 | `{"code": "ERR_CARDS_005", "message": "..."}` |
| Same `Idempotency-Key`, different body | 409 | `{"code": "ERR_IDEMPOTENCY_409", "message": "..."}` |

### 7.4 `POST /api/v1/cards/waitlist/deposit/cancel` — request

No body.

### 7.5 `POST /api/v1/cards/waitlist/deposit/cancel` — response (200)

```json
{
  "depositId": "dep_01HQ...",
  "status": "cancellation_requested",
  "refundedAt": null,
  "estimatedSettlementDays": 10,
  "message": "Refund initiated. Funds will return to your card in 5–10 business days."
}
```

When Stripe refund was immediate (session never completed):

```json
{
  "depositId": "dep_01HQ...",
  "status": "refunded",
  "refundedAt": "2026-05-10T14:05:00Z",
  "estimatedSettlementDays": 0,
  "message": "Your Checkout session has been cancelled. No charge was made."
}
```

### 7.6 `POST /api/v1/cards/waitlist/deposit/cancel` — error responses

| Scenario | HTTP | Body |
|---|---|---|
| Missing `Idempotency-Key` header | 422 | `{"code": "ERR_VALIDATION_001", "message": "..."}` |
| No active deposit to cancel | 404 | `{"code": "ERR_CARDS_003", "message": "..."}` |
| Deposit already shipped or in terminal state | 409 | `{"code": "ERR_CARDS_004", "message": "..."}` |

### 7.7 `GET /api/v1/cards/waitlist/entry` — response (not on waitlist)

```json
{
  "tier": "none",
  "depositStatus": null,
  "depositAmount": null,
  "queuePosition": null,
  "joinedAt": null,
  "refundEligibleAfter": null,
  "refundedReason": null,
  "refundedAt": null,
  "quoteId": null
}
```

### 7.8 `GET /api/v1/cards/waitlist/entry` — response (deposit paid)

```json
{
  "tier": "deposit",
  "depositStatus": "paid",
  "depositAmount": { "amount": "500", "decimals": 2, "currency": "EUR" },
  "queuePosition": 312,
  "joinedAt": "2026-03-15T10:00:00Z",
  "refundEligibleAfter": "2027-11-08T00:00:00Z",
  "refundedReason": null,
  "refundedAt": null,
  "quoteId": null
}
```

### 7.9 `GET /api/v1/cards/waitlist/entry` — response (refunded)

```json
{
  "tier": "free",
  "depositStatus": "refunded",
  "depositAmount": { "amount": "500", "decimals": 2, "currency": "EUR" },
  "queuePosition": 1204,
  "joinedAt": "2026-03-15T10:00:00Z",
  "refundEligibleAfter": "2027-11-08T00:00:00Z",
  "refundedReason": "user_cancelled",
  "refundedAt": "2026-05-15T09:00:00Z",
  "quoteId": null
}
```

---

## 8. Open design decisions

### OD-1: Refund mechanism on `POST /deposit/cancel`

**Question:** Should cancel initiate an immediate synchronous Stripe refund call, or should it mark `cancellation_requested` and delegate to an async worker?

**Context from Q9.1:** "marks deposit as `cancellation_requested`, fires Stripe refund, removes from queue." This implies synchronous Stripe API call in the request path.

**Recommendation (synchronous):** Initiate the Stripe refund synchronously in the cancel handler. The `cancellation_requested` state is still necessary because the `charge.refunded` webhook (not the synchronous API response) is what confirms the refund and transitions to `refunded`. The synchronous call starts the refund; the webhook completes it. This matches the Q9.4 pattern and gives the user the best feedback latency.

**Failure mode:** if the synchronous Stripe call fails (5xx), mark `refund_pending_manual` and page ops. Do NOT return an error to the user — return 200 with a message that the refund is being processed manually. Per Q9.4 principle.

**Status:** Recommendation. Requires controller confirmation.

### OD-2: `card_waitlist_deposits` primary key — UUID vs BIGINT

**Question:** UUID (`CHAR(36)`) or `BIGINT UNSIGNED AUTO_INCREMENT`?

**Recommendation (UUID):** Deposit IDs appear in the API response (`depositId`) and potentially in mobile deep links. Sequential BIGINT IDs are enumerable — a bad actor could enumerate all deposit IDs. UUID v4 is not enumerable. Additionally, consistency with other Plan B tables (`subscriptions`, `price_quotes`, `revenue_events`) which all use UUID PKs.

**Status:** Decided (UUID). Documented here for visibility.

### OD-3: Per-user single-active-deposit invariant

**Question:** If a user already has an in-flight (`pending_payment` or `paid`) deposit and calls `POST /deposit` again, should the endpoint reject or replace?

**Context:** Q9.3 only specifies the cancel+ship race; it does not address the double-start scenario.

**Recommendation (reject):** Return `ERR_CARDS_002` (409) if the user already has an active deposit. Rationale: replacing would silently cancel a potentially-completed Checkout session (money may have moved); rejecting is safe and instructs mobile to show the existing active deposit status. Mobile handles this by calling `GET /entry` first to check whether a deposit is already in progress.

**Status:** Recommendation. Requires controller confirmation.

### OD-4: Quote release on deposit expiry

**Question:** When a `pending_payment` deposit expires (the 24h cron in §5.8), should the associated `price_quotes` row have its `consumed_at` reset to `NULL` so the user can attempt a new deposit with a new quote?

**Context:** `QuoteService::redeem()` sets `consumed_at = NOW()` atomically when the deposit is started. If the Checkout session expires without payment, the quote has been "consumed" by a deposit that never completed. The user would need a new quote for their next deposit attempt — which is acceptable (quotes expire in 5 minutes anyway). However, if the quote itself is still within its TTL (unlikely for a 24h-expired session), resetting `consumed_at` would allow the original quote to be reused.

**Recommendation (do NOT reset):** Do not reset `consumed_at`. The user simply gets a new quote (5-minute TTL means it has long since expired anyway). Resetting introduces a rollback path in `QuoteService` that could create edge cases. The implementation cost is not justified. The deposit-expired state is clear; mobile shows "session expired, tap to restart" and calls `POST /pricing/quote` + `POST /cards/waitlist/deposit` again.

**Status:** Recommendation. Requires controller confirmation.

### OD-5: GET `/cards/waitlist/entry` vs `/cards/waitlist/deposit/status`

**Question:** The commercial spec §6.1 defines a `GET /api/v1/cards/waitlist/deposit/status` endpoint. Q9.2 defines an updated shape for the same endpoint. The slice 5 spec (this document) proposes renaming this to `GET /api/v1/cards/waitlist/entry` for a more holistic view (covering both free-tier and deposit-tier state in one call). Does mobile prefer the holistic `/entry` endpoint or the narrower `/deposit/status`?

**Context:** Slice 1's `/subscription/me` is the model for a holistic "what is my status" endpoint. Mirroring that pattern gives mobile a single call to populate the entire waitlist UI card. The Q9.2 shape is a subset of the `/entry` response.

**Recommendation (implement both or just `/entry`):** At a minimum, implement `GET /entry` as specified. If mobile has already built against `/deposit/status`, implement both (the controller can delegate to the same service method). The `deposit/status` URL serves users who call the narrower path; `/entry` serves the holistic use case. This is a mobile-coordination question.

**Status:** Open. Requires mobile team confirmation before implementation.

---

## 9. Acceptance criteria

A feature is **done** when ALL of the following are true:

- [ ] `POST /api/v1/cards/waitlist/deposit` creates a Stripe Checkout session, inserts a `card_waitlist_deposits` row with `status = 'pending_payment'`, and returns `{ depositId, checkoutUrl, sessionId, depositAmount, expiresAt }`.
- [ ] `POST /api/v1/cards/waitlist/deposit` rejects with `ERR_QUOTE_001` (410) on expired `quoteId`.
- [ ] `POST /api/v1/cards/waitlist/deposit` rejects with `ERR_QUO_002` (409) on already-consumed `quoteId`.
- [ ] `POST /api/v1/cards/waitlist/deposit` rejects with `ERR_CARDS_002` (409) when the user already has an active deposit.
- [ ] `POST /api/v1/cards/waitlist/deposit` rejects with `ERR_CARDS_001` (404) when the user has no `card_waitlist` row.
- [ ] `POST /api/v1/cards/waitlist/deposit/cancel` performs the Q9.3 atomic `UPDATE ... WHERE status IN ('pending_payment', 'paid')` and returns `affected_rows = 0` as `ERR_CARDS_004` (409).
- [ ] `POST /api/v1/cards/waitlist/deposit/cancel` initiates a synchronous Stripe refund and marks `cancellation_requested`; on Stripe API failure, marks `refund_pending_manual` and logs a warning without returning a user-facing error.
- [ ] `GET /api/v1/cards/waitlist/entry` returns the correct shape for: not-on-waitlist, free-tier, pending_payment, paid, refunded states.
- [ ] `GET /api/v1/cards/waitlist/entry` computes `queuePosition` via `ROW_NUMBER() OVER (ORDER BY deposit_paid DESC, joined_at ASC)` — deposit holders ranked before free-tier members.
- [ ] `GET /api/v1/cards/waitlist/entry` returns `refundEligibleAfter` frozen at deposit time (paidAt + 18 months), never recalculated.
- [ ] `POST /webhooks/stripe/cards` verifies `Stripe-Signature` against `STRIPE_CARDS_WEBHOOK_SECRET`; returns 400 on signature failure.
- [ ] `POST /webhooks/stripe/cards` for `checkout.session.completed` + `payment_status = 'paid'` writes dedup row AND outbox row in the same `DB::transaction()`.
- [ ] `POST /webhooks/stripe/cards` is idempotent on Stripe `event.id` via `processed_webhook_events (provider='stripe_cards', event_id)`.
- [ ] `cards:purge-expired-deposits` command marks sessions older than 24 hours as `expired` and caps at 1,000 records per run.
- [ ] Both POST endpoints require `Idempotency-Key` header; `ERR_VALIDATION_001` (422) returned when absent.
- [ ] Both POST endpoints return `ERR_IDEMPOTENCY_409` (409) when same key is presented with a different body.
- [ ] All six `ERR_CARDS_*` codes are registered in `config/error_codes.php`.
- [ ] Pest unit tests cover `WaitlistDepositService`: all state transitions, quote validation, Q9.3 race condition, Stripe failure → `refund_pending_manual`.
- [ ] Pest feature tests cover: `POST /deposit` happy path, all error branches; `POST /cancel` happy path + race; `GET /entry` all five response shapes; webhook dedup; webhook outbox write.
- [ ] PHPStan Level 8 passes on all new files with zero new baseline entries.
- [ ] `php-cs-fixer fix` produces no diffs on new files.
- [ ] No `(float)` cast on any money value — `bcmath` only.
- [ ] No `assert()` as auth guard — `if (! $user instanceof User) return 401` only.
- [ ] No raw `$payload` stored — whitelist fields via `array_intersect_key()` before DB writes.
- [ ] Webhook auth bypass uses `app()->environment('local', 'testing')` only — never `return true`.
- [ ] OpenAPI spec updated under `docs/04-API/` covering the three new endpoints.
- [ ] Mobile team has reviewed §7 wire shapes and confirmed the URL change from legacy to `/deposit/cancel`.

---

## 10. Risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| 1 | Stripe Checkout session completes but webhook is delayed/dropped | Low | Medium | `cards:purge-expired-deposits` cron verifies session state via Stripe API on every run; any completed session detected without a `paid` DB record triggers the state transition |
| 2 | Race between cancel and card.shipped (Q9.3) | Low | High | Atomic `UPDATE ... WHERE status = 'paid'` — `affected_rows = 0` detection in both handlers. Structural, not advisory |
| 3 | Stripe refund API 5xx during cancel | Medium | Low | Mark `refund_pending_manual`, page ops, return 200 to user with manual-processing message. Per Q9.4 — user flow never blocked |
| 4 | User double-taps deposit start (race on INSERT) | Low | Medium | Per-user active-deposit check + `idempotency.required` middleware covers double-tap. If idempotency key is reused, cached response is returned. If two genuinely separate requests race, the second's `startDeposit()` will find an active deposit and return `ERR_CARDS_002` |
| 5 | Quote redeemed by deposit start but Checkout session creation fails | Low | Medium | `QuoteService::redeem()` runs inside `DB::transaction()`; if Checkout session creation fails, the transaction rolls back and `consumed_at` remains NULL. Requires wrapping the Stripe API call carefully — the transaction should NOT include the Stripe API call itself (Stripe is external). Correct pattern: redeem quote in transaction, then call Stripe outside it. If Stripe fails, re-open a new transaction to un-redeem (set `consumed_at = NULL`). Document this as a known compensating pattern in the service |
| 6 | Mobile calls `/cards/waitlist/{id}/cancel` legacy URL | High (before URL change) | Low | The legacy endpoint should return a 410 or redirect until mobile ships the URL update. Coordinate with mobile — see §15 |
| 7 | `refundEligibleAfter` miscalculation | Low | High | Unit test explicitly asserts `refundEligibleAfter = paidAt.addMonths(18)`, frozen, using Carbon immutable |

---

## 11. Security considerations

- **Webhook signature:** `STRIPE_CARDS_WEBHOOK_SECRET` must be provisioned separately from `STRIPE_WEBHOOK_SECRET` (subscriptions). Rotating one does not break the other.
- **Checkout session metadata:** metadata fields stored in `card_waitlist_deposits` must be whitelisted via `array_intersect_key()` before storage — never persist the raw Stripe event payload.
- **Deposit ID non-enumerability:** UUID v4 for `card_waitlist_deposits.id` prevents enumeration of deposit IDs in URLs or deep links.
- **Amount tampering:** the deposit amount (€5) is a server-side constant from `config('cards.deposit_amount_eur_cents')`. It is NOT read from the quote's `feeBreakdown` without validation — compare the quote's breakdown amount against the server-side constant and reject if they differ.
- **Stripe Checkout trust:** after `checkout.session.completed`, verify `payment_status = 'paid'` before transitioning to `paid` state. A `payment_status = 'unpaid'` event (e.g. Checkout with `mode: 'setup'` by mistake) must not trigger the `paid` transition.
- **PII in logs:** do not log Stripe payment intent IDs, customer emails, or card last-four digits. The `stripe_checkout_session_id` and `stripe_payment_intent_id` columns are stored (needed for operations) but never logged at DEBUG level.
- **Withdrawal consent:** when `withdrawalConsent` is present in the request, store it in `subscription_consent_log` (reusing slice 1's table) with `context = 'card_waitlist_deposit'`. This is an EU consumer right compliance requirement, not merely a best practice.

---

## 12. Test strategy

### 12.1 Unit (Pest — `tests/Unit/CardIssuance/`)

- `WaitlistDepositServiceTest`
  - `startDeposit` happy path: correct DB insert, quote consumed, Stripe session created
  - `startDeposit` with expired quote → `ERR_QUOTE_001`
  - `startDeposit` with already-consumed quote → `ERR_QUO_002`
  - `startDeposit` with active existing deposit → `ERR_CARDS_002`
  - `startDeposit` with no waitlist entry → `ERR_CARDS_001`
  - `cancelDeposit` happy path (paid status) → `cancellation_requested`, Stripe refund initiated
  - `cancelDeposit` happy path (pending_payment status) → Checkout session cancelled
  - `cancelDeposit` on shipped deposit (Q9.3 `affected_rows = 0`) → `ERR_CARDS_004`
  - `cancelDeposit` Stripe API failure → `refund_pending_manual` state, no exception propagated
  - `entryFor` all five states → correct response shape
  - `entryFor` frozen `refundEligibleAfter` assertion
  - All fee math uses `bcmath` (no float assertions)

### 12.2 Feature (Pest — `tests/Feature/CardIssuance/`)

- `WaitlistDepositEndpointTest`
  - `POST /cards/waitlist/deposit` happy path → 200 + correct shape
  - `POST /cards/waitlist/deposit` missing Idempotency-Key → 422
  - `POST /cards/waitlist/deposit` replay with same key → cached 200
  - `POST /cards/waitlist/deposit` replay with same key, different body → 409
  - `POST /cards/waitlist/deposit/cancel` happy path → 200
  - `POST /cards/waitlist/deposit/cancel` no active deposit → 404
  - `GET /cards/waitlist/entry` all five states
  - Queue position ordering: deposit-paid users ranked before free-tier users
- `CardWaitlistWebhookTest`
  - `checkout.session.completed` (paid) → `paid` state + outbox row written atomically
  - `checkout.session.completed` (not paid) → no state change
  - `checkout.session.expired` → `expired` state
  - `charge.refunded` → `refunded` state + negative outbox row
  - Duplicate event_id → idempotent (second request returns 200, no duplicate outbox)
  - Invalid signature → 400
  - Signature bypass in `testing` environment

### 12.3 Concurrent access (Pest — `tests/Feature/CardIssuance/`)

- `WaitlistDepositRaceTest` — two concurrent `POST /deposit` calls for the same user → exactly one `card_waitlist_deposits` row created; second returns `ERR_CARDS_002`
- Q9.3 race: cancel + ship concurrent UPDATE → `affected_rows = 0` path exercised for both directions

### 12.4 Purge cron (`tests/Feature/CardIssuance/`)

- `PurgeExpiredDepositsCommandTest` — 24h-old pending_payment session → marked `expired`; 23h-old → not purged; cap at 1,000 records

---

## 13. Status report format (for implementation agent)

When implementation is complete, report using this format:

```
Status: DONE | DONE_WITH_CONCERNS | BLOCKED

Branch: feat/plan-b-slice-5-card-waitlist-deposit
PR URL: <URL>

Files created:
  - app/Domain/CardIssuance/Http/Controllers/WaitlistDepositController.php
  - app/Domain/CardIssuance/Http/Requests/DepositRequest.php
  - app/Domain/CardIssuance/Services/WaitlistDepositService.php
  - app/Domain/CardIssuance/Webhooks/CardWaitlistWebhookController.php
  - app/Domain/CardIssuance/Models/CardWaitlistDeposit.php
  - app/Console/Commands/PurgeExpiredDepositsCommand.php
  - database/migrations/2026_05_XX_XXXXXX_create_card_waitlist_deposits_table.php
  - tests/Unit/CardIssuance/WaitlistDepositServiceTest.php
  - tests/Feature/CardIssuance/WaitlistDepositEndpointTest.php
  - tests/Feature/CardIssuance/CardWaitlistWebhookTest.php

Files modified:
  - routes/api.php (3 new routes)
  - routes/console.php (1 new schedule append)
  - config/error_codes.php (6 new ERR_CARDS_* codes)

PHPStan: PASS (0 new baseline entries)
php-cs-fixer: PASS (no diffs)
Pest: PASS (XX tests, 0 failures)

Open design decisions resolved during implementation:
  OD-1 (refund mechanism): <synchronous / async>
  OD-3 (double-start invariant): <reject / replace>
  OD-4 (quote release on expiry): <do not reset / reset>
  OD-5 (entry vs deposit/status): <entry only / both>

Concerns: <list any deviations from this spec or unresolved items>
```

---

## 14. Estimated effort

**3 engineer-days** — the smallest slice in Plan B v1.3.0.

Breakdown:

| Task | Days |
|---|---|
| Migration + model (`card_waitlist_deposits`) | 0.25d |
| `WaitlistDepositService` (startDeposit + cancelDeposit + entryFor) | 0.75d |
| Controller + FormRequest (3 endpoints + error handling) | 0.5d |
| `CardWaitlistWebhookController` (signature verify + dedup + outbox) | 0.5d |
| Error code registration + route wiring + cron | 0.25d |
| Unit + feature tests | 0.75d |

The estimate is low because:
- The Stripe Checkout integration pattern is already proven in slice 1 (subscription checkout).
- `QuoteService::redeem()` is a direct dependency injection from slice 3; no re-implementation.
- `processed_webhook_events` + `revenue_outbox_events` tables and their write patterns are already in place from slice 1.
- The Q9.3 atomic state transition is a single SQL UPDATE — not a multi-step saga.

---

## 15. Coordination items

### Before implementation starts

- [ ] **Slice 3 must be merged** to `main` before slice 5 implementation begins — `QuoteService::redeem()` is a hard dependency.
- [ ] **Mobile team confirmation on OD-5** (`GET /entry` vs `GET /deposit/status`) — implement accordingly.
- [ ] **Mobile URL change:** mobile's service spec currently calls `POST /cards/waitlist/{id}/cancel` (with a per-deposit ID in the path). Slice 5 implements `POST /api/v1/cards/waitlist/deposit/cancel` (no ID in path; identified by authenticated user). This is a two-line change in mobile's card service. Coordinate the cutover timing — mobile must deploy the URL change before or simultaneously with the slice 5 deploy. Until mobile ships the URL change, the legacy path may need a 410 Gone redirect.
- [ ] **`STRIPE_CARDS_WEBHOOK_SECRET` env var** — must be generated by the operator (Stripe dashboard → Webhooks → `POST /webhooks/stripe/cards` endpoint → signing secret). Add to `.env.production.example` and `.env.zelta.example`. This is the only new env var slice 5 requires.
- [ ] **`config('cards.allowed_return_urls')` config key** — add `cards.php` config file OR extend `config/subscription.php` with a `cards_waitlist` section. Controller decision: which file.
- [ ] **Open design decisions OD-1, OD-3, OD-4** — confirm before implementation agent starts. All have recommendations that are likely correct but require explicit sign-off.

### After implementation merges

- [ ] **Mobile smoke test** — on the next preview/debug build after slice 5 merges, verify the three endpoints and the Stripe Checkout flow end-to-end.
- [ ] **ERR_CODES PR review** — the six new `ERR_CARDS_*` codes in `config/error_codes.php` are proposals from this spec; the controller should confirm code naming before merge.
- [ ] **Stripe webhook endpoint registration** — register `POST /webhooks/stripe/cards` in the Stripe dashboard (test mode) before running integration tests; production registration before go-live.
- [ ] **GDPR erasure flow owner notification** — the erasure flow implementer must wire `WaitlistDepositService::cancelDeposit()` into the `UserAccountClosed` event handler per Q9.4. This spec documents the method signature; the wiring is their responsibility.
- [ ] **`closed_account_refunds` table alignment** — Backend-Q7 (Backend-Q7 is in the deltas doc: GDPR + IAP refund tracking) extends `closed_account_refunds` with a `source ENUM('stripe_card_deposit', 'apple_iap', 'google_play')`. Slice 5 assumes this table exists with the `source` column. If the `closed_account_refunds` table has not yet been created, the erasure-flow implementer must create it; slice 5 does not create it (it is erasure-flow scope).

---

## Appendix A — Slice 5 API surface summary

| Method | Path | Idempotent? | Auth ability | Notes |
|---|---|---|---|---|
| `POST` | `/api/v1/cards/waitlist/deposit` | via `Idempotency-Key` | `write` | Requires `quoteId` from slice 3 |
| `POST` | `/api/v1/cards/waitlist/deposit/cancel` | via `Idempotency-Key` | `write` | No body; user-scoped cancel |
| `GET` | `/api/v1/cards/waitlist/entry` | yes (GET) | `read` | Holistic waitlist state |
| `POST` | `/webhooks/stripe/cards` | by Stripe `event.id` | none (webhook) | Internal; no Sanctum |

---

## Appendix B — Slice 5 error codes (proposed)

All are new; none currently exist in `config/error_codes.php`.

| Code | HTTP | Description |
|---|---|---|
| `ERR_CARDS_001` | 404 | User is not on the card waitlist. |
| `ERR_CARDS_002` | 409 | User already has an active deposit (pending or paid). |
| `ERR_CARDS_003` | 404 | No active deposit found to cancel. |
| `ERR_CARDS_004` | 409 | Deposit state conflict — already shipped or cancellation already in progress. |
| `ERR_CARDS_005` | 422 | Invalid return URL — not in the configured allow-list. |
| `ERR_CARDS_006` | 409 | Quote `kind` is not `card_waitlist_deposit`. |

---

## Appendix C — Source document cross-references

| Claim in this spec | Source |
|---|---|
| `POST /deposit/cancel` path (singular, per-user) | Deltas Q9.1 |
| Updated `GET /deposit/status` shape with `refundEligibleAfter` frozen | Deltas Q9.2 |
| CHECK-constrained UPDATE for cancel vs ship race | Deltas Q9.3 |
| Account closure: never block on Stripe failure | Deltas Q9.4 |
| "5–10 business days" refund copy | Deltas Q9.5 |
| `position = ROW_NUMBER() OVER (ORDER BY deposit_paid DESC, joined_at ASC)` | Commercial §6.3 |
| Four refund triggers and their windows | Commercial §6.4 |
| `quote_id` as input to deposit endpoint | Commercial §6.1 (implied by §3 quote integration) + Slice 5 context |
| Off-chain outbox saga: PSP webhook → outbox + dedup in same transaction | ADR-0002 §"Outbox saga for off-chain fees" |
| Money triple `(amount, decimals, denomination)` on all wire money fields | ADR-0004 §Decision |
| `QuoteService::redeem()` four-step validation contract | Slice 3 spec §5.4 |
| `kind: "card_waitlist_deposit"` quote shape (flat €5 EUR) | Slice 3 spec §5.1 |
| `processed_webhook_events` + `revenue_outbox_events` write pattern | Subscription webhook controller (slice 1) |
