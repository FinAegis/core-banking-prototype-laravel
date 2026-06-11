# Backend API Requirements - FinAegis Mobile v2

> Handover document for the backend AI developer.
> Prepared: 2026-02-07
> Updated: 2026-02-07 (decisions locked)
> Context: The mobile app is being redesigned with new screens and flows. This document specifies the API contracts the frontend needs.

---

## Locked Constraints for v1

| Constraint | Detail |
|------------|--------|
| **Branding** | App = "FinAegis". Privacy feature = "Aegis Shield". No "ShieldPay" references in API responses. |
| **Networks** | Solana + Tron **only** for v1. Backend should reject/ignore other networks. |
| **Assets** | USDC **only** for v1. Backend should reject/ignore other tokens. |
| **Rewards** | **Out of scope for v1.** Do not build rewards endpoints. |

---

## Overview

The mobile frontend needs the following backend capabilities to implement the new designs:

1. **Payment Intent lifecycle** (create, authorize, poll, cancel) - **P0, blocking**
2. **Activity feed** with cursor-based pagination - **P0**
3. **Receive address generation** per network/asset - **P0**
4. **Transaction details** with receipt generation - **P0**
5. **Super-KYC certificate** export - **P2**
6. **Real-time payment status** via WebSocket - **P0**

> Note: Rewards API (previously P1) has been deferred to v2.

---

## 1. Payment Intent API (P0)

The payment flow follows a state machine: `CREATED -> AWAITING_AUTH -> SUBMITTING -> PENDING -> CONFIRMED/FAILED/CANCELLED/EXPIRED`.

### 1.1 Create Payment Intent

**`POST /v1/payments/intents`**

The mobile app calls this after scanning a merchant QR code or receiving a payment request.

**Request:**
```json
{
  "merchantId": "merchant_abc123",
  "amount": "12.00",
  "asset": "USDC",
  "preferredNetwork": "SOLANA",
  "shield": true
}
```

**Response (201):**
```json
{
  "success": true,
  "data": {
    "intentId": "pi_abc123def456",
    "merchantId": "merchant_abc123",
    "merchant": {
      "displayName": "Starbolt Coffee",
      "iconUrl": "https://cdn.example.com/merchants/starbolt.png"
    },
    "asset": "USDC",
    "network": "SOLANA",
    "amount": "12.00",
    "status": "AWAITING_AUTH",
    "shieldEnabled": true,
    "feesEstimate": {
      "nativeAsset": "SOL",
      "amount": "0.00004",
      "usdApprox": "0.01"
    },
    "createdAt": "2026-02-07T10:00:00Z",
    "expiresAt": "2026-02-07T10:15:00Z"
  }
}
```

**Error cases:**
- `400` - Invalid amount, unsupported asset/network
- `404` - Merchant not found
- `422` with `error.code`:
  - `MERCHANT_UNREACHABLE` - Terminal/merchant session not reachable
  - `WRONG_NETWORK` - Merchant doesn't accept this network
  - `WRONG_TOKEN` - Merchant doesn't accept this token
  - `INSUFFICIENT_FUNDS` - User balance too low
  - `INSUFFICIENT_FEES` - User doesn't have enough native token for gas

**Backend responsibilities:**
- Validate merchant session/terminal is reachable
- Validate merchant accepts the asset on the requested network
- Check user balance (both token and fee token)
- Estimate fees
- Set `expiresAt` (recommended: 15 minutes)
- If `shield: true`, prepare privacy transaction parameters

---

### 1.2 Submit / Authorize Payment

**`POST /v1/payments/intents/{intentId}/submit`**

Called after user completes biometric/PIN authentication on the mobile device.

**Request:**
```json
{
  "auth": "biometric",
  "shield": true
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "intentId": "pi_abc123def456",
    "status": "SUBMITTING"
  }
}
```

**Backend responsibilities:**
- Verify the auth token/session is valid
- Broadcast the transaction to the network
- Transition status to `SUBMITTING` then `PENDING` once tx hash is available
- If `shield: true`, handle privacy-preserving transaction submission
- Send WebSocket event on status change

**Error cases:**
- `401` - Auth failed
- `404` - Intent not found
- `409` - Intent already submitted/cancelled/expired
- `422` - `INSUFFICIENT_FEES` (if fee changed since intent creation)

---

### 1.3 Poll Payment Intent Status

**`GET /v1/payments/intents/{intentId}`**

Mobile app polls this every 2-3 seconds while on the confirming screen. (WebSocket is preferred but polling is the fallback.)

**Response (200):**
```json
{
  "success": true,
  "data": {
    "intentId": "pi_abc123def456",
    "status": "PENDING",
    "tx": {
      "hash": "5UxR...3mKq",
      "explorerUrl": "https://solscan.io/tx/5UxR...3mKq"
    },
    "confirmations": 12,
    "requiredConfirmations": 30,
    "error": null,
    "merchant": {
      "displayName": "Starbolt Coffee",
      "iconUrl": "https://cdn.example.com/merchants/starbolt.png"
    },
    "amount": "12.00",
    "asset": "USDC",
    "network": "SOLANA",
    "shieldEnabled": true
  }
}
```

**Status transitions the frontend handles:**
| Status | Frontend behavior |
|--------|-------------------|
| `SUBMITTING` | Show "Confirming your payment" with lock animation |
| `PENDING` | Same screen, or switch to "Pending" variant if `NETWORK_BUSY` |
| `CONFIRMED` | Navigate to Success screen |
| `FAILED` | Navigate to Error screen with `error.code` |
| `CANCELLED` | Navigate to Cancelled screen |
| `EXPIRED` | Show "Payment expired" error |

**Special case**: If `error` field contains `NETWORK_BUSY`, the frontend shows the extended confirming screen with progress bar and "This may take a minute" text.

---

### 1.4 Cancel Payment Intent

**`POST /v1/payments/intents/{intentId}/cancel`**

**Request:** (empty body or with reason)
```json
{
  "reason": "user_cancelled"
}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "intentId": "pi_abc123def456",
    "status": "CANCELLED",
    "merchant": {
      "displayName": "Netflix Subscription"
    },
    "amount": "14.99"
  }
}
```

**Backend responsibilities:**
- Only allow cancellation if status is `CREATED` or `AWAITING_AUTH`
- If `SUBMITTING` or `PENDING`, return `409 Conflict` (transaction already on-chain)
- Notify merchant of cancellation

---

## 2. Activity Feed API (P0)

### 2.1 List Activity

**`GET /v1/activity?cursor={cursor}&limit={limit}&filter={filter}`**

**Query Parameters:**
| Param | Type | Default | Description |
|-------|------|---------|-------------|
| `cursor` | string | null | Cursor for pagination (opaque token) |
| `limit` | number | 20 | Items per page (max 50) |
| `filter` | enum | `all` | `all`, `income`, `expenses` |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "items": [
      {
        "id": "tx_abc123",
        "type": "merchant_payment",
        "merchantName": "Starbolt Coffee",
        "merchantIconUrl": "https://...",
        "amount": "-12.00",
        "asset": "USDC",
        "timestamp": "2026-02-07T10:45:00Z",
        "status": "confirmed",
        "protected": true
      },
      {
        "id": "tx_def456",
        "type": "transfer_in",
        "fromAddress": "8x...4921",
        "amount": "+500.00",
        "asset": "USDC",
        "timestamp": "2026-02-06T14:20:00Z",
        "status": "confirmed",
        "protected": false
      }
    ],
    "nextCursor": "eyJ0IjoiMjAyNi0wMi0wNlQxNDoyMDowMFoifQ==",
    "hasMore": true
  }
}
```

**Item types:**
- `merchant_payment` - Payment to merchant (has `merchantName`, `merchantIconUrl`)
- `transfer_out` - Send to address (has `toAddress`)
- `transfer_in` - Receive from address (has `fromAddress`)
- `shield` - Shield operation
- `unshield` - Unshield operation

**Frontend grouping:** The frontend groups items by date ("TODAY", "YESTERDAY", "Feb 5", etc.) based on `timestamp`. The backend does NOT need to group.

---

## 3. Transaction Details API (P0)

### 3.1 Get Transaction Details

**`GET /v1/transactions/{txId}`**

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": "tx_abc123",
    "type": "merchant_payment",
    "merchantName": "Jumia Marketplace",
    "merchantIconUrl": "https://...",
    "amount": "-42.50",
    "asset": "USDC",
    "network": "SOLANA",
    "timestamp": "2026-02-07T10:42:00Z",
    "referenceId": "#829A...4B2",
    "fee": {
      "nativeAsset": "SOL",
      "amount": "0.00004",
      "usdApprox": "0.01"
    },
    "protected": true,
    "privacyNote": "Privacy-preserving by default. Additional disclosure available when legally required.",
    "explorerUrl": "https://solscan.io/tx/5UxR...3mKq",
    "status": "confirmed"
  }
}
```

### 3.2 Generate/Download Receipt

**`POST /v1/transactions/{txId}/receipt`**

**Response (200):**
```json
{
  "success": true,
  "data": {
    "receiptId": "rcpt_abc123",
    "merchantName": "Jumia Marketplace",
    "amount": "42.50",
    "asset": "USDC",
    "dateTime": "2026-02-07T10:42:00Z",
    "networkFee": "0.01 USD",
    "sharePayload": "https://app.finaegis.com/receipt/rcpt_abc123",
    "pdfUrl": "https://cdn.finaegis.com/receipts/rcpt_abc123.pdf"
  }
}
```

---

## 4. Receive Address API (P0)

### 4.1 Get Receive Address

**`GET /v1/wallet/receive?asset={asset}&network={network}`**

**Query Parameters:**
| Param | Type | Required | Description |
|-------|------|----------|-------------|
| `asset` | string | yes | `USDC` or `USDT` |
| `network` | string | yes | `SOLANA`, `ETHEREUM`, `POLYGON`, etc. |

**Response (200):**
```json
{
  "success": true,
  "data": {
    "address": "7xR9...4f2Z",
    "qrPayload": "solana:7xR9...4f2Z?spl-token=USDC",
    "network": "SOLANA",
    "asset": "USDC",
    "warning": "Only send USDC on Solana to this address. Using other tokens or networks may result in loss."
  }
}
```

### 4.2 Get Network Status

**`GET /v1/networks/status`**

Frontend needs to know which networks are available (for the network selector with disabled state).

**Response (200):**
```json
{
  "success": true,
  "data": {
    "networks": [
      { "id": "SOLANA", "name": "Solana", "nativeAsset": "SOL", "status": "active" },
      { "id": "ETHEREUM", "name": "Ethereum", "nativeAsset": "ETH", "status": "unavailable", "reason": "Network congestion" },
      { "id": "POLYGON", "name": "Polygon", "nativeAsset": "MATIC", "status": "active" },
      { "id": "BITCOIN", "name": "Bitcoin", "nativeAsset": "BTC", "status": "active" },
      { "id": "AVALANCHE", "name": "Avalanche C-Chain", "nativeAsset": "AVAX", "status": "active" }
    ]
  }
}
```

---

## ~~5. Rewards API~~ - DEFERRED TO v2

> Rewards/gamification endpoints (profile, quests, shop, claim) are **out of scope for v1**.
> Do not build these endpoints. They will be specified in a future version.

---

## 6. Super-KYC Certificate API (P2)

### 6.1 Get Certificate Details

**`GET /v1/trustcert/{certId}/certificate`**

**Response (200):**
```json
{
  "success": true,
  "data": {
    "certId": "cert_abc123",
    "status": "verified",
    "verificationStatus": "Fully Verified",
    "identityId": "SP-9921-X",
    "scope": "Individual Global Account",
    "validUntil": "2025-12-31",
    "issuedAt": "2025-01-15",
    "disclaimer": "This certificate confirms identity trust within the ShieldPay ecosystem...",
    "qrPayload": "https://trust.finaegis.com/verify/cert_abc123"
  }
}
```

### 6.2 Export Certificate as PDF

**`POST /v1/trustcert/{certId}/export-pdf`**

**Response (200):**
```json
{
  "success": true,
  "data": {
    "pdfUrl": "https://cdn.finaegis.com/certs/cert_abc123.pdf",
    "expiresAt": "2026-02-07T11:00:00Z"
  }
}
```

---

## 7. WebSocket Events (P0)

### Channel: `private-payments.{userId}`

**Event: `payment.status_changed`**
```json
{
  "intentId": "pi_abc123def456",
  "status": "PENDING",
  "tx": {
    "hash": "5UxR...3mKq",
    "explorerUrl": "https://solscan.io/tx/..."
  },
  "confirmations": 12,
  "requiredConfirmations": 30,
  "error": null
}
```

This event should be sent on every status transition:
- `SUBMITTING` (tx broadcast started)
- `PENDING` (tx hash available, with confirmation updates every ~5 confirmations)
- `CONFIRMED` (final)
- `FAILED` (with error code)

The frontend uses this to update the confirming screen in real-time instead of polling.

---

## 8. Error Response Format

All errors should follow this format:

```json
{
  "success": false,
  "error": {
    "code": "MERCHANT_UNREACHABLE",
    "message": "We couldn't connect to the merchant terminal.",
    "details": {
      "merchantId": "merchant_abc123",
      "lastAttempt": "2026-02-07T10:00:05Z"
    }
  }
}
```

### Payment Error Codes

| Code | HTTP Status | Description | Frontend CTA |
|------|-------------|-------------|--------------|
| `MERCHANT_UNREACHABLE` | 422 | Cannot reach merchant terminal | "Try again" |
| `WRONG_NETWORK` | 422 | Merchant requires different network | "Switch network" |
| `WRONG_TOKEN` | 422 | Merchant requires different token | "Select USDC" |
| `INSUFFICIENT_FEES` | 422 | Not enough native token for gas | "Add SOL for fees" |
| `INSUFFICIENT_FUNDS` | 422 | Not enough token balance | "Add funds" |
| `NETWORK_BUSY` | 200 | Network congested (not an error, info) | Show extended confirming |
| `INTENT_EXPIRED` | 409 | Payment intent timed out | "Try again" (new intent) |
| `INTENT_ALREADY_SUBMITTED` | 409 | Double-submit attempt | Ignore, continue polling |

---

## 9. Priority & Timeline

| Priority | Endpoints | Blocking |
|----------|-----------|----------|
| **P0** | Payment Intents (create, submit, poll, cancel), Activity list, Transaction details, Receive address, Network status, WebSocket events | Yes - blocks all payment UI |
| **P1** | Receipt generation | No - can mock on frontend |
| **P2** | Certificate export PDF, Certificate QR | No - existing TrustCert flow works |
| ~~P1~~ | ~~Rewards (profile, quests, shop, claim)~~ | **Deferred to v2** |

### Suggested backend implementation order:
1. Payment Intent CRUD + status machine
2. WebSocket integration for payment status
3. Activity feed with cursor pagination
4. Transaction details + receipt generation
5. Receive address generation
6. Network status endpoint
7. Certificate export

### Network constraints:
- v1 supports **Solana + Tron only**. The `preferredNetwork` field in payment intents should only accept `SOLANA` and `TRON`.
- The network status endpoint should return only these two networks (plus others marked as `coming_soon` if desired).
- Asset constraint (as of v7.13.1): the `asset` field accepts `USDC` and `USDT`. Both are 6-decimal stablecoins. Asset support is enum-driven via `App\Domain\MobilePayment\Enums\PaymentAsset` — future additions auto-extend the validators.

---

## 10. Design Reference

The backend developer has access to the `docs/designs/` folder which contains:
- 20 screen designs (screenshot + HTML code per screen)
- `docs/designs/specsheet.md` - specification document

Key designs relevant to the backend:
- `payment/` - Payment keypad (shows merchant info, shield toggle, amount)
- `confirming-payment/` - Confirming screen (status polling UI)
- `pending-confirmation/` - Extended confirming (confirmations progress)
- `payment-successful/` - Success screen (shows receipt + XP reward)
- `payment-cancelled/` - Cancelled screen (shows merchant + amount context)
- `error-states/` - Three error variants (wrong network, wrong token, insufficient fees)
- `merchant-unreachable/` - Terminal error
- `activity-history/` - Activity list with filters
- `transaction-details/` - Transaction detail with privacy info
- `receive-stablecoins/` - Receive with network/asset selector
- `rewards/` - Full rewards screen
- `super-kyc/` - Certificate display
