# RAILGUN Mobile Integration — Start Here

> Replaces the stale `docs/archive/RAILGUN_MOBILE_HANDOVER.md`. Full architecture
> in `docs/superpowers/specs/2026-06-20-railgun-noncustodial-design.md`.

## Summary

RAILGUN privacy is moving to a non-custodial model. In that model the device holds the keys, as it already does for wallet send through Privy.

The backend side of the move is partly done. Phase 0 and Phase 1 shipped in v7.16.0. They added wallet registration, the engine configuration endpoint and the RPC proxy. The on-device engine is the mobile app's work and is still pending. It waits on a spike that must first prove the on-device prover works on a real phone (see section 3). Until it ships there is no private transaction flow in production. The old custodial bridge stays in the code until Phase 3 removes it.

The backend exposes a stable REST interface under `/api/v1/privacy/*`. It uses the normal Sanctum bearer token and needs no extra Know Your Customer (KYC) step. The mobile team can build the whole privacy UI today against demo mode and switch to the on-device engine when it is ready. The biggest risk is the on-device prover, so run the spike first.

## 1. Start today with demo mode

Set the backend to `ZK_PROVIDER=demo`, which is the default. In demo mode every endpoint returns well-formed data that has no real on-chain meaning. That is enough to build and wire the whole flow.

- Authentication uses the normal Sanctum bearer token, the same one as the rest of the app. There is no extra step.
- `GET /api/v1/privacy/networks` is the only reliable list of supported chains. Always read it and never hardcode chains. In production with RAILGUN the list is `ethereum, polygon, arbitrum, bsc`. Base is not on it.
- `GET /balances`, `GET /total-balance` and `GET /transactions` return balances and history.
- `POST /shield`, `POST /unshield` and `POST /transfer` return two different shapes. In demo mode they return a delegated proof job, `{status:'queued', progress:0}`. In RAILGUN mode they return `{status:'transaction_ready', transaction:{to,data,value}, gas_estimate, railgun_address:'0zk...'}`. Handle both shapes, or branch on `status`.
- `PUT /transactions/{id}/tx-hash` records the on-chain hash after the app broadcasts the transaction.

These custodial endpoints are switched off in production. `POST /shield`, `POST /unshield`, `POST /transfer`, `GET /viewing-key`, `POST /proof-of-innocence` and `POST /delegated-proof` return `501` with the error `CUSTODIAL_PRIVACY_DISABLED` there, whatever `ZK_PROVIDER` is set to. They work only outside production, so the UI can be built and tested against them. The production flow is the on-device engine described below.

> Do not rely on `GET /viewing-key`. It returns a server-side SHA-256 placeholder, not a real RAILGUN viewing key. In the non-custodial model the device holds the viewing key and decrypts balances locally.

## 2. What changes in the non-custodial model

The roles swap. Today the server builds and proves the transaction. In the non-custodial model the device does both, and the backend only provides support services.

| The device does | The backend provides (no keys) |
|---|---|
| derive seed (from a Privy signature), hold spending + viewing keys | `POST /wallet/register` — register your **public** `0zk` address |
| run `@railgun-community/wallet` v10 in an embedded Node runtime + native Groth16 prover | merkle/commitment feed (scan acceleration) |
| generate shield/unshield/transfer proofs **on-device** (~20–30s) | artifact CDN mirror, self-hosted POI node, RPC proxy |
| derive `shieldPrivateKey` = `keccak256(privySign(getShieldPrivateKeySignatureMessage()))` | activity-feed mirror (sees only public EVM txs) |
| decrypt balances with the viewing key | broadcaster info / gas |

### `POST /api/v1/privacy/wallet/register` (live since Phase 1)

After the app creates the wallet on the device, it registers the wallet's public address.

```http
POST /api/v1/privacy/wallet/register
Authorization: Bearer <sanctum>
{ "railgun_address": "0zk1...", "network": "polygon" }
→ 200 { "success": true, "data": { "railgun_address": "0zk1...", "network": "polygon", "status": "active", "custodial": false, "registered_at": "..." } }
```

The call is idempotent for each user and address pair. The backend stores no seed, so `encrypted_mnemonic` is null for registered wallets. The endpoint returns `409 ADDRESS_ALREADY_REGISTERED` if the address belongs to another account. It returns `422` for a malformed `0zk` address or an unsupported network.

### `GET /api/v1/privacy/engine-config` (live since Phase 1)

The app fetches this once at startup. It points the on-device engine at our self-hosted infrastructure. The SDK can use the returned shapes directly.

```http
GET /api/v1/privacy/engine-config  (Authorization: Bearer <sanctum>)
→ data: {
    "wallet_source": "zelta",                 // startRailgunEngine arg (≤16 chars)
    "txid_version": "V2_PoseidonMerkle",
    "use_native_artifacts": true,             // mobile → native Groth16 prover
    "artifact_base_url": "https://...",        // YOUR ArtifactStore.get() fetches mirrored artifacts from here (see note)
    "poi_node_urls": ["https://..."],          // startRailgunEngine(..., poiNodeURLs)
    "broadcaster_enabled": true,
    "networks": [
      { "key":"polygon", "network_name":"Polygon", "chain_id":137,
        "fallback_provider_config": { "chainId":137, "providers":[{ "provider":"https://...", "priority":1, "weight":2, "maxLogsPerBatch":1, "stallTimeout":2500 }] } }
    ]
  }
```

Pass `network_name`, which is the exact SDK `NetworkName`, and `fallback_provider_config` straight to `loadProvider(config, networkName)`. Pass `poi_node_urls` to `startRailgunEngine`. The response leaves out any network that has no RPC configured, and any network whose RPC URL looks like it contains a credential. For `bsc`, `network_name` is `"BNB_Chain"`.

> The SDK does not read `artifact_base_url`. The v9 and v10 engines use their own fixed IPFS gateway for artifacts. To use our mirror, the app's `ArtifactStore` `get()` and `exists()` implementation must fetch from `artifact_base_url` when the artifact is not cached, and then `store()` it. That is the only hook. Without it, artifacts come from the SDK's gateway and our mirror is not used.
>
> The device builds some arguments itself, so this payload does not include them. They are the LevelDB instance, the `ArtifactStore` and `shouldDebug`, which are positional `startRailgunEngine` arguments.
>
> The `provider` URL in `fallback_provider_config` can be a short-lived signed proxy URL (`/api/v1/privacy/rpc/{network}?...&signature=...`). The backend does this when it proxies RPC calls, so the provider key stays off the device. Use the URL exactly as given. The signature is valid for between 60 and 900 seconds. If a proxied call returns HTTP 403, the signature has expired, and the app must fetch `engine-config` again and rebuild the provider. The proxy only lets through read methods and `eth_sendRawTransaction`.

## 3. Run the spike first

The on-device path has one big unknown. Before building it, prove that it works on a real device.

1. Set up `nodejs-mobile-react-native`, `@railgun-community/wallet` v10, the native Groth16 prover (`@railgun-privacy/native-prover`), `leveldown-nodejs-mobile` and an `fs`-backed artifact store. The Railway Wallet repository is a useful reference, because it is React Native with an embedded Node runtime.
2. Create a wallet and prove one testnet shield from start to finish on a physical phone. Measure the proof time and the memory use.
3. Confirm that Privy signatures are deterministic. Sign the same fixed message with the Privy embedded wallet across app restarts and devices, and check that the signature bytes are identical (RFC 6979). The result decides the seed model. If the signatures are deterministic, the seed comes from a signature and needs no backup. If they are not, the app falls back to a standard BIP-39 seed that the user backs up.

If all three steps pass, the rest is integration work. If the prover is too slow or too heavy on target devices, raise it early. A non-custodial design has no server-side proving fallback.

## 4. Things to watch for

- Networks: read `GET /networks`. The values are case-sensitive, and RAILGUN does not support Base.
- Proof of Innocence standby: newly shielded funds can only be unshielded for about one hour, until the on-device Proof of Innocence (POI) proof is validated. Design the screens for this wait.
- Artifacts: about 50 MB or more of circuit artifacts download on demand from our mirror. Plan for the delay on first use and show a progress indicator.
- Proving time: a proof takes 20 to 30 seconds on slow devices, and it must survive the app going to the background.
- The archived handover is not reliable. Its merkle endpoint paths are the bridge's internal routes, not the Laravel API.
