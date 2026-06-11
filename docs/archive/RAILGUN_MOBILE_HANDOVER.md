# RAILGUN Privacy Protocol — Mobile Developer Handover

## Key Message

The same 26 privacy REST endpoints return real data now instead of demo/static responses. The API contract is identical — only the backend implementation changed. **However, the mobile app needs minor type and UI updates** because supported networks changed (removed Base, added Ethereum + BSC).

---

## Architecture

```
Mobile App (Expo/React Native)
    │
    ▼
Laravel API (26 privacy endpoints)
    │  ── same REST contract, same auth ──
    ▼
RailgunBridgeClient (HTTP client)
    │  ── bearer-token-auth JSON API ──
    ▼
Node.js Bridge Service (port 3100)
    │
    ▼
RAILGUN SDK (@railgun-community/wallet)
    │
    ▼
On-chain Privacy Pool Contracts
```

---

## Chain Support

| Chain | Supported | Chain ID |
|-------|-----------|----------|
| Ethereum | Yes | 1 |
| Polygon | Yes | 137 |
| Arbitrum | Yes | 42161 |
| BSC | Yes | 56 |
| **Base** | **No** | **NOT supported by RAILGUN** |

The `GET /api/v1/privacy/networks` endpoint returns only supported chains.

---

## API Endpoints (No Changes)

All 26 privacy endpoints work exactly as before. Here are the key ones:

### Wallet & Balances

```
GET  /api/v1/privacy/balances         → Shielded balances per token/network
GET  /api/v1/privacy/total-balance    → Total USD value of shielded assets
GET  /api/v1/privacy/viewing-key      → User's viewing key for decryption
GET  /api/v1/privacy/networks         → Supported privacy pool networks
```

### Shield / Unshield / Transfer

```
POST /api/v1/privacy/shield           → Deposit tokens into privacy pool
POST /api/v1/privacy/unshield         → Withdraw tokens from privacy pool
POST /api/v1/privacy/transfer         → Private transfer between 0zk addresses
```

### Merkle Tree

```
GET  /api/v1/privacy/merkle/root/:network    → Current Merkle root
GET  /api/v1/privacy/merkle/path/:commitment → Merkle proof for commitment
POST /api/v1/privacy/merkle/verify           → Verify a commitment
POST /api/v1/privacy/merkle/sync/:network    → Trigger tree sync
```

---

## Response Format Changes

### Balances (before: demo)
```json
{
  "success": true,
  "data": [
    { "token": "USDC", "balance": "0.00", "network": "polygon" }
  ]
}
```

### Balances (after: RAILGUN)
```json
{
  "success": true,
  "data": [
    {
      "token": "USDC",
      "balance": "150.50",
      "network": "polygon",
      "last_synced_at": "2026-02-27T12:00:00+00:00"
    }
  ]
}
```

### Shield (before: delegated proof job)
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "proof_type": "shield_1_1",
    "status": "queued",
    "progress": 0
  }
}
```

### Shield (after: RAILGUN transaction)
```json
{
  "success": true,
  "data": {
    "operation": "shield",
    "status": "transaction_ready",
    "transaction": {
      "to": "0x...",
      "data": "0x...",
      "value": "0"
    },
    "gas_estimate": "150000",
    "token": "USDC",
    "amount": "100.00",
    "network": "polygon",
    "railgun_address": "0zk..."
  }
}
```

---

## Shield / Unshield / Transfer Flow

### Shield (Deposit)
1. User selects token, amount, network in the app
2. App calls `POST /api/v1/privacy/shield`
3. Backend builds the shield transaction via RAILGUN bridge
4. Response contains unsigned `transaction` calldata
5. App signs and submits the transaction on-chain
6. Tokens move from user's wallet to RAILGUN privacy pool

### Unshield (Withdraw)
1. User selects token, amount, network, recipient address
2. App calls `POST /api/v1/privacy/unshield`
3. Backend generates ZK proof + builds unshield transaction
4. Response contains unsigned transaction calldata
5. App signs and submits — tokens exit the privacy pool

### Private Transfer
1. User enters recipient's 0zk... address, token, amount
2. App calls `POST /api/v1/privacy/transfer`
3. Backend generates ZK proof + builds transfer transaction
4. Response contains unsigned transaction calldata
5. Transfer occurs entirely within the privacy pool — no public trace

---

## Wallet Creation

RAILGUN wallets are created automatically on first privacy operation (shield, unshield, or transfer). The mobile app does not need to trigger wallet creation explicitly.

Each user gets one RAILGUN wallet per network, identified by a `0zk...` address.

---

## Balance Sync & Caching

- Balances are fetched live from the RAILGUN bridge on each `GET /balances` request
- Results are cached in the `shielded_balances` table for resilience
- If the bridge is temporarily unreachable, cached balances are returned
- The `last_synced_at` field indicates freshness

---

## Error Codes

| Code | Meaning |
|------|---------|
| 401 | Missing or invalid auth token |
| 422 | Validation error (missing required fields) |
| 503 | RAILGUN bridge not ready (engine initializing) |
| 500 | Bridge connection error or internal error |

The mobile app should handle 503 gracefully (retry after a few seconds — the engine may still be starting).

---

## Mobile App Code Changes

### Required (4 files, ~8 lines)

**1. `src/services/api/types.ts` line 152** — Add `'bsc'` to ChainId:
```diff
-export type ChainId = 'polygon' | 'base' | 'arbitrum' | 'ethereum';
+export type ChainId = 'polygon' | 'base' | 'arbitrum' | 'ethereum' | 'bsc';
```

**2. `src/services/api/types.ts` lines 319-322** — Add `'bsc'` to privacy types:
```diff
-export type PrivacyChainId = 'polygon' | 'arbitrum' | 'ethereum';
-export const PRIVACY_SUPPORTED_CHAINS: PrivacyChainId[] = ['polygon', 'arbitrum', 'ethereum'];
+export type PrivacyChainId = 'polygon' | 'arbitrum' | 'ethereum' | 'bsc';
+export const PRIVACY_SUPPORTED_CHAINS: PrivacyChainId[] = ['polygon', 'arbitrum', 'ethereum', 'bsc'];
```

**3. `src/stores/walletStore.ts` line 5** — Add new networks to store type:
```diff
-export type Network = 'polygon' | 'base' | 'arbitrum';
+export type Network = 'polygon' | 'base' | 'arbitrum' | 'ethereum' | 'bsc';
```

**4. `src/hooks/usePreferences.ts` line 17** — Accept new networks in validation:
```diff
-const VALID_NETWORKS = new Set(['polygon', 'base', 'arbitrum']);
+const VALID_NETWORKS = new Set(['polygon', 'base', 'arbitrum', 'ethereum', 'bsc']);
```

### Recommended UI Updates (8 files)

These screens hardcode network lists. They won't crash, but will show outdated options:

| File | What to change |
|------|---------------|
| `app/flows/receive/network-select.tsx:17-22` | Add bsc entry, enable ethereum (`available: true`) |
| `app/flows/receive/index.tsx:12,102` | Add `'ethereum' \| 'bsc'` to Network type and render list |
| `app/flows/receive/request.tsx:14-19,242` | Add `bsc: 'BNB Chain'` to CHAIN_NAMES and render list |
| `app/(tabs)/wallet.tsx:16-20` | Add ethereum/bsc to NETWORKS filter pills |
| `app/flows/settings/network.tsx:21-55` | Enable ethereum, add bsc entry (`color: '#F0B90B'`) |
| `app/flows/pay/confirm.tsx:48` | Add `'bsc'` to VALID_CHAIN_IDS |
| `app/flows/pay/pending.tsx:18,177` | Add `'bscscan.com'` to allowed hosts, `bsc: 'BNB Chain'` to names |
| `src/services/mock/data.ts` | Add bsc entries to mock wallet addresses and relayer status |

### What Already Works (No Changes Needed)

- `usePrivacyNetworks()` hook fetches dynamically from backend
- Privacy shield/unshield screens use `PRIVACY_SUPPORTED_CHAINS` with fallback
- Privacy API service correctly calls all endpoints
- X402 protocol references are separate and unaffected

---

## Testing

```bash
# Run unit tests
./vendor/bin/pest tests/Unit/Domain/Privacy/Services/Railgun

# Run feature tests
./vendor/bin/pest tests/Feature/Api/Privacy/RailgunIntegrationTest.php

# Run all privacy tests
./vendor/bin/pest tests/Unit/Domain/Privacy/ tests/Feature/Api/Privacy/
```
