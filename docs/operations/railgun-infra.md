# RAILGUN Non-Custodial Infra — Ops Runbook

For the non-custodial RAILGUN wallet (design: `docs/superpowers/specs/2026-06-20-railgun-noncustodial-design.md`). The mobile app runs the RAILGUN engine **on-device** and holds all keys; the backend only points it at our **self-hosted support infra** via `GET /api/v1/privacy/engine-config`. None of these services hold keys or see private transactions.

Three services to stand up (decision: self-host, not public RAILGUN infra):

## 1. POI aggregator node → `RAILGUN_POI_NODE_URLS`

Run `Railgun-Community/private-proof-of-innocence` (a Node service; IPFS-like — anyone can run one). The on-device engine generates its own POI proof and talks to this node to sync lists / validate merkleroots — it is **not** a prover-on-your-behalf and holds no keys.

- Deploy the node (its own host; persistent volume for its DB).
- Set `RAILGUN_POI_NODE_URLS=https://poi.zelta.app` (comma-separated for multiple, in priority order).
- The device passes these to `startRailgunEngine(..., poiNodeURLs)`.
- **Note:** until a node is healthy, mainnet shields stay in the ~1h "unshield-only standby" longer. Monitor it like any prod dependency.

## 2. Artifact mirror → `RAILGUN_ARTIFACT_BASE_URL`

The device downloads Groth16 circuit artifacts (~50MB+ total, on demand) for proving. Mirror RAILGUN's content-addressed artifacts (from `assets.railgun.org` / IPFS) onto our CDN for reliability + first-use latency.

- Sync the artifact set to a CDN bucket (content-addressed paths preserved).
- Set `RAILGUN_ARTIFACT_BASE_URL=https://cdn.zelta.app/railgun-artifacts`.
- **mobile uses the native (`.dat`) artifacts** (`use_native_artifacts=true`).
- ⚠️ The v9 SDK hardcodes its own IPFS artifact gateway and does **not** read this URL. The mirror is only used if the **mobile app's `ArtifactStore.get()`** fetches from it on a cache miss — a client-side responsibility (documented in `docs/RAILGUN_MOBILE_INTEGRATION.md`). Leaving `RAILGUN_ARTIFACT_BASE_URL` empty is harmless (the app falls back to the SDK's pinned gateway).

## 3. Per-chain RPC → `RAILGUN_RPC_{ETHEREUM,POLYGON,ARBITRUM,BSC}`

The device reads chain state + syncs the merkle tree through these.

**PREFERRED — built-in RPC proxy (`RAILGUN_RPC_UPSTREAM_*`):** set the real provider URL (key included) as the upstream. When set, `engine-config` returns a short-lived **signed proxy URL** (`POST /api/v1/privacy/rpc/{network}`) and the proxy injects the key server-side + whitelists read methods — the **key never reaches the device**. This is the recommended path: no separate proxy infra, no keyless-endpoint hunt.
- `RAILGUN_RPC_PROXY_TTL` (default 3600s) is the signed-URL lifetime; the app refetches `engine-config` on a `403` (expired/invalid signature).

**Fallback — client RPC (`RAILGUN_RPC_*`):** used only when no upstream is set for that chain. Returned to the client verbatim, so:
> 🔒 **MUST be key-safe.** Never put a provider API key here — `engine-config` defensively drops a URL that looks like it embeds a credential (`rpcUrlIsUnsafe`), but use a keyless/proxy URL to be safe.

- A chain with neither an upstream nor a (safe) client URL is **omitted** from `engine-config` (the app won't offer it).
- RAILGUN supports `ethereum/polygon/arbitrum/bsc` only — **not** Base.

## 4. Broadcaster → `RAILGUN_BROADCASTER_ENABLED`

Broadcasters relay gas-paying meta-txs over the public Waku network (they never hold user tokens/keys). Default `true` (use the public network). Self-signing from the user's Privy EOA is the fallback (needs the EOA to hold gas). Reconcile with the Pimlico sponsorship used by Wallet Send in Phase 2.

## Verify

```bash
# As an authenticated mobile user (Sanctum bearer):
curl -s https://<host>/api/v1/privacy/engine-config -H "Authorization: Bearer <token>" | jq .data
```
Confirm: `poi_node_urls` non-empty, `artifact_base_url` set, and one `networks[]` entry per chain that has an RPC URL, each with a `fallback_provider_config` (chainId + providers[0].provider = your key-safe RPC URL). `bsc` maps to `network_name: "BNB_Chain"`.

`php artisan ops:verify-env` also gates the legacy bridge's `privacy.railgun.providers` consistency (Phase 0).
