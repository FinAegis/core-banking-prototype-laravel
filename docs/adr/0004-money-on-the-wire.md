# ADR-0004 — Money on the wire: smallest-unit strings with explicit decimals

**Status:** Accepted
**Date:** 2026-05-09
**Decision-maker:** Backend engineer
**Related:** Backend-Q9 in `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md`; original handover §0.2; deltas Q3.1

---

## Context

Plan B Commercial introduces money on the API surface in three distinct shapes that contradict each other:

- **§0.2** of the original handover specified decimal strings: `{"amount": "4.99", "currency": "EUR"}`. Reasoning given: "Never a number literal — JSON loses precision."
- **§1.2** entitlements payload mixed shapes within a single response: `feeTier.txFlatEur: "0.05"` (decimal-string EUR) alongside `feeTier.swapMarginBps: 20` (integer basis-points).
- **Deltas Q3.1** introduced a third shape for fee breakdowns: `{"amount": "1000000", "asset": "USDC"}` (smallest-unit string for assets) with `eurEquivalent: "92"` (cents-string for EUR).
- **Storage** uses integer cents columns (`price_eur_cents INT UNSIGNED`).

Consumers of the API would have to remember which endpoint uses which shape, with no consistent rule. A field named `amount` could mean €4.99, €0.99, 1.0 USDC, or 1.0 micro-USDC depending on context.

This decision was caught immediately before mobile started its week-0 money-helper spike. Locking the shape pre-shipment is free; locking it after mobile builds against an inconsistent format requires a coordinated retrofit. **The cost asymmetry between deciding-now and deciding-later is the load-bearing reason this decision was elevated to an ADR.**

---

## Decision

**Every Money-typed field on the wire is a triple of `(amount, decimals, denomination)`** where:

- `amount`: integer-string of the smallest indivisible unit, sign-prefix permitted for refunds
- `decimals`: non-negative integer indicating the power-of-ten the smallest unit represents
- `denomination`: either `currency` (ISO-4217, e.g. `"EUR"`) for fiat, or `asset` (ticker, e.g. `"USDC"`) for tokens

**Examples:**

```json
// Fiat — €4.99
{"amount": "499", "decimals": 2, "currency": "EUR"}

// Asset — 1.0 USDC (USDC has 6 decimals on Polygon/Base/Arbitrum/Ethereum)
{"amount": "1000000", "decimals": 6, "asset": "USDC"}

// Negative — refund of €4.99
{"amount": "-499", "decimals": 2, "currency": "EUR"}

// Zero — explicit, never null/omitted
{"amount": "0", "decimals": 2, "currency": "EUR"}
```

`decimals` is **per-object**, never derived from a shared lookup table. Self-contained payloads are debuggable in isolation; lookup-coupling forces consumers to maintain a synchronized table across backend / mobile / SDK.

---

## Alternatives considered

### (α) Decimal string everywhere

`{"amount": "4.99", "currency": "EUR"}` for fiat; `{"amount": "1.000000", "asset": "USDC"}` for assets.

**Pros:** human-readable; one format; matches original §0.2's stated intent.

**Rejected because:** parsing as `Number` in JS loses precision past ~16 significant digits (USDC at 6 decimals × balances over 10B reach the limit; not realistic for v1.3.0 but a real long-term floor); arithmetic on decimal strings requires careful BCMath / `Decimal.js` handling and is easy to get wrong; consumers must apply asset-decimal knowledge to convert for storage.

### (β) Smallest-unit string + explicit `decimals` everywhere — **chosen**

The shape documented above.

**Pros:** unambiguous integer math via BCMath (PHP) and BigInt (JS); no asset-decimals lookup needed; matches Stripe's API convention (cents for fiat) which integrators already think in; BigInt-safe arbitrary precision; storage format mirrors wire format (both are smallest-unit), removing conversion at the controller boundary; format does not depend on the asset registry being in sync across runtimes.

**Cons:** mobile / web must format for display (`amount` × `10^-decimals`); less human-readable in dev tools and logs; one extra field per money triple.

### (γ) Hybrid: decimal-string for fiat, smallest-unit for assets

What deltas Q3.1 effectively did, ad-hoc.

**Rejected because:** two formats coexist on the wire; consumers must branch on whether the field is fiat or asset; a future endpoint adding a new field could pick the wrong format and pass review unless reviewers happen to remember the rule. Subtle bug surface for negligible benefit over (β).

### (δ) Decimal string everywhere with mandatory `decimals` annotation for assets

`{"amount": "4.99", "currency": "EUR", "decimals": 2}` and `{"amount": "1.000000", "asset": "USDC", "decimals": 6}`.

**Rejected because:** still has the precision risk of (α); the `decimals` field becomes redundant for fiat (always 2 for EUR) which invites consumers to ignore it; doesn't actually solve the asset-decimal-knowledge problem (the value `"1.000000"` requires the consumer to count the digits past the decimal anyway).

---

## Consequences

### Positive

- Math safety: integer-string arithmetic via BCMath (PHP) and BigInt (JS) is bulletproof. No accidental float conversions, no rounding modes.
- Format consistency: one Money shape across `/subscription/me`, `/pricing/quote`, fee breakdowns, savings calculations, and any future endpoint. New endpoints inherit the convention rather than re-deciding.
- Stripe alignment: cents-for-fiat matches Stripe's API convention. Anyone integrating Stripe Bridge, Stripe Connect, or Stripe Tax already thinks in this shape.
- Storage parity: DB columns store smallest-unit integers; wire format stores smallest-unit strings. The conversion at the controller boundary is one cast, not a unit conversion.
- Idempotency-key stability: with strict input validation (no decimal points in `amount`), there is exactly one canonical representation of a given monetary value. The `request_hash` derived from `(amount, decimals, currency, …)` is stable across retries.

### Negative

- Display formatting required at every render point on mobile and web. The Money helper is non-optional.
- Less human-readable in API dev tools; engineers debugging will read `{"amount": "499", "decimals": 2}` and have to mentally apply the divisor.
- One extra field per money triple. Payload size impact is negligible (a few bytes) but it is non-zero.
- A regression to (α) is hard once shipped — every endpoint and every consumer would need a coordinated migration.

### Neutral

- Negative amounts use sign-prefix on the string (`"-499"`). No separate `direction` field.
- Zero amounts are explicit (`"0"`), never null or omitted. Absence of money is communicated by absence of the parent object.
- Multi-chain asset disambiguation (e.g., USDC-on-Polygon vs USDC-on-Ethereum) is **deferred to v1.3.1**. v1.3.0 endpoints carry chain context implicitly via the request shape (`kind`, `route`, etc.). When the first multi-chain reporting endpoint is needed, the Money triple gains an optional `asset_chain` field.

---

## Implementation notes

### Validation rules (Form Request)

```php
'amount' => ['required', 'string', 'regex:/^-?[0-9]+$/'],
'decimals' => ['required', 'integer', 'min:0', 'max:18'],
'currency' => ['required_without:asset', 'string', 'size:3', new ValidIso4217],
'asset' => ['required_without:currency', 'string', new InKnownAssetRegistry],
```

`max:18` covers ETH's 18 decimals — the tallest known on chains we integrate. Anything beyond is rejected with `ERR_VALIDATION_002`.

Reject cases (all return `ERR_VALIDATION_002`):

| Input | Why |
|---|---|
| `{"amount": "4.99", …}` | decimal point in `amount` |
| `{"amount": 499, …}` | number literal, not string |
| `{"amount": "499", "decimals": 2}` (no currency or asset) | missing denomination |
| `{"amount": "499", "decimals": 2, "currency": "EUR", "asset": "USDC"}` | both denominations specified |

Accept cases:

| Input | Meaning |
|---|---|
| `{"amount": "499", "decimals": 2, "currency": "EUR"}` | €4.99 |
| `{"amount": "0", "decimals": 2, "currency": "EUR"}` | €0.00 (zero, explicit) |
| `{"amount": "-499", "decimals": 2, "currency": "EUR"}` | -€4.99 (refund) |
| `{"amount": "1000000", "decimals": 6, "asset": "USDC"}` | 1.0 USDC |

### Money value object

A single `App\Domain\Pricing\ValueObjects\Money` VO encapsulates the triple. All Pricing/Subscription/Wallet endpoints serialize through it. Internal arithmetic uses BCMath (`bcadd`, `bcsub`, `bcmul`, `bcdiv`) on the smallest-unit string, never `(float)` cast.

```php
final readonly class Money implements JsonSerializable
{
    public function __construct(
        public string $amount,
        public int $decimals,
        public string $denomination,         // 'EUR', 'USDC', etc.
        public bool $isFiat,                  // true for currency, false for asset
    ) {
        // assertions: amount matches /^-?[0-9]+$/, decimals 0..18, denomination known
    }

    public function add(self $other): self { … }     // asserts same denomination + decimals
    public function negate(): self { … }
    public function eurEquivalentAt(string $rate, int $rateDecimals): self { … }
    public function jsonSerialize(): array { … }
}
```

Mobile mirrors with a TS type:

```ts
type Money = {
  amount: string;
  decimals: number;
  currency?: string;     // ISO-4217 for fiat
  asset?: string;        // ticker for tokens
};
```

Helpers `formatMoney(m): string`, `parseMoney(s, denomination): Money`, `addMoney(a, b)`, `compareMoney(a, b)`.

### DB storage — per-table judgment

Wire format does not dictate storage format. Storage is column-level judgment:

| Table type | Recommended column shape |
|---|---|
| Single-currency tables (e.g., `subscriptions.price` is always EUR) | Implicit via column name: `price_eur_cents BIGINT UNSIGNED` — fine |
| Variable-currency tables (e.g., `price_quotes.fee_amount` is EUR or USDC depending on `kind`) | Explicit triple: `fee_amount BIGINT, fee_decimals TINYINT UNSIGNED, fee_denomination VARCHAR(16)` |
| Telemetry / events (`revenue_events`, `subscription_events`) | Explicit triple — preserves originating-currency context for cohort analysis |

Pattern: when the answer to "could this column ever hold non-EUR" is yes, go explicit.

### Sections to amend in the spec

Locking this ADR requires three amendments to the handover doc plus a note in the deltas:

1. **Original §0.2** — replace "decimal string with explicit currency" language with the (β) shape.
2. **Original §1.2** — `feeTier.txFlatEur: "0.05"` becomes `feeTier.txFlat: {"amount": "5", "decimals": 2, "currency": "EUR"}`. Basis-point fields (`swapMarginBps`, `rampMarginBps`) stay as integers — `bps` is itself a unit-of-measure with no decimals confusion.
3. **Deltas Q3.1** — fee-breakdown items use the triple; `eurEquivalent` becomes `{"amount": "92", "decimals": 2, "currency": "EUR"}`.
4. **Deltas Backend-Q9 section** — already captures the (β) decision. ADR-0004 is the durable reference.

### Migration impact

**Zero shipped endpoints affected.** All endpoints touched by Plan B (`/api/v1/pricing/quote`, `/api/v1/subscription/*`, `/api/v1/me/entitlements`, `/api/v1/wallet/transactions/exports`, etc.) are net-new in v1.3.0. The convention is locked before any of them ship.

Existing endpoints that pre-date Plan B and use mixed money formats (e.g., `/api/v1/exchange/*`, `/api/v1/ramp/*`) are NOT retrofitted in v1.3.0. They keep their current shape until v1.3.1 has a planned endpoint-by-endpoint migration. Consumers of those endpoints continue using their existing parsing.

---

## References

- `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md` — Backend-Q9 section, the source material for this ADR
- `docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` — original handover §0.2, §1.2, §3 (to be amended per this ADR)
- Stripe API reference — convention precedent for cents-as-integer-strings in fiat amounts
- ERC-20 standard — the asset-decimals model this ADR aligns with for tokens
- `docs/adr/0001-fee-collector-wallets.md` (pending) — sibling ADR; on-chain fee transfers carry Money in this format
- `docs/adr/0002-revenue-projection-dual-upstream.md` (pending) — `revenue_events` rows store Money in this format
- `docs/adr/0003-pricing-bounded-context.md` (pending) — `Domain/Pricing` owns the `Money` VO
