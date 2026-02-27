# RAILGUN Privacy Protocol — Mobile Developer Handover

## Key Message

**The mobile app needs zero code changes.** The same 26 privacy REST endpoints return real data now instead of demo/static responses. The API contract is identical — only the backend implementation changed.

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

## Testing

```bash
# Run unit tests
./vendor/bin/pest tests/Unit/Domain/Privacy/Services/Railgun

# Run feature tests
./vendor/bin/pest tests/Feature/Api/Privacy/RailgunIntegrationTest.php

# Run all privacy tests
./vendor/bin/pest tests/Unit/Domain/Privacy/ tests/Feature/Api/Privacy/
```
