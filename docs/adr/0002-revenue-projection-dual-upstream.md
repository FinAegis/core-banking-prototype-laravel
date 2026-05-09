# ADR-0002 — Revenue projection: chain-event-sourced for on-chain, outbox saga for off-chain

**Status:** Accepted
**Date:** 2026-05-09
**Decision-maker:** Backend engineer
**Related:** Backend-Q3 in `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md`; original handover §0.2 (Multi-tenancy / saga pattern), §4 (Revenue attribution); ADR-0001 (Fee collector wallets); ADR-0003 (Pricing bounded context)

---

## Context

Plan B Commercial v1.3.0 records revenue events from two structurally different upstreams:

- **On-chain fees** (`tx_fee`, `swap_margin`): collected via sibling transfers in user-signed userOps to the fee-collector wallets defined in ADR-0001. The chain itself is the durable log of which fee was collected when.
- **Off-chain fees** (`subscription_initial`, `subscription_renewal`, `refund`, `ramp_margin`, `baas`, `kyc_margin`): sourced from PSP webhooks (Stripe, Apple App Store, Google Play, Stripe Bridge). The PSP is the source of truth.

Original §0.2 specified a single mechanism for both — a multi-connection saga where the tenant-side wallet write commits, then a queued job records the revenue event on the global connection. That pattern was written for the deprecated custodial Shamir-shard wallet flow. Under v7.12.0+ non-custodial Privy, **there is no tenant-side wallet write in the user's transaction path** — the user's funds move on-chain, signed by their Privy keys, never touching a Zelta-tenant DB connection. The saga's premise doesn't apply.

The deeper issue surfaced by Backend-Q3: under the saga pattern, a queued job that permanently fails (Redis loses the message; worker dies; DB hiccups during dispatch) means the on-chain fee transfer succeeded but no `revenue_events` row was written. Money is in the collector wallet without an audit trail back to user/tx. PSP-side reconciliation in §4.4 doesn't catch this — it's not a Stripe payout issue.

We need a projection mechanism that's at least as durable as the upstream, for both upstream classes.

---

## Decision

**Dual upstream — chain-event-sourced ingestor for on-chain fees; outbox saga for off-chain fees.** Both project into the same `revenue_events` read model.

- **On-chain path:** Helius / Alchemy webhook subscriptions on the fee-collector addresses (one per chain per ADR-0001). Webhook arrives → ingestor verifies finality → looks up the source userOp / Solana tx via `quotes.user_op_hash` → writes the `revenue_events` row. The chain is the durable log; we project from it.
- **Off-chain path:** PSP webhook arrives → handler writes a `revenue_outbox_events` row in the SAME transaction as the webhook-dedup row in `processed_webhook_events` (deltas Q7.3). A worker reads outbox rows, writes `revenue_events`, marks delivered. Idempotent on `(source_type, source_event_id)`.

Same `revenue_events` table; two distinct projection paths.

---

## Alternatives considered

### (α) Saga via queued job for everything (original §0.2)

The single-mechanism approach the spec started with.

**Rejected because:**
- Saga premise (tenant-connection wallet write) doesn't apply to Privy non-custodial flow — there's no tenant wallet write in the user's transaction path.
- Queued-job dispatch can drop messages between dispatch and worker pickup (Redis crash; worker death). On-chain fees succeed regardless; revenue events go missing.
- PSP-side reconciliation (§4.4) catches Stripe-side variance but not on-chain variance — chain-side dropped events would only be detected at end-of-day sweep reconciliation, with no way to attribute "money in collector without revenue_event" back to a specific user/tx.

### (β) Outbox saga for everything

Replace queue-dispatch with a transactional outbox table on the global connection. Worker reads outbox, writes `revenue_events`, marks delivered.

**Rejected for on-chain fees:** still has the same failure mode for on-chain — if the controller doesn't write to the outbox after the on-chain transaction succeeds (request times out, container dies between on-chain success and outbox write), revenue is still lost. The chain *did* succeed; we just didn't observe it.

**Accepted for off-chain fees:** it's the right shape. PSP webhook handler writes outbox + dedup row in one transaction; worker is the only thing that can drop, and the worker can retry indefinitely against a durable row.

### (γ) Chain-event-sourced ingestor for on-chain fees + (β) outbox for off-chain — **chosen**

Two upstream paths. On-chain treats the chain as the durable log and projects via webhook-driven ingestor with periodic backfill. Off-chain uses the outbox saga.

**Pros:** structurally rules out dropped events on the on-chain side (can't drop what's permanently on-chain); off-chain reuses the proven outbox pattern; reuses already-paid-for infrastructure (Helius for Solana webhooks, Alchemy for EVM webhooks — both already in production for user-balance sync); each path's reconciliation has a natural source-of-truth (chain history vs PSP reports).

**Cons:** two distinct upstream paths to monitor; webhook latency adds ~30s to revenue recognition vs synchronous saga; chain reorgs require finality thresholds; ingestor extends existing processors (not net-new infrastructure but adds code surface).

---

## Consequences

### Positive

- **Structural durability for on-chain fees.** The chain is replayable; we never lose data the chain has. Webhook drops are caught by the periodic chain-history scan against the collector address. The saga's "queued job dropped between dispatch and consume" failure mode is structurally impossible.
- **Reconciliation is automatic on the chain side.** ADR-0001's daily sweep reads collector balance and matches against `revenue_events` for the window — divergence is detected within 24h with attribution.
- **Off-chain saga has clean upstream-of-truth.** Stripe `event.id` / Apple `notificationUUID` / Google `messageId` is the dedup key; outbox idempotency is exact; reconciliation matches against PSP reports per §4.4.
- **Reuses existing infrastructure.** `HeliusTransactionProcessor` and `AlchemyWebhookManager` are in production. Adding fee-collector address subscriptions is config + code-path extension, not new providers.

### Negative

- **Two distinct upstream paths.** A future engineer reading `revenue_events` will need to know which paths feed it. Documented explicitly in this ADR + the deltas Backend-Q3 section.
- **Latency.** On-chain transfer settles in ~5s (Polygon/Base/Arbitrum), ~12s (Ethereum), ~1s (Solana). Webhook fires within ~30s. Finality wait adds ~3 minutes (Polygon), ~13 minutes (Ethereum), ~13s (Solana). Hourly backfill scan covers any webhook drops. Net: a fee shows up in `revenue_events` within ~1 minute under normal operation, within ~1 hour under degraded webhook conditions. Acceptable for revenue recognition cadence.
- **Reorg handling required.** Polygon and Ethereum can reorg up to ~12 blocks. The ingestor MUST wait for finality before writing to `revenue_events`. Pre-finality txs are tracked in-memory but not projected; reorg before finality drops the tracked entry silently.
- **Original §0.2 saga rule is superseded for v1.3.0 fee path.** The rule stays as a guard-rail for FUTURE cross-connection writes that legitimately touch tenant + global tables. Backend-Q3 supersession note explicitly applies only to the fee path.

### Neutral

- **Off-chain fees' outbox lives on the global connection.** No multi-connection issue (PSP webhook handler runs on global; outbox table is global; revenue_events is global). The original §0.2 multi-connection saga concern doesn't apply here either.
- **Both paths share the same idempotency primitive.** Off-chain via `processed_webhook_events` + outbox `(source_type, source_event_id)` UNIQUE. On-chain via `quotes.user_op_hash` lookup (the userOp hash is unique per quote per ADR-0003 / deltas Q2.2).

---

## Implementation notes

### Per-chain finality thresholds

| Chain | Threshold | Rationale |
|---|---|---|
| Polygon | 32 confirmations (~1 min) | Past typical reorg depth (12-16 blocks); industry standard |
| Base | 32 confirmations (~1 min) | Same as Polygon; OP-stack chain |
| Arbitrum | 32 confirmations (~7 sec) | Sequencer-confirmed; low reorg risk |
| Ethereum | Finalized Beacon commitment (~12.8 min) | Strict finality; matches L1 risk profile |
| Solana | Commitment level `finalized` (~13s) | Native Solana finality semantics |

Pre-finality txs are tracked in an in-memory cache (`Cache::tags(['unconfirmed-fees'])`) but never projected to `revenue_events`. A reorg that drops a pre-finality tx removes the cache entry silently. A finalized tx that reorgs (extremely rare on Ethereum, never on Polygon/Base/Arbitrum/Solana finality) is a P0 incident that pages ops; manual reconciliation required.

### Chain-event-sourced ingestor

Extend `HeliusTransactionProcessor` (Solana) and `AlchemyWebhookManager` (EVM) with a fee-collector code path:

- Webhook subscribes to incoming USDC transfers to each collector address (config from ADR-0001).
- Inbound webhook: parse the transfer, extract `from_address`, `amount`, `tx_hash`. Wait for finality.
- Look up `quotes.user_op_hash` matching the source userOp / Solana instruction. The hash is part of the userOp's signed payload per deltas Q2.2.
- If found: write `revenue_events` row with `(user_id, source, reference_quote_id, gross_eur_cents, asset_amount, asset, collector_address, occurred_at)`. Match the extended `ServiceFeeCharged` event shape from ADR-0001.
- If not found: write `unmatched_collector_inflows` row, page ops if amount > €10. Never write `revenue_events` without quote attribution.

```sql
CREATE TABLE unmatched_collector_inflows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chain VARCHAR(32) NOT NULL,
    collector_address VARCHAR(128) NOT NULL,
    from_address VARCHAR(128) NOT NULL,
    asset VARCHAR(16) NOT NULL,
    amount_smallest_unit BIGINT UNSIGNED NOT NULL,
    amount_decimals TINYINT UNSIGNED NOT NULL,
    eur_equivalent_cents BIGINT UNSIGNED NULL,
    tx_hash VARCHAR(128) NOT NULL,
    detected_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    resolution_note TEXT NULL,
    UNIQUE KEY uniq_unmatched_tx (chain, tx_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Hourly chain-history scan

Belt-and-braces against webhook drops. `php artisan revenue:scan-collector-history` runs hourly:

- For each collector address, query the chain for transfers since last_scanned_block.
- For each transfer not already in `revenue_events`, run the ingestor logic (finality check, quote lookup, write or unmatched).
- Update `last_scanned_block` for the chain.

This is the durability backstop. Webhooks deliver fast-path; the scan covers gaps.

### Outbox saga for off-chain fees

```sql
CREATE TABLE revenue_outbox_events (
    id CHAR(36) PRIMARY KEY,
    source_type VARCHAR(32) NOT NULL,                    -- 'stripe' | 'apple_iap' | 'google_play' | 'stripe_bridge'
    source_event_id VARCHAR(255) NOT NULL,                -- Stripe event.id, Apple notificationUUID, etc.
    payload JSON NOT NULL,                                -- intended revenue_events row body
    status ENUM('pending','delivered','failed') NOT NULL DEFAULT 'pending',
    delivered_at TIMESTAMP NULL,
    error_message TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_outbox_source (source_type, source_event_id),
    KEY idx_outbox_pending (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

PSP webhook handler writes the outbox row and the `processed_webhook_events` dedup row in the same `DB::transaction()`. `ProjectRevenueOutbox` worker reads pending rows, writes `revenue_events`, marks delivered. Standard Laravel queue retries on transient failure; permanent failures land in `failed` status with `error_message` populated for ops review.

### `revenue_events` is the unified read model

Schema unchanged from original §4.1. Both paths write into it. Reconciliation in §4.4:

- For PSP-sourced rows: diff `revenue_events` sum-by-provider against PSP reports (Stripe Reporting API, Apple settlement reports, Google Play earnings).
- For chain-sourced rows: diff `revenue_events` sum-by-collector against the daily `revenue_sweeps` reconciliation (ADR-0001).

### Multi-connection regression test scope

Original §0.2 required a regression test for the saga's multi-connection deadlock. With (γ)+(β):

- On-chain projection has no multi-connection issue (chain ingestor and revenue_events are both on global). No test required.
- Off-chain outbox saga: PSP webhook handler writes to `revenue_outbox_events` AND `processed_webhook_events`, both global. The worker writes to `revenue_events`, also global. No tenant connection involved. No test required.

The original `tests/MultiConnection/FeeDeductionMultiConnectionTest` from §11.3 is replaced by an outbox-delivery integration test under `tests/Feature/Revenue/RevenueOutboxDeliveryTest`.

### §0.2 supersession note

Add to deltas closing — already captured in Backend-Q3:

> The multi-connection saga rule from original §0.2 stays as a guard-rail for future flows that legitimately write across tenant and global connections. It is **superseded specifically for the v1.3.0 fee projection path**, where on-chain fees use the chain-event-sourced ingestor (no cross-connection write) and off-chain fees use the outbox saga (single-connection write).

---

## References

- `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md` — Backend-Q3 section, source material
- `docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` — original §0.2 (Conventions / multi-tenancy), §4 (Revenue attribution)
- `docs/adr/0001-fee-collector-wallets.md` — collector addresses are the upstream for the on-chain projection path
- `docs/adr/0003-pricing-bounded-context.md` — `quotes.user_op_hash` is the join key from inbound chain transfer to user/quote
- `docs/adr/0004-money-on-the-wire.md` — revenue_events stores Money in the smallest-unit-with-decimals shape
- `app/Domain/Wallet/Helpers/HeliusTransactionProcessor` — existing infrastructure being extended for the on-chain ingestor path
- `processed_webhook_events` table — existing infrastructure for off-chain webhook dedup; outbox writes alongside it
