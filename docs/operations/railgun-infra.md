# RAILGUN Non-Custodial Infra Ops Runbook

This runbook is for operators of the non-custodial RAILGUN wallet. The design is in `docs/superpowers/specs/2026-06-20-railgun-noncustodial-design.md`. The mobile app runs the RAILGUN engine **on the device** and holds every key. The backend only tells the engine where our **self-hosted support services** are, through `GET /api/v1/privacy/engine-config`. None of these services hold keys or see private transactions.

The backend part shipped in v7.16.0. The on-device engine in the mobile app is still in progress, so no user sends private transactions yet.

We decided to host the support services ourselves instead of using the public RAILGUN infrastructure. There are three services to set up, plus one setting for broadcasters.

## 1. Proof of innocence node (`RAILGUN_POI_NODE_URLS`)

Run `Railgun-Community/private-proof-of-innocence`. It is a Node service, and anyone can run one, much like an IPFS node. The on-device engine makes its own proof of innocence (POI) and talks to this node to sync the lists and validate Merkle roots. The node does not prove anything on the user's behalf and holds no keys.

- Deploy the node on its own host with a persistent volume for its database.
- Set `RAILGUN_POI_NODE_URLS=https://poi.zelta.app`. For several nodes, separate the URLs with commas in priority order.
- The device passes these URLs to `startRailgunEngine(..., poiNodeURLs)`.
- **Until a node is healthy**, shields on mainnet stay longer in RAILGUN's "unshield-only standby", which normally lasts about an hour. Monitor the node like any other production dependency.

## 2. Artifact mirror (`RAILGUN_ARTIFACT_BASE_URL`)

The device downloads Groth16 circuit artifacts for proving when it needs them. Together they are more than 50 MB. We mirror RAILGUN's content-addressed artifacts from `assets.railgun.org` or IPFS onto our CDN, so the download is reliable and the first use is faster.

- Sync the artifact set to a CDN bucket and keep the content-addressed paths.
- Set `RAILGUN_ARTIFACT_BASE_URL=https://cdn.zelta.app/railgun-artifacts`.
- **The mobile app uses the native `.dat` artifacts** (`use_native_artifacts=true`).
- **The v9 and v10 SDKs ignore this URL.** They use their own built-in IPFS gateway. The mirror is used only if the **mobile app's `ArtifactStore.get()`** fetches from it when its cache misses, which is the app's job and is described in `docs/RAILGUN_MOBILE_INTEGRATION.md`. Leaving `RAILGUN_ARTIFACT_BASE_URL` empty does no harm, because the app then falls back to the SDK's built-in gateway.

## 3. RPC for each chain (`RAILGUN_RPC_{ETHEREUM,POLYGON,ARBITRUM,BSC}`)

The device reads chain state and syncs the Merkle tree through these RPC endpoints.

**The preferred option is the built-in RPC proxy (`RAILGUN_RPC_UPSTREAM_*`).** Set the real provider URL, including its key, as the upstream. `engine-config` then returns a short-lived **signed proxy URL** for `POST /api/v1/privacy/rpc/{network}`. The proxy adds the key on the server and allows only whitelisted methods, so the **key never reaches the device**. This option needs no separate proxy service and no search for keyless endpoints.
- `RAILGUN_RPC_PROXY_TTL` sets how long a signed URL is valid. The default is 300 seconds, and the value is always kept between 60 and 900 seconds. When a URL has expired or its signature is invalid, the proxy answers `403`, and the app fetches `engine-config` again.

**The fallback is a client RPC URL (`RAILGUN_RPC_*`).** It is used only when a chain has no upstream. The URL is sent to the client exactly as configured.
> 🔒 **It MUST be safe to share.** Never put a provider API key here. `engine-config` drops a URL that looks like it contains a credential (`rpcUrlIsUnsafe`), but use a keyless or proxy URL anyway.

- A chain that has neither an upstream nor a safe client URL is **left out** of `engine-config`, so the app does not offer it.
- RAILGUN supports only `ethereum`, `polygon`, `arbitrum` and `bsc`. It does **not** support Base.

## 4. Broadcasters (`RAILGUN_BROADCASTER_ENABLED`)

Broadcasters relay meta-transactions that pay gas over the public Waku network. They never hold user tokens or keys. The default is `true`, which uses the public network. The fallback is to sign from the user's Privy externally owned account, which then needs to hold gas. How this fits with the Pimlico sponsorship used by Wallet Send is still to be decided in Phase 2.

## Verify

```bash
# As an authenticated mobile user (Sanctum bearer):
curl -s https://<host>/api/v1/privacy/engine-config -H "Authorization: Bearer <token>" | jq .data
```

Check the response. `poi_node_urls` should not be empty, and `artifact_base_url` should be set. There should be one `networks[]` entry for each chain that has an RPC URL. Each entry should have a `fallback_provider_config` with the chainId, and `providers[0].provider` should be your safe RPC URL. `bsc` maps to `network_name: "BNB_Chain"`.

`php artisan ops:verify-env` also checks the old custodial bridge settings. In production it FAILs when `ZK_PROVIDER=railgun`, because that setting turns on the custodial path, where the server derives the wallet seed. Production keeps `ZK_PROVIDER=demo`, and the custodial shield, unshield, transfer and viewing-key endpoints answer `501` there anyway. The command also FAILs when `ZK_PROVIDER` and `MERKLE_PROVIDER` differ, and when both are `railgun` without `RAILGUN_BRIDGE_SECRET`. So a production deploy passes this check only with `MERKLE_PROVIDER=demo` as well. Both env templates still set `MERKLE_PROVIDER=railgun`, which fails it.
