# Plan B v1.3.0 — Slice 3: Pricing Quote (`POST /api/v1/pricing/quote`)

**Date:** 2026-05-10
**Author:** Backend Architect
**Status:** Spec — implementation not started
**Slice predecessors:**
  - Slice 1 (Cashier Stripe subscription path) — merged to `main` as commit `957ea3d8` via PR #1037
  - Slice 2 (Apple/Google IAP path) — spec open at PR #1038 (implementation pending)
**Estimated implementation effort:** 4–5 engineer-days (revised from 3–4d — CheckoutRequest expansion adds 0.5d)
**Mobile target:** Zelta v1.3.0
**Mobile priority:** P1 — blocks dynamic price display and card waitlist deposit (slice 5)

---

## 1. Working directory and authorisation

You are in a git worktree branched off `main` (which already includes slice 1 from PR #1037 and will include slice 2 once that PR merges — verify the base includes all `app/Domain/Pricing/` foundations before starting).

- Create branch: `feat/plan-b-slice-3-pricing-quote`
- Worktree base: `origin/main` (post-slice-1; confirm slice 2 merged before starting if slice 2 blocks anything you need)
- Implement ONLY inside `app/Domain/Pricing/`, `routes/api.php`, `config/pricing.php`, and `database/migrations/`. Do NOT create or modify `app/Domain/Subscription/` unless you are filling in the `quoteId`-optional parameter on `POST /subscription/checkout` (which is back-compat, see §5.4).
- Do NOT modify `bootstrap/app.php` or `composer.json` unless the controller explicitly approves a new dependency.
- Commit + push + open PR against `main` titled `feat(pricing): slice 3 — PriceQuote endpoint + replay protection`.
- Do NOT merge; the human reviewer will.
- After all commits are clean (php-cs-fixer, PHPStan L8, Pest pass), report your status using the format in §13.

---

## 2. Situation summary

### What slices 1 and 2 delivered

**Slice 1 (PR #1037)** delivered the Stripe-only subscription path including:
- `POST /api/v1/subscription/checkout` — Stripe Cashier session, trial fingerprint gate
- Subscription lifecycle endpoints (`/change-plan`, `/cancel`, `/reactivate`)
- `POST /webhooks/stripe/subscriptions` — webhook handler writing to `revenue_outbox_events`
- `processed_webhook_events`, `trial_card_fingerprints`, `subscription_consent_log`, `revenue_outbox_events`, `revenue_events` tables
- Slice 1's `CheckoutRequest` does **not** currently accept `quoteId` — the field is absent from `CheckoutRequest::rules()`. Slice 3 expands `CheckoutRequest::rules()` to add `quoteId` as a nullable string, AND adds the `QuoteService::redeem()` call to `SubscriptionService::startCheckout()`. Both expansions are slice 3 scope (see §5.4).

**Slice 2 (PR #1038)** delivers the Apple + Google IAP path: `POST /api/v1/subscription/iap/verify`, IAP webhook receivers, `iap_subscriptions` + `iap_receipts` tables, and the `SubscriptionProjection` IAP join.

**Slice 1 foundations PR #1033** delivered (already on `main`):
- `app/Domain/Pricing/ValueObjects/Money.php` — the ADR-0004 Money VO
- `app/Domain/Pricing/ValueObjects/MoneyFormatter.php`
- `app/Domain/Pricing/Validation/MoneyFormRule.php`
- `app/Http/Middleware/IdempotencyKey.php` — registered as `idempotency.required` alias
- `app/Support/ErrorResponse.php`
- `config/error_codes.php` — including `ERR_QUOTE_001`, `ERR_QUOTE_002`, `ERR_FEE_001`

### What slice 3 adds

Slice 3 delivers the **universal pricing quote endpoint** — the P1 mobile endpoint that produces a tamper-evident, time-limited, tier-aware `PriceQuote` for any send/swap/ramp/subscription transaction before the user signs. Its primary consumers are:

1. **Mobile price display** — called before any user-initiated transaction to show the fee breakdown
2. **`POST /subscription/checkout` (slice 1)** — accepts `quoteId` as optional input to lock the displayed price at checkout time (currently a no-op stub; slice 3 makes it functional)
3. **`POST /cards/waitlist/deposit` (slice 5)** — will require a `quoteId` to lock the deposit amount; slice 5 is a direct downstream of slice 3

At a high level, slice 3 adds:

1. **`POST /api/v1/pricing/quote`** — the P1 endpoint. Resolves fee tier, assembles a `PriceQuote`, persists it to `price_quotes`, returns the tamper-evident wire response.
2. **`GET /api/v1/pricing/quote/{quoteId}`** — optional read endpoint; see §5.2 and §8 (open design decision).
3. **`price_quotes` migration** — includes all columns from ADR-0003's schema plus the Q2.2/Q2.3 delta additions (`entity_key`, `user_op_hash`, `user_op_payload`, `superseded_by`, `terms_changed`).
4. **`App\Domain\Pricing\Services\PriceQuoteIssuer`** — the main entry point, orchestrates fee-tier resolution, upstream quote fetching, signing, and persistence.
5. **`App\Domain\Pricing\Services\FeeResolverService`** — thin adapter wrapping the `ResolveFeeTier` CQRS query handler from `app/Infrastructure/QueryBus`. Returns a `FeeTier` VO or throws `ERR_FEE_001`.
6. **`App\Domain\Pricing\Services\QuoteSigner`** — HMAC-SHA256 sign and verify using `PRICING_QUOTE_PEPPER` env var.
7. **`App\Domain\Pricing\ValueObjects\Quote`** (wire-facing, distinct from `PriceQuote` DB aggregate) and **`App\Domain\Pricing\ValueObjects\FeeTier`** VOs.
8. **Expiry purge cron** — `pricing:purge-quotes` command, wired daily at 03:10 UTC in `routes/console.php`.

### Where slice 3 plugs in

Slice 3 is the pricing layer that all downstream submission flows depend on. The dependency direction is:

```
POST /api/v1/pricing/quote
  ↓ (returns quoteId)
POST /api/v1/wallet/transactions/submit  (send/swap — validates userOpHash against stored quote)
POST /api/v1/subscription/checkout       (optional quoteId to lock price)
POST /api/v1/cards/waitlist/deposit      (slice 5 — requires quoteId)
```

**Revenue boundary:** slice 3 does NOT write to `revenue_outbox_events` or `revenue_events`. Quote issuance does not constitute revenue recognition. Revenue is recognised when:
- Subscription is started (slice 1 Stripe webhook / slice 2 IAP webhook)
- On-chain fee is collected from a signed userOp (ADR-0002 chain-event-sourced ingestor)
- Card deposit is captured (slice 5)

This boundary is explicit per ADR-0002. The `price_quotes.user_op_hash` column is the join key that the chain ingestor uses to attribute an on-chain fee transfer to a user/quote, but slice 3 itself never touches `revenue_*` tables.

---

## 3. Read these BEFORE writing code (in order)

These files are the canonical source of truth. Read them in this order; the later files override the earlier ones on any point of conflict.

1. **`docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md`** — the master architectural baseline. Key sections for slice 3:
   - `### Q2 — Quote contract architecture` (line ~1527): the three concrete patches — Q2.1 entity-key dedup, Q2.2 userOp server-side storage + hash verification, Q2.3 quote refresh with `superseded_by` and `terms_changed`. Read in full before touching any code.
   - `### Q3 — Currency model (kind-dependent quote response)` (line ~1569): Q3.1 per-kind response shape (updated per Backend-Q9 to use Money VO triples), Q3.2 `terms_changed` decision rules on refresh, Q3.3 Pro savings stored at transaction time.
   - `### Pricing bounded context (Backend grilling, Q4)` (line ~538): the `Domain/Pricing` rename rationale, naming table, `PriceQuoteIssuer` orchestration flow, `config/pricing.php` shape.
   - `### Fee-collector wallet architecture (Backend grilling, Q2)` (line ~209): native-asset-send fee policy (no service fee for native ETH/MATIC/SOL sends; quote returns `feeBreakdown` with only network fee), per-chain fee config.
   - `### Backend-Q9` (line ~1286): the money format decision (β) — smallest-unit strings, explicit `decimals`, `currency`-or-`asset` everywhere. All Money fields in the quote response use this shape.
   - Search for "pricing/quote", "price_quotes", "ERR_QUOTE", "ERR_FEE", "entity_key", "user_op_hash", "terms_changed", "PRICING_QUOTE_PEPPER" throughout.

2. **`docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §3** — original `Domain/Quote` spec (now renamed per ADR-0003 to `Domain/Pricing`). Read §3.1 (request/response shape), §3.2 (replay protection schema), §3.3 (submission contract), §3.4 (rate limiting), §3.5 (authorisation gating). Note: the schema in §3.2 is the base; the deltas Q2 additions (`entity_key`, `user_op_hash`, `user_op_payload`, `superseded_by`, `terms_changed`) supplement it. Also read §2 (tier-aware fee resolver — the `ResolveFeeTier` query handler that slice 3 wraps) and §6 (card waitlist deposit — slice 5 will require a `quoteId` from this endpoint).

3. **`docs/adr/0003-pricing-bounded-context.md`** — applies in full. Wire-vs-internal naming asymmetry MUST be honoured: `quoteId` on the wire, `price_quote_id` internal column. The `price_quotes` schema in the Implementation Notes section is the authoritative DDL starting point. Read the Form Request and API Resource translation examples before writing any controller code.

4. **`docs/adr/0004-money-on-the-wire.md`** — applies to every Money field in the quote response. All `feeBreakdown` items, `amount`, `eurEquivalent`, rate values are `Money` VO triples. Reject decimal-point amounts with `ERR_VALIDATION_002`. Never store raw micros or decimal strings — use smallest-unit integers.

5. **`docs/adr/0002-revenue-projection-dual-upstream.md`** — read §"Chain-event-sourced ingestor" to understand how `price_quotes.user_op_hash` is the join key for the revenue ingestor. Slice 3 stores the hash; the ingestor reads it. Slice 3 does NOT write `revenue_events`. This ADR establishes the boundary.

6. **`app/Domain/Pricing/ValueObjects/Money.php`** — already implemented. Understand `Money::fiat()`, `Money::asset()`, `jsonSerialize()`. Slice 3's quote response serialises all money fields through this VO.

7. **`app/Http/Middleware/IdempotencyKey.php`** — understand the idempotency implementation. `POST /api/v1/pricing/quote` applies `idempotency.required` middleware (**DECIDED** — both middleware and entity-key dedup coexist per Q2.1; see §8 OD-1). Read the middleware's 24h replay logic and body-hash mismatch detection.

8. **`app/Domain/Subscription/Http/Requests/CheckoutRequest.php`**, **`app/Domain/Subscription/Services/SubscriptionService.php`**, **`app/Domain/Subscription/Http/Controllers/SubscriptionController.php`** — structural templates for the Pricing controller, service, and request classes. Mirror the patterns: constructor-injected services, `if (! $user instanceof User)` auth guard (never `assert()`), `ErrorResponse::make($code)` for errors, `response()->json(...)` for success.

9. **`config/error_codes.php`** — verify `ERR_QUOTE_001` (410 expired), `ERR_QUOTE_002` (409 payload mismatch), `ERR_FEE_001` (500 resolver failure) are already registered. They are — from PR #1033. Do NOT re-register. Determine in §5 which additional codes (if any) this slice actually emits and add only those.

10. **`CLAUDE.md`** — project conventions. Especially: BCMath for all money, multi-connection rule (all `price_quotes` table writes are global-connection-only; no `UsesTenantConnection` touch), `assert()` as auth guard (never), idempotency key is HTTP header not body field, camelCase wire, PHPStan Level 8.

---

## 4. Existing repo state (foundations slice 3 builds on)

The following is already in `main` after slice 1 (PR #1033 foundations + PR #1037 slice 1). Do not re-create any of it.

### Value objects and utilities

| Class | Location | Status |
|---|---|---|
| `Money` | `app/Domain/Pricing/ValueObjects/Money.php` | Exists; implements ADR-0004 |
| `MoneyFormatter` | `app/Domain/Pricing/ValueObjects/MoneyFormatter.php` | Exists |
| `MoneyFormRule` | `app/Domain/Pricing/Validation/MoneyFormRule.php` | Exists; validates Money triples in Form Requests |
| `ErrorResponse` | `app/Support/ErrorResponse.php` | Exists; `ErrorResponse::make($code)` reads `config/error_codes.php` |

### Middleware

| Alias | Class | Status |
|---|---|---|
| `idempotency.required` | `app/Http/Middleware/IdempotencyKey.php` | Exists; registered in `bootstrap/app.php` |

### Tables (created by slice 1 migrations)

| Table | Purpose |
|---|---|
| `processed_webhook_events` | Webhook dedup — not used by slice 3 (quote issuance is not a webhook flow) |
| `revenue_outbox_events` | Off-chain outbox — not written by slice 3 (revenue recognised downstream) |
| `revenue_events` | Unified revenue read model — not written by slice 3 |
| `idempotency_keys` | Idempotency replay table — slice 3 WILL write here if `idempotency.required` is applied |

### Error codes already registered (`config/error_codes.php`)

| Code | HTTP | Purpose |
|---|---|---|
| `ERR_QUOTE_001` | 410 | Quote expired |
| `ERR_QUOTE_002` | 409 | Submitted payload hash doesn't match stored quote (payload mismatch on redemption) |
| `ERR_FEE_001` | 500 | Fee tier could not be resolved |
| `ERR_VALIDATION_001` | 422 | Missing `Idempotency-Key` header |
| `ERR_VALIDATION_002` | 422 | Money field malformed |
| `ERR_VALIDATION_003` | 422 | Idempotency-Key invalid format |
| `ERR_CUR_001` | 400 | Currency must be EUR in v1.3.0 |

**Registered in companion hotfix PR `fix(plan-b): error code registry` (available before slice 3 implementation):**

| Code | HTTP | Purpose |
|---|---|---|
| `ERR_QUO_002` | 409 | Quote already consumed (consumed-replay path — distinct from `ERR_QUOTE_002` payload mismatch) |

### Routes (already registered in `routes/api.php` by slice 1)

Slice 3 must add:
- `POST /api/v1/pricing/quote`
- `GET /api/v1/pricing/quote/{quoteId}` (if the open decision in §8 resolves to "include it")

### Config

| Key | File | Status |
|---|---|---|
| `config/pricing.php` | Tier definitions, fee-collector addresses | **Must be created by slice 3** (renamed from `config/fees.php` per ADR-0003) |
| `PRICING_QUOTE_PEPPER` | `.env` | **Must be generated and added** by controller before staging deploy |

---

## 5. Slice 3 scope — BUILD this

### 5.1 Endpoint: `POST /api/v1/pricing/quote`

**Auth:** Sanctum bearer token, ability `write` (quote issuance reserves upstream rates — it is a write operation even if it creates no financial transaction).

**Idempotency:** The Q2.1 delta mandates **backend-computed idempotency** for this endpoint — the backend computes the entity-key dedup rather than relying on a caller-supplied `Idempotency-Key`. The rationale: caller-supplied keys are too easy to mis-key on a double-tap, producing two distinct quotes for one user intent. See §8 open design decision on whether to additionally apply `idempotency.required` middleware.

**Rate limiting:** 60 quotes / minute / user; 600 quotes / minute / IP. Implemented via Redis sliding-window using `Cache::add($key, 0, $ttl)` + `Cache::increment()` per CLAUDE.md (never read-then-write counters). Returns `429` with `Retry-After` header and `ERR_QUO_005` on breach (registered by this slice — see §5.1 new codes table).

#### Request body shape

```json
{
  "kind": "send",
  "amount": {
    "amount": "1000000",
    "decimals": 6,
    "asset": "USDC"
  },
  "from": {
    "asset": "USDC",
    "network": "polygon"
  },
  "to": {
    "asset": "USDC",
    "network": "base"
  },
  "recipient": "0xabc...",
  "currency": "EUR"
}
```

| Field | Type | Required | Notes |
|---|---|---|---|
| `kind` | `"send"` \| `"swap"` \| `"ramp_buy"` \| `"ramp_sell"` \| `"subscription_initial"` \| `"card_waitlist_deposit"` | yes | Determines which upstream service is called and which response shape is returned (Q3 kind-dependent shapes). `subscription_initial` and `card_waitlist_deposit` are fiat-only; `send`/`swap` involve on-chain assets. |
| `amount` | Money triple (ADR-0004) | yes | The amount the user is sending/swapping/paying. Must match Money VO validation (`MoneyFormRule`). Decimal-point in `amount` string → `ERR_VALIDATION_002`. |
| `from` | `{asset, network}` | yes for `send`/`swap` | Source asset + chain. Omit for fiat-only kinds. |
| `to` | `{asset, network}` | yes for `send`/`swap`/`ramp_buy` | Destination asset + chain. |
| `recipient` | string | yes for `send` | Destination address. Screened by GoPlus / Chainalysis. Blocked address → `ERR_QUO_007`. |
| `currency` | `"EUR"` | yes | Must be `"EUR"` in v1.3.0 — `ERR_CUR_001` otherwise. Present on all kinds so the fee's EUR equivalent is always calculable. |
| `dryRun` | — | — | **Query parameter** (`?dryRun=true`), NOT a body field. Per commercial spec §3.5. If present and `true`, assembles the quote without persisting or consuming upstream rate limits. Useful for price-preview without commitment. Returns the same shape without a `quoteId` (see §5.3). |

**Entity-key computation (Q2.1):** backend computes `SHA256(user_id || kind || canonical_amount || recipient || from_json || to_json)` as the dedup key. If a live (unexpired, unconsumed) quote exists for this entity-key in `price_quotes`, the existing row is returned without creating a new one. This is the backend-side dedup that replaces caller-supplied idempotency on quote issuance.

#### Response body shape (200 OK) — kind-dependent per Q3.1 + Backend-Q9

The shape differs by `kind`. All money fields are Money VO triples per ADR-0004.

**For `kind: "send"` or `kind: "swap"` (on-chain, asset-denominated fees):**

```json
{
  "quoteId": "qte_01HQ...",
  "kind": "send",
  "expiresAt": "2026-05-10T14:32:00Z",
  "feeBreakdown": [
    {
      "label": "service",
      "amount": { "amount": "1000000", "decimals": 6, "asset": "USDC" },
      "eurEquivalent": { "amount": "92", "decimals": 2, "currency": "EUR" }
    },
    {
      "label": "network",
      "amount": { "amount": "210000", "decimals": 6, "asset": "USDC" },
      "eurEquivalent": { "amount": "19", "decimals": 2, "currency": "EUR" }
    }
  ],
  "rates": {
    "USDC/EUR": {
      "value": "0.92",
      "decimals": 4,
      "timestamp": "2026-05-10T14:31:55Z",
      "sourceId": "oracle:chainlink:USDC-EUR:0xabc..."
    }
  },
  "feeTier": {
    "txFlat": { "amount": "1000000", "decimals": 6, "asset": "USDC" },
    "swapMarginBps": 50,
    "rampMarginBps": 100
  },
  "userOpHash": "0x...",
  "termsChanged": false,
  "saveWithPro": {
    "amount": { "amount": "500000", "decimals": 6, "asset": "USDC" },
    "eurEquivalent": { "amount": "46", "decimals": 2, "currency": "EUR" },
    "label": "Save with Pro"
  }
}
```

> **`feeTier` in quote response — spec-author addition:** The `feeTier` object echoed in the quote response is not present in the Q3.1 wire shape (which only includes `feeTier` in the entitlements endpoint). It is provided here as a mobile convenience — allows the app to display the user's fee tier alongside the quote without a separate entitlements call. This field is removable without a contract break if mobile does not need it. **Verify with mobile-dev before implementation.** If removed, also remove `swapMarginBps`/`rampMarginBps`/`txFlat` from the `feeTier` key in the response shape.

`userOpHash` is `keccak256(canonicalize(userOp))` stored in `price_quotes.user_op_hash`. Required for `send`/`swap`; `null` for `ramp_*`/`subscription_initial`/`card_waitlist_deposit`.

`saveWithPro` is omitted if the user is already Pro tier.

**For `kind: "ramp_buy"` or `kind: "ramp_sell"` (fiat, EUR-denominated fees):**

```json
{
  "quoteId": "qte_01HQ...",
  "kind": "ramp_buy",
  "expiresAt": "2026-05-10T14:32:00Z",
  "feeBreakdown": [
    {
      "label": "service",
      "amount": { "amount": "100", "decimals": 2, "currency": "EUR" },
      "eurEquivalent": { "amount": "100", "decimals": 2, "currency": "EUR" }
    },
    {
      "label": "provider",
      "amount": { "amount": "40", "decimals": 2, "currency": "EUR" },
      "eurEquivalent": { "amount": "40", "decimals": 2, "currency": "EUR" },
      "provider": "stripe_bridge"
    }
  ],
  "rates": {},
  "feeTier": {
    "txFlat": null,
    "swapMarginBps": 50,
    "rampMarginBps": 100
  },
  "userOpHash": null,
  "termsChanged": false,
  "saveWithPro": {
    "amount": { "amount": "50", "decimals": 2, "currency": "EUR" },
    "eurEquivalent": { "amount": "50", "decimals": 2, "currency": "EUR" },
    "label": "Save with Pro"
  }
}
```

`rates: {}` for ramp (no asset/EUR rate needed — the fee is already in EUR).

**For `kind: "subscription_initial"` or `kind: "card_waitlist_deposit"` (flat EUR):**

```json
{
  "quoteId": "qte_01HQ...",
  "kind": "subscription_initial",
  "expiresAt": "2026-05-10T15:02:00Z",
  "feeBreakdown": [
    {
      "label": "subscription",
      "amount": { "amount": "499", "decimals": 2, "currency": "EUR" },
      "eurEquivalent": { "amount": "499", "decimals": 2, "currency": "EUR" }
    }
  ],
  "rates": {},
  "feeTier": {
    "txFlat": null,
    "swapMarginBps": 50,
    "rampMarginBps": 100
  },
  "userOpHash": null,
  "termsChanged": false,
  "saveWithPro": null
}
```

`expiresAt` TTL for fiat-only kinds is longer — see §5.5 expiry policy.

**Native-asset-send fee policy (Q2 / ADR-0001):** `kind: "send"` with `from.asset = "ETH"` or `"MATIC"` or `"SOL"` (native assets) returns `feeBreakdown` with **no `"service"` item** — only `"network"` and optionally `"bridge"`. This is per the v1.3.0 policy: native-asset sends incur no service fee. `saveWithPro` is `null` in this case (no service fee to save on).

#### Error codes emitted by `POST /api/v1/pricing/quote`

| Code | HTTP | Condition |
|---|---|---|
| `ERR_VALIDATION_001` | 422 | Missing `Idempotency-Key` header (`idempotency.required` middleware is applied — **DECIDED**: both middleware and entity-key dedup coexist per Q2.1; see §8 OD-1) |
| `ERR_VALIDATION_002` | 422 | Money field malformed (decimal point in `amount`, missing denomination, both `currency` and `asset`) |
| `ERR_VALIDATION_003` | 422 | `Idempotency-Key` invalid format |
| `ERR_CUR_001` | 400 | `currency` field is not `"EUR"` |
| `ERR_FEE_001` | 500 | Fee tier could not be resolved for this user |

**New codes this slice must register** in `config/error_codes.php` (not registered by #1033):

| Code | HTTP | Condition | Notes |
|---|---|---|---|
| `ERR_QUO_005` | 429 | Quote rate limit exceeded (60/min/user) | Per commercial spec §3.4 + Appendix B convention — **DECIDED: ERR_QUO_005** (see §8 OD-5) |
| `ERR_QUO_006` | 403 | `kind: ramp_buy\|ramp_sell` requires KYC level ≥ Basic | Per commercial spec §3.5 |
| `ERR_QUO_007` | 403 | Destination address (`recipient`) blocked by screening | Per commercial spec §3.5 |
| `ERR_QUO_008` | 400 | Source asset balance insufficient for swap | Per commercial spec §3.5 |

Note: `ERR_QUO_002` (409, quote already consumed) is registered in the companion hotfix PR `fix(plan-b): error code registry`. Do not re-register it here. Use `ERR_QUO_002` for the consumed-replay path in `QuoteService::redeem()` (see §5.4).

### 5.2 Optional read endpoint: `GET /api/v1/pricing/quote/{quoteId}`

**Recommendation:** include this endpoint. Rationale:
- Provides replay safety for mobile: if the `/quote` POST response was received but the app crashed before display, the app can refetch the quote by ID
- Protects against `quoteId` enumeration: only the originating user (auth check `$quote->user_id === $user->id`) can read back their quote
- Consistent with the idempotency-key replay principle: we store the quote — we should serve it back

**Auth:** Sanctum bearer token, ability `read`.

**Response:** Same JSON shape as the `POST /api/v1/pricing/quote` 200 response, plus:
- An additional `status` field: `"active"` | `"expired"` | `"consumed"` | `"superseded"`
- If `status: "consumed"`, the `consumedAt` and `consumedBy` fields are populated (redacted to just the kind, e.g. `"wallet_send"`, not the raw `tx_hash` — reduces PII exposure in GET responses)
- If `status: "superseded"`, a `supersededBy: "<quoteId>"` field pointing to the replacement quote

**Error codes:**

| Code | HTTP | Condition |
|---|---|---|
| `ERR_QUO_001` | 404 | Quote not found or does not belong to authenticated user |

`ERR_QUO_001` is not registered in `config/error_codes.php` from PR #1033 — add it in this slice. (PR #1033 registered only `ERR_QUOTE_001` and `ERR_QUOTE_002` per the wire-vs-internal naming — note that `ERR_QUO_001` is the NOT-FOUND code from the original commercial spec §3.3, distinct from `ERR_QUOTE_001` the expiry code. This spec uses `ERR_QUO_001` for the GET 404 path to match the commercial spec's Appendix B table and avoid confusion with the expiry code.)

**Open question:** Whether to implement `GET /pricing/quote/{quoteId}` in slice 3 or defer to slice 5. See §8.

### 5.3 Dry-run mode

When `?dryRun=true` is appended as a **query parameter** (per commercial spec §3.5), the endpoint:
- Resolves the fee tier and upstream quotes
- Assembles the `PriceQuote` VO and computes the signature
- Does NOT persist a row to `price_quotes`
- Does NOT consume rate-limit quota
- Returns the same JSON shape as a live quote, but with `quoteId: null` and `expiresAt: null`
- Useful for price-preview before the user decides to initiate the transaction

Dry-run requests bypass the entity-key dedup logic (no row to deduplicate against).

The `QuoteRequest` FormRequest validates `$request->boolean('dryRun')` from the query string — not from the request body. The route declaration does not change; the query parameter is read directly in the request class or controller.

> **Extended `kind` enum note:** This slice introduces `subscription_initial` and
> `card_waitlist_deposit` `kind` values beyond the deltas Q3.1 enumeration
> (`send`/`swap`/`ramp_buy`/`ramp_sell`). These are required to enable slice 1's checkout
> redemption and slice 5's card-waitlist deposit. **A deltas Q3.1 amendment is
> needed to ratify these `kind` values formally** — tracked as open coordination
> item in §15. The implementation lands them in v1.3.0 with this caveat documented.

### 5.4 Quote consumption by downstream endpoints

Slice 3 defines the `QuoteService::redeem()` method that slice 1 and slice 5 call. The redemption contract:

1. `price_quotes.id = quoteId AND price_quotes.user_id = currentUserId` — else `ERR_QUO_001` (404)
2. `consumed_at IS NULL` — else `ERR_QUO_002` (409) with `"consumed"` in context. (`ERR_QUO_002` = "Quote already consumed" per Appendix B; registered in the companion hotfix PR `fix(plan-b): error code registry`.)
3. `expires_at > NOW()` — else `ERR_QUOTE_001` (410 expired)
4. For `send`/`swap`: `keccak256(canonicalize(signedUserOp.message))` must equal `price_quotes.user_op_hash` — else `ERR_QUOTE_002` (409 payload mismatch). This is the Q2.2 invariant. (`ERR_QUOTE_002` = "Submitted payload does not match quote" — distinct from `ERR_QUO_002`: the consumed-replay and payload-mismatch paths are different user-facing situations.)
5. `DB::transaction()` + `lockForUpdate()` on the `price_quotes` row — prevents concurrent redemption races
6. Set `consumed_at = NOW()`, `consumed_by = <ref>` (tx_hash | subscription_id | deposit_id)

**Slice 1 (`POST /subscription/checkout`) integration (slice 3 scope expansion):** Slice 1's `CheckoutRequest` does not currently accept `quoteId`. Slice 3 adds `'quoteId' => 'nullable|string'` to `CheckoutRequest::rules()` AND adds the `QuoteService::redeem()` call inside `SubscriptionService::startCheckout()`. When a `quoteId` is present, the checkout action calls `QuoteService::redeem()` before creating the Stripe session; the subscription plan + price in the Checkout session is validated against the quote's `feeBreakdown`. This is quote-optional in v1.3.0 — `quoteId` absent means proceed without a locked quote (back-compat preserved). Both the `CheckoutRequest` extension and the service integration are explicitly slice 3 commits (see §12 commit topology).

**Note on multi-connection safety:** `price_quotes` is a global-connection table. Slice 1's Stripe checkout also touches `trial_card_fingerprints` (global) and potentially Cashier tables (global via Stripe). No `UsesTenantConnection` models are involved in the quote redemption flow. `DB::transaction()` is safe here.

### 5.5 Tables: `price_quotes`

#### Migration

Create `database/migrations/2026_05_10_180001_create_price_quotes_table.php` with this DDL:

```sql
CREATE TABLE price_quotes (
    id                CHAR(36) NOT NULL PRIMARY KEY,          -- RFC 4122 v4 UUID
    user_id           CHAR(36) NOT NULL,                       -- FK → users.id
    user_tier         VARCHAR(32) NOT NULL,                    -- 'free' | 'pro'
    kind              ENUM(
                        'send',
                        'swap',
                        'ramp_buy',
                        'ramp_sell',
                        'subscription_initial',
                        'card_waitlist_deposit'
                      ) NOT NULL,
    request_payload   JSON NOT NULL,                           -- full request body
    response_payload  JSON NOT NULL,                           -- full response body (for replay)
    entity_key        CHAR(64) NOT NULL,                       -- SHA256 of intent (Q2.1)
    user_op_hash      CHAR(66) NULL,                           -- 0x + 32 bytes hex; null for fiat kinds
    user_op_payload   JSON NULL,                               -- full unsigned userOp; null for fiat kinds
    signature         CHAR(64) NOT NULL,                       -- HMAC-SHA256 over canonical payload
    superseded_by     CHAR(36) NULL,                           -- self-FK: points to newer quote on refresh
    terms_changed     TINYINT(1) NOT NULL DEFAULT 0,           -- true on refresh if delta is material (Q3.2)
    expires_at        TIMESTAMP NOT NULL,
    consumed_at       TIMESTAMP NULL,
    consumed_by       VARCHAR(255) NULL,                       -- tx_hash | subscription_id | deposit_id
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_price_quotes_user_expires (user_id, expires_at),
    INDEX idx_price_quotes_consumed (consumed_at),
    INDEX idx_price_quotes_entity_live (entity_key, expires_at, consumed_at),
    UNIQUE KEY uniq_price_quotes_user_op_hash (user_op_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Column notes:**
- `id`: RFC 4122 v4 UUID (`CHAR(36)`) per CLAUDE.md — MariaDB rejects non-v4 UUIDs in UUID columns. Generate with `Str::uuid()`.
- `user_op_hash`: `CHAR(66)` (covers `0x` prefix + 64 hex chars). Unique constraint prevents two live quotes sharing the same userOp hash. `NULL` allowed because fiat-only kinds have no userOp.
- `signature`: `CHAR(64)` holds a 32-byte HMAC-SHA256 output as 64 hex characters. Computed by `QuoteSigner` over the canonical form of `(id, user_id, kind, amount, expires_at)`.
- `superseded_by`: no foreign key constraint in the migration (MySQL circular FK on CHAR(36) self-reference needs `AFTER TABLE ALTER`; use a partial application-level check instead). Add a comment.
- `terms_changed`: `TINYINT(1)` (MySQL BOOLEAN alias); default 0 / false.
- Storage for variable-denomination fee amounts: the full `response_payload` JSON stores the complete fee breakdown including Money triples. No separate `fee_amount` / `fee_decimals` columns needed — the full quote is serialised in `response_payload` for replay. `revenue_events` (the downstream read model) stores the EUR equivalent when the fee is actually collected (not at quote time).

**Note on `price_quote_events` table:** ADR-0003 names `price_quote_events` as the event-sourced table. Slice 3 does NOT implement Spatie event sourcing for `PriceQuote` — the quote is ephemeral (expires in minutes) and is already durable in `price_quotes`. Event sourcing adds infrastructure cost for no benefit on a short-lived entity. The `PriceQuoteIssued`, `PriceQuoteConsumed`, `PriceQuoteSuperseded` events from ADR-0003 are published as domain events (fire-and-forget for websocket broadcast, not Spatie-persisted). Deferred to v1.3.1 if Spatie event sourcing on quotes is ever needed.

### 5.6 Services

#### `App\Domain\Pricing\Services\PriceQuoteIssuer`

The main orchestrating service. Called by `PricingController::quote()`.

Entry point: `issue(QuoteRequest $request, User $user): Quote`

Internal flow:

```
PriceQuoteIssuer::issue()
  → FeeResolverService::resolve($user)       → FeeTier VO  (ERR_FEE_001 on failure)
  → match $request->kind:
       'send'                → EvmUserOpPreparer / SolanaSendPreparer + fee tier application
       'swap'                → Domain\DeFi\SwapQuoteService + margin application
       'ramp_buy' / 'ramp_sell' → Domain\Ramp\SessionFactory + margin application
       'subscription_initial'   → config/pricing.php tier table (no upstream — flat price)
       'card_waitlist_deposit'  → config/pricing.php deposit amount (flat €5, no upstream)
  → entity_key = sha256(user_id || kind || canonical_amount || recipient || from_json || to_json)
  → check price_quotes for live entity_key match → return existing if found (Q2.1)
  → userOpHash = keccak256(canonicalize(userOp)) for send/swap; null otherwise
  → QuoteSigner::sign($priceQuoteId, $userId, $kind, $expiresAt, $responsePayload)
  → DB::transaction(): INSERT INTO price_quotes (...)
  → return Quote VO (wire-formatted)
```

Returns a `Quote` VO. The controller serialises the VO to JSON.

On entity-key dedup hit, returns the existing `Quote` VO hydrated from the stored `response_payload` (no upstream re-call). Sets `termsChanged: false` in the returned shape (the quote is the same).

#### `App\Domain\Pricing\Services\FeeResolverService`

Thin adapter wrapping the `ResolveFeeTier` CQRS query handler from `app/Infrastructure/QueryBus`. Never reads `config('pricing.tiers')` directly — always goes through the query handler (per ADR-0003 acceptance criterion: "Subscription-domain code never reads `config('pricing.tiers')` directly; goes through `ResolveFeeTier` query handler").

Entry point: `resolve(User $user): FeeTier`

Throws `App\Domain\Pricing\Exceptions\FeeResolverException` (caught by the controller to emit `ERR_FEE_001`) if the query handler returns null or throws.

Cached result TTL: 5 minutes (same as entitlements — busted on `subscription.changed` event).

#### `App\Domain\Pricing\Services\QuoteSigner`

Signs and verifies quotes using HMAC-SHA256 over a canonical form, keyed by `PRICING_QUOTE_PEPPER` env var.

Canonical form for signing: `"{id}|{user_id}|{kind}|{expires_at_unix}|{sha256_of_response_payload}"` — a pipe-delimited string. Deterministic, order-fixed, no JSON serialisation ambiguity.

Entry points:
- `sign(string $id, string $userId, string $kind, int $expiresAtUnix, string $responsePayloadHash): string` — returns 64-char hex HMAC
- `verify(string $id, string $userId, string $kind, int $expiresAtUnix, string $responsePayloadHash, string $storedSignature): bool` — constant-time comparison via `hash_equals()`

**Security note:** Uses `hash_hmac('sha256', $canonical, config('pricing.quote_pepper'))`. The pepper is an app-specific secret, NOT `config('app.key')` (which is the Laravel encryption key — using it for HMAC creates key reuse). Generate with `openssl rand -hex 32` and store as `PRICING_QUOTE_PEPPER` in `.env`.

This pattern mirrors `TrialFingerprintService`'s use of `TRIAL_FINGERPRINT_PEPPER` from slice 1.

> **Note — quote signature scheme (spec-author addition):** HMAC-SHA256 with `PRICING_QUOTE_PEPPER` is a spec-author addition. Source docs (Q2.2 + ADR-0003) define `userOpHash` as the chain-integrity check and `DB::transaction + lockForUpdate` as the race-protection mechanism. The HMAC adds a third layer protecting against DB-write-side abuse (a direct DB write could fabricate a `price_quotes` row without the HMAC passing verification at redemption time). Implementation may proceed with the HMAC **OR** drop it (using just `userOpHash` + DB locking) — this is a controller decision (see §8 OD-4). Recommend keeping for defense-in-depth but flagging in PR review.

#### `App\Domain\Pricing\Services\QuoteService` (public facade)

Exposes `create()`, `retrieve()`, and `redeem()` to the outside world (controllers, downstream slices). Wraps `PriceQuoteIssuer` for create, direct DB lookup for retrieve, and the redemption logic for redeem. Downstream slices (slice 1 checkout, slice 5 deposit) inject `QuoteService`.

### 5.7 Value objects (new in slice 3)

#### `App\Domain\Pricing\ValueObjects\Quote`

Final readonly VO. Carries the wire-formatted quote for serialisation by the controller. Does NOT extend or replace the `PriceQuote` Eloquent model. Constructed by `PriceQuoteIssuer` and returned to the controller.

Key properties: `id` (string UUID), `kind` (string), `expiresAt` (\DateTimeImmutable), `feeBreakdown` (array of line items), `rates` (array), `feeTier` (FeeTier VO), `userOpHash` (?string), `termsChanged` (bool), `saveWithPro` (?SaveWithProVO).

Implements `JsonSerializable` — returns the camelCase wire shape.

#### `App\Domain\Pricing\ValueObjects\FeeTier`

Final readonly VO. Carries fee-tier data resolved by `FeeResolverService`.

Key properties: `txFlat` (?Money), `swapMarginBps` (int), `rampMarginBps` (int).

> **Note (F-17):** No `id` property on the wire-facing `feeTier` object — neither commercial §1.2 nor deltas §Q1.2 amendment include `id` in the `feeTier` shape. The free/pro distinction is available from the `tier` field at the response root (if added) or from `swapMarginBps` value. The VO may carry `id` internally for logging, but it is NOT serialised into the quote response.

### 5.8 Quote refresh (Q2.3)

Quote refresh is triggered when mobile calls `POST /api/v1/pricing/quote` with the same intent but the prior quote has expired. The entity-key dedup only matches LIVE (unexpired) quotes. If the prior quote is expired, the issuer creates a fresh row with a new UUID and new `expires_at`.

The refresh flow:

1. Prior quote is expired (or never existed) for this entity-key
2. Issuer creates a new `price_quotes` row with a new UUID
3. If a prior expired row for this entity-key exists, set `prior_row.superseded_by = new_id`
4. Compare new `response_payload` to prior's `response_payload` for `terms_changed` (Q3.2 rules):
   - `terms_changed: true` iff: total displayed fee delta > €0.10 OR > 5% (whichever is larger), OR currency/network/route differs
   - Otherwise `terms_changed: false`
5. Return the new `Quote` VO with the `termsChanged` flag set

Mobile uses `termsChanged: true` to decide whether to re-prompt the user (biometric re-auth) or silently re-render the quote.

### 5.9 Expiry policy

Default TTLs per `kind`:

| Kind | TTL | Rationale |
|---|---|---|
| `send` | 5 minutes | Gas estimation drift is slow; Privy device-signing window is ~5min |
| `swap` | 30 seconds | AMM prices move fast; stale swap quote is dangerous |
| `ramp_buy` / `ramp_sell` | 60 seconds | Stripe Bridge quote window |
| `subscription_initial` | 60 minutes | Subscription price is stable; user may delay during consent flow |
| `card_waitlist_deposit` | 60 minutes | Flat €5 deposit; no rate drift |

TTLs are stored in `config/pricing.php` under `quote_ttl_seconds` keyed by kind, not hardcoded in the service. Example:

```php
'quote_ttl_seconds' => [
    'send'                  => 300,
    'swap'                  => 30,
    'ramp_buy'              => 60,
    'ramp_sell'             => 60,
    'subscription_initial'  => 3600,
    'card_waitlist_deposit' => 3600,
],
```

**Open question:** whether TTLs should be use-case-specific (recommended above) or a single global default. See §8.

### 5.10 Expiry purge cron

Add to `routes/console.php` (append-only — do not modify any existing schedule entries):

```php
Schedule::command('pricing:purge-quotes')->daily()->at('03:10');
```

The `pricing:purge-quotes` Artisan command deletes `price_quotes` rows where `expires_at < NOW() - INTERVAL 7 DAY`. The 7-day grace period allows post-expiry forensics (e.g., chain ingestor referencing `user_op_hash` after a slow chain confirmation). After 7 days, the row can safely be deleted.

This mirrors the `idempotency:purge` and `trial:purge-fingerprints` patterns from slice 1.

### 5.11 `config/pricing.php`

The new config file (renamed from `config/fees.php` per ADR-0003). Sample shape:

```php
return [
    'tiers' => [
        'free' => [
            'tx_flat_eur_cents'    => 20,              // €0.20  ← verify against commercial agreement before bake
            'tx_flat_asset_amount' => '1000000',       // 1.0 USDC (6 decimals)
            'swap_margin_bps'      => 20,
            'ramp_margin_bps'      => 100,
        ],
        'pro' => [
            'tx_flat_eur_cents'    => 5,               // €0.05
            'tx_flat_asset_amount' => '50000',         // 0.05 USDC
            'swap_margin_bps'      => 5,
            'ramp_margin_bps'      => 50,
        ],
    ],
    'quote_ttl_seconds' => [
        'send'                  => 300,
        'swap'                  => 30,
        'ramp_buy'              => 60,
        'ramp_sell'             => 60,
        'subscription_initial'  => 3600,
        'card_waitlist_deposit' => 3600,
    ],
    'quote_pepper_env_key' => 'PRICING_QUOTE_PEPPER',
    'rate_limit' => [
        'quotes_per_minute_per_user' => 60,
        'quotes_per_minute_per_ip'   => 600,
    ],
    // fee_collector addresses per chain — see ADR-0001
    'fee_collectors' => [
        'polygon'   => env('FEE_COLLECTOR_POLYGON', ''),
        'base'      => env('FEE_COLLECTOR_BASE', ''),
        'arbitrum'  => env('FEE_COLLECTOR_ARBITRUM', ''),
        'ethereum'  => env('FEE_COLLECTOR_ETHEREUM', ''),
        'solana'    => env('FEE_COLLECTOR_SOLANA', ''),
    ],
];
```

`Domain/Subscription` reads tiers via the `ResolveFeeTier` query handler — never via `config('pricing.tiers')` directly. The query handler is the only path.

> **Important:** The `tx_flat_eur_cents: 20` (€0.20) value above is sourced from commercial §10.2 + deltas Q4 sample. **Verify against the commercial agreement before baking into production.** The `pricing.php` config is the runtime source of truth — if the agreed rate differs, update the config without touching any migration or code.

### 5.12 Route registration

In `routes/api.php`, add under `auth:sanctum` middleware group:

```
POST   /api/v1/pricing/quote                   → PricingController@quote         (+ idempotency.required — DECIDED: apply per Q2.1; see §8 OD-1)
GET    /api/v1/pricing/quote/{quoteId}          → PricingController@show          (read-only; no idempotency middleware)
```

The `PricingController` lives in `app/Domain/Pricing/Http/Controllers/PricingController.php`.

---

## 6. Slice 3 — out of scope

The following are explicitly NOT part of this slice:

- **Slice 5 (card waitlist deposit, `POST /api/v1/cards/waitlist/deposit`)** — separate slice; receives the `quoteId` from slice 3 as input. Slice 3 must expose `QuoteService::redeem()` in a way slice 5 can call.
- **Cue queue infrastructure (slice 4)** — independent; slice 3 does not emit any cues.
- **`price_quote_events` Spatie event-sourcing table** — deferred to v1.3.1 (see §5.5 note). Slice 3 does not create this table.
- **Per-buyer dynamic pricing beyond fee-tier resolution** — no promo codes, A/B price testing, or personalised pricing in slice 3. Deferred to v1.4.
- **Multi-currency support beyond EUR** — Plan B v1.3.0 is EUR-only per `ERR_CUR_001`. The `currency` field in the request must equal `"EUR"`. Asset-denominated amounts (USDC, ETH) are computed and returned in the response, but the `currency: "EUR"` constraint on the request body is enforced.
- **`GET /api/v1/quotes` deprecation (RampController)** — this existing endpoint is left alone in v1.3.0. The v1.3.1 alias `GET /api/v1/pricing/ramp-quotes` is not part of slice 3 (per ADR-0003 deprecation plan).
- **Revenue events** — slice 3 does not write to `revenue_outbox_events` or `revenue_events`. Quote issuance is not revenue recognition (see §2 situation summary revenue boundary).
- **ADR-0001 fee-collector wallet setup** — the `fee_collectors` config keys are referenced but the wallet provisioning (KMS-backed EOA) is a separate ops concern.

---

## 7. Mobile contract

### `POST /api/v1/pricing/quote` — request (all kinds)

```json
POST /api/v1/pricing/quote HTTP/1.1
Authorization: Bearer <sanctum_token>
Content-Type: application/json
Idempotency-Key: <required — enforced by idempotency.required middleware per Q2.1 decision; see §8 OD-1>

{
  "kind": "send",
  "amount": { "amount": "1000000", "decimals": 6, "asset": "USDC" },
  "from": { "asset": "USDC", "network": "polygon" },
  "to":   { "asset": "USDC", "network": "base" },
  "recipient": "0xabc...",
  "currency": "EUR"
}
```

### `POST /api/v1/pricing/quote` — 200 response (send example)

```json
HTTP/1.1 200 OK
Content-Type: application/json

{
  "quoteId": "qte_01HQ...",
  "kind": "send",
  "expiresAt": "2026-05-10T14:32:00Z",
  "feeBreakdown": [
    {
      "label": "service",
      "amount":        { "amount": "1000000", "decimals": 6, "asset": "USDC" },
      "eurEquivalent": { "amount": "92",      "decimals": 2, "currency": "EUR" }
    },
    {
      "label": "network",
      "amount":        { "amount": "210000",  "decimals": 6, "asset": "USDC" },
      "eurEquivalent": { "amount": "19",      "decimals": 2, "currency": "EUR" }
    }
  ],
  "rates": {
    "USDC/EUR": {
      "value": "0.92",
      "decimals": 4,
      "timestamp": "2026-05-10T14:31:55Z",
      "sourceId": "oracle:chainlink:USDC-EUR:0xabc..."
    }
  },
  "feeTier": {
    "txFlat": { "amount": "1000000", "decimals": 6, "asset": "USDC" },
    "swapMarginBps": 50,
    "rampMarginBps": 100
  },
  "userOpHash": "0x1a2b3c...",
  "termsChanged": false,
  "saveWithPro": {
    "amount":        { "amount": "500000", "decimals": 6, "asset": "USDC" },
    "eurEquivalent": { "amount": "46",     "decimals": 2, "currency": "EUR" },
    "label": "Save with Pro"
  }
}
```

### `POST /api/v1/pricing/quote` — 200 response (subscription_initial example)

```json
{
  "quoteId": "qte_01HQ...",
  "kind": "subscription_initial",
  "expiresAt": "2026-05-10T15:32:00Z",
  "feeBreakdown": [
    {
      "label": "subscription",
      "amount":        { "amount": "499", "decimals": 2, "currency": "EUR" },
      "eurEquivalent": { "amount": "499", "decimals": 2, "currency": "EUR" }
    }
  ],
  "rates": {},
  "feeTier": {
    "txFlat": null,
    "swapMarginBps": 50,
    "rampMarginBps": 100
  },
  "userOpHash": null,
  "termsChanged": false,
  "saveWithPro": null
}
```

### Error responses mobile must handle

```json
// ERR_FEE_001 — fee tier resolver failure (500)
HTTP/1.1 500 Internal Server Error
{
  "error": {
    "code": "ERR_FEE_001",
    "message": "Fee tier could not be resolved."
  }
}

// ERR_QUOTE_001 — expired quote (on redemption by /submit or /checkout)
HTTP/1.1 410 Gone
{
  "error": {
    "code": "ERR_QUOTE_001",
    "message": "Quote expired.",
    "expiresAt": "2026-05-10T14:32:00Z"
  }
}

// ERR_QUOTE_002 — payload mismatch (on redemption by /submit)
HTTP/1.1 409 Conflict
{
  "error": {
    "code": "ERR_QUOTE_002",
    "message": "Submitted payload does not match quoted userOp hash."
  }
}

// ERR_VALIDATION_002 — malformed Money field
HTTP/1.1 422 Unprocessable Entity
{
  "error": {
    "code": "ERR_VALIDATION_002",
    "message": "Money field is malformed.",
    "field": "amount"
  }
}

// ERR_QUO_006 — KYC required for ramp
HTTP/1.1 403 Forbidden
{
  "error": {
    "code": "ERR_QUO_006",
    "message": "KYC level insufficient for ramp operations.",
    "requiresKycLevel": "basic"
  }
}

// ERR_QUO_007 — blocked destination
HTTP/1.1 403 Forbidden
{
  "error": {
    "code": "ERR_QUO_007",
    "message": "Destination address is blocked."
  }
}

// ERR_QUO_008 — insufficient balance (swap)
HTTP/1.1 400 Bad Request
{
  "error": {
    "code": "ERR_QUO_008",
    "message": "Insufficient balance for swap source asset."
  }
}

// ERR_QUO_005 — rate limit (DECIDED: ERR_QUO_005 per Appendix B convention)
HTTP/1.1 429 Too Many Requests
Retry-After: 12
{
  "error": {
    "code": "ERR_QUO_005",
    "message": "Quote rate limit exceeded. Try again shortly."
  }
}

// ERR_CUR_001 — non-EUR currency
HTTP/1.1 400 Bad Request
{
  "error": {
    "code": "ERR_CUR_001",
    "message": "Currency must be EUR in v1.3.0."
  }
}
```

### How mobile uses the quote in downstream submissions

```json
// POST /api/v1/wallet/transactions/submit — send kind
{
  "quoteId": "qte_01HQ...",
  "signedUserOp": { "...": "..." }
}

// POST /api/v1/subscription/checkout — subscription_initial kind (quoteId optional in v1.3.0)
{
  "quoteId": "qte_01HQ...",
  "plan": "monthly_pro",
  "withdrawalConsent": { "..." }
}

// POST /api/v1/cards/waitlist/deposit — card_waitlist_deposit kind (slice 5; quoteId required)
{
  "quoteId": "qte_01HQ...",
  "currency": "EUR"
}
```

---

## 8. Open design decisions

### OD-1 — Caller-supplied `Idempotency-Key` on `POST /pricing/quote` — **DECIDED**

**Context:** Q2.1 mandates backend-computed entity-key dedup for quote issuance. Q2.1 also states "Backend computes **BOTH** keys: HTTP-layer idempotency from header (mobile provides; standard pattern), and entity-layer dedup (backend-computed SHA256). Both coexist." Additionally, the acceptance criterion from Q2.1 requires "Every POST/PATCH/DELETE mutating endpoint in routes/api.php enforces Idempotency-Key header presence."

**Decision: APPLY `idempotency.required` middleware AND compute entity-key dedup. Both layers coexist per Q2.1.**

The two mechanisms serve different purposes:
- `idempotency.required` middleware: enforces header presence and 24h HTTP-layer replay protection
- Entity-key dedup: backend-computed SHA256 that deduplicates same-intent quotes regardless of the Idempotency-Key value

Processing order in the request lifecycle:
1. `idempotency.required` middleware checks `Idempotency-Key` header presence and format — returns `ERR_VALIDATION_001` / `ERR_VALIDATION_003` if absent or malformed
2. Controller invokes `PriceQuoteIssuer::issue()` which computes the entity-key and checks for a live matching quote
3. If entity-key match found: return existing quote (idempotency at the business-logic layer)
4. If no match: create new quote, persist, return

This means mobile must send an `Idempotency-Key` header on every `POST /pricing/quote` request, consistent with other mutating endpoints.

### OD-2 — TTL: single default or per-kind

**Context:** swap quotes (30s) and subscription quotes (60min) have very different lifetimes. A single default would either expire subscription quotes too fast or leave swap quotes live too long.

**Options:**
- **(α) Per-kind TTLs** (recommended). Values in §5.9. Stored in `config/pricing.php` under `quote_ttl_seconds`. Flexible and explicit.
- **(β) Single 15-minute default.** Simpler config. Swap quotes are dangerously stale at 15 minutes; subscription quotes are unnecessarily short.

**Recommended:** (α) per-kind TTLs per §5.9. Swap = 30s, send = 5min, ramp = 60s, subscription/deposit = 60min.

**This is a recommend-only decision — implementation should proceed with (α) unless the controller overrides.**

### OD-3 — `GET /api/v1/pricing/quote/{quoteId}` in slice 3 vs deferred

**Context:** A read endpoint allows mobile to replay the quote by ID without re-computing it. It also enables slice 5 (card deposit) to validate the quote before building the checkout UI.

**Options:**
- **(α) Include in slice 3** (recommended). The `price_quotes` table exists; the service layer is being built. Adding a GET endpoint is a 0.25d incremental. Protects against `quoteId` enumeration by gating on `$quote->user_id === $user->id`.
- **(β) Defer to slice 5.** Less code in slice 3; slice 5 can add it if needed.

**Recommended:** (α) include in slice 3. Slice 5 will need to read quotes to validate the `quoteId` input anyway. Building the read path in slice 3 avoids a cross-slice dependency.

**Controller decision required; this decision affects §5.2.**

### OD-4 — Quote signature scheme

**Context:** The `signature` column in `price_quotes` provides tamper-evidence if a DB row is modified directly. This is a spec-author addition (see §5.6 note). Source docs (Q2.2 + ADR-0003) define `userOpHash` as the chain-integrity check and `DB::transaction + lockForUpdate` as the race-protection mechanism. The HMAC is a third layer.

**Options:**
- **(α) HMAC-SHA256 over canonical form using `PRICING_QUOTE_PEPPER`** (recommended). Matches `TrialFingerprintService`'s `TRIAL_FINGERPRINT_PEPPER` pattern from slice 1. Prevents a compromised DB write from fabricating a valid quote without the pepper. One new env var required.
- **(β) Drop the HMAC; rely on `userOpHash` + DB locking.** Simpler. Source docs only require `userOpHash` (Q2.2) and `lockForUpdate` (Q2.1). If DB access is fully controlled, the HMAC adds overhead for little gain. No `PRICING_QUOTE_PEPPER` env var needed; remove `signature` column from migration.

**Recommended:** (α) for defense-in-depth. The env-var overhead is minimal, the pattern has precedent in the codebase, and it protects against the insider-threat vector. Flagging in PR review is recommended regardless.

**This is a recommend-only decision — (α) is the default implementation path. Controller may select (β) to simplify.**

### OD-5 — Rate limit error code: `ERR_QUO_429` vs `ERR_QUO_005` — **DECIDED**

**Decision: `ERR_QUO_005`** per Appendix B convention.

`ERR_QUO_005` matches the commercial spec Appendix B exactly and is consistent with the ERR-code naming convention (sequential domain-local IDs, not HTTP statuses). The registry entry's `http: 429` field communicates the HTTP status to consumers. All references to `ERR_QUO_429` in this spec are replaced with `ERR_QUO_005`.

### OD-6 — `quoteId` required vs optional on `POST /subscription/checkout` in v1.3.0

**Context:** Slice 1 accepts `quoteId` as optional (no-op). Should v1.3.0 make `quoteId` required on checkout, or keep it optional for back-compat?

**Options:**
- **(α) Keep optional in v1.3.0; make required in v1.3.1.** Preserves back-compat for any client that calls checkout without a quote.
- **(β) Make required in v1.3.0.** Forces mobile to always request a quote before checkout. Cleaner price-locking guarantee.

**Recommended:** (α) keep optional in v1.3.0. Slice 1 is already merged and accepted with `quoteId` as optional. Making it required in the same release creates a dependency ordering issue (slice 3 must be deployed before any client can checkout). When mobile confirms week-0 integration, make required in v1.3.1.

**This is a recommend-only decision — proceed with (α) unless controller explicitly requires (β).**

---

## 9. Acceptance criteria

An implementation is complete when every item below passes.

### `POST /api/v1/pricing/quote` endpoint

- [ ] Returns `200` with correct `quoteId`, `feeBreakdown`, `feeTier`, `expiresAt` for `kind: "send"` with USDC on Polygon
- [ ] Returns `200` for `kind: "swap"` with correct margins applied
- [ ] Returns `200` for `kind: "ramp_buy"` with EUR-denominated fees and empty `rates`
- [ ] Returns `200` for `kind: "subscription_initial"` with flat subscription fee breakdown
- [ ] Returns `200` for `kind: "card_waitlist_deposit"` with flat €5 breakdown
- [ ] `saveWithPro` is present for free-tier users and absent (null) for Pro-tier users
- [ ] Native-asset send (`kind: "send"`, `from.asset: "ETH"`) returns no `"service"` fee item in `feeBreakdown`
- [ ] Entity-key dedup: same intent twice within TTL returns same `quoteId` without creating a new row
- [ ] Entity-key dedup: expired quote for same intent creates a new quote row with `superseded_by` populated on the expired row
- [ ] `termsChanged: true` returned on refresh when fee delta > €0.10 or > 5%
- [ ] `termsChanged: false` returned when fee delta is within threshold
- [ ] `?dryRun=true` query parameter request returns `quoteId: null`, does not create a `price_quotes` row
- [ ] `idempotency.required` middleware is applied to `POST /api/v1/pricing/quote` — missing `Idempotency-Key` header returns `ERR_VALIDATION_001` (422)
- [ ] Rate limit: 61st request from same user within 60s returns `429` with `Retry-After` header and `ERR_QUO_005`
- [ ] Returns `ERR_FEE_001` when `ResolveFeeTier` query handler throws
- [ ] Returns `ERR_VALIDATION_002` when `amount.amount` contains a decimal point
- [ ] Returns `ERR_CUR_001` when `currency` field is not `"EUR"`
- [ ] Returns `ERR_QUO_006` when `kind: "ramp_buy"` and user has no KYC
- [ ] Returns `ERR_QUO_007` when `recipient` address is on GoPlus blocklist
- [ ] Returns `ERR_QUO_008` when `kind: "swap"` and source balance is insufficient
- [ ] All Money fields in response are `{amount, decimals, currency}` or `{amount, decimals, asset}` — never decimal-string format

### `price_quotes` table

- [ ] Migration creates the table with all columns per §5.5
- [ ] UUID `id` is RFC 4122 v4 (MariaDB does not reject it)
- [ ] `user_op_hash` UNIQUE constraint prevents two active quotes with the same hash
- [ ] `idx_price_quotes_entity_live` composite index is present

### Quote signing

- [ ] `QuoteSigner::sign()` returns a 64-char hex string
- [ ] `QuoteSigner::verify()` returns `true` for a valid signature
- [ ] `QuoteSigner::verify()` returns `false` for a tampered signature
- [ ] `verify()` uses `hash_equals()` (constant-time — prevents timing attacks)

### Quote redemption

- [ ] `QuoteService::redeem()` marks `consumed_at` and `consumed_by` inside `DB::transaction()` with `lockForUpdate()`
- [ ] Consumed-quote re-submission returns `ERR_QUO_002` (409) — "Quote already consumed" (NOT `ERR_QUOTE_002`)
- [ ] `POST /api/v1/wallet/transactions/submit` with mismatched `userOp` hash returns `ERR_QUOTE_002` (409) — "Submitted payload does not match quote" (NOT `ERR_QUO_002`)
- [ ] Concurrent redemption calls (same `quoteId`, two racing requests): only one succeeds; the other receives `ERR_QUO_002` (409)
- [ ] Expired-quote redemption returns `ERR_QUOTE_001` (410)
- [ ] `POST /api/v1/subscription/checkout` with a valid `quoteId` succeeds (quote is consumed)
- [ ] `POST /api/v1/subscription/checkout` without `quoteId` continues to succeed (back-compat)

### `GET /api/v1/pricing/quote/{quoteId}` (if included per OD-3)

- [ ] Returns `200` with quote details for the authenticated user's own quote
- [ ] Returns `ERR_QUO_001` (404) for a quote belonging to another user
- [ ] Returns `ERR_QUO_001` (404) for a non-existent `quoteId`
- [ ] `status` field accurately reflects `"active"` | `"expired"` | `"consumed"` | `"superseded"`

### Expiry purge cron

- [ ] `php artisan pricing:purge-quotes` deletes rows where `expires_at < NOW() - INTERVAL 7 DAY`
- [ ] `php artisan schedule:list` shows `pricing:purge-quotes` daily at 03:10

### Config and env

- [ ] `config/pricing.php` exists with `tiers`, `quote_ttl_seconds`, `rate_limit`, `fee_collectors` keys
- [ ] `PRICING_QUOTE_PEPPER` is documented in `.env.production.example` and `.env.zelta.example`
- [ ] Code comment in `.env.production.example` explains the key is used for HMAC signing and cannot be rotated without invalidating all live quotes

### Error code registry

- [ ] `config/error_codes.php` contains `ERR_QUO_001` (404, quote not found), `ERR_QUO_005` (429, rate limit — **DECIDED**), `ERR_QUO_006` (403, KYC required), `ERR_QUO_007` (403, address blocked), `ERR_QUO_008` (400, insufficient balance). `ERR_QUO_002` (409, already consumed) is registered in the companion hotfix PR — do not re-register here.

### Revenue boundary

- [ ] No `revenue_outbox_events` rows are written by any slice 3 code path
- [ ] No `revenue_events` rows are written by any slice 3 code path
- [ ] `price_quotes.user_op_hash` is stored for `send`/`swap` kinds (available for chain ingestor per ADR-0002)

### Quality gates

- [ ] `./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php` — zero violations
- [ ] `XDEBUG_MODE=off vendor/bin/phpstan analyse --memory-limit=2G` — zero new errors (level 8)
- [ ] `./vendor/bin/pest --parallel --stop-on-failure` — all existing + new tests pass
- [ ] `./vendor/bin/phpcs --standard=PSR12 app/Domain/Pricing/` — zero violations

---

## 10. Conventions

All conventions from `CLAUDE.md` apply. Reproduced here for convenience.

### PHP file header

```php
<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Services;
```

### Import order

`App\Domain` → `App\Http` → `App\Models` → `Illuminate` → Third-party

### Money

- API wire: `{"amount": "499", "decimals": 2, "currency": "EUR"}` or `{"amount": "1000000", "decimals": 6, "asset": "USDC"}` per ADR-0004
- Math: `bcmath` exclusively. Never `(float)` cast. Normalize with `bcadd($val, '0', 4)`.
- Rate application: `bcmul($amount, $rate, $scale)` — never `(float)($amount * $rate)`
- EUR equivalent: `bcmul($assetAmount, $eurRate, 2)` where `$assetAmount` and `$eurRate` are both `numeric-string`

### DB transactions

- Wrap `price_quotes` write (INSERT) in `DB::transaction()` — protects against entity-key race conditions
- `QuoteService::redeem()`: `DB::transaction()` + `lockForUpdate()` on the quote row
- `price_quotes` is on the **global** connection — no `UsesTenantConnection` models are touched. `DB::transaction()` is safe; the CLAUDE.md multi-connection rule does not apply.
- The multi-connection rule DOES apply if a downstream caller (e.g., `PrepareTransaction` in `Domain/Wallet`) combines quote redemption with a tenant-connection write. That caller must use the saga pattern; do NOT wrap it in a single `DB::transaction()` that spans both connections.

### Multi-connection safety note

Add a comment in `QuoteService::redeem()` making the global-connection assertion explicit:

```php
// price_quotes is on the default (global) connection — no UsesTenantConnection
// models involved. DB::transaction() is safe here.
// Callers that also write to tenant-connection models (Domain/Wallet) MUST
// use the saga pattern: redeem inside its own transaction, then dispatch a
// queued job for the tenant write.
```

### Wire contract

- camelCase JSON keys: `quoteId`, `expiresAt`, `feeBreakdown`, `feeTier`, `userOpHash`, `termsChanged`, `saveWithPro`, `eurEquivalent`, `swapMarginBps`, `rampMarginBps`
- `quoteId` on the wire; `price_quote_id` in DB columns and PHP FK references (ADR-0003 asymmetry)
- Error codes on the wire: `ERR_QUOTE_001`, `ERR_QUOTE_002` (per ADR-0003 — preserved for mobile compat)

### Auth guard

```php
$user = $request->user();
if (! $user instanceof User) {
    return response()->json(['error' => ['code' => 'UNAUTHENTICATED']], 401);
}
```

Never `assert($user instanceof User)` — compiled out with `zend.assertions=-1`.

### Sanctum abilities

All tests: `Sanctum::actingAs($user, ['read', 'write', 'delete'])`.

### Webhook auth bypass

Not applicable to slice 3 (no webhook endpoints). The `idempotency.required` middleware does not have an environment bypass.

### Error emission

```php
return ErrorResponse::make('ERR_FEE_001');          // 500 from registry
return ErrorResponse::make('ERR_QUOTE_001');         // 410 from registry
```

Never hardcode HTTP status codes in controllers — read from `config/error_codes.php` via `ErrorResponse::make()`.

### UUID generation

```php
$id = (string) \Illuminate\Support\Str::uuid();     // RFC 4122 v4
```

### Redis rate limiting

```php
// Correct (per CLAUDE.md — never read-then-write):
Cache::add($key, 0, $ttl);
$count = Cache::increment($key);
if ($count > $limit) { /* rate limit exceeded */ }
```

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
./vendor/bin/phpcs --standard=PSR12 app/Domain/Pricing/
```

All four must pass clean before creating the PR.

PHPStan-specific notes for slice 3:

- `bcmath` functions require `numeric-string` type. Use `bcadd($val, '0', 4)` to normalize inputs that come from the request (validated as string but not typed as `numeric-string` by PHPStan). The `Money` VO's `numericAmount()` method already handles this pattern — reuse it.
- `json_encode()` returns `string|false`. Cast: `(string) json_encode(...)` or use `JSON_THROW_ON_ERROR`.
- `->first()` on Eloquent queries returns nullable. Use `assert($quote instanceof PriceQuote)` ONLY after an `if ($quote === null)` guard — never as the primary guard.
- `$request->user()` returns `Authenticatable|null`. Cast via `instanceof User` guard.

---

## 12. Workflow expectations

Suggested commit topology (each commit is atomic + passing quality gates):

```
feat(pricing): create price_quotes migration
feat(pricing): config/pricing.php — tiers, TTLs, rate limits, fee collectors
feat(pricing): FeeTier VO + FeeResolverService (wraps ResolveFeeTier query handler)
feat(pricing): QuoteSigner — HMAC-SHA256 sign/verify with PRICING_QUOTE_PEPPER
feat(pricing): Quote VO + PriceQuoteIssuer orchestrating service
feat(pricing): QuoteService — create/retrieve/redeem public facade
feat(pricing): PricingController + QuoteRequest + GET/POST routes
feat(pricing): pricing:purge-quotes command + daily cron at 03:10
feat(pricing): ERR_QUO_001/005/006/007/008 registered in config/error_codes.php
feat(pricing): PRICING_QUOTE_PEPPER in .env.production.example + .env.zelta.example
feat(pricing): CheckoutRequest::rules() — add quoteId nullable string (slice 3 scope)
feat(pricing): slice 1 checkout QuoteService::redeem() integration (quoteId optional)
test(pricing): feature tests for /pricing/quote — entity-key dedup, TTLs, rate limit
test(pricing): feature tests for redemption — lockForUpdate race, expiry, hash mismatch
test(pricing): unit tests for QuoteSigner sign/verify, FeeTier VO, Money arithmetic
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
Branch: feat/plan-b-slice-3-pricing-quote
PR URL: <url>
Quality gates:
  php-cs-fixer: PASS | FAIL
  PHPStan L8:   PASS | FAIL (N errors)
  Pest:         PASS | FAIL (N failed / N total)
  PHPCS:        PASS | FAIL
Files changed: <list>
New migrations: <list>
New error codes registered: <list>
Open questions resolved pre-implementation: OD-1 (DECIDED: apply idempotency middleware), OD-5 (DECIDED: ERR_QUO_005)
Open questions requiring controller decision before start: OD-3
Open questions unresolved: <any from §8 not yet answered>
Concerns: <any design compromise made under time pressure>
Revenue boundary verified: yes/no (no revenue_* rows written)
```

---

## 14. Estimated effort

**4–5 engineer-days** (revised from 3–4d — `CheckoutRequest` expansion adds 0.5d).

Breakdown:

| Task | Days |
|---|---|
| `price_quotes` migration | 0.25 |
| `config/pricing.php` — tiers, TTLs, rate limits | 0.25 |
| `FeeTier` VO + `FeeResolverService` adapter | 0.25 |
| `QuoteSigner` — HMAC sign/verify | 0.25 |
| `Quote` VO + `PriceQuoteIssuer` — kind-dispatch, entity-key dedup, Q2.3 refresh | 0.75 |
| `QuoteService` — create/retrieve/redeem facade | 0.5 |
| `PricingController` + `QuoteRequest` + route registration | 0.5 |
| `pricing:purge-quotes` command + cron | 0.25 |
| Error codes in `config/error_codes.php` | 0.1 |
| Env vars in `.env.production.example` + `.env.zelta.example` | 0.1 |
| `CheckoutRequest::rules()` extension — add `quoteId` nullable string (slice 3 scope) | 0.25 |
| Slice 1 `checkout` `QuoteService::redeem()` integration (optional redemption call) | 0.25 |
| Feature + unit tests (§9 acceptance criteria coverage) | 0.75 |
| **Total** | **~4.7d** |

Compared to slice 2 (5–7d, two store SDKs + pseudonymisation), slice 3 is smaller because:
- The `Money` VO, `ErrorResponse`, and `IdempotencyKey` middleware are already built
- `ERR_QUOTE_001`, `ERR_QUOTE_002`, `ERR_FEE_001` are already registered
- The `HMAC-PEPPER` signing pattern is already established by slice 1's `TrialFingerprintService`
- No third-party store SDK integration is required
- No erasure / pseudonymisation logic

The main complexity is the kind-dispatch logic in `PriceQuoteIssuer` (each `kind` calls a different upstream service), the Q2.1 entity-key dedup semantics, and the Q3.2 `terms_changed` computation.

---

## 15. Coordination items

These are actions the human controller must complete before or during implementation.

### Before implementation starts (controller decisions required)

- [x] **OD-1 — Idempotency-Key on `/pricing/quote`:** **DECIDED** — apply `idempotency.required` middleware AND entity-key dedup (both coexist per Q2.1). See §8 OD-1.
- [ ] **OD-3 — `GET /pricing/quote/{quoteId}` in slice 3:** Confirm whether to include the read endpoint in slice 3 (recommend: yes — see §8 OD-3).
- [x] **OD-5 — Rate limit error code:** **DECIDED** — `ERR_QUO_005` per Appendix B convention. See §8 OD-5.

### Open coordination items

- [ ] **Deltas Q3.1 amendment — ratify `subscription_initial` + `card_waitlist_deposit` `kind` values:** This slice introduces two `kind` values beyond the Q3.1 enumeration. Coordinate with the deltas-doc maintainer before v1.3.1 to formally ratify these values in the deltas document. Implementation proceeds in v1.3.0 with this caveat documented. See §5.3 extended kind note.

### Before staging deploy (controller must action)

- [ ] **Generate `PRICING_QUOTE_PEPPER`:** run `openssl rand -hex 32` and store the value securely. Add to `config('pricing.quote_pepper')` env chain.
- [ ] **Note rotation caveat:** the pepper cannot be rotated without invalidating all live `price_quotes` rows. Live quotes will fail signature verification after rotation. Coordinate rotation with a `pricing:purge-quotes --force-all` run or a quote-TTL drain window.

### After merge — before going live

- [ ] Run `php artisan migrate` in staging then production (`create_price_quotes_table` migration)
- [ ] Set `PRICING_QUOTE_PEPPER` in production env (`php artisan config:cache` after setting)
- [ ] Verify `php artisan schedule:list` shows `pricing:purge-quotes` daily at 03:10
- [ ] Confirm `config/pricing.php` fee-tier amounts match the commercial agreed rates (monthly Pro = €4.99/month; free tx-flat = €1.00; Pro tx-flat = €0.05 — verify against business decision)
- [ ] Confirm `POST /api/v1/pricing/quote` with `kind: "subscription_initial"` returns the correct subscription price for the current `monthly_pro` and `annual_pro` plan IDs

### Third-party setup required

None. Unlike slice 2 (App Store Connect, Google Play Console, service accounts), slice 3 has no external provider setup. The fee-collector addresses referenced in `config/pricing.php` come from ADR-0001's KMS-backed EOA provisioning (a separate ops concern, not a slice 3 dependency).

### Next step

Once this spec merges and OD-1 / OD-3 / OD-5 are answered by the controller, the implementation agent uses this file as the direct input prompt. The agent should start at §2 (situation summary) and §3 (read before writing code), then implement the scope from §5, validate against §9, and report via §13.

---

*Document traceability:*
- `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md` — Backend-Q2 (quote contract, entity-key, userOpHash, Q2.1-Q2.3), Backend-Q3 (kind-dependent shapes Q3.1-Q3.3), Backend-Q4 (Domain/Pricing naming), Backend-Q9 (Money format)
- `docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §2 (fee resolver), §3 (quote endpoint), §6 (card waitlist — slice 5 downstream)
- `docs/adr/0002-revenue-projection-dual-upstream.md` — `price_quotes.user_op_hash` is the ingestor join key; slice 3 does NOT write revenue events
- `docs/adr/0003-pricing-bounded-context.md` — wire-vs-internal naming (`quoteId`/`price_quote_id`), `price_quotes` schema, `PriceQuoteIssuer` orchestration pattern
- `docs/adr/0004-money-on-the-wire.md` — every Money field is a triple; no decimal strings; `ERR_VALIDATION_002` rejects malformed inputs
