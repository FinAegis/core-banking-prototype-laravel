# Backend Handover — Plan B Commercial — Review Deltas

> Companion to `BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md`. This document captures every architectural change agreed during the joint Q1–Q17 architectural review with the mobile team. Mirror-document of `finaegis-mobile/docs/MOBILE_HANDOVER_PLAN_B_REVIEW_DELTAS.md` from the same review.
>
> **Apply these as a patch PR against the original handover before week 1 starts.** The original doc remains intact for git-history clarity; this file is the changelog and the source of truth for what changed and why.

---

## Schedule delta

Original scope: 36 engineer-days across 9 features.

Review-surfaced additions: ~8.5 engineer-days of backend work, distributed across the existing features. None of it is new scope — every increment defuses a money-path bug, regulatory exposure, or App Store rejection that the original spec underspecified.

| Bucket | Effort | What it adds |
|---|---|---|
| Q2/Q3 quote contract patches (kind discriminator, signed-payload verification, frozen rates) | ~1.5d | Closes the userOp-vs-displayed-fee divergence; closes the rate-drift dispute window |
| Q5 cue queue infrastructure (table, endpoints, cron, server-side reaping) | ~3d | Replaces the screen-bound poll-loop with a durable cross-session UI pull |
| Q7 reconciler-supporting backend (`/checkout` 409 + stale-session cleanup, `/iap/verify` server-side dedup) | ~1d | Closes the IAP-paid-but-backend-unaware money hole |
| Q9 card waitlist refund operations (cancel endpoint, closure ledger, ops paging) | ~1d | Closes the "user paid €5, account closure failed mid-Stripe-call" failure mode |
| Q11 trial reminder cron + marketing opt-out | ~0.5d | Closes the "fresh-install user never sees Pro after onboarding" discovery hole |
| Q13 exports list + URL refresh + WS event | ~0.75d | Replaces the lost-on-navigate poll-loop with a persistent job model |
| Q14 EU consent persistence + ERR_SUB_004 | ~0.5d | Closes the "we showed a checkbox" → audit-trail gap for EU CRD disputes |
| Q15 right-to-erasure endpoint + pseudonymisation salt secret | ~0.5d | GDPR Article 17 compliance; makes the email-to-support escalation path actually work |
| Q17 `/change-plan` + `/reactivate` endpoints | ~0.5d | Closes the no-self-serve-upgrade and no-undo-cancel UX holes (reduced from 1d under Cashier wrapper approach — see Backend-Q1) |
| **Backend-Q1 Cashier hybrid (γ) — Stripe via Cashier, custom for IAP** | **−3d** | Reclaims days from Cashier's free Stripe lifecycle plumbing; Q17 endpoints become thin wrappers; drops event-sourced `subscription_events` for the Stripe path |
| **Backend-Q2 Fee-collector wallets (α) + sweep audit** | **+2d** | Defines on-chain custody surface for service fees; KMS-backed signing, daily sweep cron, `revenue_sweeps` audit table, migration thresholds for (β) |
| **Backend-Q3 Revenue projection: chain-ingestor (on-chain) + PSP outbox (off-chain)** | **+1.5d** | Replaces fragile saga with chain-projection for on-chain fees and outbox-pattern for off-chain; eliminates dropped-message failure mode; reuses Helius/Alchemy infra |
| **Backend-Q4 Domain/Pricing rename (Quote → Pricing/PriceQuote)** | **~0d** | Disambiguates from 6 existing `Quote`-named types in CrossChain/DeFi/Exchange/Interledger; locks `/api/v1/pricing/quote` URL before launch; near-zero cost if done now |
| **Backend-Q5 Trial-abuse card-fingerprint check (α) + 12mo retry window** | **+2d** | Closes the Stripe-side "new account, same card" trial abuse vector; `trial_card_fingerprints` table; SetupIntent-mode Checkout flow; Filament admin override |
| **Backend-Q6 Pseudonymisation framing correction (δ) + vendor deletion orchestration** | **+0.5d** | Drops false GDPR pseudonymisation claim (salt-on-device fails Art 4(5) "kept separately"); accurate framing in deltas/privacy/DPIA; `vendor_data_deletions` audit table for `/me/data-deletion` |
| **Backend-Q7 IAP receipt pseudonymisation (α) — corrects deltas Q15.2 contradiction with §4.5** | **+0.75d** | Pseudonymise (not delete) IAP receipts per §4.5 pattern; Art 17(3)(b) tax-retention exception; webhook hash-fallback for refund/renewal post-erasure; `IAP_RECEIPT_PEPPER` (one-way rotation) joins simple-pepper convention |
| **Backend-Q8 Cue dispatch architecture (γ) — queued jobs for time-from-event, cron for aggregate-condition** | **−1.5d** | Replaces fragile windowed-cron pattern with Laravel delayed jobs for time-from-event cues; aggregate-condition cues stay on cron with `LEFT JOIN`-based candidate query; durable failure recovery via `failed_jobs`; net reclaim of engineer-days |
| **Backend-Q9 Money format on the wire (β) — smallest-unit string + decimals + currency-or-asset everywhere** | **+0.75d** | Locks unified Money VO shape across all endpoints before mobile builds week-0 helper; eliminates three coexisting formats (decimal-string §0.2, mixed §1.2, ad-hoc deltas Q3.1); BigInt-safe; matches Stripe-cents convention; zero shipped endpoints to migrate |

Net additions: **~11.5 engineer-days** (was −1.5d after Q8; +0.75d after Q9). Revised backend total: **~47.5 engineer-days**. Cumulative architectural-improvement budget reclaim of −4.5d vs deltas-implied worst-case baseline (Cashier hybrid −3d via Backend-Q1, queued jobs −1.5d via Backend-Q8) lands the project at +3d above the disciplined joint-review baseline of 44.5d, with materially better architecture across every load-bearing dimension.

Backend can ship most of this in parallel with mobile. Critical-path dependencies for mobile are flagged in each Q below.

---

## Subscription architecture baseline (Backend grilling, Q1)

> The backend dev's review opened with: should `Domain/Subscription` be (α) pure-custom, (β) Cashier-only, or (γ) hybrid Cashier-for-Stripe + custom-for-IAP + unified projection? **Decision: (γ).** The original handover assumed (α); this baseline supersedes that assumption everywhere subscriptions are touched.
>
> Audit doesn't drive (α) for Plan B. We're a software vendor (subscriptions for software access, not money movement), not a regulated PSP. Stripe is the audit-of-record for the Stripe channel; Apple/Google's `originalTransactionId` and purchase-token chains are the audit-of-record for IAP. Mirroring Stripe's event log in our event store is double bookkeeping with no regulatory benefit.

### Why (γ), not (α)

- Cashier provides ~5–7 engineer-days of free Stripe lifecycle plumbing: subscription create/cancel/swap/resume, dunning, retry schedules, trial extension, prorated invoices. Re-implementing this is cost without coverage benefit.
- Cashier's webhook handlers (`customer.subscription.created`, `invoice.payment_failed`, etc.) are extensible via override — we add side effects (cue events, projection updates) without owning the lifecycle.
- Cashier doesn't help with IAP, so the custom path is real engineering work either way; isolating it to `Domain/Subscription/Iap/` keeps the source-specific code in its own corner.
- The unified entitlements projection that the original handover already specified (§1.2 returns the same shape regardless of source) becomes the read model that consumes both Cashier's tables and our custom IAP tables.

### Source-of-truth split

| Concern | Source | Notes |
|---|---|---|
| Stripe subscription lifecycle | Cashier (`subscriptions`, `subscription_items` tables) | Cashier-managed; we don't event-source this path |
| IAP subscription lifecycle | Custom `iap_subscriptions` + event-sourced `iap_subscription_events` | Apple + Google notification chains arrive partial/out-of-order — internal replay genuinely helps |
| Withdrawal consent (Q14) | Custom `subscription_consent_log` | FK to `subscriptions.id` (Stripe) or `iap_subscriptions.id`; nullable until subscription created |
| Multi-store conflict detection (Q6) | Unified entitlements projection | Both creation paths consult projection before persisting |
| "What plan does this user have" | Unified entitlements projection (read model) | Materialized via observer on Cashier events + IAP events |
| `price_quote_events` (§3, renamed per Backend-Q4) | Unchanged structurally from original handover (custom event-sourced) | Not subscription-domain |
| `revenue_events_log` (§4) | Unchanged from original handover | Not subscription-domain |

### Endpoint mapping under (γ)

Q17 lifecycle endpoints become thin wrappers around Cashier methods for the Stripe path. IAP plan changes happen via store-side APIs (`requestSubscriptionPlanChange` / `BillingClient.updateSubscription`) — backend gets notified via webhook, no separate endpoint.

```php
// POST /api/v1/subscription/change-plan (Q17.1; Stripe-only)
public function changePlan(Request $request, User $user): SubscriptionResource
{
    abort_unless($user->subscribed('default'), 422, 'No active subscription');
    abort_if(
        $user->subscription('default')->stripe_price === 'price_annual_pro'
            && $request->plan === 'monthly_pro',
        422,
        'ERR_SUB_006: Annual → Monthly downgrade not offered'
    );

    return new SubscriptionResource(
        $user->subscription('default')->swap($this->priceIdFor($request->plan))
    );
}

// POST /api/v1/subscription/cancel (existing; cancel-at-period-end is Cashier default)
public function cancel(User $user): SubscriptionResource
{
    return new SubscriptionResource($user->subscription('default')->cancel());
}

// POST /api/v1/subscription/reactivate (Q17.2; Stripe-only — clears ends_at)
public function reactivate(User $user): SubscriptionResource
{
    abort_unless($user->subscription('default')?->onGracePeriod(), 422, 'Not in grace period');
    return new SubscriptionResource($user->subscription('default')->resume());
}
```

Each endpoint is ~10 lines + idempotency wrapper + projection refresh trigger. The 1d Q17 estimate becomes ~0.5d under (γ).

### Cashier event → cue event bridge

Override Cashier webhook handler methods to dispatch our cue events and update the projection. This is the only place where Stripe lifecycle crosses into the custom domain.

| Cashier-handled Stripe event | Triggers our cue (Q5) | Triggers our projection update |
|---|---|---|
| `invoice.payment_failed` | `payment_failed` (critical) | Set `grace_period_started_at` |
| `customer.subscription.trial_will_end` (3d) | `trial_ending_2d` / `_1d` / `_1h` (cron-based subdivision in `App\Console\Commands\DispatchTrialReminders`) | — |
| `invoice.payment_succeeded` | suppress active `payment_failed` cue | clear `grace_period_started_at` |
| `customer.subscription.deleted` | none (silent) | mark inactive |
| `charge.refunded` (subscription-linked) | `refund_processed` (high) | mark refunded |
| `customer.subscription.updated` (status='past_due') | `grace_period_started` | — |

### Cross-source conflict resolution

Both subscription-creation entry points consult the unified projection BEFORE writing. Implementation:

```php
// In Cashier checkout webhook handler (override handleCustomerSubscriptionCreated)
if ($projection->hasActiveProSubscription($user, source: 'iap')) {
    Log::warning('subscription.conflict.two_stores_active', [
        'user_id' => $user->id, 'attempted' => 'stripe', 'existing' => 'iap',
    ]);
    // Refund the Stripe subscription that was just created
    $newSubscription->cancel(['immediately' => true]);
    return response()->json([
        'code' => 'ERR_SUB_002',
        'conflict' => [...]
    ], 409);
}

// In POST /iap/verify handler
if ($projection->hasActiveProSubscription($user, source: 'stripe')) {
    return response()->json([
        'code' => 'ERR_SUB_002',
        'conflict' => [...]
    ], 409);
}
```

The Redis lock from §1.5 ("first sub wins") moves to operate on `user_id` (not subscription_id), TTL ~30s.

### Operational gotchas

1. **Cashier version pin:** confirm `laravel/cashier-stripe` ^15.x against L12 / PHP 8.4 before merging. Cashier upgrades sometimes bump the pinned Stripe API version, which can cascade into the existing Stripe Bridge integration.

2. **Single Stripe API version pin:** lock both Cashier and the existing Stripe Bridge client to the same API version in one config key:

   ```php
   // config/services.php
   'stripe' => [
       'api_version' => env('STRIPE_API_VERSION', '2025-04-30.basil'),
       // ...
   ],
   ```

   Acceptance: "Stripe API version is pinned in a single config key consumed by both Cashier and Stripe Bridge clients; CI test asserts no direct version pinning elsewhere."

3. **`paused_until` lives on `iap_subscriptions` only.** Apple's pause feature has no Stripe equivalent; don't mutate Cashier's `subscriptions` table to add it. Projection union returns whichever source has the field populated.

4. **Cashier's `ends_at` IS `cancelledAtPeriodEnd`.** No new column needed for Stripe path:

   ```php
   $resource['cancelledAtPeriodEnd'] = $sub->ends_at !== null && $sub->ends_at->isFuture();
   ```

5. **`processed_webhook_events` dedup wraps Cashier.** Apply our existing dedup BEFORE Cashier's webhook router dispatches — single dedup point covers both Cashier-handled and custom-handled Stripe events. Acceptance: "Stripe webhook redelivery of the same `event.id` is no-op regardless of whether Cashier or custom handler processes it."

6. **GDPR data deletion (Q15.2) needs explicit Stripe customer delete:**

   ```php
   if ($user->stripe_id) {
       $user->asStripeCustomer()->delete();   // Stripe-side erasure
       $user->forceFill(['stripe_id' => null, 'pm_type' => null, 'pm_last_four' => null])->save();
   }
   // continue with iap_subscriptions, subscription_consent_log, closed_account_refunds, etc.
   ```

   Cashier doesn't auto-cascade on `User::delete()`. Add to `App\Domain\User\Actions\DeleteAccountData::handle()`.

7. **Multi-connection deadlock risk (per CLAUDE.md):** Cashier subscription writes hit the default connection; if our cue/projection updates touch `UsesTenantConnection` models in the same flow, do NOT wrap in `DB::transaction()` — that produces a self-deadlock against the wrapping connection's row locks. Use the saga pattern documented in §0.2 of the original handover. Add a regression test in `tests/MultiConnection/` for the checkout-completed → cue-creation flow.

### Schedule impact

- Cashier integration setup (config, customer migration if any test users have stripe_id, webhook signature verification reuse): **+1d**
- Q17 endpoints become wrappers (~0.5d each instead of 1d, three endpoints total): **−1.5d**
- Drop event-sourced `subscription_events` scaffolding for Stripe path: **−1.5d**
- Webhook handler skeleton replaced by Cashier overrides: **−2d**

**Net: −3d returned to the budget.** Revised backend total: ~41.5 engineer-days.

### What this changes in the Q4–Q17 sections below

| Section | Change |
|---|---|
| Q4 (entitlements) | Projection reads from Cashier + IAP tables; schema versioning contract unchanged |
| Q5 (cue queue) | Trial-reminder subdivision cron complements Cashier's `trial_will_end` event (Cashier fires once at 3d; we fan out to 2d/1d/1h via cron) |
| Q6 (multi-store conflict) | Detection consults unified projection (see "Cross-source conflict resolution" above) |
| Q7 (`/checkout` 409) | Cashier's `Cashier::createSubscriptionCheckout()` is wrapped to detect and clean up stale incomplete sessions before returning a fresh URL |
| Q14 (consent log) | `subscription_consent_log.subscription_id` FK references Cashier's `subscriptions.id` (or `iap_subscriptions.id`); nullable until creation |
| Q15 (data deletion) | Adds explicit Stripe customer delete step (see operational gotcha 6 above) |
| Q17 (lifecycle) | Endpoints rewritten as Cashier wrappers (see "Endpoint mapping" above) |

---

## Fee-collector wallet architecture (Backend grilling, Q2)

> Architecture A from deltas Q2 ("sibling fee transfer in the userOp batch") commits the user to signing transfers to a fee-collector address. Where does that address live, and what custody surface does that create? **Decision: (α) — one EOA per chain, KMS-backed key, daily sweep to off-ramp.** Migrate to (β) smart-account-per-chain when daily holdings cross thresholds documented below.

### Why (α), not (β) or (γ)

- (β) Smart-account multi-sig per chain costs ~3–5d migration + ~€1k/mo ops; the risk surface it insures against (multi-day holdings) doesn't exist at v1.3.0 sweep cadence
- (γ) MPC custody (Fireblocks-class) is right *if* we ever hold material treasury balances on-chain longer than a sweep cycle — overengineered for €100/day/chain holdings
- (α) is the standard pattern for SaaS-style on-chain revenue collection at this scale; risk is bounded by daily sweep discipline, not by key architecture

### Custody framing

**Service fees are platform revenue, not customer funds.** The user's signed userOp atomically transfers the fee directly to a Zelta-controlled address; no intermediary account holds gross before forwarding net. This is the same framing as a SaaS company collecting Stripe payouts and holding transient USD before sweep — it's operating cash, not custody-of-third-party-funds.

The framing holds *as long as Architecture A's invariant holds* (user signs the batch where fees go directly to fee-collector). Any future migration that introduces a "Zelta receives gross, forwards net" pattern flips the framing into MiCA territory. Backed by Q2's userOpHash invariant.

> **§0.1 prereq addition:** "Service fees are platform revenue collected at the point of transaction, held transiently in operating wallets before conversion to EUR. **Customers do not have a claim on these fees.**" Get legal sign-off on this exact wording before launch.

### Key custody pattern

| Aspect | v1.3.0 implementation |
|---|---|
| Production keys | AWS KMS-backed signing — private key never leaves the HSM; signing happens during scheduled sweep cron only |
| Staging keys | Local ENV-driven; rotates per environment |
| Key access pattern | At rest 23h/day; unlocked only during scheduled sweep run |
| Web/API request handlers | **Never** access fee-collector keys; signing path is queue-only (sweep job) |
| Per-chain config | `config/fees.php` keyed by chain symbol; address is hardcoded, never user-supplied or quote-supplied |

Pattern mirrors existing Solana sweeper (grep `app/Domain/` for existing signing patterns; if Solana sweeper exists, reuse its KMS abstraction; if not, this is greenfield).

### Sweep mechanics

```
Daily cron at 03:00 UTC: php artisan revenue:sweep-fee-collectors
  for each chain in [polygon, base, arbitrum, ethereum, solana]:
    1. Read fee-collector balance via RPC
    2. Compute expected_eur_cents = SUM(ServiceFeeCharged.gross_eur_cents WHERE chain = X AND in_window)
    3. Build sweep transaction (transfer balance to off-ramp destination)
    4. Sign via KMS, broadcast, wait for confirmation
    5. Initiate off-ramp conversion (Coinbase Prime / Circle Mint / Stripe Bridge connect)
    6. Insert revenue_sweeps row with expected_eur_cents, swept_amount_asset, swept_tx_hash
    7. (Async) Off-ramp settlement webhook updates actual_eur_cents + variance
    8. Variance > 0.5% → ops alert (matches §4.4 PSP reconciliation threshold)
```

### `revenue_sweeps` audit table

```sql
CREATE TABLE revenue_sweeps (
  id CHAR(36) NOT NULL PRIMARY KEY,
  chain VARCHAR(32) NOT NULL,
  collector_address VARCHAR(64) NOT NULL,
  asset VARCHAR(16) NOT NULL,
  swept_amount_asset VARCHAR(64) NOT NULL,             -- raw smallest-unit string
  swept_to_destination VARCHAR(128) NOT NULL,           -- 'coinbase_prime:acct_xyz' / 'circle_mint:...'
  swept_tx_hash CHAR(66) NOT NULL,
  swept_at TIMESTAMP NOT NULL,
  expected_eur_cents BIGINT NOT NULL,                   -- promised: sum of ServiceFeeCharged in window
  actual_eur_cents BIGINT NULL,                         -- realised: nullable until off-ramp settles
  off_ramp_settlement_id VARCHAR(128) NULL,
  off_ramp_settled_at TIMESTAMP NULL,
  variance_eur_cents BIGINT GENERATED ALWAYS AS (actual_eur_cents - expected_eur_cents) STORED,
  reconciled BOOLEAN NOT NULL DEFAULT FALSE,
  created_at TIMESTAMP NOT NULL,
  INDEX idx_revenue_sweeps_unreconciled (chain, reconciled, swept_at)
);
```

### `ServiceFeeCharged` event (extends §4.1)

```
ServiceFeeCharged {
  user_id,
  source: 'tx_fee',
  reference_quote_id: 'qte_...',                        -- links to Q2/Q3 quote
  reference_user_op_hash: '0x...',
  reference_tx_hash: '0x...',
  gross_eur_cents: 20,
  asset_amount: '200000',                               -- smallest unit
  asset: 'USDC',
  asset_chain: 'polygon',
  collector_address: '0xZeltaPolygon...',
  eur_rate_at_quote: '0.92',                            -- frozen rate from quote.rates
  rate_source_id: 'oracle:chainlink:USDC-EUR:...',
  emitted_at: ...
}
```

The `reference_quote_id` + `eur_rate_at_quote` pair is the source of truth for "we promised €0.20 at this exchange rate." Variance calculation in `revenue_sweeps` derives from these.

### Native asset send fee policy

**v1.3.0: native asset sends incur no service fee.** Native ETH/MATIC/SOL sends are rare in a stablecoin-focused app; fee in native asset has unpredictable EUR equivalent due to volatility within the quote window; fee in sibling stablecoin transfer requires user to hold stablecoin balance (awkward UX). Network/gas fee still applies (chain-level, not service-level).

Acceptance: "`POST /api/v1/pricing/quote` with native-asset `currency` returns `feeBreakdown` with no `service` item; only `network` and (if cross-chain) `bridge` items present."

### Migration thresholds for (β)

Encode in `config/fees.php`; ops dashboard renders current values vs thresholds; crossing one auto-creates a "consider (β)" ticket (not an immediate migration).

```php
'fee_collector_migration_thresholds' => [
    'daily_holding_eur_cents' => 500_000,        // €5k for 7+ consecutive days
    'peak_balance_eur_cents' => 2_500_000,       // €25k single-day max
    'annual_per_chain_eur_cents' => 50_000_000,  // €500k cumulative on a single chain
],
```

Maps to roughly **€100–150k/mo per chain** under daily sweep cadence as the natural (β) trigger. Below that, (α) is correct; above, (β)'s ops cost (~€1k/mo) starts paying back against single-key blast radius.

### Native gas for sweep transactions

Fee-collector EOA holds small native balance (€20–50 worth of MATIC/ETH/SOL) for sweep gas. Ops monitors via daily balance check; alert at <€10 equivalent remaining. Top-up is manual via ops runbook.

If/when migrating to (β), the smart-account naturally pairs with paymaster for sponsored sweep gas — defer the paymaster decision to (β) migration.

### Schedule impact

| Item | Effort |
|---|---|
| Fee-collector address config + KMS signing integration | 1d |
| Daily sweep cron + balance monitoring + native-gas top-up alerts | 1d |
| `ServiceFeeCharged` event with quote linkage (extends §4) | 0.25d |
| `revenue_sweeps` table + reconciliation extension to §4.4 | 0.5d |
| `fee_collector_migration_thresholds` config + ops dashboard panel | 0.25d |
| ADR + legal review coordination | 0.5d |

Total ~3.5d; ~1.5d overlaps with §4 revenue attribution scope. **Net new: +2d.**

### Acceptance

- Fee-collector addresses configured per chain; addresses are hardcoded in `config/fees.php`, never user-supplied or quote-supplied
- Production fee-collector keys use KMS-backed signing; integration test asserts no other code path can access the keys
- Native asset sends produce a quote with no service fee item
- `ServiceFeeCharged` event includes `reference_quote_id` and `eur_rate_at_quote`
- Daily sweep cron writes a `revenue_sweeps` row with `expected_eur_cents`; off-ramp settlement webhook updates `actual_eur_cents`
- Variance > 0.5% triggers ops alert
- `fee_collector_migration_thresholds` checked daily; crossing creates a tracking ticket (not auto-migration)
- Native gas balance monitored; alert at <€10 equivalent remaining

### ADR

`docs/adr/0001-fee-collector-wallets.md` — the dev will draft. Captures the (α) vs (β) vs (γ) decision, the legal framing language, the migration thresholds, and the operational runbook. Required reading for anyone touching the fee path.

---

## Revenue projection architecture (Backend grilling, Q3)

> Original §0.2 specified a multi-connection saga: write fee on tenant connection, dispatch queued job, worker writes revenue event on global. The dev's review identified this is the wrong shape for the v7.12.0+ non-custodial model. **Decision: dual upstream — (γ) chain-ingestor projection for on-chain fees + (β) outbox-pattern projection for off-chain fees.** The saga from the original §0.2 is superseded for the v1.3.0 fee path.

### Why dual upstream, not unified saga

- **For on-chain fees, the chain IS the durable log.** The user's signed userOp atomically transfers the fee to the collector address; backend was not a participant in the money movement. Treating `revenue_events` as a side-effect of a backend action that didn't happen inverts the dependency. Project from chain (the source-of-truth), not via dispatched job (which can drop messages).
- **For off-chain fees, the PSP is the durable log.** Stripe / Apple / Google webhooks are the source-of-truth event. Outbox-write-on-receipt + worker-projects-to-revenue collapses the multi-connection saga to a single-connection write because Stripe webhook handlers run on global anyway.
- **The v7.12.0+ non-custodial model removes the multi-connection saga premise.** Original §0.2 assumed a custodial flow where the backend wrote the fee deduction to the tenant-Wallet connection. Under Privy + smart accounts, balances are read from chain; there's no tenant-connection write in the user's transaction path. The cross-connection saga rule is preserved for future flows that need cross-connection writes — but the v1.3.0 fee path doesn't.
- **Failure mode under the original saga is genuinely bad.** Permanent job failure → money in collector wallet that never appears in `revenue_events`, requiring forensic chain analysis to attribute. Under (γ), this case is structurally eliminated; the chain-history scan auto-recovers any dropped webhook.

### Source-of-truth split

| Fee origin | Source-of-truth | Projection trigger | Backup catch |
|---|---|---|---|
| `tx_fee` (send / swap, on-chain) | Chain (post-finality) | Helius / Alchemy webhook on collector address | Hourly chain-history scan; Backend-Q2 `revenue_sweeps` variance |
| `ramp_margin` | Stripe Bridge webhook | `RevenueOutboxEvent` row → worker | §4.4 PSP reconciliation |
| `subscription_*` | Cashier-managed Stripe webhook | `RevenueOutboxEvent` row → worker | §4.4 PSP reconciliation |
| IAP subscription | Apple/Google notification → custom IAP handler | `RevenueOutboxEvent` row → worker | §4.4 PSP reconciliation |
| `refund` | Stripe / IAP refund webhook | `RevenueOutboxEvent` row → worker (negative) | §4.4 PSP reconciliation |
| `kyc_margin` | Ondato webhook | `RevenueOutboxEvent` row → worker | §4.4 PSP reconciliation |
| `baas` | Direct admin entry (§5) | Direct write | (none — admin-controlled) |

### Chain ingestor — on-chain projection

Extends existing Helius (Solana) and Alchemy (EVM) infrastructure already in production. Add fee-collector addresses to webhook subscription config; add a separate processor code path for collector-keyed inflows (revenue, not user balance — don't pollute existing user-balance logic).

```
Webhook arrives at HeliusWebhookController / AlchemyWebhookController
  → routed to FeeCollectorInflowProcessor (new) if `to_address ∈ fee_collector_addresses`
  → check finality:
       - Polygon: block_confirmations >= 32
       - Base: block_confirmations >= 32
       - Arbitrum: block_confirmations >= 32
       - Ethereum: commitment === 'finalized' (Beacon, ~12.8min)
       - Solana: commitment === 'finalized' (~13s)
  → if not yet finalized: enqueue for re-check (in-memory queue or `chain_ingestor_pending` table); a reorg before finality drops the entry silently
  → if finalized:
       lookup quotes.user_op_hash by inflow tx → matching quote
       if match found:
         create ServiceFeeCharged event with reference_quote_id, eur_rate_at_quote, etc. (Backend-Q2 schema)
       if no match:
         insert unmatched_collector_inflows row; page ops at >€10
```

### Periodic chain-history scan (belt-and-braces)

Hourly cron per fee-collector. Catches webhook drops, ingestor crashes, race conditions:

```
foreach (collectors as $collector):
  $current_finalized_block = $rpc->getFinalizedBlockNumber()
  $safety_margin = 20  // blocks
  $scan_to = $current_finalized_block - $safety_margin

  $transfers = $rpc->getTransfersTo($collector->address,
                                    fromBlock: $collector->last_full_scan_block,
                                    toBlock: $scan_to)

  foreach ($transfers as $tx):
    if (revenue_events.where('reference_tx_hash', $tx->hash)->doesntExist() &&
        unmatched_collector_inflows.where('inflow_tx_hash', $tx->hash)->doesntExist()):
      // Webhook drop or ingestor crash; project now
      ProjectFeeFromChainEvent::dispatch($tx)

  $collector->update(['last_full_scan_block' => $scan_to])
```

Acceptance: "Hourly scan ensures `revenue_events` completeness against chain history within 60min of finalisation."

### `unmatched_collector_inflows` audit table

Defensive: under normal operation, every collector inflow matches a `quotes.user_op_hash`. Defensive cases:

```sql
CREATE TABLE unmatched_collector_inflows (
  id CHAR(36) NOT NULL PRIMARY KEY,
  chain VARCHAR(32) NOT NULL,
  collector_address VARCHAR(64) NOT NULL,
  inflow_tx_hash CHAR(66) NOT NULL,
  inflow_amount_asset VARCHAR(64) NOT NULL,
  asset VARCHAR(16) NOT NULL,
  observed_at TIMESTAMP NOT NULL,
  resolved_at TIMESTAMP NULL,
  resolution_note TEXT NULL,
  created_at TIMESTAMP NOT NULL,
  UNIQUE KEY uniq_unmatched_inflow (chain, inflow_tx_hash)
);
```

Behaviour: ingestor sees inflow → no `user_op_hash` match in `quotes` → write here, NOT to `revenue_events`. Page ops at >€10. Under steady-state operation this table should be empty; non-empty rows are signal.

### PSP outbox — off-chain projection

```sql
CREATE TABLE revenue_outbox_events (
  id CHAR(36) NOT NULL PRIMARY KEY,
  source_event_id VARCHAR(255) NOT NULL,                   -- Stripe event.id, Apple originalTransactionId, etc.
  source_type ENUM('stripe', 'apple_iap', 'google_play', 'ondato', 'stripe_bridge') NOT NULL,
  event_kind VARCHAR(64) NOT NULL,                         -- e.g. 'invoice.payment_succeeded'
  payload JSON NOT NULL,
  status ENUM('pending', 'delivered', 'failed') NOT NULL DEFAULT 'pending',
  attempts INT NOT NULL DEFAULT 0,
  delivered_at TIMESTAMP NULL,
  failed_reason TEXT NULL,
  created_at TIMESTAMP NOT NULL,
  UNIQUE KEY uniq_outbox_source (source_type, source_event_id),
  INDEX idx_outbox_pending (status, created_at)
);
```

Webhook handler writes outbox row in same transaction as `processed_webhook_events` dedup row. Worker (`ProjectRevenueOutbox` job) picks up pending rows, projects to `revenue_events`, marks delivered. Idempotent on `source_event_id` (the `UNIQUE` key prevents double-creation; the worker re-running on the same delivered row is a no-op).

```php
// Inside webhook handler (Stripe / IAP / Ondato — same pattern)
DB::transaction(function () use ($event, $sourceType) {
    if (ProcessedWebhookEvent::where('event_id', $event->id)->exists()) {
        return;   // Q7 dedup; idempotent replay
    }

    ProcessedWebhookEvent::create([
        'event_id' => $event->id,
        'source' => $sourceType,
        'received_at' => now(),
    ]);

    RevenueOutboxEvent::create([
        'source_event_id' => $event->id,
        'source_type' => $sourceType,
        'event_kind' => $event->type,
        'payload' => $event->data,
        'status' => 'pending',
    ]);
});
```

### §0.2 multi-connection saga rule — superseded for v1.3.0 fee path

The original §0.2 rule ("write fee on tenant + dispatch job to write revenue event on global") is superseded for v1.3.0 because:

1. On-chain fees: no tenant-connection write happens (non-custodial — chain debits the user's smart account directly)
2. Off-chain fees: PSP webhook handlers run on global; outbox is single-connection

**The saga rule is preserved for future flows that need cross-connection writes.** The multi-connection regression test in `tests/MultiConnection/` stays as a guard-rail. Add a comment to §0.2 noting the supersession.

### ADR-0002 + dichotomy documentation

The "future engineer will be confused by two upstream paths" risk is real. Mitigations:

- **`docs/adr/0002-revenue-projection-dual-upstream.md`** — captures the on-chain-vs-off-chain split, the reasoning, the dichotomy invariant
- **Comment block** at top of `database/migrations/*_create_revenue_events_table.php` referencing the ADR
- **Comment** in `App\Domain\RevenueAnalytics\Projectors\RevenueEventsProjector` noting the dual upstream

A future engineer reading the projector should see "this projector consumes BOTH `chain_ingestor.fee_observed` events AND `revenue_outbox_events.delivered` — see ADR-0002" and not be tempted to "unify" them by re-introducing the dropped saga.

### Schedule impact

| Item | Effort |
|---|---|
| Helius processor extension for fee-collector addresses | 0.5d |
| Alchemy processor extension for fee-collector addresses | 0.5d |
| Periodic chain-history scan job + finality threshold logic | 0.75d |
| `revenue_outbox_events` table + `ProjectRevenueOutbox` worker | 0.75d |
| `unmatched_collector_inflows` audit + ops alert | 0.25d |
| Drop original "RecordServiceFeeCharged" event-sourcing chain | **−1.0d** |
| Multi-connection regression test (kept as guard-rail; was already budgeted) | (no add) |
| ADR-0002 + dichotomy documentation | 0.25d |
| Tests for both upstream paths + finality replay scenarios | 0.5d |

Total ~2.75d new, ~1d reclaimed. **Net: +1.5d.**

### Acceptance

- Chain ingestor writes to `revenue_events` only after the funded transfer reaches finality per the chain-by-chain table
- Fee-collector inflow without matching `price_quotes.user_op_hash` writes to `unmatched_collector_inflows`, NOT `revenue_events`; ops alert at >€10
- Hourly chain-history scan ensures completeness within 60min of finalisation
- Webhook handlers (Stripe / IAP / Ondato) write `revenue_outbox_events` row in the same transaction as `processed_webhook_events` dedup
- `ProjectRevenueOutbox` worker idempotent on `(source_type, source_event_id)`
- §0.2 supersession note added to original handover via this deltas doc
- ADR-0002 exists and is referenced from the projector code + migration

---

## Pricing bounded context (Backend grilling, Q4)

> The original handover proposed `Domain/Quote` as a new bounded context with model `Quote`, aggregate `QuoteAggregate`, table `quotes`, endpoint `POST /api/v1/quote`. The dev's review identified this collides with **six existing Quote-named types** across `CrossChain`, `DeFi`, `Exchange`, and `Interledger` domains, and uses the most-overloaded noun in the codebase as the new domain's primary identifier. **Decision: rename to `Domain/Pricing` with aggregate `PriceQuote`, table `price_quotes`, internal foreign keys `price_quote_id`.** Wire contracts (mobile-facing JSON fields, error codes) stay stable to avoid forcing mobile rework; only the URL prefix and internal naming change.

### Why Pricing, not Quote

The existing Quote VOs (`BridgeQuote`, `CrossChainSwapQuote`, `SwapQuote`, `ExchangeRateQuote`) and the `QuoteService` in `Domain/Interledger` are all **upstream price snapshots** — stateless, sourced from external markets, valid for seconds. The new domain is **user-facing committed pricing** — stateful (`consumed_at`, bound to a `user_op_hash`), tier-aware (free vs Pro), replay-protected, and outlives the source quote (a single PriceQuote for a 5-minute send window encompasses many refreshed upstream quotes).

These are different concepts:

| Aspect | Upstream Quote VOs | New PriceQuote (Pricing) |
|---|---|---|
| Lifecycle | Stateless snapshot | Stateful aggregate |
| Lifetime | Seconds | Up to quote-window expiry (5min send / 30s swap / 60s ramp) |
| Source | External markets / oracles | This domain (composed from upstream) |
| Consumed | Read once for display | Single-consume invariant via `consumed_at` |
| Tier-aware | No | Yes (free vs Pro fee structure) |
| Replay-protected | No | Yes (Q2.2 signed-payload verification) |
| Mutates | No | Refresh creates new aggregate; old superseded |
| Dependency direction | Standalone | Pricing depends on these; never reverse |

`Pricing` describes the responsibility cleanly: this domain wraps upstream quote services and adds Zelta's tier-aware service fee — it's the user-facing pricing layer. Upstream domains are inputs; the dependency arrow runs `Pricing → Exchange/CrossChain/DeFi/StripeBridge`, never the other way.

### Wire-vs-internal naming asymmetry

Wire contracts stay stable to avoid forcing mobile rework on contracts the mobile deltas Q2/Q3 already established:

| Layer | Naming |
|---|---|
| Wire URL | `POST /api/v1/pricing/quote` (renamed from `/api/v1/quote`) |
| Wire JSON field (request body, response, FK in submission contract) | **`quoteId`** (unchanged) |
| Wire error codes | **`ERR_QUOTE_001` / `ERR_QUOTE_002`** (unchanged) |
| Internal PHP namespace | `Domain/Pricing/` |
| Internal aggregate class | `PriceQuote` |
| Internal DB table | `price_quotes` |
| Internal DB foreign keys | `price_quote_id` |
| Internal event-sourced table | `price_quote_events` (was `quote_events` in original) |
| Internal config file | `config/pricing.php` (was `config/fees.php`) |

This asymmetry is **deliberate**. ADR-0003 documents it. A future engineer who tries to "fix" the wire-side naming to match internal will create a mobile-breaking change for zero functional benefit. Equally, a future engineer who "fixes" the internal naming to match wire reintroduces the Quote overload.

### Internal call graph

```
PricingController::quote()
  → PriceQuoteIssuer::issue($request)
       → branches on $request->kind:
           - 'send'        → EvmUserOpPreparer / SolanaSendPreparer (gas estimate)
                             then Pricing\TierFeeResolver (service fee)
           - 'swap'        → Domain\DeFi\SwapQuoteService (upstream Uniswap/Curve)
                             then Pricing\TierFeeResolver (service margin)
           - 'ramp_buy'    → Domain\Ramp\SessionFactory (Stripe Bridge / Onramper quote)
                             then Pricing\TierFeeResolver (ramp margin)
           - 'ramp_sell'   → Domain\Ramp\SessionFactory
                             then Pricing\TierFeeResolver
       → assembles PriceQuote aggregate, persists to price_quotes
       → returns wire-formatted response (JSON shape per Q3.1)
```

The existing upstream quote VOs (`BridgeQuote`, `SwapQuote`, etc.) are **not** renamed and **not** moved. They remain in their domains and serve their domain-specific purposes (e.g., `Domain/DeFi/SwapQuoteService` still exposes its own non-tier-aware quote endpoint for advanced/B2B use). Pricing consumes them and produces `PriceQuote`.

### `config/fees.php` → `config/pricing.php`

Fee-tier definitions belong in the domain that owns pricing logic. Move from `config/fees.php` → `config/pricing.php`. Sample shape (extending the original §10.2):

```php
return [
    'tiers' => [
        'free' => [
            'tx_flat_eur_cents' => 20,
            'ramp_margin_bps' => 100,        // 1.00%
            'swap_margin_bps' => 50,         // 0.50%
        ],
        'pro' => [
            'tx_flat_eur_cents' => 5,
            'ramp_margin_bps' => 50,         // 0.50%
            'swap_margin_bps' => 20,         // 0.20%
        ],
    ],
    'fee_collector_addresses' => [
        'polygon' => env('FEE_COLLECTOR_POLYGON'),
        'base' => env('FEE_COLLECTOR_BASE'),
        // ... per Backend-Q2
    ],
    'fee_collector_migration_thresholds' => [   // Backend-Q2
        'daily_holding_eur_cents' => 500_000,
        // ...
    ],
    'sweep_destinations' => [   // Backend-Q2
        // ...
    ],
];
```

`Domain/Subscription` reads tiers via the existing `ResolveFeeTier` CQRS query handler (no logic change; query key unchanged). Acceptance: "Subscription-domain code never reads `config('pricing.tiers')` directly; goes through `ResolveFeeTier` query handler."

### Existing `GET /api/v1/quotes` (RampController) — left alone for v1.3.0

In-production endpoint listing user's recent ramp quotes. Renaming is a mobile-breaking change. **Strategy:**

- v1.3.0: leave as-is at `/api/v1/quotes`; adds no new behaviour
- v1.3.1: alias `GET /api/v1/pricing/ramp-quotes`; dual-serve for one release cycle; document the cutover in v1.3.1 release notes
- v1.4: cut over fully; deprecate `/api/v1/quotes`

Add to "v1.3.1 — EU launch follow-up" section of this deltas doc (see below).

### Mobile deltas patch

One-line URL change in `MOBILE_HANDOVER_PLAN_B_REVIEW_DELTAS.md`:

- `POST /api/v1/quote` → `POST /api/v1/pricing/quote` (everywhere it appears in mobile deltas — primary in §Q2 deltas section, plus a few cross-references)

All other wire contracts unchanged. Mobile dev gets a single string-replace task.

### ADR-0003

`docs/adr/0003-pricing-bounded-context.md` — the dev will draft. Captures:

- (α) / (β) / (γ) decision and reasoning
- "Stateless market snapshot vs stateful committed pricing" insight
- Wire-vs-internal naming asymmetry (the load-bearing convention)
- `/api/v1/quotes` deprecation plan
- Forward extension space (`PriceTier`, `PriceQuoteSuperseded`, etc.)

Required reading before anyone modifies `Domain/Pricing` or proposes "unifying" upstream Quote VOs into it.

### Schedule impact

Spec rename pass is the primary cost. Mostly text editing across deltas + original handover; PHP class/table/config naming flows naturally because no code is written yet.

| Item | Effort |
|---|---|
| Spec rename pass (deltas doc + original handover §3, §10.2) | 0.25d |
| Mobile deltas one-line URL patch | (~5min, no separate budget) |
| ADR-0003 drafting | 0.25d |

Total ~0.5d, absorbed within the existing §3 + §10 budgets. **Net: 0d.** Backend total stays at **45 engineer-days**.

The rename is the cheapest architectural insurance policy in the project. Locking the right name now before any code is written costs nothing; locking it post-launch costs a deprecation cycle.

### Acceptance

- All deltas-doc references to `Domain/Quote` rewritten to `Domain/Pricing`
- All deltas-doc references to `quotes` table rewritten to `price_quotes`
- All deltas-doc references to `quote_events` rewritten to `price_quote_events`
- Wire URL `/api/v1/pricing/quote` documented in §3 of original handover via deltas; mobile deltas patched to match
- Wire field name `quoteId` and error codes `ERR_QUOTE_*` explicitly preserved (documented in ADR-0003 as deliberate)
- `config/pricing.php` exists with tier definitions; `config/fees.php` removed (fresh project, no migration needed)
- `ResolveFeeTier` query handler reads from `pricing.tiers`; no direct `config()` reads in subscription/entitlements code
- ADR-0003 exists and is referenced from `Domain/Pricing/README.md` (or domain-root namespace docblock)
- `/api/v1/quotes` (RampController) untouched in v1.3.0; v1.3.1 deprecation captured in follow-up section

---

## Trial-abuse card-fingerprint check (Backend grilling, Q5)

> Original §1.6 trial eligibility leans on `users.has_used_trial = false` plus a Stripe-subscriptions-history check. The Apple/Google paths enforce at the store-account level (settled). The Stripe path has a hole: a bad actor creates Zelta account A with card X, cancels, creates Zelta account B (different email), reuses card X, gets a new trial. **Decision: (α) Stripe `card.fingerprint` HMAC-hash + 12-month retry window.** Catches the casual-abuse 90%+ vector without ongoing Stripe Radar Plus cost.

### Why (α), not (γ) defer-and-monitor or (β) Radar

- **(γ) defer**: defensible at v1.3.0 traffic volumes (sub-€500/year exposure at 1k trials/month × 1–3% abuse rate), but loses detection capability. Without the fingerprint table we can't quantify abuse — only see it indirectly in conversion funnels. The metric-without-prevention version still needs the table; might as well wire the rejection path while we're there.
- **(β) Stripe Radar Plus**: ongoing per-screened-transaction cost; rule-writing surface; Stripe vendor dependency for what's essentially a hash lookup. Better fit if abuse rate climbs above ~5% of trial population in v1.3.1+; not warranted at v1.3.0 baseline.
- **(α) fingerprint check**: ~2d engineering, no ongoing cost, catches casual abuse, doesn't elevate PCI scope (fingerprint isn't PAN; SAQ A unchanged), industry-standard pattern.
- **(δ) hybrid**: overkill for v1.3.0; reconsider in v1.3.1 if (α)'s detection signal shows sophisticated abuse beyond what fingerprinting catches.

### Honest about (α)'s limits

Document explicitly in deltas + ADR notes (no separate ADR — borderline-worthy, not load-bearing):

| Abuse pattern | Caught by (α) |
|---|---|
| Same user, one card, two Zelta accounts | ✓ |
| Same user, two cards, two Zelta accounts | Caught only when card #1 reappears (multi-card abusers are tiny population) |
| Virtual cards (Revolut Disposable, Privacy.com) generating fresh fingerprints | ✗ — trivial bypass (escalate to Radar in v1.3.1+ if pattern emerges) |
| Different Stripe Customers from different cards | ✗ — untraceable (no signal exists) |

If sophisticated abuse rises above 5% of trials in production telemetry, escalate to (β) Radar Plus rules. The metric (`trial_block_rate`, `false_positive_rate`, multi-fingerprint cluster detection) is what tells us when.

### `trial_card_fingerprints` schema

```sql
CREATE TABLE trial_card_fingerprints (
  fingerprint_hash CHAR(64) PRIMARY KEY,                 -- HMAC-SHA256(card.fingerprint, TRIAL_FINGERPRINT_PEPPER)
  first_used_at TIMESTAMP NOT NULL,
  last_used_at TIMESTAMP NOT NULL,
  trial_user_count INT UNSIGNED NOT NULL DEFAULT 1,
  stripe_payment_method_id VARCHAR(255) NULL,             -- last seen; for support-only debugging
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_fingerprint_last_used (last_used_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Pepper management — simple-pepper pattern

`TRIAL_FINGERPRINT_PEPPER` is a 32-byte env-var secret. **Single env-var, event-triggered rotation only, no scheduled cron, no version column for v1.3.0.** Same pattern as `TELEMETRY_USER_PSEUDONYM_SALT` (see "v1.3.0 simple-pepper pattern" in closing notes). Rotation = re-hash all rows in one transaction (table will be small at v1.3.0 — 100k rows is sub-second).

If rotation becomes routine in v1.3.1+, add `pepper_version` column + dual-pepper grace period.

### Setup-mode Checkout flow (option c)

The dev's original 1.75d estimate assumed the user has a `default_payment_method` already, which is false for first-time users. Three viable flows considered:

- **(a)** Two-step with SetupIntent — cleanest UX, +0.5d
- **(b)** Subscription-mode Checkout, check fingerprint in webhook, cancel if abuse — awkward UX (subscription appears for ~5s then disappears)
- **(c)** Setup-mode Checkout, backend creates Subscription with captured PM after fingerprint check — reuses Cashier infrastructure

**Decision: (c).** Reuses Cashier (Backend-Q1 γ) `Cashier::createSetupIntent()` + chained subscription creation. +0.25d engineering vs the original estimate.

```
POST /api/v1/subscription/checkout flow:
  1. Backend creates Stripe Checkout Session in 'setup' mode (capture PM only, no charge)
  2. User completes Checkout (Stripe-hosted card form)
  3. checkout.session.completed webhook → backend has PaymentMethod ID + card.fingerprint
  4. Hash fingerprint with HMAC-SHA256(fingerprint, TRIAL_FINGERPRINT_PEPPER)
  5. Acquire Redis lock keyed on fingerprint_hash, 5s acquire timeout, 30s TTL
       → on lock acquire timeout: return 503 retry-after (legitimate same-user retries resolve in <1s; abuse races fail fast)
  6. Lookup trial_card_fingerprints: if hit AND last_used_at + 12mo > now()
       → return 403 ERR_SUB_003 + { eligibleAfter, code, message }
       → release lock; do NOT create subscription
  7. Else: backend creates Stripe Subscription with trial_period_days: 7, attaching captured PaymentMethod
  8. On customer.subscription.created webhook with trial_started_at IS NOT NULL:
       INSERT or UPDATE trial_card_fingerprints with the fingerprint_hash + payment_method_id
  9. Release lock
```

### `ERR_SUB_003` response shape

```json
{
  "code": "ERR_SUB_003",
  "message": "Trial already used on this card.",
  "eligibleAfter": "2027-05-09T00:00:00Z"
}
```

Mobile renders as: *"You've used a trial within the last 12 months. Try Pro for €4.99/mo or come back next May."* Falls back to the paid CTA. Add to mobile deltas Q6 conflict-screen list as a new state — not a "conflict" per se, but a structurally similar one-CTA error screen.

### 12-month retry window — adjustment to spec §1.6

Original spec said `eligibleAfter: null` (lifetime block). **Lifetime is harsh and probably wrong** — a user who cancelled 3 years ago and wants to re-trial Pro shouldn't be permanently blocked. Adjust §1.6 of original handover to `eligibleAfter: <last_used_at + 12 months>`. Twelve months matches industry SaaS retrial pattern.

### Filament admin override

Subscription → Trial Block Override resource. Requires admin role + 2FA. Audit-logged. Use case: support sees a legitimate user (cardholder re-tokenization, family sharing the same card across distinct Zelta accounts, etc.) and manually unblocks. Inserts a row in `audit_logs` with `event_type: 'trial_block_overridden'`, `reason: <support note>`, `overridden_by: admin_user_id`.

### Daily purge cron

`php artisan trial:purge-fingerprints` daily — drops rows where `last_used_at < now() - 18 months` (gives 6 months past the 12mo retry window for support diagnostics). Same pattern as the investor inquiry cleanup.

### Monitoring + metrics

Filament dashboard widget:

- `trial_block_rate` = blocked trials / total trial requests (weekly). Alert at >5% (real abuse OR false-positive bug).
- `false_positive_rate` = blocked-then-converted-on-different-account / blocked. Alert at >10% (12mo window may be too aggressive).
- Weekly cohort breakdown: block rate by signup week, to catch abuse waves early.

### Privacy / PCI / GDPR

- **PCI scope unchanged** — `card.fingerprint` is not PAN; storing the HMAC adds nothing to SAQ A scope.
- **Privacy policy update** — one line under "Fraud prevention": *"We use a hashed identifier derived from your payment card to prevent repeated trial abuse on the same card. The hash uses a server-side internal key as a technical safeguard."*
- **GDPR** — fingerprint hash is fraud-prevention data; legitimate interest under Art 6(1)(f). Right to erasure satisfied via `/me/data-deletion` (deletes fingerprint row alongside other personal data per Backend-Q6 expanded sequence).

### Schedule impact

| Item | Effort |
|---|---|
| Schema migration + model + repository | 0.25d |
| Setup-mode Checkout integration (option c) | 0.25d |
| Fingerprint hashing + lookup + Redis lock with acquire timeout | 0.25d |
| Webhook handler: record fingerprint on `customer.subscription.created` with trial_started_at | 0.25d |
| Filament admin override (Subscription → Trial Block Override) | 0.25d |
| Daily purge cron (>18mo cleanup) | 0.1d |
| Monitoring widget + alerts | 0.15d |
| Privacy policy "Fraud prevention" line | 0.1d |
| Tests (positive flow, blocked flow, lock contention, override path) | 0.5d |

Total ~**2d**. Backend total: 45d → 47d.

### Acceptance

- `trial_card_fingerprints` table created with the schema above
- `TRIAL_FINGERPRINT_PEPPER` env var configured; rotation event-triggered only (no scheduled cron)
- `POST /api/v1/subscription/checkout` uses Setup-mode Checkout flow per the diagram above
- Redis lock acquire timeout 5s; loser returns 503 retry-after, not blocking
- `ERR_SUB_003` returned with `eligibleAfter` for blocked trials
- Filament admin override exists; requires admin role + 2FA; audit-logged
- Daily purge cron deletes rows older than 18 months
- Filament widget tracks `trial_block_rate` and `false_positive_rate`
- Privacy policy includes the fraud-prevention line
- §1.6 of original handover updated: `eligibleAfter: null` → `eligibleAfter: <last_used_at + 12mo>`

---

## Pseudonymisation framing correction (Backend grilling, Q6)

> Deltas Q15.1 introduced "pseudonymisation salt on every device" as the architecture for telemetry user-identifier hashing. The dev's review identified this fails GDPR Art 4(5)'s "kept separately" test — if the salt sits in the user's SecureStore, the linking key is inside the user's environment, not separated from it. **Decision: (δ) — drop the pseudonymisation framing entirely. Document accurately as "personal data with technical safeguards (server-side internal hashing salt)."**

### Why (δ), not (α)/(β)/(γ)

- **(α) Server-derived pseudonyms** (mobile sends raw user_id → backend hashes → forwards to vendor): real GDPR pseudonymisation, but puts backend on the critical path for every telemetry event. Breaks crash reporting (Sentry must work when backend is down — that's its job).
- **(β) Server-issued per-session pseudonyms**: real pseudonymisation but the mapping table IS the linkable PII, requires its own GDPR protection, breaks cross-session analytics.
- **(γ) Two-stage hybrid (mobile pseudo_d + backend pseudo_v)**: technically clean but solves a problem we don't have at v1.3.0 telemetry volume.
- **(δ) Document accurately**: the "pseudonymisation" claim wasn't doing operational work. All GDPR rights apply either way; vendor contracts are already Art 28; transfers are SCC/DPF. We weren't relying on the pseudonymisation framing to skip any compliance step. Calling it accurately reduces legal exposure (false-claim risk) at zero engineering cost.

### Why we don't call this pseudonymisation

GDPR Art 4(5):

> *"'pseudonymisation' means the processing of personal data in such a manner that the personal data can no longer be attributed to a specific data subject without the use of additional information, provided that such additional information is **kept separately** and is subject to technical and organisational measures..."*

The "kept separately" phrase is load-bearing. In our v1.3.0 architecture, the salt is distributed to mobile devices via authenticated session and lives in SecureStore alongside the user_id. A device compromise yields salt → hash-of-user-id → user mapping. Structurally this is hashing-with-shared-secret, not pseudonymisation. Future engineers reading this section: don't re-introduce "pseudonym" / "pseudonymisation" language without first migrating to (α)/(β)/(γ) architecture that actually keeps the salt separate from the data subject.

### What changes if it's not pseudonymisation

Almost nothing operationally — we already treat telemetry as personal data:

| Aspect | Status |
|---|---|
| Hashed identifiers in telemetry remain personal data | Already treated this way |
| GDPR rights (access, erasure, portability) apply to telemetry events | Already in scope via `/me/data-deletion` |
| Vendor agreements as Art 28 processor contracts | Already SCC/DPF for Firebase, Sentry, Stripe etc. |
| International transfers under SCC / DPF | Already in place |
| Breach notification thresholds | Already applied |
| DPIA classification | **Needs refresh — was aspirational language** |

The bug is in the language, not the operational posture. Privacy policy / DPIA / deltas Q15.1 need accurate framing.

### Language pass

Replace throughout deltas, internal docs, and code comments:

| Before | After |
|---|---|
| "pseudonym" / "pseudonymise" | "hashed identifier" / "hash" |
| "pseudonymisation" | "internal hashing safeguard" |
| "device-keyed identifier" | (drop — implies per-device when salt is actually shared) |
| "per-device key" | "shared internal hashing salt" |

`TELEMETRY_USER_PSEUDONYM_SALT` env var name is preserved (env vars rarely rename), with a code comment in the env-loader explaining the historical name does not reflect current legal framing.

### Privacy policy text

Replaces any "pseudonymous analytics" framing with:

> *"Analytics: We use [Firebase, Sentry] to monitor app stability and product usage. Events sent to these vendors are keyed on a hashed identifier derived using an internal technical safeguard. Events remain personal data; you can request access or deletion via [contact form] or in-app under Settings → Privacy → Delete my data."*

Drops "per-device" framing entirely (the salt is shared, not per-device). User-facing copy doesn't need to specify the hashing mechanism's distribution model.

### DPIA refresh

Add to data-protection-impact-assessment doc:

> *"Telemetry data classification — user-keyed personal data with internal hashing safeguard (NOT pseudonymisation under GDPR Art 4(5)). Lawful basis split: crash reports under legitimate interest (Art 6(1)(f)) — necessary for service stability and security; product analytics under consent (Art 6(1)(a)) per the in-app consent banner. Right to object satisfied via the analytics opt-out in Settings → Privacy. Right to erasure satisfied via vendor delete APIs called from `/me/data-deletion` (see Backend-Q6 vendor_data_deletions audit table)."*

Lawful-basis declaration is what regulators look for first; this is what's missing in the original Q15.1 framing.

### `vendor_data_deletions` audit table

Backend-Q6 expands `/me/data-deletion` (deltas Q15.2) to orchestrate vendor deletion calls and track completion. Vendor APIs are async (Firebase Analytics deletion takes up to 72h; Sentry faster).

```sql
CREATE TABLE vendor_data_deletions (
  id CHAR(36) NOT NULL PRIMARY KEY,
  deletion_request_id CHAR(36) NOT NULL,                -- FK to data_deletion_requests
  vendor VARCHAR(64) NOT NULL,                          -- 'firebase_analytics', 'sentry', 'mixpanel', etc.
  vendor_user_identifier VARCHAR(255) NOT NULL,         -- the hashed identifier sent to vendor
  status ENUM('pending', 'requested', 'completed', 'failed', 'unsupported') NOT NULL,
  vendor_request_id VARCHAR(255) NULL,                  -- vendor's tracking ID if returned
  requested_at TIMESTAMP NOT NULL,
  completed_at TIMESTAMP NULL,
  error_message TEXT NULL,
  created_at TIMESTAMP NOT NULL,
  INDEX idx_vendor_deletions_request (deletion_request_id),
  INDEX idx_vendor_deletions_pending (status, requested_at)
);
```

### `/me/data-deletion` updates

- Returns **202 Accepted** (not 200) with `request_id` — settlement is async
- Orchestrates per-vendor delete calls: Firebase user-data-deletion API, Sentry user-deletion endpoint, Mixpanel `delete_user`, etc.
- Hourly cron polls vendor APIs (where supported) for completion status, updates audit table
- User receives confirmation email when all `vendor_data_deletions` rows reach `status: 'completed'` OR after 7 days have elapsed (whichever first)
- The 7-day deadline matches typical regulator-expected response time even if a vendor is slow

### v1.3.1 follow-up: processor list publication

Some EU regulators expect a published list of data processors. Captured as a v1.3.1 follow-up (see v1.3.1 section): publish at `zelta.app/privacy/processors` with name, role, data categories, transfer mechanism, DPA link per processor.

### Schedule impact

| Item | Effort |
|---|---|
| Deltas Q15.1 language pass + "Why we don't call this pseudonymisation" sub-section | 0.25d |
| Privacy policy text + DPIA section update | 0.15d |
| `vendor_data_deletions` table migration + integration into `/me/data-deletion` | 0.1d |
| Hourly vendor-status poll cron stub | (deferred to v1.3.1; v1.3.0 ships with synchronous request, async poll TODO) |

Total ~**0.5d**, all documentation + small migration. Backend total: 47d → 47.5d.

### Acceptance

- All deltas-doc references to "pseudonym" / "pseudonymisation" replaced per the language-pass table above
- `TELEMETRY_USER_PSEUDONYM_SALT` env var retained with code comment explaining the historical name
- Privacy policy updated with accurate framing (no "per-device" or "pseudonymous" claims)
- DPIA includes lawful-basis split (legitimate interest for crash, consent for product analytics)
- `vendor_data_deletions` table created
- `/me/data-deletion` returns 202 + `request_id`, orchestrates per-vendor deletion calls
- Confirmation email triggered when all per-vendor rows complete OR after 7 days
- Processor list publication captured as v1.3.1 follow-up

---

## IAP receipt pseudonymisation (Backend grilling, Q7)

> Deltas Q15.2 originally said "delete IAP receipts" during the Article 17 erasure walk. The dev's review identified this contradicts the original handover's §4.5 pseudonymise-don't-delete pattern AND creates concrete operational/legal exposure (tax-law retention obligations, refund webhook orphans up to 180 days post-purchase, renewal webhook orphans for annual subs, internal-consistency fragmentation). **Decision: (α) — pseudonymise IAP receipts per §4.5, leveraging GDPR Art 17(3)(b) tax-law-retention exception.** Q15.2's "delete IAP receipts" language is a bug; this section is the in-place correction.

### Why (α), not (β)/(γ)/(δ)

- **(β) Hard-delete (Q15.2 as-written)**: Lithuania VATA Art 78 requires accounting records retained 10 years; Apple App Store tax docs require receipt retention for the audit window. Article 17(3)(b) explicitly permits non-erasure where Union/Member State law requires retention. Hard-delete creates legal exposure and breaks refund/renewal webhook matching post-erasure.
- **(γ) Move out of erasure scope** ("transactional records, not personal data"): wrong under GDPR. `originalTransactionId` is a linkable identifier; we processed it; it's PII.
- **(δ) Hybrid two-table split**: refund matching becomes harder; still requires hashed bridge ID — which is just (α) with extra steps.
- **(α) Pseudonymise** matches §4.5 pattern, satisfies Art 17(3)(b), preserves webhook-match capability via hash lookup, costs the same engineer-time as the wrong answer.

### Schema additions to `iap_receipts`

```sql
ALTER TABLE iap_receipts
  ADD COLUMN original_transaction_id_hash CHAR(64) NULL,
  ADD COLUMN scrubbed_at TIMESTAMP NULL,
  ADD COLUMN scrubbed_renewal_count INT NOT NULL DEFAULT 0,
  ADD INDEX idx_iap_receipts_scrubbed_hash (original_transaction_id_hash);
```

`original_transaction_id_hash = HMAC-SHA256(original_transaction_id, IAP_RECEIPT_PEPPER)`. Populated only on pseudonymisation; for non-scrubbed rows the column stays NULL and the raw `original_transaction_id` (with its `UNIQUE` constraint per deltas Q7.1) is the lookup key.

### Erasure walk replacement language for Q15.2

Drop "delete IAP receipts." Replace with:

> For each `iap_receipts` row owned by the user:
>
> - `user_id := NULL`
> - `original_transaction_id_hash := HMAC-SHA256(original_transaction_id, IAP_RECEIPT_PEPPER)`
> - `original_transaction_id := NULL` (raw destroyed; future webhooks match via hash lookup)
> - `apple_app_account_token := NULL` (Apple-only)
> - `google_obfuscated_account_id := NULL` (Google-only)
> - `receipt_blob := NULL` (raw JWS / JSON destroyed; transactional fields preserved in their own columns: tier, amount_eur_cents, period_start, period_end, etc.)
> - `scrubbed_at := NOW()`
>
> Audit row in `gdpr_erasure_log`: `(row_id, user_id, table='iap_receipts', scrubbed_at, request_id)`.

### Webhook handler updates for scrubbed receipts

Inbound Apple/Google webhook lookup logic, in priority order:

1. Look up by raw `original_transaction_id` (current behaviour, fast path for non-scrubbed rows)
2. If not found: hash inbound ID with `IAP_RECEIPT_PEPPER`; look up by `original_transaction_id_hash`
3. If found AND `scrubbed_at IS NOT NULL`:
   - **REFUND:** insert `closed_account_refunds` row (Q9.4 table extended with `source` ENUM — see below); no user notification (user is gone); reconciles against PSP reports per §4.4
   - **RENEWAL with `scrubbed_renewal_count == 0`:** drop + alert ops "first stale renewal post-erasure for receipt X" (likely Apple race — RENEWAL fires after CANCELLATION when cancellation was processed during the renewal window); increment `scrubbed_renewal_count`
   - **RENEWAL with `scrubbed_renewal_count > 0`:** drop silently + log "recurring stale renewal post-erasure"; increment counter; if counter `> 3`, page ops (real cancel-failure post-erasure — user's payment method is being charged with no service)
   - **Other status changes:** log + drop

Identifiers we set ourselves (`appAccountToken`, `obfuscatedAccountId`) are NOT used for post-erasure lookup — they're nulled at scrub time and the store-issued `original_transaction_id` is the canonical match key.

### Erasure flow ordering (full sequence, supersedes Q15.2's draft)

a. Cancel all active subscriptions (Stripe via Cashier `cancel()`; IAP via store APIs) — MUST be before pseudonymisation
b. Refund any pending card deposit (deltas Q9.4)
c. Pro-rata refund any annual subscription where store permits (Stripe: `proration_behavior: 'create_prorations'` returns credit; Apple/Google: best-effort via `Refund Request V2 API` / `Voided Purchases API`, store-discretion-bound)
d. Pseudonymise `iap_receipts` rows (this Q's change)
e. Pseudonymise `users` row per §4.5
f. NULL `revenue_events.user_id` per §4.5 (keep `user_id_hash` for cohort analytics)
g. Rewrite `subscription_consent_log.consent_text` to scrubbed copy + NULL identifying columns
h. Rewrite `subscription_events.event_payload` per §4.5
i. Stripe customer delete (`$user->asStripeCustomer()->delete()`) per Backend-Q1
j. Trigger vendor data deletion (Firebase, Sentry, etc.) async per Backend-Q6 `vendor_data_deletions` table
k. Final `users` row state: `email := 'erased+<hash>@finaegis.invalid'`, `name := 'Erased User'`, etc. — placeholder values keeping the row valid for FK references that aren't NULL-able (e.g., historical `audit_logs.user_id`)
l. Return 202 Accepted + `request_id`; confirmation email within 7d (or "investigation underway" email if step (a) escalates per gotcha below)

### Edge case: subscription cancellation fails during erasure

If step (a) fails for an active subscription:

- Don't proceed with pseudonymisation (queue for retry)
- Mark `data_deletion_requests.status := 'pending_subscription_cancel'`
- Background job retries the cancel for up to 7 days, then escalates to ops
- User gets confirmation email after the full sequence settles, OR an "investigation underway" email at 7d if it doesn't settle

This is the only path where the user-facing 7-day confirmation slips — defensible because we're not in control of Apple/Google's API uptime.

### `closed_account_refunds` extension

Q9.4's `closed_account_refunds` table currently tracks Stripe card-deposit refunds. Backend-Q7 extends it for post-erasure IAP refunds with a `source` ENUM (single table, sparse columns; simpler than a sibling table for the volume this sees):

```sql
ALTER TABLE closed_account_refunds
  ADD COLUMN source ENUM('stripe_card_deposit', 'apple_iap', 'google_play') NOT NULL DEFAULT 'stripe_card_deposit',
  ADD COLUMN apple_original_transaction_id_hash CHAR(64) NULL,
  ADD COLUMN google_purchase_token_hash CHAR(64) NULL,
  ADD INDEX idx_closed_refunds_iap_hash (apple_original_transaction_id_hash),
  ADD INDEX idx_closed_refunds_google_hash (google_purchase_token_hash);
```

Single ops dashboard view, single reconciliation path against §4.4 PSP reports. New rows from IAP refund webhooks for scrubbed users carry the hash identifier (since raw `original_transaction_id` is gone).

### `IAP_RECEIPT_PEPPER` — third simple-pepper instance, with ONE-WAY ROTATION caveat

`IAP_RECEIPT_PEPPER` joins the simple-pepper convention from Backend-Q5/Q6 as the third instance. **But unlike `TRIAL_FINGERPRINT_PEPPER` and `TELEMETRY_USER_PSEUDONYM_SALT`, this one cannot be rotated cleanly:**

- For `TRIAL_FINGERPRINT_PEPPER`: rotation = re-hash all rows (raw fingerprint accessible via Stripe). Cheap.
- For `TELEMETRY_USER_PSEUDONYM_SALT`: rotation impacts vendor-side identifier continuity but doesn't break our internal lookups.
- For `IAP_RECEIPT_PEPPER`: rotation is **destructive**. The raw `original_transaction_id` was nulled at scrub time; we can't re-hash what we don't have. Once rotated, every previously-scrubbed row's `original_transaction_id_hash` becomes orphaned — future webhooks for those receipts won't match anything.

Consequences are bounded:
- Tax retention: unaffected (transactional fields still present on each row)
- Refund webhook for previously-erased user during rotation window: orphans → unmatched-webhook ops queue → manual resolution
- Internal accounting: one-off refund anomaly per affected receipt

**Operational rule:** rotate `IAP_RECEIPT_PEPPER` only on confirmed compromise; expect manual ops resolution for refund webhooks during the rotation window. Code comment in env loader makes this explicit:

```php
// IAP_RECEIPT_PEPPER: One-way rotation. Previously-scrubbed receipts cannot
// be re-hashed because the raw original_transaction_id is destroyed at scrub
// time. Rotate only on confirmed compromise; expect manual ops resolution
// for refund/renewal webhooks during the rotation window.
// See Backend-Q7 in docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md.
```

### 10-year retention horizon

Pseudonymised IAP receipts persist for tax-retention compliance. Lithuania VATA Art 78 baseline: 10 years. Most EU markets: 6–10 years. US (when v1.3.0 EN-market list expands per Q16): typically 7 years.

**Document a 10-year retention floor.** v1.4+ purge cron drops rows where `scrubbed_at < now() - 10 years`. **Defer the cron to v1.4** — first scrubbed receipts won't hit the 10-year horizon until 2036; no urgency.

### Privacy policy / DPIA addition

Privacy policy paragraph (Art 17(3)(b) tax-law-retention disclosure):

> *"Right to erasure (Art 17): we honour erasure requests fully for personal identifiers (name, email, billing address, login data). Where we have legal obligations to retain transactional records — tax law in your jurisdiction (typically 5–10 years), subscription dispute records with payment processors (typically 2 years), audit obligations to Apple / Google for in-app purchases — we pseudonymise rather than delete those records. Your personal identifiers are removed; transactional history remains as anonymous financial records as required by applicable law.*
>
> *Where you cancel mid-period via account deletion: subscriptions paid through Stripe are pro-rata refunded for unused time. Subscriptions paid through Apple App Store or Google Play follow each store's refund policy — we initiate refund requests on your behalf but final refund discretion rests with Apple or Google. You may also request a refund directly via the App Store / Google Play app.*
>
> *To request erasure: in-app under Settings → Privacy → Delete my data, or email privacy@finaegis.com."*

DPIA addition: classify pseudonymised IAP receipts as "transactional records retained under Art 17(3)(b) Member State legal obligation"; lawful basis for retention is Art 6(1)(c) (legal obligation).

### Schedule impact

| Item | Effort |
|---|---|
| Schema migration (`original_transaction_id_hash`, `scrubbed_at`, `scrubbed_renewal_count`, indexes) | 0.25d |
| Erasure walk implementation in `/me/data-deletion` handler | 0.25d |
| Webhook handler updates (raw → hash fallback, scrubbed-receipt branch with REFUND vs RENEWAL race-vs-failure logic) | 0.2d |
| `closed_account_refunds` `source` extension + migration | 0.05d |
| Documentation: privacy policy + DPIA + deltas Q15.2 in-place fix + simple-pepper convention update | 0.1d |

Total ~**0.75d** (slightly above the dev's initial estimate due to `scrubbed_renewal_count` + `closed_account_refunds` extensions; absorbed at ~0.85d under generous estimate). Backend total: 47.5d → **48.25d**.

### Acceptance

- `iap_receipts` schema additions present (hash column + scrubbed_at + scrubbed_renewal_count)
- Erasure walk pseudonymises IAP receipts per the language above; raw `original_transaction_id` is nulled
- Webhook lookup priority: raw → hash; `appAccountToken` and `obfuscatedAccountId` NOT used post-erasure
- Scrubbed-receipt REFUND webhook inserts `closed_account_refunds` row with `source = 'apple_iap'` or `'google_play'`
- Scrubbed-receipt RENEWAL webhook: first occurrence drops + alerts ops; recurring drops silently; >3 pages ops
- Cancel-failure during erasure does NOT proceed to pseudonymisation; queues retry; escalates at 7d
- `IAP_RECEIPT_PEPPER` env var configured; code comment in env loader documents one-way rotation caveat
- `closed_account_refunds` table extended with `source` ENUM and per-source identifier columns
- 10-year retention floor documented; v1.4+ purge cron explicitly deferred
- Privacy policy + DPIA paragraphs updated per the language above
- Webhook hash-lookup tested with both raw and pseudonymised receipts in a single test fixture

---

## Cue dispatch architecture (Backend grilling, Q8)

> Deltas Q5.5 + Q11.2 schedule four time-from-event cues (`trial_ending_2d` / `_1d` / `_1h`, `pro_trial_reminder_d1`) plus aggregate-condition cues (`kyc_required`, `payment_failed`, `family_sharing_unsupported`). The deltas didn't specify the iteration pattern. The dev's review identified that **time-from-event cues and aggregate-condition cues should use different patterns** — and that windowed-cohort cron has no recovery for missed cohorts. **Decision: (γ) hybrid — Laravel delayed queued jobs for time-from-event cues, windowed cron with `LEFT JOIN` candidate query for aggregate-condition cues.**

### The dichotomy

| Cue type | Trigger | Best pattern |
|---|---|---|
| **Time-from-event** (`pro_trial_reminder_d1`, `trial_ending_*`) | Deterministic offset from a known source event (onboarding completion, trial start) | Laravel delayed job dispatched at source-event time |
| **Aggregate-condition** (`kyc_required` and any future condition-driven cue) | Evolving condition (lifetime spend ≥ €1,000; etc.) | Daily/hourly windowed cron with `LEFT JOIN` against `cues` table |
| **Webhook-driven** (`payment_failed`, `family_sharing_unsupported`, `refund_processed`, `subscription_canceled_external`, `export_ready`, `grace_period_started`) | External event arrival | Direct cue insert from webhook handler (already covered in Q5/Q6/Q7/Q9/Q13) |

Mismatched patterns produce real failure modes: windowed cron for time-from-event cues loses entire cohorts on a single failed run; queued jobs for aggregate-condition cues require source-event hooks that don't naturally exist.

### Why (γ), not (α) or (β)

- **(α) Windowed-cohort cron for everything (deltas-implied)**: a one-day cron miss permanently loses the cohort that should have fired in that window. Year-1 with 1–2k onboarding events daily means a single failed cron loses ~1–2k cues. Detectable but unrecoverable. The deltas Q5.2 server-side reaping safety net only addresses over-firing (suppress cues whose precondition is now false), not under-firing.
- **(β) Persistent worklist for everything**: writes `cue_candidates` rows at source events; cron processes them, marks processed. Durable. But this is exactly what Laravel's `jobs` table already gives us when using the database queue driver — the candidate row IS the job row. Reinventing it is custom infra without coverage benefit.
- **(γ) Hybrid**: aligns each cue type with its natural pattern. Failure recovery is structural — Laravel queue workers retry transient failures, permanent failures land in `failed_jobs` for ops review. Aggregate-condition cron failures are recoverable via the next-run query (idempotent per Q5.4).

### Time-from-event job pattern

```php
class EnqueueProTrialReminderD1 implements ShouldQueue {
    public function __construct(public readonly string $userId) {}

    public function handle(EntitlementsService $entitlements, CueRepository $cues): void {
        $user = User::find($this->userId);

        if (! $user) {
            Metric::increment('cue.job.skipped', ['kind' => 'pro_trial_reminder_d1', 'reason' => 'user_erased']);
            return;
        }
        if ($user->pro_marketing_opt_out) {
            Metric::increment('cue.job.skipped', ['kind' => 'pro_trial_reminder_d1', 'reason' => 'opted_out']);
            return;
        }

        $ent = $entitlements->for($user);
        if ($ent->tier !== 'free') {
            Metric::increment('cue.job.skipped', ['kind' => 'pro_trial_reminder_d1', 'reason' => 'tier_changed']);
            return;
        }
        if (! $ent->trialEligible) {
            Metric::increment('cue.job.skipped', ['kind' => 'pro_trial_reminder_d1', 'reason' => 'trial_used']);
            return;
        }

        $cues->createIdempotent($user, 'pro_trial_reminder_d1', /* payload + occurrence_window */);
        Metric::increment('cue.job.success', ['kind' => 'pro_trial_reminder_d1']);
    }
}

// Dispatch at source event:
class OnboardingCompletedListener {
    public function handle(OnboardingCompleted $event): void {
        EnqueueProTrialReminderD1::dispatch($event->userId)
            ->delay(now()->addDay());
    }
}
```

Four job classes follow the same shape:

| Job class | Source event | Delay |
|---|---|---|
| `EnqueueProTrialReminderD1` | `OnboardingCompleted` | 24h |
| `EnqueueTrialEnding2d` | `SubscriptionTrialStarted` | trial_start + 5d |
| `EnqueueTrialEnding1d` | `SubscriptionTrialStarted` | trial_start + 6d |
| `EnqueueTrialEnding1h` | `SubscriptionTrialStarted` | trial_start + 7d − 1h |

Job-fire-time eligibility re-evaluation is the equivalent of Q5.2 server-side reaping applied at dispatch time instead of at GET time. Both layers stay; the job-side check prevents writing a cue that would be reaped a second later. **Both alone are sufficient for correctness in steady state; both together cover concurrent-state-change races.**

### Aggregate-condition cron pattern

For `kyc_required` (and any future condition-driven cue):

```sql
-- Run hourly via App\Console\Commands\DispatchKycRequiredCues
SELECT u.id AS user_id
  FROM users u
  LEFT JOIN cues c
    ON c.user_id = u.id
   AND c.kind = 'kyc_required'
   AND c.dismissed_at IS NULL
 WHERE u.lifetime_spend_cents >= 100000
   AND u.kyc_completed_at IS NULL
   AND c.id IS NULL
```

`LEFT JOIN ... WHERE c.id IS NULL` rather than `NOT IN (SELECT ...)` — the subquery form degrades on the cues table at scale. Iterate via `chunkById(1000)` for memory isolation.

Required indexes:
- `users(lifetime_spend_cents, kyc_completed_at)` — composite, candidate filter
- `cues(user_id, kind, dismissed_at)` — already exists per Q5

Cron cadence: hourly. The AMLD5 €1,000 threshold isn't second-sensitive — users approaching €1k usually have a few transactions of headroom.

### Cancel-on-state-change — self-cancel pattern

Some state changes during the delay window (user converts to paid mid-trial; opts out; deletes account) make the queued job's cue inappropriate. The job re-evaluates at fire time and self-cancels via the early-return + `cue.job.skipped` metric. **No proactive cancellation via `Bus::findBatch()`** — the wasted job-run is harmless and the implementation is simpler.

Per cancelled trial, three jobs (`trial_ending_2d` / `_1d` / `_1h`) fire and self-cancel. At a 30% cancel rate, that's 0.9 wasted job-runs per trialing user. Marginal at v1.3.0 traffic.

### Time zones — UTC throughout backend

UTC dispatch is fine because **cues are pulled, not pushed**. The cue dispatch time = "when the cue becomes available for the user to see in-app on next foreground." The user opens the app at 9am their time → sees the cue then. Mobile renders `due_at` (UTC) relative to user's current time ("ends in 2 days" / "ends tomorrow"), not relative to dispatch time.

Push notifications for cues (deltas Q5 has push as the fast path, cue queue as the durable path) are governed by the existing notification infrastructure's quiet-hours logic — orthogonal to cue dispatch.

### Database queue driver — locked for v1.3.0

Production queue driver is **`database`**. Laravel's database driver is durable across worker restarts and Redis crashes. For cue dispatch, durability > latency. At 100k MAU × 4 cue job kinds = ~400k pending rows steady-state; well within MySQL InnoDB capacity, and `SELECT ... FOR UPDATE SKIP LOCKED` handles contention.

**Migration trigger** documented for future engineer:

> Migration to dedicated infrastructure (Redis with AOF persistence, SQS/SNS, or Horizon-with-Redis) triggered when sustained queue throughput exceeds 100 jobs/sec for >1h, OR when `jobs` table size exceeds 1M rows steady-state. Neither threshold is reachable at v1.3.0 traffic; defer the decision.

### Observability metrics

Per-job-kind metrics emitted to Sentry + Filament dashboard:

| Metric | Purpose |
|---|---|
| `cue.job.dispatched.{kind}` | Count of jobs queued (sanity vs source-event count) |
| `cue.job.success.{kind}` | Count of successful cue inserts |
| `cue.job.skipped.{kind}.{reason}` | Count of no-ops by reason: `user_erased` / `tier_changed` / `opted_out` / `trial_used` |
| `cue.job.failed.{kind}` | Count of exceptions; alert at >1% failure rate |
| `cue.job.duration.{kind}` | p50/p95/p99 latency (eligibility check + insert) |
| `cue.cron.aggregate.{kind}.candidates` | Gauge: candidate count per run |
| `cue.cron.aggregate.{kind}.processed` | Counter: cues created per run |
| `cue.cron.aggregate.{kind}.duration` | Gauge: cron duration |
| `cue.dispatch_health` | Composite green/yellow/red for dashboard |

Filament widget `cue_dispatch_health` is green if all dispatch paths processed in last 24h with <1% failure rate. Alert thresholds: cron failure → page; job failure rate >5% → page.

`cue.job.skipped` is the load-bearing observability signal. Failed jobs land in `failed_jobs` (visible). Successful no-ops would otherwise leave no trace — the metric makes them legible. A spike in `tier_changed` skips means the trial-conversion funnel jumped (good); a spike in `user_erased` skips means an account-deletion bug (bad). Without the metric we'd see neither.

### Idempotency under retry

`cues.idempotency_key` (Q5.4) covers the duplicate-job edge case. If a delayed job fires twice (queue retry after partial failure), the cue insertion deduplicates on `(user_id, kind, occurrence_window_start_iso8601)`. No double-cues.

### Legacy users (pre-v1.3.0 onboarding)

Users who onboarded before the v1.3.0 rollout don't have a delayed job dispatched at their onboarding event. They never get `pro_trial_reminder_d1`. **Acceptable** — these are existing users; the cue is for net-new acquisition.

If founder wants to retroactively offer trial-prompts to legacy users: one-shot backfill cron, not a recurring pattern. Captured as v1.3.1 follow-up.

### Schedule impact

Net **−1.5d** vs (α). Replaces ~3d of cron-iteration scaffolding (per-cue daily cron + worklist tracking + recovery cron for missed cohorts) with ~1.5d of delayed-job scaffolding (4 job classes + dispatch hooks at source events + observability metrics).

Backend total: 48.25d → **46.75d**.

### Acceptance

- Production queue driver is `database`; documented migration trigger captured
- Four `EnqueueXxxCue` job classes implement the time-from-event pattern (`ProTrialReminderD1`, `TrialEnding2d`, `TrialEnding1d`, `TrialEnding1h`)
- Source-event listeners (`OnboardingCompletedListener`, `SubscriptionTrialStartedListener`) dispatch the delayed jobs
- Each job re-evaluates eligibility at fire time; emits `cue.job.skipped` metric per skip reason
- `kyc_required` and any future aggregate-condition cues use windowed cron with `LEFT JOIN` candidate query (NOT `NOT IN (SELECT ...)`)
- `users(lifetime_spend_cents, kyc_completed_at)` composite index exists for the candidate filter
- All observability metrics in the table above are emitted; Filament `cue_dispatch_health` widget renders
- `cues.idempotency_key` deduplication tested with a queue-retry scenario
- No proactive job cancellation; self-cancel via re-evaluation is the only cancellation path
- Legacy-user backfill captured as v1.3.1 follow-up (no v1.3.0 implementation)

---

## Money format on the wire (Backend grilling, Q9)

> Three money formats coexist and contradict in the spec — original §0.2 (decimal-string), original §1.2 entitlements (decimal-string with mixed bps), deltas Q3.1 (smallest-unit string for assets + cents-string for EUR via `eurEquivalent`), and DB storage (integer cents). Mobile builds a money helper in week 0; whichever format is chosen affects every payload going forward. **Decision: (β) — smallest-unit string + explicit `decimals` + `currency`-or-`asset` field, everywhere on the wire.** This is the last architectural decision before week-0 starts; locking it now is genuinely free because zero shipped endpoints need migration.

### Why (β), not (α)/(γ)/(δ)

- **(α) Decimal string everywhere**: human-readable but loses precision past ~16 significant digits in JS Number; mobile must apply asset-decimal knowledge anyway for arithmetic; doesn't match Stripe's industry-standard cents convention.
- **(γ) Hybrid (current ad-hoc deltas Q3.1)**: decimal-string for fiat + smallest-unit string for assets matches the human mental model but consumers must branch on whether each field is fiat or asset; subtle bug surface.
- **(δ) Decimal string with optional `decimals` annotation**: still has the precision risk of (α); the `decimals` annotation feels like a band-aid on the wrong default.
- **(β) Smallest-unit string + decimals**: BigInt-safe; matches Stripe's API convention (cents for fiat) and the assets-on-chain reality (raw units); self-documenting; eliminates the format split. Mobile money helper is `BigInt(amount) / BigInt(10n ** BigInt(decimals))` for display, integer arithmetic for everything else.

### The wire shape

```json
// Fiat:
{"amount": "499", "decimals": 2, "currency": "EUR"}    // €4.99
{"amount": "92",  "decimals": 2, "currency": "EUR"}    // €0.92
{"amount": "0",   "decimals": 2, "currency": "EUR"}    // €0.00
{"amount": "-499","decimals": 2, "currency": "EUR"}    // -€4.99 (refund)

// Assets:
{"amount": "1000000",  "decimals": 6,  "asset": "USDC"}    // 1.000000 USDC
{"amount": "100000000000000000", "decimals": 18, "asset": "MATIC"}    // 0.1 MATIC
```

Discriminator: **`currency` for fiat (ISO 4217), `asset` for tokens (ticker)**. Exactly one must be present.

### Money VO shape — keep `decimals` per-object, not derived from lookup table

Two candidates considered:

| Option | Shape | Tradeoff |
|---|---|---|
| (i) `{amount, decimals, currency-or-asset}` (per-object explicit) | Self-contained payloads; debuggable in isolation; +1 integer field per Money | Slightly more verbose |
| (ii) `{amount, currency-or-asset}` with decimals from shared lookup table | Compact; lookup gives `EUR=2`, `USDC=6` | Couples consumers to a lookup table maintained in lockstep across backend / mobile / SDK |

**Pick (i).** The verbosity is small; logged events tell you everything you need without reaching for a table. Self-containment beats compactness for debuggability.

### Asset chain context — implicit for v1.3.0

`{amount: "1000000", decimals: 6, asset: "USDC"}` works for v1.3.0 because the chain context is implicit from the request (`kind`, `route`, etc.). USDC on Polygon vs Base vs Arbitrum vs Ethereum has different contract addresses, but at v1.3.0 endpoints, only one chain is in scope per request.

For v1.3.1+ multi-chain reporting endpoints (e.g., "show my balance across all chains"), Money VO needs an `asset_chain` field. Captured as v1.3.1 follow-up — defer until first endpoint actually needs it.

### Negative amounts — sign-prefix on the string

Refunds in `revenue_events` are negative. Use `-` prefix on the amount string; do NOT introduce a separate `direction` field.

```json
{"amount": "-499", "decimals": 2, "currency": "EUR"}    // -€4.99
```

Validation regex: `/^-?[0-9]+$/`. Money VO supports negative; `formatMoney()` renders `-€4.99` (or locale-appropriate `-4,99 €`).

### Zero amounts — explicit, never null

`{amount: "0", decimals: 2, currency: "EUR"}` is `€0.00`. The Money field is always present even when zero. Consumers should never have to handle "Money or null."

Acceptance: "Money fields in API responses are non-nullable; absence of money is communicated by absence of the parent object, not by null/omitted Money fields."

### Form Request validation rejects malformed input

Idempotency-Key body-hash semantics depend on canonical body. With (β), `{amount: "499.0"}` (decimal point) is invalid input — must be rejected at the controller before idempotency-key computation, not silently normalised.

```php
'amount' => ['required', 'string', 'regex:/^-?[0-9]+$/'],
'decimals' => ['required', 'integer', 'min:0', 'max:18'],
'currency' => ['required_without:asset', 'string', 'size:3', new ValidIso4217],
'asset' => ['required_without:currency', 'string', new InKnownAssetRegistry],
```

Reject with **`ERR_VALIDATION_002`** ("malformed amount field"). Documented examples in API docs:

| Input | Result |
|---|---|
| `{amount: "499", decimals: 2, currency: "EUR"}` | OK |
| `{amount: "4.99", decimals: 2, currency: "EUR"}` | `ERR_VALIDATION_002` (decimal point in amount) |
| `{amount: 499, decimals: 2, currency: "EUR"}` | `ERR_VALIDATION_002` (number literal, not string) |
| `{amount: "-499", decimals: 2, currency: "EUR"}` | OK (negative refund) |
| `{amount: "0", decimals: 2, currency: "EUR"}` | OK (zero) |
| `{amount: "499", decimals: 2}` | `ERR_VALIDATION_002` (missing currency-or-asset) |
| `{amount: "499", decimals: 2, currency: "EUR", asset: "USDC"}` | `ERR_VALIDATION_002` (both specified) |

This eliminates the "two valid representations of the same number" idempotency hash bug at the source.

### Backend Money VO

```php
namespace App\Domain\Pricing\ValueObjects;

final class Money
{
    public function __construct(
        public readonly string $amount,        // integer-string, may be negative
        public readonly int $decimals,
        public readonly ?string $currency = null,    // ISO 4217 (mutually exclusive with $asset)
        public readonly ?string $asset = null,       // ticker (mutually exclusive with $currency)
    ) {
        // Validation in constructor; throws on malformed input
    }

    public static function eur(string $cents): self;
    public static function asset(string $smallestUnits, string $asset, int $decimals): self;
    public function add(Money $other): Money;       // throws if denominations differ
    public function compare(Money $other): int;     // -1, 0, 1
    public function toBigInt(): string;             // bcmath-friendly
    public function toJson(): array;                 // wire shape
}
```

All endpoints serialise through it. Internal arithmetic via BCMath. Storage stays integer cents (already the case per `price_eur_cents`, `gross_eur_cents` columns); the wire format now matches storage format, removing the conversion at the controller boundary.

### DB storage — per-table judgment call

Wire format is `(amount, decimals, denomination)`. Storage doesn't have to mirror exactly:

| Table type | Recommended column shape |
|---|---|
| **Single-currency tables** (e.g., `subscriptions.price` always EUR) | Implicit via column name: `price_eur_cents BIGINT` — fine, matches existing pattern |
| **Variable-currency tables** (e.g., `price_quotes.fee_amount` is EUR or USDC depending on kind) | Explicit triple: `fee_amount BIGINT, fee_decimals TINYINT, fee_denomination VARCHAR(16)` |
| **Telemetry / events** (e.g., `revenue_events`) | Explicit triple — preserves originating-currency context for cohort analysis |

Pattern: when the answer to "could this column ever hold non-EUR" is yes, go explicit. Backend dev's call per table.

### Mobile money helper — locked contract for week-0 spike

Mobile's week-0 `money.ts` builds against this contract:

```ts
type Money = {
  amount: string;          // integer-string, may be negative ("-499")
  decimals: number;        // 0–18
  currency?: string;       // ISO 4217, present for fiat
  asset?: string;          // ticker, present for tokens
};

formatMoney(m: Money, locale: string): string;   // "€4.99" / "1.000000 USDC" / locale-aware
parseMoney(amount: bigint, denomination: string, decimals: number): Money;
addMoney(a: Money, b: Money): Money;             // throws if denominations differ
compareMoney(a: Money, b: Money): -1 | 0 | 1;
toBigInt(m: Money): bigint;                       // BigInt(amount), no float math
```

Implications for week-0 spike (mobile-Q1):

- `formatEur(cents)` becomes `formatMoney(m, locale)` — works for EUR and assets uniformly
- `toCents(decimalString)` is no longer needed on the receiving end (wire is already integer-cents)
- Asset-decimal lookup table inside the helper is no longer needed (decimals come on the wire)
- All BigInt math; never `Number` coercion (precision risk past 16 digits)
- Validation: serialise `amount` as pure integer string (no `.` allowed) — backend rejects malformed at boundary

### Original §0.2 amendment

Replace "decimal string with explicit currency" with:

> *"Money on the wire: smallest-unit string + explicit `decimals` + exactly one of `currency` (ISO 4217) or `asset` (ticker). Examples: `{"amount": "499", "decimals": 2, "currency": "EUR"}` (€4.99); `{"amount": "1000000", "decimals": 6, "asset": "USDC"}` (1.0 USDC). Negative via sign-prefix `-`. Validation rejects decimal-point amounts with `ERR_VALIDATION_002`. See ADR-0004 for the four-options analysis and rationale."*

### Original §1.2 entitlements `feeTier` amendment

Original:
```json
"feeTier": { "txFlatEur": "0.05", "swapMarginBps": 20, "rampMarginBps": 100, "currency": "EUR" }
```

Amended:
```json
"feeTier": {
  "txFlat": { "amount": "5", "decimals": 2, "currency": "EUR" },
  "swapMarginBps": 20,
  "rampMarginBps": 100,
  "currency": "EUR"
}
```

Basis-points fields stay as integer (bps is a unit-of-measure with no decimals confusion). Cash amounts move to the Money shape.

### Deltas Q3.1 fee breakdown amendment

Original:
```json
{
  "label": "service",
  "asset": "USDC",
  "amount": "1000000",
  "eurEquivalent": "92"
}
```

Amended:
```json
{
  "label": "service",
  "amount": { "amount": "1000000", "decimals": 6, "asset": "USDC" },
  "eurEquivalent": { "amount": "92", "decimals": 2, "currency": "EUR" }
}
```

Two Money objects per fee breakdown item. Explicit beats implicit.

### Schedule

| Item | Effort |
|---|---|
| Spec amendment pass (deltas Q3.1, original §0.2 / §1.2; mobile deltas one-line) | 0.25d |
| Money VO + helpers in `Domain/Pricing/ValueObjects/` | 0.25d |
| Contract tests (request validation rejects decimals; canonical hashing stable; round-trip cents↔Money lossless) | 0.15d |
| `ERR_VALIDATION_002` error code registered in Appendix B with documented examples | 0.1d |

Total ~**0.75d**. Backend total: 46.75d → **47.5d**.

### ADR-0004

`docs/adr/0004-money-on-the-wire.md` — the dev will draft. Captures:

- Four-options analysis: (α) decimal-string / (β) smallest-unit + decimals / (γ) hybrid / (δ) decimal-string with optional decimals
- Why (β): math safety, Stripe consistency, asset clarity, eliminates format split, self-documenting
- Wire-vs-internal: wire is JSON Money objects, internal is `App\Domain\Pricing\ValueObjects\Money`, storage is per-table judgment call
- Migration story: zero shipped endpoints affected (pre-shipment lock)
- Forward extension: multi-chain `asset_chain` field deferred to v1.3.1

### Acceptance

- All money fields on the wire follow the (β) Money VO shape
- Form Request validation rejects decimal-point amounts, number literals, missing currency-or-asset, both-specified, with `ERR_VALIDATION_002`
- `App\Domain\Pricing\ValueObjects\Money` exists with `add`, `compare`, `toBigInt`, `toJson` methods; constructor enforces invariants
- All endpoints serialise money through the Money VO; no ad-hoc dictionary construction
- Storage column shape is per-table judgment (implicit-via-name for single-currency, explicit-triple for variable-currency)
- Idempotency-Key body-hash is stable across canonical-equivalent requests; cannot be tricked by non-canonical input (rejected at validation)
- ADR-0004 exists and is referenced from `Domain/Pricing/ValueObjects/Money` PHP namespace docblock
- Mobile money helper signature in `MOBILE_HANDOVER_PLAN_B_REVIEW_DELTAS.md` §Q1 patched to match the locked contract

---

## Architectural changes by question

### Q1 — Foundations (mobile-side; backend-confirming only)

Mobile owns the week-0 spike: money helpers, `idempotentPost` wrapper, mock IAP module, `useNetInfo` rename, `errorMapper.ts` rename. Backend's role is contract-validation: confirm `Idempotency-Key` is required on every mutating endpoint and rejected with `ERR_VALIDATION_001` if missing on a non-idempotent path. (Already in §0.1 of the original; just be explicit in tests.)

**Backend acceptance:** Every `POST` / `PATCH` / `DELETE` mutating endpoint in `routes/api.php` enforces `Idempotency-Key` header presence; integration test asserts 422 on missing header.

---

### Q2 — Quote contract architecture

The original §3 quote endpoint left the userOp-fee linkage implicit. The review agreed on **Architecture A** (quote replaces prepare for send/swap; sibling fee transfer in the userOp batch; userOpHash signs over the fee structure). Three concrete patches:

**Q2.1 — `Idempotency-Key` derivation, not caller-supplied for `/quote`.**

Caller-supplied keys are too easy to mis-key on a double-tap and produce two distinct quotes for one user intent. Backend computes both keys:

- HTTP-layer idempotency: from the `Idempotency-Key` header (mobile provides; standard pattern)
- Entity-layer dedup: SHA256 of `(sender, kind, amount, recipient, currency, route)` — backend-computed, used to find an existing live quote for the same intent

If a live quote exists for the same entity-key, return it (do not create a new one). `quoteId` itself is a fresh RFC 4122 v4 each time the entity-key would otherwise create a new quote.

**Q2.2 — Quote stores full userOp server-side; submit verifies hash match.**

For `kind: 'send' | 'swap'`, the quote endpoint stores the full constructed userOp in `quotes.user_op_payload` (JSON). `POST /api/v1/wallet/transactions/submit` computes `keccak256(canonicalize(signedUserOp.message))` and rejects with `ERR_QUOTE_002` (signature-payload mismatch) if it doesn't equal the stored `userOpHash`. This closes the obvious attack: client signs a different userOp than was quoted.

**Q2.3 — Quote refresh creates new `quoteId`; old's `consumed_at` stays null.**

The quote-refresh path issues a new `quoteId` with new `expires_at`; the old quote's `consumed_at` stays null until expiry sweeps both. Mobile tracks only the latest `quoteId`. Submitting an expired-but-unconsumed quote returns `ERR_QUOTE_001` (expired). The refresh response includes a `terms_changed: bool` flag computed by diffing the new payload against the prior — mobile uses this to decide whether to re-prompt the user (Q3 detail).

**Schema additions to `price_quotes` table** (renamed from `quotes` per Backend-Q4):

```sql
ALTER TABLE price_quotes
  ADD COLUMN entity_key CHAR(64) NOT NULL,                  -- SHA256 hex of intent
  ADD COLUMN user_op_hash CHAR(66) NULL,                    -- 0x + 32 bytes; null for ramp
  ADD COLUMN user_op_payload JSON NULL,                     -- full userOp; null for ramp
  ADD COLUMN superseded_by CHAR(36) NULL,                   -- self-FK to next quote on refresh
  ADD COLUMN terms_changed BOOLEAN NOT NULL DEFAULT FALSE,
  ADD INDEX idx_price_quotes_entity_live (entity_key, expires_at, consumed_at);
```

**Acceptance:**

- `POST /api/v1/pricing/quote` with the same intent within the live window returns the existing `quoteId` (no duplicate row created)
- `POST /api/v1/wallet/transactions/submit` with a `quoteId` whose stored `user_op_hash` doesn't match `keccak256(signedUserOp.message)` returns `ERR_QUOTE_002`
- Expired-quote submit returns `ERR_QUOTE_001`
- Refresh path: new `quoteId`; `superseded_by` populated on the prior row; `terms_changed` flag accurate

---

### Q3 — Currency model (kind-dependent quote response)

The quote response shape is **`kind`-dependent**, not uniform. Send/swap fees are asset-denominated and on-chain; ramp fees are EUR-denominated and off-chain via Stripe Bridge. Backend already encodes this implicitly; the doc and contract need to be explicit.

**Q3.1 — Per-kind response shape:**

```json
// Updated per Backend-Q9 — every money field is a Money VO {amount, decimals, currency-or-asset}
{
  "quoteId": "...",
  "kind": "send" | "swap" | "ramp",
  "expiresAt": "2026-05-08T14:32:00Z",
  "feeBreakdown": [
    {
      "label": "service",
      "amount": { "amount": "1000000", "decimals": 6, "asset": "USDC" },        // 1.0 USDC for send/swap; for ramp use currency: "EUR"
      "eurEquivalent": { "amount": "92", "decimals": 2, "currency": "EUR" }    // €0.92 — always present, even when amount is itself EUR
    },
    ...
  ],
  "rates": {                              // empty {} for ramp
    "USDC/EUR": {
      "value": "0.92",
      "decimals": 4,
      "timestamp": "2026-05-08T14:32:00Z",
      "sourceId": "oracle:chainlink:USDC-EUR:0xabc..."
    }
  },
  "userOpHash": "0x..." | null,           // null for ramp; required for send/swap
  "termsChanged": false                   // true on refresh if delta is material (Q3.2)
}
```

> **Backend-Q9 supersedes the original deltas Q3.1 inline shape** that used bare `eurEquivalent: "92"` (cents-string) and bare `amount: "1000000"` (smallest-unit-string with implicit asset-decimal lookup). Every money field is now a Money VO with explicit `amount + decimals + currency-or-asset`. Self-documenting; eliminates implicit-decimals-via-lookup; matches the unified shape across `/subscription/me`, `/pricing/quote`, `/savings-with-pro`, etc.

`sourceId` is critical for dispute resolution. "Coingecko at quote time" isn't deterministic enough — backend stores the exact oracle reading it actually used (oracle source + identifier + block height where applicable).

**Q3.2 — `terms_changed` decision rule on refresh:**

A refreshed quote is `terms_changed: true` iff:

- Recipient or amount fields differ from prior quote (must always hold; if they don't, it isn't a refresh — it's a new quote)
- Total displayed fee delta > €0.10 OR > 5%, whichever is larger
- Currency, network, or route differ

Otherwise `terms_changed: false`. Mobile uses this flag to decide whether to silently re-render the quote (no biometric re-prompt) or re-prompt the user.

**Q3.3 — Pro savings stored at transaction time, not at display time.**

`§5` (Savings calc) doc clarification: per-transaction `savings_eur_cents` is computed at transaction completion using the EUR rate frozen on the consumed `quoteId`, and stored on `transactions.savings_eur_cents`. The "Pro savings this month" aggregate is a sum of historical point-in-time EUR-equivalents — asset rate drift later does not change history.

**Acceptance:**

- Quote response shape per `kind` matches the schema in Q3.1
- `rates.{pair}.sourceId` includes the oracle identifier
- Refreshed quote's `terms_changed` flag is computed per Q3.2 rules
- `transactions.savings_eur_cents` stored at transaction time using `quote.rates['USDC/EUR'].value` (or appropriate pair) at the moment of consumption

---

### Q4 — Entitlements schema + receipt fallback

Mobile owns the local IAP receipt durable fallback (Apple/Google receipts are signed; mobile verifies them locally for offline-tolerant Pro). Backend's role is twofold:

**Q4.1 — Schema versioning contract.**

Version bumps are *additive supersets* by default. v2's response shape must contain every field v1 had with the same semantics, plus optional new fields. Any breaking change requires:

- A new endpoint path (`/api/v2/me/entitlements`) — not a version bump on the existing endpoint, OR
- `Accept-Version` header negotiation with backend keeping the v1 shape served to old clients for the deprecation window

**Q4.2 — Schema additions.**

```json
GET /api/v1/me/entitlements
{
  "version": 1,
  "tier": "free" | "pro",
  "feeTier": {...},
  "features": {...},
  "trialEligible": true,                  // NEW (also serves Q10 banner conditional)
  "etag": "..."
}
```

`trialEligible: false` once the user has used their trial (regardless of whether they converted). Mobile uses this for Q10 mid-flight banner copy adaptation and for Q11 discovery surface gating.

**Acceptance:**

- CI test asserts `/v1/me/entitlements` response is backward-compatible across mainline (compare to a fixture from the prior tagged release)
- `trialEligible` field is accurate per user's lifetime trial usage

---

### Q5 — Cue queue (modal/reminder system)

The original handover treats notifications and modal cues as the same system. They aren't. A notification is a "you should know" item that lives in the bell-icon list; a cue is a "render this UI now" item that interrupts the user with a modal. Mixing them produces UX bugs (does dismissing the bell-icon list dismiss the modal? can a user reach the modal if push is denied?).

**Q5.1 — New endpoints + table.**

```
GET /api/v1/me/pending-cues
POST /api/v1/me/cues/{cue_id}/dismissed   (idempotent)
```

```sql
CREATE TABLE cues (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  kind VARCHAR(64) NOT NULL,
  priority ENUM('critical', 'high', 'normal') NOT NULL,
  due_at TIMESTAMP NOT NULL,
  expires_at TIMESTAMP NOT NULL,
  payload JSON NOT NULL,
  dismissed_at TIMESTAMP NULL,
  dismissed_action ENUM('cancelled', 'kept', 'dismissed') NULL,
  idempotency_key CHAR(64) NOT NULL,                       -- backend-side dedup
  created_at TIMESTAMP NOT NULL,
  INDEX idx_cues_user_pending (user_id, dismissed_at, due_at, expires_at),
  UNIQUE KEY uniq_cues_idempotency (user_id, idempotency_key)
);
```

**Q5.2 — Server-side reaping at query time.**

`GET /api/v1/me/pending-cues` re-evaluates each cue's *semantic precondition* before returning it, suppressing cues whose precondition has since become false. Examples:

- `trial_ending_*` for a user who converted to paid → suppressed
- `payment_failed` for a user whose payment has since succeeded → suppressed
- `kyc_required` for a user who has completed KYC since the cue was created → suppressed

This is belt-and-braces with mobile's `cue_max_render_window` time-based discard.

**Q5.3 — Cue priority ordering.**

`critical` (action-required, e.g. `payment_failed`) renders before `high` (e.g. `trial_ending_1h`) before `normal` (e.g. `pro_trial_reminder_d1`). Within the same priority: FIFO by `due_at` ascending.

**Q5.4 — Backend dedup on `(user_id, kind, occurrence_window)`.**

Don't re-enqueue a cue of the same kind for the same user if one is already pending-and-not-expired. The `occurrence_window` is the meaningful unit: trial period for `trial_ending_*`; billing cycle for `payment_failed`; lifetime for `family_sharing_unsupported`. Idempotency key is `sha256({user_id}:{kind}:{occurrence_window_start_iso8601})`.

**Q5.5 — Cue kinds for v1.3.0.**

> **Dispatch architecture (Backend-Q8):** time-from-event cues use Laravel **delayed jobs** dispatched at the source event (durable, recoverable via `failed_jobs`). Aggregate-condition cues use **windowed cron** with `LEFT JOIN`-based candidate query. Webhook-driven cues insert directly from the webhook handler. See "Cue dispatch architecture (Backend grilling, Q8)" baseline section above for the full pattern, observability metrics, and rationale.

| Kind | Priority | Render window | Precondition reap | Trigger | Dispatch (Backend-Q8) |
|---|---|---|---|---|---|
| `trial_ending_2d` | high | 48h | suppressed if paid | Day 5 of trial | Delayed job at trial start +5d |
| `trial_ending_1d` | high | 24h | suppressed if paid | Day 6 of trial | Delayed job at trial start +6d |
| `trial_ending_1h` | high | 1h | suppressed if paid | Day 7 −1h | Delayed job at trial start +7d −1h |
| `payment_failed` | critical | 7d | suppressed if resolved | Stripe / IAP failure | Direct webhook insert |
| `subscription_canceled_external` | normal | 7d | always returned | User-initiated cancel via store | Direct webhook insert |
| `refund_processed` | high | 7d | always returned | Stripe / IAP refund settles | Direct webhook insert |
| `grace_period_started` | high | depends on retry window (~16d Apple) | suppressed if payment recovers | IAP retry window opens | Direct webhook insert |
| `kyc_required` | critical | 30d | suppressed if KYC complete | User approaching AMLD5 €1,000 lifetime threshold | Hourly aggregate-condition cron |
| `family_sharing_unsupported` | normal | none — reaped by precondition | suppressed if user owns own subscription | Apple receipt belongs to different `appAccountToken` | Direct webhook insert (during IAP verify) |
| `export_ready` | normal | 7d | suppressed if user is on exports list screen | Export job completes | Direct event insert |
| `pro_trial_reminder_d1` | normal | 7d | suppressed if user converted, opted out, or trial-used | 24h after onboarding | Delayed job at onboarding completion +24h |

**Acceptance:**

- `GET /api/v1/me/pending-cues` re-evaluates preconditions on every call; suppressed cues are not returned
- Cue creation is idempotent: same `(user_id, kind, occurrence_window)` does not produce a duplicate row
- `POST /cues/{id}/dismissed` is idempotent on `cue_id`; dismissed action recorded for analytics
- Cue priority ordering tested via fixture: `payment_failed` + `trial_ending_1h` returned in priority order

---

### Q6 — Multi-store conflict (cause-distinguishing)

The original §1.5 multi-store conflict policy collapsed all conflict cases into one `ERR_SUB_002` with one piece of copy. Five distinct underlying causes have different recovery paths. The conflict response must distinguish them.

**Q6.1 — Conflict response shape with `kind` discriminator.**

```json
{
  "code": "ERR_SUB_002",
  "message": "...",
  "conflict": {
    "kind": "two_stores_active" | "different_zelta_user" | "family_sharing_unsupported" | "stale_receipt",
    "existingSubscription": {
      "source": "apple_iap" | "google_play" | "stripe_web",   // always populated
      "currentPeriodEndsAt": "2026-06-08T..."
    },
    "attemptedSource": "apple_iap" | "google_play" | "stripe_web"
  }
}
```

`existingSubscription.source` is always populatable: backend records source on every subscription creation (IAP receipts → platform-specific; Stripe → `stripe_subscription.id` exists).

**`ownerHint` is dropped.** Even "first letter of email" is a confirmation oracle on shared devices. Mobile's conflict copy uses generic phrasing.

**Q6.2 — Resub-in-grace is a successful no-op, not an error.**

When a user cancels and then re-subscribes within the paid-up grace period, backend detects this in the IAP/Stripe receipt verification path and returns 200 with `{ subscription: {...}, reactivated: true }`. Backend flips `auto_renew` back on; the existing subscription continues. **No error code is returned.**

This requires the IAP/Stripe verification handler to do a state-aware lookup: if the inbound receipt's `original_transaction_id` matches an existing subscription that's `cancelled_at_period_end = true` and not yet expired, treat as reactivation (idempotent on the IAP transaction id).

**Q6.3 — Family sharing detected → `kind: 'family_sharing_unsupported'`.**

Backend rejects family-shared receipts (detect via `originalTransactionId` belonging to a different `appAccountToken` than the current Zelta user, or Apple's `inAppOwnershipType: "FAMILY_SHARED"`). Returns the conflict response with `kind: 'family_sharing_unsupported'`. v1.3.0 does not grant family-shared Pro; v1.4 will.

**Q6.4 — Telemetry on `conflict.kind` distribution.**

Backend emits `subscription.conflict.{kind}` counter per occurrence to logs/metrics. v1.3.0 launch tells us which cause dominates; informs whether v1.4 should prioritise family-sharing support or wait further.

**Acceptance:**

- Conflict response shape matches Q6.1 schema; tested per `kind`
- IAP receipt verification path detects resub-in-grace and returns 200 with `reactivated: true`; idempotent on `original_transaction_id`
- Family-shared receipt detection rejects with `kind: 'family_sharing_unsupported'`
- `subscription.conflict.{kind}` metric incremented per conflict

---

### Q7 — Reconciler-supporting endpoints

The cold-start reconciler is mobile-side, but it depends on three backend invariants:

**Q7.1 — `/iap/verify` server-side dedup.**

Beyond the client-supplied `Idempotency-Key` header (HTTP-layer), backend enforces `UNIQUE(original_transaction_id)` constraint on the IAP receipts persistence table. A buggy mobile build sending wrong client-side keys cannot create a duplicate subscription. The HTTP-layer key handles client-side double-tap; the persistence-layer constraint handles the money-layer safety.

**Q7.2 — `POST /api/v1/subscription/checkout` 409 on live incomplete session, 200 fresh on stale.**

```
POST /checkout response logic:
  1. user has incomplete Stripe session AND Stripe session is still live (<24h, retrievable):
     → 409 ERR_SUB_005 + { recoveryUrl }
  2. user has incomplete Stripe session AND Stripe session has expired (>24h, or Stripe returns "expired"):
     → backend abandons the stale session, returns 200 + fresh checkoutUrl
  3. no incomplete session:
     → 200 + fresh checkoutUrl
```

Mobile only renders the recovery prompt on the live-409. The expired case is invisible — user sees a fresh paywall as expected.

**Q7.3 — Stripe webhook idempotency on `event.id`.**

Confirmed against existing `processed_webhook_events` dedup table from prior Stripe Bridge work. Subscription event handlers use the same dedup key shape and table — no new infrastructure. Add subscription event types to the handler whitelist.

**Acceptance:**

- `iap_receipts` table has `UNIQUE(original_transaction_id)` constraint; integration test asserts second insert with same id returns existing row, doesn't create duplicate
- `POST /checkout` returns 409 with live `recoveryUrl` for live incomplete sessions; returns 200 with fresh URL for stale sessions
- Subscription Stripe events route through `processed_webhook_events`; redelivery of same `event.id` is no-op

---

### Q8 — Lock screen + cue + reconciler interaction

Pure client-side. **No backend changes.** Mobile owns the `lockScreenVisible` state, `<CueOrchestrator>` render gating, and on-unlock flush sequencing.

---

### Q9 — Card waitlist deposit refund mechanics

The original §6 named three refund triggers without specifying mechanics. The review added a fourth (user-initiated cancel) and tightened all four.

**Q9.1 — `POST /api/v1/cards/waitlist/deposit/cancel` endpoint.**

Required Idempotency-Key. Marks deposit as `cancellation_requested`, fires Stripe refund, removes from queue.

**Q9.2 — Updated `GET /api/v1/cards/waitlist/deposit/status` shape.**

```json
{
  "status": "none" | "pending_payment" | "paid"
          | "cancellation_requested" | "refunded" | "card_shipped",
  "paidAt": "2026-05-08T...",
  "refundEligibleAfter": "2027-11-08T...",          // paidAt + 18 months, frozen at deposit time
  "queuePosition": 312,
  "refundedReason": null | "user_cancelled" | "card_shipped"
                       | "eighteen_month_auto" | "account_closure",
  "refundedAt": null | "2026-05-15T..."
}
```

`refundEligibleAfter` is **frozen at deposit time** (paidAt + 18mo); never recalculated based on current policy.

**Q9.3 — CHECK-constrained UPDATE for state transitions.**

Avoid races between cancel and ship by using saga-style atomic state transitions:

```sql
-- cancel handler
UPDATE card_waitlist_deposits
   SET status = 'cancellation_requested', cancelled_at = NOW()
 WHERE id = ?
   AND status = 'paid';
-- if affected_rows = 0, abort: deposit is already shipped or already cancelled

-- card.shipped handler
UPDATE card_waitlist_deposits
   SET status = 'card_shipped', shipped_at = NOW()
 WHERE id = ?
   AND status = 'paid';
-- if affected_rows = 0, abort shipment: cancellation was requested first
```

Whichever transaction commits first wins; the loser's side effect (refund or shipment) is aborted on `affected_rows == 0`.

**Q9.4 — Account closure: don't block on Stripe API failure.**

Closure flow:

1. Capture user's email and pending deposit ids
2. Initiate Stripe refund(s)
3. If init succeeds → mark `status = 'refunded'`, proceed with closure
4. If init fails (transient Stripe error) → mark `status = 'refund_pending_manual'`, **page ops, proceed with closure anyway**
5. Closure email contains either "refund initiated, will settle in 5–10 business days" or "refund being processed manually, you'll hear from us within 48h"

User email + Stripe payment intent reference persisted in a separate `closed_account_refunds` ledger so the refund can settle and email can fire after the user record is gone.

**Q9.5 — Refund timing copy: "5–10 business days," not "within 7 days."**

Stripe card refunds typically settle 5–10 business days; SEPA can be 5–7. The "within 7 days" claim risks user disputes when refund lands on day 9. Soften user-facing copy throughout.

**Acceptance:**

- `POST /cancel` endpoint creates `cancellation_requested` state, fires Stripe refund, returns 200
- Concurrent cancel + ship operations resolve deterministically via affected_rows check
- Account closure with pending deposit doesn't block on Stripe failure; pages ops on init failure
- Refund timing in user-facing copy uses "5–10 business days" phrasing

---

### Q10 — Mid-flight Pro upgrade

Mobile-side architecture (modal-overlay paywall). Backend role:

- `trialEligible` field on entitlements (covered in Q4.2)
- `/iap/verify` 2xx must update entitlements canonical state synchronously before responding so mobile's optimistic flip + WS reconcile produce consistent results
- WS event `entitlements.changed` fires on tier transition (existing pattern)

**Acceptance:** `POST /iap/verify` 200 implies `GET /me/entitlements` returns the new tier on the very next call (no eventual-consistency window).

---

### Q11 — Discoverability for new users

**Q11.1 — `savingsClaimReady` boolean on `/savings-with-pro`.**

```json
GET /api/v1/me/savings-with-pro
{
  "sampleSize": 12,
  "savingsClaimReady": true,                  // backend's call: data stable enough?
  "currency": "EUR",
  "calculatedAt": "2026-05-08T12:00:00Z",
  "monthlySavingsEurCents": 630,              // null until savingsClaimReady
  "last30DaysFreeFeesEurCents": 840,
  "estimatedProFeesEurCents": 210,
  "breakEvenAtVolumeMet": true
}
```

Backend owns the "data stable enough" threshold. Mobile doesn't hardcode `sampleSize >= 5` — backend can tune the underlying threshold (5 txs, confidence interval, model accuracy bound) without a mobile release.

**Q11.2 — `pro_trial_reminder_d1` delayed job (was: cron — superseded by Backend-Q8).**

> **Dispatch architecture (Backend-Q8):** `pro_trial_reminder_d1` is a time-from-event cue. The original Q11.2 framing ("backend cron daily at 03:00 UTC scanning users in the 24–48h window") is superseded by the Backend-Q8 delayed-job pattern — windowed cron has no recovery for missed cohorts; a single failed cron run permanently loses ~1–2k cues/day.

`OnboardingCompletedListener` dispatches `EnqueueProTrialReminderD1::dispatch($userId)->delay(now()->addDay())` at onboarding completion. The job fires 24h later, re-evaluates eligibility at fire time:

- `User::find($userId)` → if null (user erased), `cue.job.skipped` with reason `user_erased`, return
- `tier === 'free'` → if not, skip with reason `tier_changed`
- `trialEligible === true` → if not, skip with reason `trial_used`
- `pro_marketing_opt_out !== true` → if not, skip with reason `opted_out`
- Else: insert cue via `Cue::createIdempotent()` (idempotency per Q5.4 catches duplicate-job retries)

Cue priority `normal`; render window 7 days; reaped at GET time per Q5.2 server-side reaping. Two-layer defence (job-fire-time eligibility check + GET-time reaping) covers concurrent-state-change races.

Failure recovery is structural: transient failures retry per Laravel queue worker policy; permanent failures land in `failed_jobs` for ops review. The cohort isn't lost — the job state is durable in the `jobs` table.

**Q11.3 — `users.pro_marketing_opt_out` flag.**

New boolean column. Mobile sets via a "Don't show me Pro reminders again" link on the onboarding paywall. Backend cron suppresses `pro_trial_reminder_d1` (and any future marketing cues) when set. This is regulatory hygiene under PECR/ePrivacy.

**Acceptance:**

- `/savings-with-pro` returns `savingsClaimReady: false` and `monthlySavingsEurCents: null` for users below the threshold
- `pro_trial_reminder_d1` cron tested via fixture cohort: only eligible users get the cue
- `users.pro_marketing_opt_out` setter endpoint exists and gates marketing cues

---

### Q12 — Test strategy

Mostly mobile-side (Maestro flows, IAP confidence ladder). Backend implication: existing Pest + integration test suite covers the new endpoints via `tests/Integration/` and `tests/MultiConnection/`. No infrastructure change.

The week-0 mock-IAP-module work is mobile-only.

---

### Q13 — Pro analytics export (long-running jobs)

The original §9 spec was screen-bound polling. The review modelled exports as persistent jobs.

**Q13.1 — New endpoints.**

```
GET  /api/v1/wallet/transactions/exports                          (list)
POST /api/v1/wallet/transactions/exports/{exportId}/refresh-url  (cheap URL refresh)
```

`GET /exports` response includes `dailyLimit`:

```json
{
  "exports": [
    {
      "exportId": "...",
      "status": "ready" | "processing" | "expired" | "failed",
      "createdAt": "...",
      "completedAt": "...",
      "format": "csv",
      "downloadUrl": "...",
      "expiresAt": "...",                     // URL TTL: 24h
      "sizeRows": 1234,
      "failureReason": null | "timeout" | "too_large" | "internal_error" | "unknown"
    }
  ],
  "dailyLimit": {
    "used": 1,
    "total": 3,
    "resetsAt": "2026-05-09T00:00:00Z"        // rolling 24h window
  }
}
```

**Q13.2 — Artifact retention 7d (longer than URL TTL of 24h).**

`POST /{exportId}/refresh-url`:

- 200 + new `downloadUrl` if artifact still on disk (no quota cost — same artifact, fresh presigned URL)
- 410 + `ERR_EXP_002` if artifact has been purged (>7d after creation; user must submit a fresh export, which costs quota)

Mobile's "Re-export" button on an expired row first tries `refresh-url`; falls back to a fresh export submit on 410.

**Q13.3 — Rate limit counts only successfully-completed exports.**

Failed exports do NOT count toward the daily limit. This is essential for users hitting transient backend issues — a flaky failure doesn't lock them out for the day.

**Q13.4 — `failure_reason` field.**

Failed exports return `failure_reason: 'timeout' | 'too_large' | 'internal_error' | 'unknown'`. Mobile renders generic copy in production but logs the reason to Sentry.

**Q13.5 — WebSocket event `export.completed`.**

Fires on private channel `private-user.{userId}` when export status transitions to `ready`. Includes `exportId` only (no payload — mobile re-fetches list). Webhook idempotency on the internal `export.completed` event ID via existing `processed_webhook_events` table.

**Q13.6 — Cue `export_ready`.**

Created on completion (suppressed if user is currently on the exports list screen — server-side check by inspecting active session route). Cue kind details in Q5.5.

**Acceptance:**

- `GET /exports` returns full list + `dailyLimit`
- `POST /{exportId}/refresh-url` returns 200 with new URL if artifact retained; 410 `ERR_EXP_002` after 7d purge
- Failed exports do not increment `dailyLimit.used`
- `export.completed` WS event fires once per completion (dedup via `processed_webhook_events`)

---

### Q14 — Apple/Google compliance disclosure

Mobile owns the disclosure block UI. Backend ask:

**Q14.1 — Withdrawal consent persistence on Stripe Web checkout.**

```
POST /api/v1/subscription/checkout  (web flow)
  body must include:
  {
    "plan": "monthly_pro" | "annual_pro",
    "withdrawalConsent": {
      "given": true,
      "shownAt": "2026-05-09T14:32:00Z",
      "acceptedAt": "2026-05-09T14:32:07Z",
      "consentText": "I understand that my subscription begins immediately and I waive my 14-day right of withdrawal.",
      "version": 1
    }
  }
```

Backend rejects with `ERR_SUB_004` if:

- `withdrawalConsent.given !== true`
- `withdrawalConsent.acceptedAt` is missing
- `withdrawalConsent.acceptedAt` is more than 5 minutes old (replay protection on stale consent)

**Q14.2 — `subscription_consent_log` table.**

```sql
CREATE TABLE subscription_consent_log (
  id CHAR(36) NOT NULL PRIMARY KEY,
  user_id CHAR(36) NOT NULL,
  subscription_id CHAR(36) NULL,           -- populated once subscription created
  consent_text TEXT NOT NULL,              -- exact wording shown
  consent_version INT NOT NULL,
  shown_at TIMESTAMP NOT NULL,
  accepted_at TIMESTAMP NOT NULL,
  ip_hash CHAR(64) NOT NULL,               -- sha256 of remote IP for audit (no raw IP)
  user_agent TEXT NOT NULL,
  created_at TIMESTAMP NOT NULL,
  INDEX idx_consent_user (user_id),
  INDEX idx_consent_subscription (subscription_id)
);
```

Audit trail is per-row: if we update the consent line text, increment `consent_version`. A dispute lookup retrieves the exact text shown at consent time, not whatever's current.

**Q14.3 — Stripe Tax enabled for v1.3.0 markets.**

Stripe Tax must be enabled in production for the v1.3.0 market list (US, GB, IE, AU, NZ, MT — see Q16). Without it, we're either under-collecting tax (legal exposure) or absorbing it into the €4.99 (margin hit). Confirm Stripe Tax is in the founder's pre-launch checklist.

**Acceptance:**

- `POST /checkout` rejects with `ERR_SUB_004` if `withdrawalConsent` missing, malformed, or stale (>5 min old)
- `subscription_consent_log` row written on every successful Stripe Web subscription creation
- Stripe Tax enabled in production for the v1.3.0 markets before launch

---

### Q15 — Telemetry PII + GDPR

Mobile owns event emission, identifier hashing, k-anonymity audit. Backend asks:

> **Framing correction (Backend-Q6):** the original Q15.1 framing called this "pseudonymisation." Under GDPR Art 4(5) it isn't — the salt sits on the user's device alongside the user_id, failing the "kept separately" test. **The accurate framing is "personal data with internal hashing safeguard."** All language changes propagated below; see "Pseudonymisation framing correction (Backend grilling, Q6)" baseline section above for the full Art 4(5) analysis and the (α)/(β)/(γ)/(δ) decision rationale.

**Q15.1 — Internal hashing salt for telemetry user identifier.**

Single global build-time secret. Stored in env: `TELEMETRY_USER_PSEUDONYM_SALT` (env var name retained per Backend-Q6 — env vars rarely rename). Mobile never sees this salt — mobile fetches it at session start via authenticated channel, caches in SecureStore, derives the hashed identifier locally. Salt rotation is event-triggered (on suspected leak), not scheduled — same simple-pepper pattern as `TRIAL_FINGERPRINT_PEPPER` per Backend-Q5 (see "v1.3.0 simple-pepper pattern" in closing notes).

**This is NOT GDPR pseudonymisation** — see Backend-Q6 baseline section. Future engineers: do not reintroduce "pseudonym" / "pseudonymisation" language without first migrating to an architecture that keeps the salt separately from the data subject (server-derived hashing or two-stage hybrid; both put backend on the telemetry critical path which we explicitly decided against for v1.3.0).

**Q15.2 — `POST /api/v1/me/data-deletion` endpoint.**

> **In-place correction (Backend-Q7):** the original Q15.2 said "delete IAP receipts" — that contradicts §4.5's pseudonymise-don't-delete pattern AND creates exposure under tax-law retention obligations (Art 17(3)(b)) plus orphan-webhook problems for refunds (180-day Apple window) and renewals. **The correct behaviour is pseudonymise, not delete.** Full rationale + schema additions in the "IAP receipt pseudonymisation (Backend grilling, Q7)" baseline section above.

Right-to-erasure (GDPR Article 17). Returns **202 Accepted** with `request_id` (settlement is async per Backend-Q6).

Authenticated user requests deletion. Backend walks the deletion sequence (full ordered sequence in Backend-Q7 baseline section; summary here):

a. **Cancel all active subscriptions** (Stripe via Cashier `cancel()`; IAP via store APIs) — MUST be before any pseudonymisation; if cancel fails, queue retry, do not proceed
b. **Refund pending card deposit** per Q9.4 closure flow; persist refund-tracking row in `closed_account_refunds`
c. **Pro-rata refund annual subscriptions** where store permits (Stripe definitive via `proration_behavior: 'create_prorations'`; Apple/Google best-effort)
d. **Pseudonymise `iap_receipts` rows (Backend-Q7):** NULL `user_id`, `original_transaction_id`, `apple_app_account_token`, `google_obfuscated_account_id`, `receipt_blob`; populate `original_transaction_id_hash` for future webhook matching; set `scrubbed_at`. Transactional fields (tier, amounts, dates) preserved per §4.5.
e. **Pseudonymise `users` row** per existing §4.5 GDPR pattern — overwrite PII fields with `sha256(pepper + user_id)`; user record stays for FK integrity
f. **NULL `revenue_events.user_id`** per §4.5 (keep `user_id_hash` for cohort analytics)
g. **Rewrite `subscription_consent_log.consent_text`** to scrubbed copy + NULL identifying columns
h. **Rewrite `subscription_events.event_payload`** per §4.5
i. **Stripe-side erasure** (Cashier-aware): if `$user->stripe_id` is set, call `$user->asStripeCustomer()->delete()` and clear `stripe_id`, `pm_type`, `pm_last_four`. Cashier doesn't auto-cascade on user delete — this step is explicit
j. **`subscription_consent_log`:** further scrubbed (audit trail no longer linked to a person)
k. **`trial_card_fingerprints`:** delete the user's fingerprint hash row (Backend-Q5 — fraud-prevention data deleted on erasure)
l. **Vendor data-deletion orchestration (Backend-Q6):** insert `vendor_data_deletions` rows for each processor (Firebase Analytics, Sentry, etc.). Backend hits each vendor's delete API; hourly cron polls for completion status. User receives confirmation email when all rows reach `status: 'completed'` OR after 7 days

For v1.3.0, the self-serve UI is deferred — mobile shows a "Delete my data" CTA that opens an email-to-support flow. Support manually triggers this endpoint after identity verification. v1.3.1 adds the self-serve UI.

**Q15.3 — `error_code` field validation.**

All `error_code` values returned in API responses MUST match `/^ERR_[A-Z]+_\d{3}$/`. Backend test asserts this on every code path that returns an error code. SDK error strings (Apple, Stripe, Google) never leak through — mobile maps them to app-level codes at the boundary.

**Q15.4 — Firebase Analytics EU data residency.**

Firebase project configured for EU data residency where supported. Configuration documented in `docs/compliance/data-transfers.md` along with the SCC / DPF transfer mechanism for products without EU residency support.

**Acceptance:**

- `TELEMETRY_USER_PSEUDONYM_SALT` env var documented; rotation policy is event-triggered
- `POST /me/data-deletion` endpoint exists and walks the full deletion sequence
- All error responses match `/^ERR_[A-Z]+_\d{3}$/`; CI test enforces
- Firebase EU data residency configured for products supporting it

---

### Q16 — Localization (option D)

Decision: ship English-only to EN-speaking markets in v1.3.0; EU launch in v1.3.1 with full i18n.

**Q16.1 — App Store / Play Store availability list.**

Codified in deployment config:

- US, GB, IE, AU, NZ, MT
- **Drop CA** (Quebec Bill 96 requires French UI; cannot sub-restrict at province level within Apple/Google)
- **Drop CY** (Greek primary, ambiguous for App Review)
- Optional: SG (English official, fintech-active) — pending 30-min legal pass on Singapore PDPA vs GDPR alignment

Document in `docs/release/v1.3.0-market-scope.md` with reasoning so the next release coordinator doesn't accidentally re-add CA/CY.

**Q16.2 — Stripe Tax for these markets.**

Already covered in Q14.3.

**Q16.3 — Ondato KYC locale configuration.**

Workspace setting: default language `en` with `en-US`, `en-GB`, `en-IE` overrides where Ondato supports them. User's device locale passed as session-creation parameter. Backend ask: confirm Ondato workspace is reconfigured before launch (config-only, not code).

**Acceptance:**

- App Store / Play Store availability config matches the v1.3.0 market list exactly
- Ondato workspace defaults to English with locale-pass-through

---

### Q17 — Subscription cancel/upgrade/downgrade lifecycle

The original §1.2 manage screen description was one sentence covering eight distinct flows. Backend ask:

> **Implementation note (under Backend-Q1 hybrid γ):** all three Stripe endpoints below are thin wrappers around Cashier's `Billable::subscription()->swap() / cancel() / resume()` methods. Pseudocode in the "Subscription architecture baseline" section above. Idempotency wrappers and projection refresh are layered on top.

**Q17.1 — `POST /api/v1/subscription/change-plan` (Stripe-only).**

```json
POST /change-plan
Idempotency-Key: required
body: {
  "plan": "monthly_pro" | "annual_pro",
  "prorationBehavior": "create_prorations"
}
response: 200 + updated subscription
```

Idempotency-Key derivation: hash of `(user_id, current_subscription_id, target_plan, requested_at_iso8601)`. IAP plan changes happen via store-side `requestSubscriptionPlanChange` / `BillingClient.updateSubscription` — backend gets notified via webhook, no separate endpoint needed.

**Q17.2 — `POST /api/v1/subscription/reactivate` (Stripe-only).**

```json
POST /reactivate
Idempotency-Key: required
body: {}
response: 200 + subscription with cancelled_at_period_end: false
```

Clears `cancelled_at_period_end` flag. IAP reactivate happens via store deep link — backend silent reactivation (Q6.2) handles the resub-in-grace flow once the store-side action completes.

**Q17.3 — `GET /api/v1/subscription/me` schema additions.**

```json
{
  "tier": "pro",
  "source": "stripe_web",
  "currentPeriodEndsAt": "...",
  "cancelledAtPeriodEnd": false,            // NEW
  "pausedUntil": null | "2026-06-08T..."    // NEW (Apple-only; null otherwise)
}
```

Mobile uses these to drive the 5-state `manage.tsx` machine (active monthly, active annual, cancelled-at-period-end, paused, payment-failed-in-grace).

**Q17.4 — Annual → Monthly downgrade intentionally not offered.**

Document in §1 design intent. A future developer adding "obvious missing feature" Switch-to-Monthly on annual users undoes the retention design and costs €10.88/user/year of LTV. Add a comment in the change-plan endpoint code: "Annual → Monthly is intentionally rejected at this layer; product review required to enable."

**Acceptance:**

- `POST /change-plan` with `target_plan === current_plan` returns the existing sub (idempotent no-op)
- `POST /change-plan` with `current_plan === 'annual_pro' AND target_plan === 'monthly_pro'` returns 422 `ERR_SUB_006` (intentional)
- `POST /reactivate` on a non-cancelled subscription returns the existing sub (idempotent no-op); on a cancelled one, clears the flag
- `GET /subscription/me` includes `cancelledAtPeriodEnd` and `pausedUntil` fields

---

## Acceptance criteria (consolidated additions to §13)

### Money / idempotency / errors (§0.1 / §0.2)

- Every mutating endpoint enforces `Idempotency-Key` header presence
- Error code taxonomy: every API error response code matches `/^ERR_[A-Z]+_\d{3}$/`; CI test enforces
- New error codes registered in Appendix B: `ERR_QUOTE_001`, `ERR_QUOTE_002`, `ERR_SUB_004`, `ERR_SUB_005`, `ERR_SUB_006`, `ERR_EXP_002`

### Subscription module (§1)

- IAP receipts table has `UNIQUE(original_transaction_id)` constraint
- Resub-in-grace returns 200 + `reactivated: true`; idempotent on `original_transaction_id`
- Family-shared receipts rejected with `kind: 'family_sharing_unsupported'`
- `subscription.conflict.{kind}` metric per occurrence
- `POST /checkout` 409 on live incomplete session, 200 fresh on stale
- `POST /checkout` rejects with `ERR_SUB_004` on missing/stale `withdrawalConsent`
- `subscription_consent_log` row written per Stripe Web subscription
- Stripe webhook events route through `processed_webhook_events`; redelivery is no-op
- `POST /change-plan` and `POST /reactivate` endpoints (Stripe-only) with idempotency
- `GET /subscription/me` returns `cancelledAtPeriodEnd` and `pausedUntil`

### Quote (§3)

- Quote response shape per `kind` (send/swap/ramp) matches Q3.1 schema
- Quote storage includes full userOp for send/swap; submit verifies `keccak256` match (`ERR_QUOTE_002` on mismatch)
- Quote idempotency: same intent within live window returns existing `quoteId`
- Quote refresh produces new `quoteId`; old's `superseded_by` populated; `terms_changed` flag accurate per Q3.2 rules
- Expired-quote submit returns `ERR_QUOTE_001`
- Rate `sourceId` includes oracle identifier

### Entitlements (§7)

- Schema is additive-superset across version bumps; CI test enforces
- `trialEligible: boolean` in response
- `/iap/verify` 200 implies the very next `/me/entitlements` call returns the new tier (synchronous canonical update)

### Cue queue (§new — add to §1 or as separate §)

- `GET /me/pending-cues` re-evaluates preconditions per call
- Cue creation idempotent on `(user_id, kind, occurrence_window)`
- `POST /cues/{id}/dismissed` idempotent on `cue_id`
- Priority ordering: critical > high > normal; FIFO within priority by `due_at`
- All 11 cue kinds in Q5.5 implemented and unit-tested

### Card waitlist deposit (§6)

- `POST /cancel` endpoint with idempotency
- State transitions use CHECK-constrained UPDATE; concurrent cancel + ship resolves via `affected_rows` check
- `refundEligibleAfter` frozen at deposit time
- Account closure with pending deposit doesn't block on Stripe failure; pages ops on init failure
- `closed_account_refunds` ledger exists for post-closure refund tracking
- `waitlist.deposit.cancelled` metric per occurrence

### Pro savings (§8)

- `savingsClaimReady` boolean in response
- `monthlySavingsEurCents` is null until `savingsClaimReady === true`
- Per-transaction `savings_eur_cents` stored at transaction time using `quote.rates` value at consumption

### Exports (§9)

- `GET /exports` list endpoint with `dailyLimit` shape
- `POST /{exportId}/refresh-url`: 200 if artifact retained, 410 + `ERR_EXP_002` after 7d purge
- Failed exports do not count toward `dailyLimit.used`
- `failure_reason` field on failed exports
- `export.completed` WS event with dedup via `processed_webhook_events`
- Artifact retention: 7 days

### Telemetry / GDPR (§4 / §new GDPR section)

- `TELEMETRY_USER_PSEUDONYM_SALT` env var; salt rotation event-triggered
- `POST /me/data-deletion` endpoint walks full deletion sequence
- All error responses match `/^ERR_[A-Z]+_\d{3}$/`
- Firebase EU data residency configured

### Rollout (§12)

- Feature flag `features.pro_ui_enabled` controls Pro UI surfacing across all mobile clients
- Backend can disable Pro upsell surfacing within 10 minutes via config flag without affecting existing Pro entitlements

### Localization / market scope (§new — add to §10)

- Market list codified: US, GB, IE, AU, NZ, MT (drop CA, drop CY)
- `docs/release/v1.3.0-market-scope.md` exists with reasoning
- Stripe Tax enabled for these markets in production
- Ondato workspace defaults to English with locale pass-through

---

## Mobile handover patch — what mobile owes back to backend

These items the mobile team is responsible for; backend tests can pin the contract:

1. **Quote intent hashing**: mobile MUST use the canonical input order `(sender, kind, amount, recipient, currency, route)` so backend's entity-key dedup matches
2. **userOp signing**: mobile signs only the `userOpHash` returned by backend; never re-derives the hash from a different payload
3. **Optimistic entitlements flip**: mobile flips entitlements to Pro on `/iap/verify` 200 before WS event arrives; backend must guarantee canonical state matches within the same response
4. **Cue idempotent dismissal**: mobile must POST `/cues/{id}/dismissed` even on offline-then-online recovery; backend deduplicates
5. **Pseudonymisation salt fetch**: mobile fetches salt at login (or session boundary), caches in SecureStore, derives pseudonym client-side; never sends raw `user_id` to telemetry SDKs
6. **Withdrawal consent payload**: mobile sends consent object with required fields; staleness must be ≤5 minutes between `acceptedAt` and request time
7. **`finishTransaction` ordering**: mobile MUST call StoreKit/BillingClient `finishTransaction` only after backend `/iap/verify` returns 2xx — never on 4xx/5xx/network failure (StoreKit's queue is the queue)
8. **Reconciler debounce**: mobile collapses concurrent reconcile triggers within 5s; backend can rate-limit `/me` endpoints accordingly
9. **Cue `export_ready` suppression**: when user is on exports list screen, mobile flags this in the request (via custom header or session route hint) so backend's pending-cues reaper suppresses `export_ready` cues for that session

---

## Out-of-scope reaffirmed

These were explicitly considered and deferred. Don't pull them into v1.3.0 mid-sprint without product/founder review:

- **Family Sharing / multi-seat** — v1.4 (handled in v1.3.0 via `family_sharing_unsupported` rejection)
- **Multi-currency** — v1.4 (v1.3.0 is EUR-only on backend; mobile renders localized via Apple/Google `getProducts()`)
- **Annual → Monthly downgrade** — intentionally not offered (revenue/retention review required to add)
- **Full account data export self-serve** — v1.4
- **A/B price testing infrastructure** — v1.4
- **Salt rotation tooling** — v1.3.1 (event-triggered manual rotation only for v1.3.0)
- **Granular per-event-type analytics opt-in** — v1.3.1
- **Self-serve delete-my-data UI** — v1.3.1 (email-to-support path for v1.3.0)
- **Sentry opt-out toggle** — v1.3.1
- **Production k-anon dashboard** — v1.3.1
- **B2B BaaS commercial onboarding (§5)** — original handover scope; not modified by review
- **Receive-screen Pro footer** — dropped (utility-screen friction, would reduce deposit completion)

---

## v1.3.1 — EU launch follow-up (not v1.3.0 scope)

Capture as a separate handover the moment v1.3.0 enters App Review:

**Engineering:**

- Backend i18n for push notification copy templates (German, French, Italian, Spanish, Polish, Dutch, Portuguese)
- Backend i18n for email templates (welcome, payment failed, refund processed, etc.)
- Localized ToS / Privacy Policy hosted at `zelta.app/{locale}/terms`, `/privacy`
- App Store / Play Store availability extends to EU markets after copy + screenshots ready
- Optional: re-add CA with French-Quebec localization for Bill 96 compliance
- **`/api/v1/quotes` (RampController) deprecation per Backend-Q4:** alias `GET /api/v1/pricing/ramp-quotes`, dual-serve through one release cycle, document the cutover in v1.3.1 release notes. Mobile to switch to the new path; legacy endpoint removed in v1.4.
- **Processor list publication per Backend-Q6:** publish at `zelta.app/privacy/processors` — Firebase Analytics, Sentry, Stripe, Apple, Google, Privy, Pimlico, Helius, Alchemy, Ondato, Stripe Bridge. Each entry includes name, role (processor / sub-processor), data categories, transfer mechanism (DPF / SCC), DPA link. Some EU regulators expect a published processor list; deferred from v1.3.0 because not launch-blocking but document as known-deferred so a regulator inquiry doesn't catch us flat-footed.
- **Vendor data-deletion async polling cron per Backend-Q6:** v1.3.0 ships `vendor_data_deletions` table + synchronous request orchestration. v1.3.1 adds the hourly poll cron to update vendor completion status and the per-vendor confirmation email automation.
- **Legacy-user `pro_trial_reminder_d1` backfill per Backend-Q8:** users who onboarded before v1.3.0 rollout don't have a delayed job dispatched at their onboarding event and never get the reminder. If the founder wants to retroactively offer trial-prompts to legacy users, ship a one-shot backfill cron (NOT a recurring pattern). Acceptable to defer because the cue is for net-new acquisition; legacy users have already either converted or churned.
- **Money VO `asset_chain` field per Backend-Q9:** v1.3.0 leaves chain context implicit (USDC unambiguously refers to the chain in the request's `route` field). For v1.3.1+ multi-chain reporting endpoints (e.g., "show my balance across all chains"), Money VO needs an `asset_chain` field for disambiguation (USDC-Polygon vs USDC-Base vs USDC-Arbitrum vs USDC-Ethereum). Defer until first endpoint actually requires multi-chain disambiguation; adds a string column to the DB explicit-triple shape and an optional field to the wire Money type.

**Translation procurement:**

- ~7 languages × subscription disclosure + consent line + email/push templates + privacy/ToS pages
- Specialised fintech translator review (consumer-facing, not generic SaaS tone)
- ~2 weeks calendar time for procurement and review (independent of engineering)

**Process:**

- v1.3.0 conversion data informs v1.3.1 copy tuning but does NOT predict EU conversion patterns — treat v1.3.0 as proof-of-mechanism, not proof-of-PMF
- Self-serve features deferred from v1.3.0 (Privacy screen polish, delete-my-data UI, Sentry opt-out, granular analytics opt-in) ship in v1.3.1
- v1.4 candidates (family sharing, multi-currency, annual→monthly downgrade if approved) get their own handover after v1.3.1 launches

---

## Closing

Original handover: 36 engineer-days backend across 9 features. Joint review (mobile Q1–Q17) added +8.5d of gap-closing work. Backend grilling rebalanced this:

- **Backend-Q1 (Cashier hybrid γ)** reclaims −3d by leaning on Cashier's free Stripe lifecycle plumbing
- **Backend-Q2 (Fee-collector wallets α + sweep audit)** adds +2d for on-chain custody surface plumbing
- **Backend-Q3 (Chain-ingestor + outbox dual-upstream projection)** adds +1.5d, replaces fragile saga with structurally-better durability
- **Backend-Q4 (Domain/Pricing rename)** adds ~0d; locks naming hygiene before any code is written
- **Backend-Q5 (Trial-abuse card-fingerprint check α)** adds +2d; closes the Stripe-side new-account-same-card abuse vector
- **Backend-Q6 (Pseudonymisation framing correction δ)** adds +0.5d; eliminates a false GDPR claim, accurate language across deltas/privacy/DPIA, vendor-deletion orchestration
- **Backend-Q7 (IAP receipt pseudonymisation α)** adds +0.75d; corrects deltas Q15.2's contradiction with §4.5; resolves the GDPR Art 17 vs tax-law-retention conflict via Art 17(3)(b) exception; preserves webhook match capability via hash fallback
- **Backend-Q8 (Cue dispatch architecture γ)** reclaims **−1.5d**; replaces fragile windowed-cron pattern with Laravel delayed jobs for time-from-event cues; aggregate-condition cues stay on cron with `LEFT JOIN` query; durable failure recovery via `failed_jobs`
- **Backend-Q9 (Money format on the wire β)** adds +0.75d; locks unified Money VO shape across all endpoints before mobile builds week-0 helper; eliminates three coexisting formats; closes the formal architectural grill

**Revised total: ~47.5 engineer-days.** Cumulative architectural-improvement reclaim of −4.5d vs deltas-implied worst-case baseline (Cashier hybrid −3d via Backend-Q1, queued jobs −1.5d via Backend-Q8) lands the project at +3d above the disciplined joint-review baseline of 44.5d, with materially better architecture across every load-bearing dimension.

### Architectural grill — closed

Backend-Q9 closes the formal architectural decision space. Across mobile-review (Q1–Q17) and backend-grilling (Q1–Q9), every load-bearing decision is locked:

| Layer | Status |
|---|---|
| Mobile-facing decisions (Q1–Q17 mobile review) | Closed |
| Subscription architecture (Backend-Q1 Cashier γ) | Closed |
| On-chain custody surface (Backend-Q2 fee collectors α) | Closed |
| Revenue projection (Backend-Q3 chain-ingestor + outbox γ/β) | Closed |
| Naming hygiene (Backend-Q4 Domain/Pricing rename) | Closed |
| Trial abuse (Backend-Q5 fingerprint α) | Closed |
| GDPR pseudonymisation framing (Backend-Q6 δ) | Closed |
| IAP receipt erasure vs tax retention (Backend-Q7 α) | Closed |
| Cue dispatch architecture (Backend-Q8 γ) | Closed |
| Money format on the wire (Backend-Q9 β) | Closed |

Remaining work is documentation + implementation:

1. **ADRs to draft**: 0001 (fee collectors), 0002 (revenue projection), 0003 (pricing), 0004 (money format) — all dev-owned, ~0.5d each, can run in parallel with week-1 implementation
2. **Original §0.2 conventions** — needs the (β) money-format amendment from Q9; rest unchanged (multi-connection saga rule got Backend-Q3 supersession note)
3. **Original §5 (B2B BaaS) and §11 (Test strategy)** — untouched by review; no architectural concerns surfaced
4. **Risk register** — refresh pass to reflect Q1–Q9 decisions; ~0.25d
5. **Implementation** — 47.5 engineer-days of engineering work, ~7.5 calendar weeks parallel with mobile

### v1.3.0 simple-pepper pattern (convention captured during Backend-Q5/Q6/Q7)

Three secrets follow the same v1.3.0 pattern. Common attributes:

- **Single env-var secret** — no `pepper_version` column, no dual-pepper grace period
- **Event-triggered rotation only** — on suspected leak, not on calendar
- **No scheduled cron** — rotation tooling deferred to v1.3.1 if rotation becomes routine

Per-instance details:

| Env var | Backend-Q | Rotation behaviour |
|---|---|---|
| `TRIAL_FINGERPRINT_PEPPER` | Q5 | Re-hash all rows in one transaction (raw fingerprint accessible via Stripe). Cheap. |
| `TELEMETRY_USER_PSEUDONYM_SALT` | Q6 | Rotation impacts vendor-side identifier continuity but doesn't break internal lookups. Minimal disruption. |
| `IAP_RECEIPT_PEPPER` | Q7 | **One-way rotation.** Raw `original_transaction_id` is destroyed at scrub time; we cannot re-hash what we don't have. Once rotated, previously-scrubbed receipts cannot be matched to inbound webhooks. Rotate ONLY on confirmed compromise; expect manual ops resolution for refund/renewal webhooks during the rotation window. Code comment in env loader documents this constraint. |

This convention exists so a future engineer adding a fourth instance of the pattern doesn't reinvent it. v1.3.1 polish can add `pepper_version` columns + dual-pepper grace periods uniformly across the rotatable instances (`TRIAL_FINGERPRINT_PEPPER`, `TELEMETRY_USER_PSEUDONYM_SALT`) if rotation cadence justifies the engineering. `IAP_RECEIPT_PEPPER`'s one-way constraint is structural and won't be improved by tooling — only by a different architecture (e.g., keeping a server-side encrypted vault of raw IDs, which has its own GDPR exposure).

The net +9d increment is gap-closing, not scope creep — every increment defuses a money-path bug, App Store rejection, GDPR exposure, custody-surface ambiguity, or operational dead-end. The Cashier decision is the rare case where the right architectural choice ALSO returns engineer-days to the budget. The fee-collector and revenue-projection asks codify load-bearing operational decisions the original spec underspecified; closing them now avoids sprint-2 remediation cycles when ops asks "where do these fees actually land?" or "why is collector balance higher than revenue?"

Backend can ship most of this in parallel with mobile's ~7.5w. Critical-path dependencies for mobile (cue queue infrastructure, `/checkout` 409 contract, `/change-plan` and `/reactivate` endpoints, schema additions to `/me/entitlements` and `/me/pending-cues`, the `entitlements_projection` read model that powers conflict detection) should land in week 1–2 so mobile's feature work isn't blocked.

Fee-collector infrastructure (Backend-Q2) and revenue-projection refactor (Backend-Q3) are independent of the mobile critical path and can land any time before sweep cadence becomes load-bearing (week 5+ realistic; both only matter once production traffic generates fees).

Patching the original handover with these deltas produces a v1.3.0 backend spec that is genuinely shippable without a remediation cycle in production.

---

## Document history

- **2026-05-08** — Initial deltas authored from joint mobile/backend Q1–Q17 architectural review
- **2026-05-08** — Backend-Q1 added: Cashier hybrid (γ) decision; Q17 endpoints rewritten as Cashier wrappers; Q15.2 deletion sequence expanded with Stripe customer erasure step; schedule recalculated to 41.5 engineer-days
- **2026-05-08** — Backend-Q2 added: Fee-collector wallets (α) decision; KMS-backed EOA per chain; daily sweep cron + `revenue_sweeps` audit table; `ServiceFeeCharged` event extended with quote linkage; native-asset-send fee policy; migration thresholds for future (β); §0.1 legal-framing prereq; ADR-0001 to be drafted; schedule recalculated to 43.5 engineer-days
- **2026-05-08** — Backend-Q3 added: dual-upstream revenue projection — chain-ingestor (Helius/Alchemy extensions) for on-chain fees, PSP outbox for off-chain fees; replaces original §0.2 multi-connection saga (superseded for v1.3.0 fee path under non-custodial v7.12.0+ model); finality thresholds per chain; `unmatched_collector_inflows` defensive audit table; hourly chain-history scan; `revenue_outbox_events` table; ADR-0002 to be drafted; schedule recalculated to 45 engineer-days
- **2026-05-08** — Backend-Q4 added: `Domain/Quote` → `Domain/Pricing` rename; aggregate `PriceQuote`, table `price_quotes`, event-sourced `price_quote_events`, `config/pricing.php`; wire URL renamed to `/api/v1/pricing/quote`; **wire field `quoteId` and error codes `ERR_QUOTE_*` deliberately preserved** (mobile-facing contracts unchanged); `/api/v1/quotes` (RampController) deprecation deferred to v1.3.1; ADR-0003 to be drafted; mobile deltas patched with the URL change; schedule unchanged at 45 engineer-days
- **2026-05-09** — Backend-Q5 added: trial-abuse card-fingerprint check (α); `trial_card_fingerprints` table; `TRIAL_FINGERPRINT_PEPPER` env var; Setup-mode Checkout flow (option c) for first-time users; Redis lock with 5s acquire timeout; Filament admin override; daily purge cron; §1.6 retry window adjusted from `null` (lifetime) to `last_used_at + 12 months`; honest documentation of (α)'s limits (multi-card abusers, virtual cards); schedule recalculated to 47 engineer-days
- **2026-05-09** — Backend-Q6 added: pseudonymisation framing correction (δ); language pass replacing "pseudonymisation" / "pseudonym" / "device-keyed identifier" with accurate framings; "Why we don't call this pseudonymisation" sub-section with GDPR Art 4(5) analysis; DPIA refresh with lawful-basis split (legitimate interest for crash, consent for product analytics); `vendor_data_deletions` audit table; `/me/data-deletion` returns 202 + `request_id`, orchestrates per-vendor deletion calls with 7-day SLA; processor list publication captured as v1.3.1 follow-up; "v1.3.0 simple-pepper pattern" convention paragraph added to closing; schedule recalculated to 47.5 engineer-days
- **2026-05-09** — Backend-Q7 added: IAP receipt pseudonymisation (α) — corrects deltas Q15.2's "delete IAP receipts" contradiction with §4.5; Art 17(3)(b) tax-law-retention exception (Lithuania VATA Art 78 ≥10 years); `iap_receipts` schema additions (`original_transaction_id_hash`, `scrubbed_at`, `scrubbed_renewal_count`); webhook lookup raw → hash fallback; REFUND vs RENEWAL race-vs-failure handling for scrubbed receipts; `closed_account_refunds` extended with `source` ENUM and per-source identifier columns; `IAP_RECEIPT_PEPPER` joins simple-pepper convention as third instance with one-way rotation caveat; full erasure walk ordering (a–l) supersedes Q15.2's draft; 10-year retention floor documented, v1.4+ purge cron deferred; privacy policy + DPIA updated with Art 17(3)(b) language and pro-rata-refund asymmetry disclosure; schedule recalculated to 48.25 engineer-days
- **2026-05-09** — Backend-Q8 added: cue dispatch architecture (γ) — Laravel delayed jobs for time-from-event cues (`pro_trial_reminder_d1`, `trial_ending_2d/1d/1h`), windowed cron with `LEFT JOIN` candidate query for aggregate-condition cues (`kyc_required`); webhook-driven cues keep direct insert pattern; four `EnqueueXxxCue` job classes with job-fire-time eligibility re-evaluation; database queue driver locked for v1.3.0 with documented migration trigger (>100 jobs/sec sustained or >1M jobs steady-state); `cue.job.skipped` observability metric per skip reason; UTC throughout backend (cues are pulled, not pushed; mobile renders local); legacy-user backfill captured as v1.3.1 follow-up; Q5.5 cue table updated with explicit Dispatch column; Q11.2 superseded inline; **schedule reclaimed −1.5d, recalculated to 46.75 engineer-days**
- **2026-05-09** — Backend-Q9 added: money format on the wire (β) — smallest-unit string + explicit `decimals` + exactly one of `currency` (ISO 4217) or `asset` (ticker), everywhere on the wire; eliminates three coexisting formats from original §0.2 / §1.2 / deltas Q3.1; backend `Domain/Pricing/ValueObjects/Money` VO; Form Request validation rejects decimal-point amounts with `ERR_VALIDATION_002`; storage stays per-table judgment (implicit-via-name for single-currency, explicit-triple for variable-currency); deltas Q3.1 fee breakdown shape updated in-place with Money VO objects; original §0.2 + §1.2 amendments specified; multi-chain `asset_chain` field deferred to v1.3.1; ADR-0004 to be drafted; mobile deltas patched with locked `money.ts` helper signature; schedule recalculated to 47.5 engineer-days. **Closes the formal architectural grill across mobile (Q1–Q17) and backend (Q1–Q9)**; remaining work is documentation + implementation only.
