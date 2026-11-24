# Backend Handover: Cards Waitlist, Paid KYC, Stripe Bridge Ramp

**Date**: 2026-04-07
**Mobile version**: 1.2.0+
**Status**: Historical request. The backend work is done or was replaced, as the note below explains.

> **Status at v7.18.1.** This is the mobile team's request from 7 April 2026, kept as a record. The backend has since built the card waitlist (`POST /api/v1/cards/waitlist` and `GET /api/v1/cards/waitlist/status`). It has also built the paid identity check with `POST /api/v1/trustcert/applications/{applicationId}/pay`, `/pay/card` and `/pay/iap`, and the `POST /api/webhooks/stripe/kyc` webhook.
>
> The ramp plan in section 3 changed. ADR-0005 chose Bridge.xyz instead of Stripe's crypto onramp, and the Stripe scaffolding was renamed `stripe_crypto_onramp`. Its webhook is `POST /api/v1/ramp/webhook/{provider}`, not `/webhooks/stripe/bridge`. The current ramp is described in `docs/operations/bridge-ramp.md`.
>
> The card plan changed too. v7.18.0 added virtual cards with Lepo Finance for a closed pilot. In production only the users on the pilot list can get a card, and adding money to a card stays off (`CARD_TOPUPS_ENABLED=false`). The card endpoints are under `/api/v1/cards/onboarding` and `/api/v1/cards/issued`, and the OpenAPI documentation at `/api/documentation` describes them under the Cards tag.

This document covers three new backend requirements:

1. **Card pre-order waitlist** — Cards are deferred to late 2027; users can join a waitlist
2. **Paid KYC verification** — Users pay a fee before starting identity verification
3. **Stripe Bridge ramp** — Migration from Onramper to Stripe Bridge for fiat on/off-ramp

---

## 1. Card Pre-Order Waitlist

### Overview

Virtual cards are not available at launch. The mobile app now shows a pre-order screen where users can join a waitlist. The backend needs two endpoints to support this.

### Endpoints

#### `POST /api/v1/cards/waitlist`

Register the authenticated user's interest in a card.

**Request**: Empty body (user identified from auth token)

**Response** `201 Created`:
```json
{
  "id": "wl_abc123",
  "position": 142,
  "joinedAt": "2026-04-07T12:00:00Z"
}
```

**Error cases**:
- `409 Conflict` — User already on waitlist (return existing record)
- `401 Unauthorized` — Invalid/expired token

**Backend behavior**:
- Create a `card_waitlist` record: `{ user_id, position, joined_at }`
- Position = count of existing records + 1
- If user already exists, return `409` with their existing record (idempotent)
- Optional: flag user record with `card_waitlist_requested = true` for future CRM/marketing

#### `GET /api/v1/cards/waitlist/status`

Check if the authenticated user is on the waitlist.

**Response** `200 OK` (on waitlist):
```json
{
  "joined": true,
  "position": 142,
  "joinedAt": "2026-04-07T12:00:00Z"
}
```

**Response** `200 OK` (not on waitlist):
```json
{
  "joined": false,
  "position": null,
  "joinedAt": null
}
```

### Database Schema

```sql
CREATE TABLE card_waitlist (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     UUID NOT NULL REFERENCES users(id) UNIQUE,
    position    INTEGER NOT NULL,
    joined_at   TIMESTAMP NOT NULL DEFAULT NOW(),
    notified_at TIMESTAMP NULL,     -- when card launch notification was sent
    converted   BOOLEAN DEFAULT FALSE  -- when user actually orders a card
);

CREATE INDEX idx_card_waitlist_user ON card_waitlist(user_id);
```

### Notes

- Cards will be **paid** (pricing TBD, announced closer to late 2027 launch)
- No card creation/management endpoints are needed until then
- The existing `GET /api/v1/cards` endpoint can return an empty array for now
- Consider a `card_waitlist_count` endpoint for admin/marketing dashboards

---

## 2. Paid KYC Verification

### Overview

Identity verification (KYC) is now a paid service. Before launching the Ondato SDK or manual document upload, the mobile app presents a payment screen with **3 payment methods**:

1. **Wallet Balance** — Deduct USDC from the user's wallet (instant)
2. **Card Payment** — Pay with debit/credit card via Stripe Checkout (hosted)
3. **In-App Purchase** — Pay via App Store / Google Play (native only)

### Flow Change

**Before**:
```
apply.tsx → startApplication() → ondato.tsx / upload.tsx
```

**After**:
```
apply.tsx → startApplication() → payment.tsx → [choose method] → ondato.tsx / upload.tsx
```

The payment screen shows all 3 methods. The user selects one, pays, and is then routed to verification.

### Endpoints

#### Updated: `GET /api/v1/trustcert/requirements/{level}`

Add a `verificationFee` field to the existing response:

```json
{
  "level": 2,
  "name": "Verified",
  "description": "Government ID verification",
  "documents": ["id_front", "id_back", "selfie"],
  "dailyLimit": 10000,
  "monthlyLimit": 50000,
  "verificationFee": 4.99
}
```

**Fee schedule** (suggested):
| Level | Fee (USD) | Description |
|-------|-----------|-------------|
| 1 (Basic) | $4.99 | Email/phone verification |
| 2 (Verified) | $4.99 | Government ID + selfie |
| 3 (Premium) | $9.99 | Enhanced verification + proof of address |

The mobile app reads `verificationFee` from this endpoint to display the fee before payment.

---

### Method 1: Wallet Balance Payment

#### `POST /api/v1/trustcert/applications/{applicationId}/pay`

Charge the verification fee from the user's USDC wallet balance.

**Request**: Empty body (fee amount determined server-side from application's target level)

**Response** `200 OK`:
```json
{
  "receiptId": "rcpt_xyz789",
  "amount": 4.99,
  "currency": "USD",
  "paidAt": "2026-04-07T12:05:00Z"
}
```

**Error cases**:
- `402 Payment Required` — Insufficient wallet balance
  ```json
  {
    "error": "ERR_CERT_501",
    "message": "Insufficient balance. Required: $4.99, Available: $2.15",
    "required": 4.99,
    "available": 2.15
  }
  ```
- `404 Not Found` — Invalid application ID
- `409 Conflict` — Application already paid (return existing receipt, idempotent)
- `400 Bad Request` — Application in wrong state (e.g., already submitted)

**Backend behavior**:
1. Look up the application and its target level
2. Get the `verificationFee` for that level from config
3. Check user's USDC balance >= fee
4. Deduct fee from wallet balance (internal ledger transfer, NOT an on-chain transaction)
5. Mark application as `paid` (new status field or `paid_at` timestamp)
6. Return receipt

---

### Method 2: Card Payment (Stripe Checkout)

#### `POST /api/v1/trustcert/applications/{applicationId}/pay/card`

Create a Stripe Checkout session for the verification fee.

**Request**: Empty body

**Response** `200 OK`:
```json
{
  "sessionId": "cs_live_abc123",
  "checkoutUrl": "https://checkout.stripe.com/c/pay/cs_live_abc123",
  "expiresAt": "2026-04-07T12:30:00Z"
}
```

**Error cases**:
- `404 Not Found` — Invalid application ID
- `409 Conflict` — Application already paid
- `500 Internal Server Error` — Stripe API failure

**Backend behavior**:
1. Look up the application and fee amount
2. Create a Stripe Checkout Session:
   ```
   mode: 'payment'
   line_items: [{ price_data: { currency: 'usd', unit_amount: 499, product_data: { name: 'KYC Verification - Level 2' } }, quantity: 1 }]
   success_url: 'zelta://kyc-payment-success?session_id={CHECKOUT_SESSION_ID}'
   cancel_url: 'zelta://kyc-payment-cancel'
   metadata: { application_id, user_id, level }
   ```
3. Return `checkoutUrl` to mobile (opened in in-app browser via `expo-web-browser`)
4. **Stripe webhook** (`checkout.session.completed`) marks the application as paid
5. Mobile navigates to verification after browser closes (backend has already marked as paid via webhook)

#### Stripe Webhook: `POST /webhooks/stripe/kyc`

Handle `checkout.session.completed` events:
1. Extract `application_id` from session metadata
2. Verify payment amount matches expected fee
3. Mark application as `paid`
4. Store Stripe session ID + payment intent ID for audit

**Environment variables**:
```env
STRIPE_SECRET_KEY=sk_live_...
STRIPE_KYC_WEBHOOK_SECRET=whsec_...
```

---

### Method 3: In-App Purchase (iOS App Store / Google Play)

#### `POST /api/v1/trustcert/applications/{applicationId}/pay/iap`

Verify an App Store or Play Store receipt and mark the application as paid.

**Request**:
```json
{
  "receipt": "MIIbkgYJKoZIhvcNAQcCoIIbg...",
  "platform": "ios"
}
```

| Field | Type | Description |
|-------|------|-------------|
| `receipt` | string | Base64-encoded receipt (iOS) or purchase token (Android) |
| `platform` | `"ios"` \| `"android"` | Which store to verify against |

**Response** `200 OK`:
```json
{
  "receiptId": "rcpt_iap_abc123",
  "amount": 4.99,
  "currency": "USD",
  "paidAt": "2026-04-07T12:10:00Z"
}
```

**Error cases**:
- `402 Payment Required` — Receipt invalid or already consumed
  ```json
  {
    "error": "ERR_CERT_502",
    "message": "Receipt verification failed"
  }
  ```
- `404 Not Found` — Invalid application ID
- `409 Conflict` — Application already paid

**Backend behavior**:

**For iOS (App Store)**:
1. Call Apple's `/verifyReceipt` endpoint (or use App Store Server API v2)
2. Validate receipt signature and product ID matches `kyc_verification_level_{N}`
3. Check transaction hasn't been used before (prevent replay)
4. Mark application as paid

**For Android (Google Play)**:
1. Call Google Play Developer API: `purchases.products.get`
2. Validate purchase token and product ID
3. Acknowledge the purchase (`purchases.products.acknowledge`)
4. Mark application as paid

**Store product setup required**:
| Store | Product ID | Type | Price |
|-------|-----------|------|-------|
| App Store | `kyc_verification_level_1` | Non-consumable | $4.99 |
| App Store | `kyc_verification_level_2` | Non-consumable | $4.99 |
| App Store | `kyc_verification_level_3` | Non-consumable | $9.99 |
| Google Play | `kyc_verification_level_1` | One-time product | $4.99 |
| Google Play | `kyc_verification_level_2` | One-time product | $4.99 |
| Google Play | `kyc_verification_level_3` | One-time product | $9.99 |

**Note**: Apple/Google take ~30% commission. Consider whether the fee should be higher on IAP to offset this, or absorb the cost.

**Mobile dependency**: `react-native-iap` package (not yet installed — the mobile app gracefully falls back if the module is missing)

---

### Application Status Flow (All Methods)

```
created → paid → submitted → reviewing → approved/rejected
```

All 3 payment methods result in the same `paid` status. The `paid_at`, `payment_method`, and `payment_receipt_id` fields record how the payment was made.

### Database Changes

```sql
ALTER TABLE trustcert_applications
    ADD COLUMN paid_at TIMESTAMP NULL,
    ADD COLUMN payment_method VARCHAR(20) NULL,  -- 'wallet', 'card', 'iap'
    ADD COLUMN payment_receipt_id VARCHAR(128) NULL,
    ADD COLUMN payment_amount DECIMAL(10,2) NULL;

-- Separate payments table for full audit trail:
CREATE TABLE verification_payments (
    id              UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id         UUID NOT NULL REFERENCES users(id),
    application_id  UUID NOT NULL REFERENCES trustcert_applications(id),
    method          VARCHAR(20) NOT NULL,  -- 'wallet', 'card', 'iap'
    amount          DECIMAL(10,2) NOT NULL,
    currency        VARCHAR(3) NOT NULL DEFAULT 'USD',
    status          VARCHAR(20) NOT NULL DEFAULT 'completed',
    stripe_session_id VARCHAR(255) NULL,    -- for card payments
    iap_transaction_id VARCHAR(255) NULL,   -- for IAP payments
    platform        VARCHAR(10) NULL,       -- 'ios', 'android' (for IAP)
    created_at      TIMESTAMP NOT NULL DEFAULT NOW()
);
```

### Refund Policy

- If verification **has not started** (application not submitted to Ondato), allow refund via admin endpoint
- If verification **has started**, no automatic refund (Ondato charges per session)
- **Card payments**: Refund via Stripe Refund API
- **IAP payments**: Users request refund through App Store / Google Play (standard platform process)
- **Wallet payments**: Refund by crediting USDC balance
- Consider an admin endpoint: `POST /api/v1/admin/trustcert/refund/{applicationId}`

---

## 3. Stripe Bridge Ramp (Migration from Onramper)

### Overview

The mobile app currently integrates with Onramper for fiat-to-crypto (buy) and crypto-to-fiat (sell) operations. This will be migrated to **Stripe Bridge** (Stripe's crypto on/off-ramp product).

The mobile app talks to the backend, NOT directly to Stripe. The backend orchestrates Stripe Bridge sessions and exposes the same REST API shape. This means the migration is primarily a **backend change** — the mobile app's API contract stays largely the same.

### Current API Contract (Onramper)

The mobile app currently calls these endpoints:

| Method | Endpoint | Purpose |
|--------|----------|---------|
| `GET` | `/api/v1/ramp/supported` | Supported currencies, limits |
| `GET` | `/api/v1/ramp/quotes?type=on&fiat=USD&amount=100&crypto=USDC` | Get provider quotes |
| `POST` | `/api/v1/ramp/session` | Create checkout session |
| `GET` | `/api/v1/ramp/session/{id}` | Poll session status |
| `GET` | `/api/v1/ramp/sessions` | List user's sessions |

### Target API Contract (Stripe Bridge)

Keep the same endpoint paths. Adapt the response format where Stripe Bridge differs.

#### `GET /api/v1/ramp/supported`

**Response** (update `provider` field):
```json
{
  "provider": "stripe_bridge",
  "fiatCurrencies": ["USD", "EUR", "GBP"],
  "cryptoCurrencies": ["USDC"],
  "modes": ["buy", "sell"],
  "limits": {
    "minAmount": 10,
    "maxAmount": 10000,
    "dailyLimit": 50000
  }
}
```

- Stripe Bridge may have different currency/limit support — adjust as needed
- The mobile app reads this dynamically, so changes propagate automatically

#### `GET /api/v1/ramp/quotes`

**Query params**: `type`, `fiat`, `amount`, `crypto` (unchanged)

**Response** (adapt from Stripe pricing):
```json
{
  "quotes": [
    {
      "providerName": "Stripe",
      "quoteId": "stripe_quote_abc",
      "fiatAmount": 100.00,
      "cryptoAmount": 98.50,
      "exchangeRate": 1.0,
      "fee": 1.00,
      "networkFee": 0.50,
      "feeCurrency": "USD",
      "paymentMethods": ["card", "bank_transfer"]
    }
  ],
  "provider": "stripe_bridge",
  "validUntil": "2026-04-07T12:10:00Z"
}
```

**Key difference**: Onramper returned multiple provider quotes (Simplex, MoonPay, etc.). Stripe Bridge is a single provider — return a single-element `quotes` array. The mobile UI already handles both single and multiple quotes gracefully.

**Payment methods mapping**:
- Stripe Bridge supports: `card`, `bank_transfer`, `link`
- Map to the existing `paymentMethods` string array
- The mobile UI renders these as pills — no code change needed

#### `POST /api/v1/ramp/session`

**Request** (unchanged):
```json
{
  "type": "on",
  "fiatCurrency": "USD",
  "fiatAmount": 100,
  "cryptoCurrency": "USDC",
  "walletAddress": "0x...",
  "quoteId": "stripe_quote_abc"
}
```

**Response** (adapt):
```json
{
  "id": "sess_stripe_xyz",
  "provider": "stripe_bridge",
  "type": "on",
  "typeLabel": "Buy Crypto",
  "fiatCurrency": "USD",
  "fiatAmount": 100,
  "cryptoCurrency": "USDC",
  "cryptoAmount": null,
  "status": "pending",
  "statusLabel": "Waiting for payment",
  "checkoutUrl": "https://crypto-onramp.stripe.com/...",
  "createdAt": "2026-04-07T12:00:00Z",
  "updatedAt": "2026-04-07T12:00:00Z"
}
```

**Backend behavior**:
1. Call Stripe Bridge API to create an onramp/offramp session
2. Get the hosted checkout URL from Stripe
3. Store session in DB with status mapping
4. Return to mobile — the app opens `checkoutUrl` in an in-app browser (expo-web-browser)

#### `GET /api/v1/ramp/session/{id}`

**Response**: Same shape as create. Status updates via Stripe webhooks.

**Status mapping** (Stripe → mobile):
| Stripe Bridge Status | Mobile `RampSessionStatus` | `statusLabel` |
|---------------------|---------------------------|---------------|
| `initialized` | `pending` | "Waiting for payment" |
| `payment_pending` | `processing` | "Payment processing" |
| `payment_complete` | `processing` | "Sending crypto" |
| `fulfilled` | `completed` | "Completed" |
| `payment_failed` | `failed` | "Payment failed" |
| `expired` | `expired` | "Session expired" |

The mobile app polls this every 5 seconds and auto-stops on terminal status (`completed`, `failed`, `expired`).

#### `GET /api/v1/ramp/sessions`

**Response**: Array of sessions (same shape). No change needed.

### Stripe Bridge Backend Implementation

#### Webhook Handler

Register a webhook endpoint for Stripe Bridge events:
- `POST /webhooks/stripe/bridge` (internal, not exposed to mobile)

Events to handle:
- `crypto_onramp.session.updated` — Update session status in DB
- `crypto_onramp.session.completed` — Mark as completed, update crypto amount

#### Environment Variables

```env
STRIPE_SECRET_KEY=sk_live_...
STRIPE_BRIDGE_WEBHOOK_SECRET=whsec_...
STRIPE_BRIDGE_ENABLED=true
```

#### Database

Reuse existing `ramp_sessions` table. Add:
```sql
ALTER TABLE ramp_sessions
    ADD COLUMN stripe_session_id VARCHAR(255) NULL,
    ADD COLUMN stripe_client_secret VARCHAR(255) NULL;
```

### Migration Checklist

- [ ] Implement Stripe Bridge API client (server-side)
- [ ] Create webhook handler for session status updates
- [ ] Map Stripe statuses to existing `RampSessionStatus` enum
- [ ] Update `/api/v1/ramp/supported` to reflect Stripe Bridge capabilities
- [ ] Update quote endpoint to fetch Stripe pricing
- [ ] Update session creation to call Stripe Bridge API
- [ ] Update session polling to read from DB (updated via webhooks)
- [ ] Set up Stripe webhook signing verification
- [ ] Test buy flow end-to-end (fiat → crypto)
- [ ] Test sell flow end-to-end (crypto → fiat)
- [ ] Remove Onramper dependencies from backend
- [ ] Update rate limiting if Stripe has different quotas

### Mobile Impact

**No mobile code changes required** for the ramp migration if the backend maintains the same response shape. The mobile app:
- Reads `provider` field but doesn't gate on it
- Uses `checkoutUrl` to open the checkout in a browser
- Polls session status until terminal
- Displays `statusLabel` as-is

The only visible difference to users will be the Stripe checkout UI instead of Onramper's.

---

## Summary of Backend Work

| Feature | Endpoints | Priority | Complexity |
|---------|-----------|----------|------------|
| Card Waitlist | 2 new (GET + POST) | Low | Simple |
| Paid KYC — Wallet | 1 new (POST pay) + 1 updated (GET requirements) | High | Low |
| Paid KYC — Card | 1 new (POST pay/card) + 1 webhook | High | Medium |
| Paid KYC — IAP | 1 new (POST pay/iap) + store product setup | Medium | High |
| Stripe Bridge Ramp | 5 existing (adapted) + 1 webhook | High | High |

**Recommended implementation order**:
1. **Paid KYC** — Blocks revenue, simple endpoint
2. **Card Waitlist** — Simple CRUD, low effort
3. **Stripe Bridge** — Largest effort, can run in parallel with other work

---

## Mobile Endpoints Summary

All endpoints the mobile app calls, grouped by feature:

### Cards Waitlist (NEW)
```
POST /api/v1/cards/waitlist
GET  /api/v1/cards/waitlist/status
```

### KYC Payment — 3 Methods (NEW + UPDATED)
```
GET  /api/v1/trustcert/requirements/{level}         ← add verificationFee field
POST /api/v1/trustcert/applications/{id}/pay         ← NEW (wallet balance)
POST /api/v1/trustcert/applications/{id}/pay/card    ← NEW (Stripe Checkout)
POST /api/v1/trustcert/applications/{id}/pay/iap     ← NEW (IAP receipt verify)
POST /webhooks/stripe/kyc                            ← internal webhook
```

### Ramp / Stripe Bridge (MIGRATED)
```
GET  /api/v1/ramp/supported
GET  /api/v1/ramp/quotes
POST /api/v1/ramp/session
GET  /api/v1/ramp/session/{id}
GET  /api/v1/ramp/sessions
POST /webhooks/stripe/bridge                     ← internal webhook
```
