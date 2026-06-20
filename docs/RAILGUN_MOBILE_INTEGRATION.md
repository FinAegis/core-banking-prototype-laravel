# RAILGUN Mobile Integration — Start Here

> Replaces the stale `docs/archive/RAILGUN_MOBILE_HANDOVER.md`. Full architecture
> in `docs/superpowers/specs/2026-06-20-railgun-noncustodial-design.md`.

## TL;DR

RAILGUN privacy is going **non-custodial** (device holds the keys, like Wallet Send via Privy). The backend already exposes a stable REST surface under `/api/v1/privacy/*` (Sanctum bearer, no extra KYC). **You can build the entire privacy UI today against demo mode** — then swap in the on-device engine as it lands. The single biggest risk is the on-device prover; **run the spike (below) first.**

## 1. Start today — build UI against demo mode

Set the backend env to `ZK_PROVIDER=demo` (the default). Every endpoint returns well-formed data with no real on-chain semantics, so you can build and wire the whole flow:

- Auth: the normal Sanctum bearer token (same as the rest of the app). No extra step.
- `GET /api/v1/privacy/networks` — **authoritative** list of supported chains. Always read this; never hardcode. (Production/railgun = `ethereum, polygon, arbitrum, bsc` — **not** Base.)
- `GET /balances`, `GET /total-balance`, `GET /transactions`
- `POST /shield`, `POST /unshield`, `POST /transfer` — **dual-mode**: in demo mode they return a delegated-proof job `{status:'queued', progress:0}`; in railgun mode they return `{status:'transaction_ready', transaction:{to,data,value}, gas_estimate, railgun_address:'0zk...'}`. Handle both shapes (or branch on `status`).
- `PUT /transactions/{id}/tx-hash` — record the on-chain hash after you broadcast.

> ⚠️ Do **not** rely on `GET /viewing-key` — it's a server-side SHA-256 stub, not a real RAILGUN viewing key. In the non-custodial model the viewing key is device-held and you decrypt balances locally.

## 2. The non-custodial target (what changes)

The contract **inverts**: instead of the server building + proving your transaction, the **device** does it. The backend becomes support services.

| The device does | The backend provides (no keys) |
|---|---|
| derive seed (from a Privy signature), hold spending + viewing keys | `POST /wallet/register` — register your **public** `0zk` address |
| run `@railgun-community/wallet` v10 in an embedded Node runtime + native Groth16 prover | merkle/commitment feed (scan acceleration) |
| generate shield/unshield/transfer proofs **on-device** (~20–30s) | artifact CDN mirror, self-hosted POI node, RPC proxy |
| derive `shieldPrivateKey` = `keccak256(privySign(getShieldPrivateKeySignatureMessage()))` | activity-feed mirror (sees only public EVM txs) |
| decrypt balances with the viewing key | broadcaster info / gas |

### New: `POST /api/v1/privacy/wallet/register` (live now, Phase 1)
After creating the wallet on-device, register its public address:
```http
POST /api/v1/privacy/wallet/register
Authorization: Bearer <sanctum>
{ "railgun_address": "0zk1...", "network": "polygon" }
→ 200 { "success": true, "data": { "railgun_address": "0zk1...", "network": "polygon", "custodial": false, "registered_at": "..." } }
```
Idempotent per (user, address). The backend stores **no seed** — `encrypted_mnemonic` is null for registered wallets. `409 ADDRESS_ALREADY_REGISTERED` if the address belongs to another account; `422` on a malformed `0zk` address or unsupported network.

### New: `GET /api/v1/privacy/engine-config` (live now, Phase 1)
Fetch this once at startup to configure the on-device engine against our self-hosted infra. The shapes are **directly consumable** by the SDK:
```http
GET /api/v1/privacy/engine-config  (Authorization: Bearer <sanctum>)
→ data: {
    "wallet_source": "zelta",                 // startRailgunEngine arg (≤16 chars)
    "txid_version": "V2_PoseidonMerkle",
    "use_native_artifacts": true,             // mobile → native Groth16 prover
    "artifact_base_url": "https://...",        // YOUR ArtifactStore.get() fetches mirrored artifacts from here (see note)
    "poi_node_urls": ["https://..."],          // startRailgunEngine(..., poiNodeURLs)
    "networks": [
      { "key":"polygon", "network_name":"Polygon", "chain_id":137,
        "fallback_provider_config": { "chainId":137, "providers":[{ "provider":"https://...", "priority":1, "weight":2, "maxLogsPerBatch":1, "stallTimeout":2500 }] } }
    ]
  }
```
Pass `network_name` (the exact SDK `NetworkName`) + `fallback_provider_config` straight to `loadProvider(config, networkName)`, and `poi_node_urls` to `startRailgunEngine`. A network with no configured RPC (or one that looks like it embeds a credential) is omitted. `bsc` → `network_name: "BNB_Chain"`.

> ⚠️ **`artifact_base_url` is NOT consumed by the SDK.** The v9 engine hardcodes its own IPFS artifact gateway. To use our mirror, your **ArtifactStore `get()`/`exists()` implementation** must fetch from `artifact_base_url` on a cache miss (and `store()` it) — that's the only hook. If you don't, artifacts come from the SDK's pinned gateway and our mirror is unused.
>
> **Device-constructed (not in this payload):** the LevelDB instance, the `ArtifactStore`, and `shouldDebug` are positional `startRailgunEngine` args you build on-device — the endpoint intentionally doesn't return them.

## 3. Spike FIRST (de-risk the biggest unknown)

Before building the on-device path, prove it works on a real device:

1. Stand up `nodejs-mobile-react-native` + `@railgun-community/wallet` **v10** + the native Groth16 prover (`@railgun-privacy/native-prover`) + `leveldown-nodejs-mobile` + an `fs`-backed artifact store. (Reference: the Railway Wallet repo — it's RN + embedded Node.)
2. Create a wallet and **prove one testnet shield end-to-end** on a physical phone. Measure proof time + memory.
3. **Confirm Privy signature determinism:** sign the same fixed message with the Privy embedded wallet across app restarts/devices and assert the signature bytes are identical (RFC-6979). This decides the seed model — deterministic ⇒ signature-derived seed (no backup); if not ⇒ standard backed-up BIP-39 fallback.

If 1–3 pass, the rest is integration. If the prover is too slow/heavy on target devices, raise it early — there is **no** server-proving fallback in a non-custodial design.

## 4. Gotchas

- **Networks:** read `GET /networks`; case-sensitive; Base is not supported by RAILGUN.
- **POI standby:** newly shielded funds are unshield-only for ~1h until the on-device POI proof validates. Design the UX for it.
- **Artifacts:** ~50MB+ of circuit artifacts download on demand (from our mirror). Plan first-use latency + a progress UI.
- **Proving UX:** 20–30s on slow devices; must survive backgrounding.
- **Don't trust the archived handover** — its merkle endpoint paths are the bridge's internal routes, not the Laravel API.
