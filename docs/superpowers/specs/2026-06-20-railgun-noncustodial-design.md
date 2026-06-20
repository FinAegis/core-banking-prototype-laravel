# RAILGUN Non-Custodial Redesign — Design & Mobile Contract

- **Status:** DRAFT — for approval before implementation
- **Date:** 2026-06-20
- **Decision owner:** (pending) — backend + mobile
- **Supersedes (for the non-custodial path):** the custodial server-side bridge integration shipped v5.6.0
- **Related:** non-custodial Wallet Send (Privy, v7.12.0); `docs/archive/RAILGUN_MOBILE_HANDOVER.md` (custodial-era handover, now stale)

---

## 1. Why this exists

The RAILGUN privacy stack shipped v5.6.0 is **custodial for the shielded funds**, which is inconsistent with the rest of the platform (Wallet Send is non-custodial via Privy). Concretely:

| Layer | Today | Custody |
|---|---|---|
| On-chain EVM tx (shield/unshield/transfer calldata) | Returned unsigned; device signs + broadcasts | ✅ non-custodial |
| **RAILGUN 0zk wallet (spending + viewing keys)** | Seed = `hash_hmac('sha512', userId, app.key)`; `encrypted_mnemonic` stored server-side; **proving done server-side in the Node bridge** | ❌ **custodial** |

Because the server can regenerate every user's 0zk spending key (`RailgunPrivacyService::generateMnemonic`/`deriveEncryptionKey`, `RailgunWallet.encrypted_mnemonic`), it can move users' shielded funds. The outer-tx non-custody is cosmetic while the spending key is server-derived.

**Decision:** go non-custodial — the device holds the seed, proves on-device, and decrypts balances with its own viewing key. The server never holds or derives spending key material.

---

## 2. The load-bearing constraint (why this is a mobile project)

RAILGUN's `JoinSplit` Groth16 circuit takes the wallet's **EdDSA-Poseidon signature** and **nullifyingKey** as **private witness inputs** and verifies them inside the circuit. Therefore:

> **Proving cannot be delegated without surrendering custody or leaking the entire private transaction graph to the server. Whoever proves must hold the spending key. Non-custodial ⇒ the device proves.**

This kills the current `DelegatedProofService` (server-side proving for low-end devices) as a non-custodial option. It is structural, not a missing API.

The official reference (Railway Wallet, which is React Native + fully self-custodial) proves on-device by **embedding a full Node.js runtime on the phone** (`nodejs-mobile-react-native`) running `@railgun-community/wallet` + a **native Rust/C++ Groth16 prover** (`@railgun-privacy/native-prover`). The WASM/snarkjs path is too slow/memory-heavy for phones. Proof generation is **~20–30s on slow devices**.

**Implication:** the bulk of this effort is **mobile native engineering** (embed Node + native prover + native LevelDB + artifact store + build pipeline). The backend's job shrinks to **non-custodial support services**.

---

## 3. Target architecture

```
┌────────────────────────── DEVICE (non-custodial) ──────────────────────────┐
│ Privy embedded EVM key ──sign(fixed msg)──► BIP-39 entropy ─► RAILGUN seed   │
│ @railgun-community/wallet v10  (in embedded Node.js via nodejs-mobile-RN)    │
│   • holds spending + viewing keys (OS secure storage / Keychain / Keystore) │
│   • native Groth16 prover (@railgun-privacy/native-prover, .dat artifacts)  │
│   • LevelDB (leveldown-nodejs-mobile) — notes/merkle cache                   │
│   • scans + DECRYPTS balances with its own viewing key                      │
│   • builds + proves + (broadcasts | hands to broadcaster) shield/unshield/tx│
│   • generates its own POI proof                                             │
└───────────────┬───────────────────────────────────┬────────────────────────┘
                │ reads (no keys ever leave device)  │ submits / records
                ▼                                     ▼
┌──────────── BACKEND (non-custodial shared services only) ───────────────────┐
│  • RPC proxy (rate-limited per-chain RPC)                                   │
│  • Merkle-tree sync / commitment feed  (speeds device first-load & re-scan) │
│  • Artifact CDN mirror  (mirror of assets.railgun.org IPFS — reliability)   │
│  • POI aggregator node  (self-hosted private-proof-of-innocence)            │
│  • Broadcaster awareness (public Waku network, or self-signing fallback)    │
│  • Activity-feed mirror  (sees only on-chain EVM txs, never the 0zk graph)  │
│  • Registers PUBLIC 0zk address for feed/notifications                      │
└─────────────────────────────────────────────────────────────────────────────┘
```

The backend **never** holds: the mnemonic, spending key, viewing key, or `shieldPrivateKey`, and never generates a spend/unshield/transfer proof.

---

## 4. Key model (device-side)

**4.1 Seed derivation (recoverable, no separate backup).** Derive the RAILGUN BIP-39 mnemonic deterministically from a Privy signature:

```
msg     = RAILGUN_SEED_DERIVATION_MESSAGE   // frozen, domain-separated, NEVER changed
sig     = privyEmbeddedWallet.signMessage(msg)
entropy = sha256(sig)                        // 256-bit
mnemonic = bip39.entropyToMnemonic(entropy)  // fed to createRailgunWallet
```

Same Privy key + same message ⇒ same 0zk wallet ⇒ recoverable on any device the user signs into via Privy, no seed phrase to back up.

> ⚠️ **Must verify before committing:** (a) Privy embedded-wallet ECDSA signatures over a fixed message are **byte-stable** (RFC-6979 deterministic) across sessions/devices — if not, "recovery" silently yields a *different* wallet. (b) The derivation message is **frozen forever** — changing it re-keys every user. (c) Security of all RAILGUN funds **collapses to the Privy key + message secrecy**. This is a non-standard pattern (RAILGUN does not bless it; Railway uses a standard backed-up BIP-39 phrase). **Fallback if (a) fails:** standard BIP-39 with user-held backup, Railway-style.

**4.2 `shieldPrivateKey` (device-derived).** Per RAILGUN canonical:

```
shieldPrivateKey = keccak256( privyEmbeddedWallet.signMessage( getShieldPrivateKeySignatureMessage() ) )
```

Per-wallet deterministic, recoverable, never sent to the server. (Today it's missing entirely — the cause of the shield 422 — and the stopgap would have derived it from `app.key`, i.e. custodial. We do **not** do that.)

**4.3 Viewing key (device-held).** The device holds the viewing key and decrypts balances/history locally. The current `GET /viewing-key` SHA-256 stub is **removed** — it was never a real viewing key.

---

## 5. New mobile API contract

The contract **inverts**: the backend stops "doing the private operation for you and returning calldata" and instead exposes read-only/relay services the on-device engine consumes. All endpoints stay under `/api/v1/privacy/*`, `auth:sanctum`.

### Retired (custodial — removed once Phase 3 lands)
- `POST /shield`, `POST /unshield`, `POST /transfer` **in their current form** (server builds + proves). The device now builds + proves + submits.
- `GET /viewing-key` (stub).
- `POST /proof-of-innocence` server-generated; `GET /proof-of-innocence/{id}/verify` (hardcoded `valid:true`).
- All `DelegatedProof*` endpoints (server-side proving is custodial).

### New / repurposed (non-custodial support)
| Method & path | Purpose | Holds keys? |
|---|---|---|
| `POST /wallet/register` | Device registers its **public** `0zk` address (+ chain) for activity-feed mirroring & push. Body: `{ railgun_address, network }`. | No |
| `GET /merkle/commitments?network=&fromBlock=` | Pre-indexed commitment/merkle feed to accelerate on-device scan (device still decrypts locally). | No |
| `GET /merkle/root?network=` | Current merkle root (convenience/verification). | No |
| `GET /artifacts/manifest` | Manifest of mirrored RAILGUN circuit artifacts (real `NxM` circuits from our `assets.railgun.org` mirror) + checksums. Repurposes the SRS endpoints. | No |
| `GET /poi/status?...` / `POST /poi/submit` | Proxy to our self-hosted POI aggregator node (device generates the POI proof; node aggregates/serves lists). | No |
| `GET /networks` | Source of truth for supported chains (`ethereum/polygon/arbitrum/bsc`; **not** base). Unchanged, but enum bug fixed. | No |
| `GET /broadcasters?network=` | Broadcaster/relayer info (or signal self-signing). | No |
| `PUT /transactions/{txHash}` | Device records a confirmed on-chain shield/unshield tx-hash for the activity feed (backend sees only the public EVM tx). | No |

The **happy path** (device): derive seed → `createRailgunWallet` → register public address → scan via `/merkle/commitments` → on `shield`: derive `shieldPrivateKey`, `populateShield`, sign+submit (or broadcaster), generate POI → record tx-hash. `unshield`/`transfer`: scan, build, **prove on-device**, submit.

---

## 6. Backend changes

**Retire / strip (Phase 3):**
- `RailgunBridgeClient` wallet-holding + proving calls; the Node bridge's `/shield`,`/unshield`,`/transfer`,`/wallet/create` (custodial). Bridge either deleted or reduced to a **stateless merkle/POI/RPC read proxy**.
- `RailgunPrivacyService::generateMnemonic` / `deriveEncryptionKey` (app.key seed).
- `DelegatedProofService`, `GenerateDelegatedProofJob`, `DelegatedProofJob` model + `delegated_proof_jobs` table.
- `railgun_wallets.encrypted_mnemonic` column → **drop** (migration). Keep `railgun_address` (public), `network`, `last_scan_block`, `status`.

**Build (Phase 1):**
- Merkle/commitment **indexer** (read-only; never holds keys) — repurpose `RailgunMerkleTreeService`.
- **Artifact CDN mirror** of `assets.railgun.org` real circuits — repurpose `config('privacy.srs')` + `/srs-*` endpoints (note: current `shield_1_1/unshield_2_1/transfer_2_2` names are a demo abstraction, not RAILGUN's `NxM` circuits — replace with real artifact manifest).
- Self-hosted **POI aggregator** (`private-proof-of-innocence`); expose `poiNodeURLs`.
- `POST /wallet/register` + activity-feed mirroring off public on-chain txs (reuse `EvmTransactionProcessor` patterns).

**Custody-neutral quick wins (Phase 0 — shippable now, no decision needed):**
- Fix the `base` network enum in `PrivacyController` (lines 67/145/331) → `ethereum/polygon/arbitrum/bsc`.
- Add an `ops:verify-env` guard: FAIL when `ZK_PROVIDER`/`MERKLE_PROVIDER` are inconsistent, or railgun-mode lacks `RAILGUN_BRIDGE_SECRET` (the silent demo-fallback footgun).
- Document the bridge sidecar's RPC/POI env vars (discoverability).
- Mark the custodial path **demo-only / flagged**; mobile builds UI against demo mode while Phases 1–2 land.

---

## 7. Phasing

- **Phase 0 — Unblock & honest baseline (days):** Phase-0 quick wins above; mobile builds the full UI against `ZK_PROVIDER=demo`. No custody change shipped; **custodial shield is NOT promoted to "working."**
- **Phase 1 — Backend support services (1–2 wk):** merkle indexer, artifact mirror, POI node, `wallet/register`, schema migration to drop the seed column.
- **Phase 2 — Mobile on-device engine (the big lift):** embed `nodejs-mobile-react-native` + `@railgun-community/wallet` v10 + native prover + LevelDB + artifact store; seed-from-Privy; on-device shield/unshield/transfer + POI. Native build pipeline (Rust cross-compile, NDK, patch-package).
- **Phase 3 — Cutover & remove custodial:** delete custodial bridge proving, `DelegatedProofService`, `encrypted_mnemonic`; default non-custodial.

---

## 8. Decisions (signed off 2026-06-20)

1. **Seed model — DECIDED: signature-derived from Privy**, gated on a determinism spike. Derive the RAILGUN mnemonic from a Privy signature over a frozen, domain-separated message (§4.1) — recoverable, no seed-phrase backup, consistent with the platform's Privy-centric non-custodial model. **Gate:** the Phase-2 spike must first prove Privy embedded-wallet ECDSA signatures over the fixed message are byte-stable (RFC-6979 deterministic) across restarts/devices. **Fallback if the gate fails:** standard backed-up BIP-39 (Railway-style). The derivation message is frozen forever and must never be reused in another signing context.
2. **Own infra — DECIDED: self-host.** We run our own POI aggregator node + artifact mirror + RPC. `poiNodeURLs` and the artifact manifest point at our infra (not public RAILGUN/IPFS gateways).
3. **Low-end devices — DECIDED: accepted.** On-device proving only, no server-proving fallback. Set a minimum-device floor + a 20–30s progress UX; devices below the floor are unsupported for privacy ops.
4. **Existing custodial wallets — DECIDED: none.** The app is not publicly released; there are no funded `railgun_wallets`. ⇒ **clean teardown, no fund migration/sweep.** The custodial seed/bridge/`encrypted_mnemonic` can be removed outright in Phase 3 (or earlier).
5. **SDK version — DECIDED: target `@railgun-community/wallet` v10.x on-device** (engine 9.6.x, shared-models 8.x). The server bridge is retired, so its own v9→v10 bump is moot for the non-custodial path.
6. **Gas — Phase-2 detail:** prefer broadcaster (Waku) for gas abstraction; self-signing from the Privy EOA is the fallback. Reconcile with the Pimlico sponsorship used by Wallet Send during Phase 2.

---

## 9. Risks

1. **Native build complexity** — embedded Node + Rust native prover + NDK + patch-package is the single biggest lift; not a drop-in SDK swap.
2. **Proving latency/memory** — 20–30s on slow devices; needs progress UI + backgrounding tolerance.
3. **Artifacts ~50MB+** downloaded on demand from IPFS — mirror them; plan first-use latency.
4. **POI standby UX** — newly shielded funds are unshield-only for ~1h until POI validates; non-custodial but a real constraint.
5. **Seed-from-signature is non-standard** — determinism + frozen message + Privy-key blast radius (§4.1).
6. **Version churn** — RAILGUN ships no changelog; pin patch versions, diff each bump.

---

## 10. What this means for the mobile dev starting now

You're signing up for the **heavy half** (on-device engine + native prover). Start in this order:
1. **Now:** build the privacy UI against demo mode (stable 26-endpoint contract) — no blockers.
2. **Spike (de-risk early):** stand up `nodejs-mobile-react-native` + `@railgun-community/wallet` v10 + native prover in the RN app; prove a testnet shield end-to-end on a real device. This validates the single biggest risk before committing.
3. **Confirm** the Privy signature-determinism question (§4.1, §8.3) — it decides the seed/recovery model.
4. Integrate against the Phase-1 backend support services as they land.
