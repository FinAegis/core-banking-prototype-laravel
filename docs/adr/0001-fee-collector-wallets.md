# ADR-0001 — Fee collector wallets: KMS-custodied EOAs with daily sweep

**Status:** Accepted
**Date:** 2026-05-09
**Decision-maker:** Backend engineer
**Related:** Backend-Q2 in `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md`; original handover §2.4 (Collection mechanism per fee type); ADR-0004 (Money on the wire — fee transfers carry Money in this format)

---

## Context

Plan B Commercial v1.3.0 introduces service fees on user-initiated on-chain transfers. The mechanism (locked in deltas Q2 as Architecture A) is a **sibling fee transfer in the userOp batch**: when the user signs a userOp to send 100 USDC to Alice, the same userOp also transfers €0.20-equivalent USDC (e.g. 200000 micro-USDC) to a Zelta-controlled fee-collector address on the same chain.

The original handover and deltas committed to Architecture A but did not specify **where the fee-collector address actually lives** — i.e., who custodies the key, how it's protected, how the accumulated balance is swept off-chain to operating accounts. That question is the load-bearing operational decision behind A and warrants its own architectural commitment.

The decision must hold for five chains in v1.3.0: Polygon, Base, Arbitrum, Ethereum, Solana. Each chain needs at least one collector address. The total fee inflow per chain at year-1 plateau (~16k MAU per Path A break-even forecast, free-tier sends at €0.20 each) is roughly **€100/day/chain**, sweep daily, on-chain balance at any moment ~€100 per chain.

---

## Decision

**One fee-collector EOA per chain, KMS-backed signing key, swept daily into operating accounts.**

Five collector addresses total:

- `fee_collector_polygon`
- `fee_collector_base`
- `fee_collector_arbitrum`
- `fee_collector_ethereum`
- `fee_collector_solana`

Configured in `config/fees.php` keyed by chain, env-driven (`FEE_COLLECTOR_POLYGON_ADDRESS`, `FEE_COLLECTOR_POLYGON_KMS_ARN`, etc.). Public addresses are not secrets — they appear in every quoted userOp's calldata and on-chain. The KMS ARN binding the address to a signing key is the secret.

Production signing path: AWS KMS asymmetric secp256k1 keys for EVM, ed25519 for Solana. Key never leaves the HSM. Application calls `kms:Sign` to produce sweep-transaction signatures. Local/staging uses env-stored private keys for the same addresses (different addresses per environment).

---

## Alternatives considered

### (α) One fee-collector EOA per chain, KMS-backed — **chosen**

Five EOAs, one per chain. Daily cron sweeps each into an off-ramp connected account (Coinbase Prime, Circle Mint, or Stripe Connect application_fee_amount where applicable).

**Pros:** operationally simplest at v1.3.0 traffic; minimal infrastructure cost; matches existing patterns for similar low-throughput operational wallets at peer companies; key custody handled by AWS KMS (operationally bulletproof, audit-trailed, key never leaves HSM); revoke / rotate available via KMS without contract redeploys.

**Cons:** custodial surface. We hold platform-revenue USDC for ~24h per chain between sweeps. Concentrated risk per chain (one EOA = one private key = one theft target). Acceptable at €100/day/chain inflow; less acceptable at €25k+/month per chain (escalation trigger documented below).

### (β) One Pimlico/Privy smart-account per chain

Account-abstraction wallets for Zelta itself (not user wallets). Multi-sig + social recovery. Paymaster-sponsored gas (collector wallets pay no gas).

**Pros:** reduces single-key blast radius via multi-sig; recovery paths exist if a key is lost; gas-free sweep transactions (paymaster covers).

**Cons:** higher upfront ops complexity (deploy + configure account contracts on each chain); ongoing Pimlico/Privy costs; v1.3.0 traffic doesn't justify the operational tax.

**Migration trigger:** revisit (β) when sustained fee inflow exceeds **€100-150k/mo per chain** for 90+ days. The original Backend-Q2 grilling proposed €25k/mo as the trigger; the orchestrator pushed back that €25k/mo is small money even after absorbing some smart-account ops cost. €100-150k/mo per chain (≈ 4-6× current Year-1 forecast) is the right threshold — at that point, on-chain collector balance is meaningful enough that the multi-sig insurance pays off.

### (γ) MPC-custodied wallets via Fireblocks / Copper / similar

Treasure-grade MPC custody. Reads as institutional-quality to investors and to a future EMI auditor.

**Pros:** strongest custody story; right answer if we ever hold material balances on-chain longer than a sweep cycle; aligns with the Path A licensed-banking narrative if we go that direction.

**Cons:** material monthly cost (Fireblocks min ~€2-3k/mo; Copper similar). Overengineered for a fee collector under €100k/mo.

**Migration trigger:** consider only if Path A licensed-banking is pursued, where on-chain custody becomes part of regulatory reporting. Path B SaaS doesn't warrant it.

---

## Consequences

### Positive

- **Operationally simple at launch.** Five EOAs, one config block, one sweep cron, one variance audit.
- **KMS handles the hardest part.** Key never in our application code; key never in our env files (only the ARN); KMS provides audit log of every signing operation; key revocation via KMS does not require contract redeploys.
- **Reuses existing infrastructure** for chain-event monitoring (Helius for Solana, Alchemy for EVM — both already integrated). The collector addresses become additional subscriptions, not new providers.
- **Migration paths are open.** If/when we cross the €100-150k/mo threshold per chain, we can deploy smart-account contracts under the same address scheme and rotate.

### Negative

- **Custodial activity, briefly.** Holding platform-revenue USDC for ~24h per chain is custody of platform earnings, not customer funds, but it requires explicit legal framing (see §0.1 Prerequisites in the deltas). Plan B's "software vendor, not payment service" positioning relies on the framing line: *"Service fees are platform revenue; customers do not have a claim against held balances. We hold them transiently before settling to our operating account."* Legal owner: founder.
- **Concentration risk per chain.** A compromise of the Polygon KMS key drains the Polygon collector. Sweep frequency limits the blast radius (max ~€100 per chain at any moment under Year-1 traffic), but a sophisticated attacker who watches the sweep cadence could time an exploit. Mitigation: anomaly detection on outbound transfers from collector addresses (any non-sweep outbound triggers an alert).
- **Native asset sends do not collect a fee.** ETH/MATIC/SOL transfers cannot piggyback a stablecoin fee (no USDC balance precondition; adding a separate native-asset call complicates the userOp). Native sends are free at all tiers in v1.3.0. Loss is negligible (native sends are rare for consumer use cases).
- **Native gas top-up pattern required.** The collector EOAs need gas (ETH on EVM chains, SOL on Solana) to execute sweep transactions. Sweep cron either: (a) uses a gas paymaster where the chain supports it, or (b) tops up collector EOAs from an operations gas wallet on a separate cron. Option (b) is the v1.3.0 default — simpler, no paymaster integration on the collector path.

### Neutral

- **Per-chain isolation.** A failure mode on one chain (Polygon RPC outage; Helius webhook delay) does not cascade. Sweep cron retries per chain independently.
- **Sweep destination per chain.** Polygon/Base/Arbitrum/Ethereum sweep to a Coinbase Prime or Circle Mint deposit address; Solana sweeps to a Coinbase Prime Solana deposit. Sweep targets are configured per chain.

---

## Implementation notes

### Configuration

```php
// config/fees.php
return [
    'collectors' => [
        'polygon' => [
            'address' => env('FEE_COLLECTOR_POLYGON_ADDRESS'),
            'kms_arn' => env('FEE_COLLECTOR_POLYGON_KMS_ARN'),
            'sweep_destination' => env('SWEEP_DESTINATION_POLYGON'),
            'sweep_provider' => 'coinbase_prime',  // or 'circle_mint'
        ],
        'base' => [...],
        'arbitrum' => [...],
        'ethereum' => [...],
        'solana' => [
            'address' => env('FEE_COLLECTOR_SOLANA_ADDRESS'),
            'kms_arn' => env('FEE_COLLECTOR_SOLANA_KMS_ARN'),  // ed25519
            'sweep_destination' => env('SWEEP_DESTINATION_SOLANA'),
            'sweep_provider' => 'coinbase_prime',
        ],
    ],
    'tiers' => [
        // unchanged from the original §10.2 spec; tier definitions move to config/pricing.php
        // per ADR-0003.
    ],
];
```

### Sweep cron

`php artisan revenue:sweep-fee-collectors` runs daily at 02:30 UTC. Per chain:

1. Read collector balance via Helius (Solana) / Alchemy (EVM).
2. If balance > minimum sweep threshold (configurable per chain, default ~€10 worth of USDC):
   - Build a transfer transaction (USDC → sweep destination address).
   - Sign via KMS (`kms:Sign` against the collector's ARN).
   - Submit to chain.
   - Wait for finality (32 confirmations on EVM, 32 slots on Solana — same finality thresholds as ADR-0002's chain-event-sourced ingestor).
3. Record the sweep in `revenue_sweeps`:
   - `chain`, `swept_amount_asset`, `swept_to`, `tx_hash`, `expected_eur_cents`, `actual_eur_cents` (set after off-ramp settlement), `settled_at`.
4. Match the sweep against the sum of `revenue_events` rows where `collector_address = collector_X AND occurred_at < sweep_window_end`. If `|variance| > €10` OR `>1%`: write `variance_reason` and page ops via the existing PagerDuty hook.

### Native gas top-up pattern

Separate `php artisan revenue:top-up-collector-gas` cron runs daily at 02:00 UTC (before sweep). Reads native balance of each collector. If below threshold (default 0.05 ETH / 0.5 SOL), transfers from operations gas wallet to collector. Operations gas wallet is a separate KMS-keyed EOA, funded manually quarterly.

### `revenue_sweeps` schema

```sql
CREATE TABLE revenue_sweeps (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chain VARCHAR(32) NOT NULL,
    collector_address VARCHAR(128) NOT NULL,
    sweep_window_start TIMESTAMP NOT NULL,
    sweep_window_end TIMESTAMP NOT NULL,
    swept_amount_asset BIGINT UNSIGNED NOT NULL,        -- smallest-unit per ADR-0004
    swept_amount_decimals TINYINT UNSIGNED NOT NULL,
    swept_amount_denomination VARCHAR(16) NOT NULL,     -- 'USDC' typically
    swept_to VARCHAR(128) NOT NULL,
    sweep_tx_hash VARCHAR(128) NOT NULL,
    expected_eur_cents BIGINT NOT NULL,                  -- sum of revenue_events for this window
    actual_eur_cents BIGINT NULL,                        -- populated after off-ramp settlement
    variance_eur_cents BIGINT NULL,
    variance_reason VARCHAR(64) NULL,                    -- 'unmatched_inflow' | 'fx_drift' | etc.
    settled_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_sweeps_chain_window (chain, sweep_window_start),
    KEY idx_sweeps_unsettled (settled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Extended `ServiceFeeCharged` event

Per Backend-Q2 refinement, the event carries the source quote ID and the EUR rate at quote time, so revenue analytics and reconciliation can join back to the originating commitment:

```
ServiceFeeCharged {
  user_id,
  source: tx | swap | ramp,
  reference_id,                            -- tx_hash | swap_id | ramp_session_id
  reference_quote_id,                      -- price_quote_id (links to Domain/Pricing)
  asset_amount_smallest_unit,
  asset_decimals,
  asset: 'USDC',
  collector_address,
  chain,
  eur_rate_at_quote: { rate, decimals, source_id },  -- frozen from the consumed quote
  gross_eur_cents,
  user_tier_at_time,
  occurred_at,
}
```

### Legal §0.1 prerequisite

Add to the deltas §0.1 (Prerequisites — business) under "Engineering pre-launch":

> **Legal sign-off on fee-collector custody framing.** Single-line ToS addition: *"Service fees are platform revenue; customers do not have a claim against amounts held in fee-collector wallets. Zelta holds these balances transiently (typically <24h) before settling to operating accounts."* Founder + counsel review before week 1 of v1.3.0 build.

---

## References

- `docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md` — Backend-Q2 section, source material
- `docs/BACKEND_HANDOVER_PLAN_B_COMMERCIAL.md` §2.4 (Collection mechanism per fee type)
- `docs/adr/0002-revenue-projection-dual-upstream.md` — sibling ADR; chain-event-sourced ingestor projects from these collector addresses into `revenue_events`
- `docs/adr/0004-money-on-the-wire.md` — fee transfers carry Money in the (amount, decimals, denomination) shape
- AWS KMS asymmetric signing — operational pattern for production keys
- `app/Domain/Wallet/Helpers/SolanaAddressHelper` — existing pattern for ed25519 derivation that the operations gas wallet's Solana key can mirror
