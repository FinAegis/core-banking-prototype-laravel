# ADR-0006 — Bridge developer fees as the 0.75% Zelta markup collection mechanism

**Status:** Accepted
**Date:** 2026-05-23
**Decision-maker:** Backend engineer
**Related:** [ADR-0005](0005-bridge-xyz-over-stripe-crypto-onramp.md); `docs/BACKEND_HANDOVER_BRIDGE_RAMP.md` §2 decision 6, §3.5 (the brief is not stored in this repository)

> **Implementation status (reviewed for v7.18.1).** The per-customer default is built. `BridgeDeveloperFeeSync` keeps `bridge_customers.developer_fee_bps` at 75 for Free and 0 for Pro. A `SubscriptionTierChanged` listener runs it on every tier change, and `php artisan bridge:sync-dev-fee` reconciles it by hand. The admin panel's Bridge customer page can trigger the same sync. The ramp quote shows `zeltaMarkup`, `zeltaMarkupWaived` and `zeltaMarkupTier`. v1 is the virtual-account onramp, so every transfer uses the per-customer default. The per-transfer fee lock described below is not used yet. Recording Bridge's developer-fee remittances as `RevenueOutboxEvent` rows is not built yet either; it remains a finance-ops step.

---

## Context

Plan B Commercial pricing for ramp is:

- **Free tier:** Bridge cost + **0.75% Zelta markup** + network + FX pass-through
- **Pro tier:** Bridge cost only — no Zelta markup
- FX is **never** marked up under any tier

The handover §3.5 specifies how this should be *displayed* in the `GET /api/v1/ramp/quotes` response — as a discrete `zeltaMarkup` line item inside `fees`, with `zeltaMarkupWaived: true` for Pro users. What it does **not** specify is *how the money physically flows to Zelta*. This matters because:

- Bridge wires fiat in, converts to USDC, ships USDC straight to the user's wallet address. We never touch the funds.
- The §7 non-custody invariants forbid `BridgeService`/`RampService` from calling `WalletService`, `BalanceService`, or any ledger writer. We are not allowed to insert ourselves into the money path.
- A markup line item shown in the quote is only honest if money actually moves to us. Otherwise it is a fiction in the UI.

So: how do we extract €0.75 on a €100 transaction without violating non-custody, without forcing users to make a second transfer, and without adding a separate billing relationship for every Free user?

---

## Decision

**The 0.75% Zelta markup is collected as a Bridge developer fee, configured per-customer.** Specifically:

- The Bridge customer record carries a default `developer_fee_bps` value. Free-tier customers default to **75 bps**; Pro-tier customers default to **0 bps**.
- For **user-initiated** ramp sessions (the user pulls a quote and confirms), the per-transfer `developer_fee_bps` is set explicitly from the user's tier at quote-lock time and overrides the customer default. This locks the fee at the moment the user accepts it, regardless of subsequent tier changes between quote and settlement.
- For **Bridge-initiated** transfers (unsolicited deposits to a virtual account, where Bridge converts and ships USDC without us creating the transfer), the per-customer default applies. There is no per-transfer fee to set because we are not the API caller that triggered the transfer.
- On Pro upgrade/downgrade, the Bridge customer is PATCHed to update the per-customer default. The PATCH is the authoritative tier change for unsolicited deposits arriving after that moment.

Bridge collects the developer fee on top of its own 10 bps and remits it to us on its settlement cadence.

---

## Alternatives considered

### (α) Bridge developer fees — **chosen**

Use Bridge's first-party developer-fee feature (https://apidocs.bridge.xyz/platform/orchestration/fees-and-mins/devfees).

**Pros:** purpose-built mechanism designed for exactly this use case; settles automatically; integrates with Bridge's existing remittance and reconciliation; respects non-custody invariants (we never touch user funds); per-customer default solves the unsolicited-deposit case that out-of-band billing cannot; the quote line item we show the user matches what Bridge actually charges (no UI fiction); Pro waiver is a one-line config flip.

**Cons:** developer-fee remittance cadence is Bridge's, not ours (we don't get markup revenue on day 1 of each transaction — it batches per Bridge's settlement schedule); a tier change between quote-lock and Bridge transfer execution requires careful ordering (we lock the per-transfer fee at quote-lock to prevent the user paying a markup they were promised wouldn't apply); cancelling or refunding a transfer requires Bridge-side reconciliation of the developer fee (out of scope for v1 — refunds are a v1.1 problem).

### (β) Out-of-band invoicing — rejected

Tally per-user ramp volume per month, invoice Free users via subscription mechanic or charge them directly.

**Rejected because:** unworkable for one-off ramp users (they never come back to settle the invoice); collections risk on uncollected balances; requires us to be in the billing business for non-Pro users (the whole point of Pro is the subscription is the billing relationship); cannot cover unsolicited deposits where the user never went through our quote API; introduces a separate revenue stream with its own VAT/tax-jurisdiction handling.

### (γ) Sender-pays into a Zelta-controlled bank account — rejected

Quote the user a markup amount; require them to wire it separately to a Zelta-controlled bank account before the ramp proceeds.

**Rejected because:** atrocious UX (two transfers per ramp); compliance burden (we'd be receiving funds without Bridge's regulated wrapper covering us — we'd need to be a money-services business ourselves, defeating one of the main reasons to use Bridge); reconciliation nightmare matching deposits to ramp sessions; doesn't cover the user who wires the wrong amount.

### (δ) Skip the markup entirely for v1 — rejected

Free tier is "Bridge cost only" too; revenue from ramp comes later.

**Rejected because:** contradicts the locked Plan B pricing decision (handover §2.6); leaves ramp as a pure cost center for v1 with no path to validate price-elasticity; defers a pricing decision that needs to ship at launch to inform Free-vs-Pro conversion modeling.

### (ε) Per-transfer dev fee only, no per-customer default — rejected

Set the developer fee only on transfers we create via the Bridge API. Unsolicited deposits get no markup.

**Rejected because:** creates a perverse incentive — Free users learn that depositing directly to their virtual account bypasses the Zelta markup, eroding the unit economics. The v1.2 "Your Zelta IBAN" surface would actively encourage this leak.

---

## Consequences

### Positive

- **Unit economics close.** Free-tier ramp is revenue-positive on every transaction regardless of the entry path (user-initiated quote or unsolicited virtual-account deposit). No leakage.
- **Pro waiver is a single config flip.** No special billing code path for Pro users; the fee just doesn't apply.
- **Non-custody invariants preserved.** §7.1–§7.4 of the handover remain merge gates. The markup mechanic does not require any ledger writes or fund movements on our side.
- **Quote line items are honest.** What we show the user as `zeltaMarkup` in `GET /ramp/quotes` is what Bridge will deduct and remit to us. No UI fiction.
- **Reconciliation is Bridge's responsibility, not ours.** They remit a settlement statement with developer-fee detail per period; we book it as platform revenue (existing `RevenueOutboxEvent` pattern in the codebase handles the eventing).

### Negative

- **Markup revenue is batched, not real-time.** We see ramp activity in our DB immediately via webhooks, but the cash from the markup arrives on Bridge's settlement cadence (which we should confirm during sandbox integration).
- **Tier-change race during quote-lock.** A user pulling a Free quote, then upgrading to Pro, then accepting the quote, pays the Free markup. The mitigation is to make the quote response explicit about the locked tier (`zeltaMarkupTier: 'free'`) so mobile can offer "Re-quote as Pro" if the user just upgraded. Adding that re-quote prompt is a v1 polish item, not blocking.
- **Refunds are a v1.1 problem.** If a ramp transfer needs to be refunded (e.g., AML hold, user dispute), reconciling the previously-collected developer fee with Bridge requires their refund flow. We treat refunds as out of scope for v1.
- **PATCH ordering on tier change.** When a user upgrades to Pro, we PATCH their Bridge customer's `developer_fee_bps` to 0. There is a race window where an unsolicited deposit can be processed by Bridge between the upgrade event and the PATCH landing. The user pays Free markup on that deposit. We accept this — it's bounded to a few seconds and only affects users who literally wire fiat into the gap.

### Neutral

- **The markup is always charged in the source fiat currency.** No FX conversion of fees; the quote shows `zeltaMarkup` in the same currency as the deposit amount.
- **No floor, no cap.** Flat 0.75% on every Free-tier transaction. Simplest pricing logic; revisit only if data shows the small-transaction floor or large-transaction ceiling needs adjusting.
- **FX spread is never marked up** (handover §2.6 — locked). Bridge's FX spread passes through to the user verbatim as the `fxSpread` line item; we add nothing on top.

---

## Implementation notes

### Bridge customer creation

```php
// In BridgeKycProvider::getHostedLink (lazy provisioning per the handover):
$tier = $this->subscriptionService->tierFor($user);
$devFeeBps = $tier->isPro() ? 0 : 75;

$bridge->post('/v0/customers', [
    'developer_fee_bps' => $devFeeBps,
    // ... rest of customer payload
], headers: ['Idempotency-Key' => "bridge_customer:{$user->id}"]);
```

### Tier change handler

```php
// Listen for Subscription tier-change events (existing event in Subscription domain):
public function handle(SubscriptionTierChanged $event): void
{
    $bridgeCustomer = BridgeCustomer::where('user_id', $event->userId)->first();
    if (! $bridgeCustomer) {
        return; // user has not started Bridge KYC yet — nothing to PATCH
    }

    $devFeeBps = $event->newTier->isPro() ? 0 : 75;
    $this->bridge->patch("/v0/customers/{$bridgeCustomer->bridge_customer_id}", [
        'developer_fee_bps' => $devFeeBps,
    ]);
}
```

### Per-transfer override (user-initiated only)

```php
// In BridgeProvider::createSession for offramp, or transfer creation in onramp finalization:
$bridge->post('/v0/transfers', [
    'developer_fee_bps' => $quoteAcceptedTierBps, // locked at quote time, NOT current tier
    // ... rest of transfer payload
]);
```

The locked tier comes from the quote object stored when the user accepted it, not from a live read of the user's current subscription. This is what prevents the upgrade-mid-flow race from charging unfair fees.

### Quote response shape (handover §3.5)

```json
{
  "providerName": "Bridge",
  "fees": {
    "providerFee": "0.50",
    "networkFee": "0.01",
    "fxSpread": "0.00",
    "zeltaMarkup": "3.75",
    "zeltaMarkupWaived": false,
    "zeltaMarkupTier": "free",
    "total": "4.26"
  }
}
```

`zeltaMarkupTier` is added to the documented shape so mobile can render "Upgrade to Pro to waive this" CTA when appropriate, and to detect a stale quote after upgrade.

### Revenue accounting

Developer-fee remittances arrive via Bridge's settlement reports. Each remittance is recorded as a `RevenueOutboxEvent` (existing pattern in `app/Domain/Subscription/Models/RevenueOutboxEvent.php`) keyed by Bridge's transfer ID, so the per-transaction lineage is auditable. Reconciliation between expected developer fees (sum of locked-tier × transfer amount across our `ramp_sessions`) and received developer fees (Bridge's report) is a finance-ops process, not an automated equality check.

---

## References

- [ADR-0005](0005-bridge-xyz-over-stripe-crypto-onramp.md) — sibling ADR; the choice of Bridge.xyz over Stripe Crypto Onramp / Onramper makes this fee mechanic possible
- `docs/BACKEND_HANDOVER_BRIDGE_RAMP.md` (not stored in this repository) §2.6 (Pricing locked), §3.5 (Pricing wrap in `RampService`), §7 (non-custody invariants)
- Bridge developer fees — https://apidocs.bridge.xyz/platform/orchestration/fees-and-mins/devfees
- `app/Domain/Subscription/Models/RevenueOutboxEvent.php` — existing revenue eventing pattern reused for Bridge settlement remittances
