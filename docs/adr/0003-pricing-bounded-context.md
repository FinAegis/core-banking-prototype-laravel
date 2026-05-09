# ADR-0003 — Pricing bounded context: stateful committed pricing, distinct from upstream Quote VOs

**Status:** Accepted
**Date:** 2026-05-09
**Decision-maker:** Backend engineer
**Related:** Backend-Q4 in `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md`; original handover §3 (Universal quote endpoint); ADR-0001 (fee collectors); ADR-0002 (revenue projection); ADR-0004 (Money on the wire)

---

## Context

Plan B Commercial v1.3.0 originally specified a new bounded context `Domain/Quote` with model `Quote`, aggregate `QuoteAggregate`, table `quotes`, and endpoint `POST /api/v1/quote` for universal user-facing pricing across send/swap/ramp_buy/ramp_sell.

The codebase already has six existing `Quote`-related types across other domains:

| Domain | Class | Type | Purpose |
|---|---|---|---|
| `CrossChain` | `BridgeQuote` | VO | Cross-chain bridge price snapshot |
| `CrossChain` | `CrossChainSwapQuote` | VO | Cross-chain swap snapshot |
| `DeFi` | `SwapQuote` | VO | Uniswap/Curve quote |
| `Exchange` | `ExchangeRateQuote` | VO | FX rate snapshot |
| `Exchange` | `QuotesUpdated` | Event | Spatie event-sourced |
| `Interledger` | `QuoteService` | Service | ILP cross-currency |

Plus existing routes: `POST /api/v1/defi/swap/quote`, `POST /api/v1/crosschain/bridge/quote`, `POST /api/v1/interledger/quotes`, `POST /api/v1/mobile/wallet/quote`, `GET /api/v1/quotes` (RampController), and others.

A new `Domain/Quote` with namespace-only differentiation would be the seventh `Quote`-anything in the codebase. Every `grep "Quote"` would return 50+ unrelated hits. Every conversation about "the quote service" would need disambiguation. Mobile devs would confuse `POST /api/v1/quote` (the universal endpoint) with `POST /api/v1/defi/swap/quote` (DeFi-specific).

The deeper issue: the existing `Quote`-named VOs are **stateless market snapshots** — sourced from external markets, valid for seconds, no consumer-side state. The new domain represents **stateful committed pricing** — replay-protected (deltas Q2.2's userOp hash binding), tier-aware (free vs Pro), with a `consumed_at` lifecycle that outlives the source market quote (a 5-minute send window encompasses many refreshed upstream Uniswap quotes). Different concept. Different lifecycle. Different responsibility. Different word.

---

## Decision

**`Domain/Pricing` is the new bounded context.** Aggregate `PriceQuote`, table `price_quotes`, internal references `price_quote_id`. The wire format and error codes preserve `quoteId` and `ERR_QUOTE_*` to avoid forcing mobile / SDK consumer changes for zero functional benefit — but the URL path moves to `POST /api/v1/pricing/quote` to lock the bounded-context home for future related endpoints.

**Wire-vs-internal naming asymmetry is intentional and documented.**

- **Internal** (DB columns, PHP foreign keys, aggregate names, namespaces): `price_quote_id`, `App\Domain\Pricing\Aggregates\PriceQuoteAggregate`, `price_quotes` table.
- **Wire** (JSON request/response payloads, error codes): `quoteId` field, `ERR_QUOTE_001` / `ERR_QUOTE_002` codes.

The wire contract is what mobile / SDK consumers / external integrators see. They already use `quoteId` and `ERR_QUOTE_*` per deltas Q2 mobile review. Renaming forces ~30 mobile-side string changes for zero functional benefit. Internal naming is what backend engineers see; that gets to be precise.

URLs are the load-bearing wire-rename: `POST /api/v1/quote` → `POST /api/v1/pricing/quote`. Locking the namespace prefix now is cheap (no production traffic on the new endpoint yet); locking it after launch is expensive.

---

## Alternatives considered

### (α) Spec-as-written: `Domain/Quote`, `quotes` table, `POST /api/v1/quote`

What the original handover specified.

**Rejected because:** seventh `Quote` namespace in the codebase. Every backend search for "Quote" returns mixed hits. Mobile devs guaranteed to confuse `POST /quote` (universal) with `POST /defi/swap/quote` (DeFi). Singular-vs-plural URL collision (`POST /quote` vs existing `GET /quotes` for RampController). Naming the new domain after the most-overloaded noun in the codebase is a bug-magnet.

### (β) Embed inside an existing domain

Stuff the universal-pricing logic into `Domain/Subscription` (because fee tier is the gating factor) or `Domain/Wallet` (because 2/4 kinds are wallet operations).

**Rejected because:** this isn't subscription-domain logic — it's pricing logic. It isn't wallet-only either — swap and ramp flows aren't wallet-bounded. The bounded context is real; embedding it inside another collapses the abstraction.

### (γ) New bounded context `Domain/Pricing` — **chosen**

Aggregate `PriceQuote`, internal naming precise, wire naming preserves `quoteId` and `ERR_QUOTE_*` for mobile contract stability.

**Pros:**
- Zero name collision with any existing `Quote` type.
- Semantically accurate: this domain wraps upstream quote services and adds Zelta's tier-aware service fee. Existing domains are about UPSTREAM quotes; this domain is about USER-FACING pricing.
- The wire path `POST /api/v1/pricing/quote` is unambiguously distinct from `POST /api/v1/exchange/swap/quote` etc., signaling to mobile that this is the universal entry point.
- `PriceQuote` aggregate name avoids conflict with all six existing types.
- Wire-vs-internal asymmetry preserves mobile contracts without making backend code use the overloaded "quote" noun internally.

**Cons:** ~30 string-replacements across the spec docs (already done in the deltas Backend-Q4 section). One-line URL change required from mobile (`/api/v1/quote` → `/api/v1/pricing/quote`).

---

## Consequences

### Positive

- **No name collision.** Backend engineers searching for "Pricing" find Pricing-domain code; searching for "Quote" finds the existing market-snapshot VOs. Each noun has a clear home.
- **Wire stability for mobile.** Mobile already uses `quoteId` on submission contracts, replay protection, refresh logic, error responses, and telemetry events per deltas Q2/Q3. Preserving this avoids ~30 mobile-side string changes for zero functional benefit.
- **URL prefix locks future home.** `/api/v1/pricing/*` becomes the canonical home for related endpoints — `/pricing/fee-tiers` for admin, `/pricing/savings-with-pro` if that ever surfaces directly, `/pricing/quotes/{id}` for read-back. Locking the prefix pre-shipment is cheap; locking it post-shipment is expensive.
- **Dependency direction is clear.** `Pricing` calls into `Domain/Exchange/QuoteService`, `Domain/DeFi/SwapQuoteService`, `Domain/Ramp/SessionFactory`, etc. — never the other way. Existing domains are downstream of `Pricing` from the user's perspective.

### Negative

- **Wire-vs-internal asymmetry is a real cognitive cost.** A future engineer reading `quoteId` on the wire and `price_quote_id` in DB columns has to know they're the same thing. Mitigated by this ADR being discoverable via grep + the deltas Backend-Q4 section being explicit about the convention.
- **Existing `GET /api/v1/quotes` (RampController) is left in production.** Renaming it is a mobile-breaking change. v1.3.0 keeps both endpoints; v1.3.1 deprecates the old one with an alias.
- **One-time mobile coordination.** Mobile changes `idempotentPost('/api/v1/quote', ...)` to `idempotentPost('/api/v1/pricing/quote', ...)`. One-line change, but requires coordination with the mobile week-0 spike.

### Neutral

- **Error code prefix.** `ERR_QUOTE_*` stays despite the domain being named `Pricing`. The QUOTE prefix on the wire refers to the `PriceQuote` user-facing concept, not the upstream market-snapshot Quote VOs. Future error codes for Pricing-domain features (e.g., savings calculator, fee-tier admin) use new prefixes (`ERR_SAVINGS_*`, `ERR_FEE_*`).
- **`config/fees.php` → `config/pricing.php`.** Fee-tier definitions belong in the domain that owns pricing logic. `Domain/Subscription` reads via the `ResolveFeeTier` query handler from the original §2 spec — no logic change, just config-key namespace move.

---

## Implementation notes

### Naming convention summary

| Surface | Convention |
|---|---|
| Bounded context | `App\Domain\Pricing\` |
| Aggregate | `PriceQuoteAggregate` |
| Models | `PriceQuote`, `PriceTier` (the §10.2 fee-tier struct moves here) |
| Events | `PriceQuoteIssued`, `PriceQuoteConsumed`, `PriceQuoteSuperseded` (deltas Q2.3 refresh path) |
| DB tables | `price_quotes`, `price_quote_events` |
| FK columns | `price_quote_id` |
| Config | `config/pricing.php` (replaces `config/fees.php`) |
| HTTP routes | `POST /api/v1/pricing/quote`; future endpoints under `/api/v1/pricing/*` |
| HTTP request/response field | `quoteId` (preserved from deltas Q2; backend translates to `price_quote_id` at the controller boundary) |
| Error codes | `ERR_QUOTE_001` (expired), `ERR_QUOTE_002` (signature mismatch) — preserved per deltas Q2 |

### Wire ↔ internal translation at the controller boundary

Form Requests and API resources translate between the two:

```php
// Form Request — mobile sends quoteId
public function rules(): array
{
    return [
        'quoteId' => ['required', 'string', 'uuid'],
        // ...
    ];
}

public function priceQuoteId(): string
{
    return (string) $this->validated('quoteId');
}

// Controller
public function submit(SubmitWalletSendRequest $request, PriceQuoteRepository $repo): JsonResponse
{
    $priceQuote = $repo->findOrFail($request->priceQuoteId());
    // ... internal logic uses $priceQuote and price_quote_id
}

// API Resource — backend emits quoteId
public function toArray(Request $request): array
{
    return [
        'quoteId' => $this->price_quote_id,
        // ...
    ];
}
```

### Dependency direction

`Pricing` consumes upstream quote VOs; never the other way:

```
PricingController::quote()
  → PriceQuoteIssuer::issue($request)
    → match $request->kind:
        'send'      → EvmUserOpPreparer / SolanaSendPreparer + tier fee
        'swap'      → Domain\DeFi\SwapQuoteService + tier margin
        'ramp_buy'  → Domain\Ramp\SessionFactory + tier margin
        'ramp_sell' → Domain\Ramp\SessionFactory + tier margin
```

Existing upstream `Quote` VOs (`BridgeQuote`, `SwapQuote`, `ExchangeRateQuote`) stay in their domains. `Pricing` consumes them and produces `PriceQuote` rows.

### `price_quotes` schema

Per deltas Q2.2 / Q2.3 additions:

```sql
CREATE TABLE price_quotes (
    id                CHAR(36) PRIMARY KEY,
    user_id           CHAR(36) NOT NULL,
    user_tier         VARCHAR(32) NOT NULL,
    kind              VARCHAR(16) NOT NULL,        -- send | swap | ramp_buy | ramp_sell
    request_payload   JSON NOT NULL,
    response_payload  JSON NOT NULL,
    entity_key        CHAR(64) NOT NULL,           -- SHA256 of intent (sender, kind, amount, recipient, currency, route)
    user_op_hash      CHAR(66) NULL,                -- 0x + 32 bytes; null for ramp
    user_op_payload   JSON NULL,                    -- full userOp; null for ramp
    superseded_by     CHAR(36) NULL,                -- self-FK on refresh
    terms_changed     BOOLEAN NOT NULL DEFAULT FALSE,
    expires_at        TIMESTAMP NOT NULL,
    consumed_at       TIMESTAMP NULL,
    consumed_by       VARCHAR(255) NULL,            -- tx_hash | swap_id | ramp_session_id
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_price_quotes_user_expires (user_id, expires_at),
    KEY idx_price_quotes_consumed (consumed_at),
    KEY idx_price_quotes_entity_live (entity_key, expires_at, consumed_at),
    UNIQUE KEY uniq_price_quotes_user_op_hash (user_op_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Rename pass coverage

The deltas Backend-Q4 section already captures the spec rename:

- §3 endpoint definitions: `POST /api/v1/quote` → `POST /api/v1/pricing/quote`
- §3.2 schema name: `quotes` → `price_quotes`
- §3.3 submission contracts: `{ quoteId, ... }` (wire stays `quoteId`); FK columns become `price_quote_id`
- §10.2 config: `config/fees.php` → `config/pricing.php`
- All references to `Domain/Quote` → `Domain/Pricing`; aggregate `Quote` → `PriceQuote`

### Deprecation plan for `GET /api/v1/quotes` (RampController)

The existing endpoint stays for v1.3.0. v1.3.1 adds an alias `GET /api/v1/pricing/ramp-quotes` that dual-serves. Once mobile has migrated, the old route returns 410 with a `deprecated_path` field pointing to the new one. Document in deltas closing as v1.3.1 follow-up.

### What this ADR does NOT change

- The `Domain/Subscription` boundary (Backend-Q1 γ): subscription state lives in `Domain/Subscription`; pricing-tier reads happen via `ResolveFeeTier` query handler against `config/pricing.php`. The two domains are clean.
- Existing upstream `Quote` VOs: `BridgeQuote`, `SwapQuote`, `ExchangeRateQuote`, `QuotesUpdated` event, `Interledger\QuoteService`, `AI\MCP\Tools\Exchange\QuoteTool` — all stay where they are. `Pricing` calls into them but doesn't replace them.
- Existing `/api/v1/exchange/*` and `/api/v1/defi/*` and `/api/v1/crosschain/*` routes — all stay. They serve their domain-specific purposes; `Pricing` is additive, not replacement.

---

## References

- `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md` — Backend-Q4 section, source material
- `docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §3 (Universal quote endpoint), §10.2 (fee tier config)
- `docs/adr/0001-fee-collector-wallets.md` — fee-collector address is referenced in the userOp's calldata at quote-issuance time
- `docs/adr/0002-revenue-projection-dual-upstream.md` — `quotes.user_op_hash` is the join key for chain-event-sourced ingestion
- `docs/adr/0004-money-on-the-wire.md` — `PriceQuote` serializes Money fields in the smallest-unit-with-decimals shape
- Existing `app/Domain/Exchange/ValueObjects/ExchangeRateQuote.php`, `app/Domain/DeFi/ValueObjects/SwapQuote.php`, `app/Domain/CrossChain/ValueObjects/BridgeQuote.php` — upstream stateless market-snapshot Quote VOs that `Pricing` consumes
