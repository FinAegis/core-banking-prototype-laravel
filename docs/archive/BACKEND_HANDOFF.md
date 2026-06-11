# Backend Developer Handoff — Mobile API Contract Gaps

> Generated from a comprehensive audit comparing all mobile service calls against the Laravel backend (core-banking-prototype-laravel). Mobile PR #202 added normalizers to handle most mismatches on the frontend side. Items below require **backend changes** to fully resolve.

## Priority Legend
- 🔴 **Critical** — Will cause user-visible failures or data loss
- 🟡 **Medium** — Feature works partially, degraded experience
- 🟢 **Low** — Minor inconsistency, mobile has workarounds

---

## 1. Wallet Service

### 🟡 GET /api/v1/wallet/balances — Missing fields
**Current response:**
```json
{ "token": "USDC", "network": "polygon", "balance": "1000.50", "error": null }
```
**Requested additions:**
- `usd_value` (number) — USD equivalent. Mobile currently defaults to `0`.
- `balance_formatted` (string) — Human-friendly format. Mobile falls back to raw balance.
- `change_24h` (number, optional) — 24h price change percentage.

### 🟡 GET /api/v1/wallet/state — Missing balance aggregation
**Current response:** Returns `addresses` only.
**Requested additions:**
- `total_usd_value` (number) — Sum of all balances in USD.
- `shielded_balance` (number) — Total shielded balance.
- `balances` (array) — Same structure as `/balances` endpoint.

### 🟡 GET /api/v1/wallet/tokens — Multi-network format
**Current response:** Returns tokens with `networks[]` array and `addresses{}` object.
**Mobile expects:** One token entry per chain (e.g., USDC on polygon and USDC on base as separate entries).
**Note:** Mobile now handles this with normalizer. No backend change required unless you want to simplify.

### 🟡 GET /api/v1/wallet/transactions — Cursor vs page pagination
**Current:** Backend uses cursor-based (`items` + `cursor`).
**Mobile sends:** `page` + `limit` params.
**Note:** Mobile normalizer handles `items` → `transactions` key mapping. Consider adding page-based pagination alias or documenting cursor usage for mobile.

### 🟢 POST /api/v1/transactions/{txId}/receipt — Missing fields
**Current response:** Missing `tx_id` and `network` fields.
**Note:** Mobile injects `txId` from the request parameter. Backend should include them for completeness.

### 🟢 GET /api/v1/wallet/recent-recipients — Time format
**Current:** Returns `last_sent_at` as ISO 8601.
**Note:** Mobile converts to relative time ("2 days ago"). This is fine — no change needed.

---

## 2. Auth Service

### 🟢 POST /api/auth/register — Mobile now sends correct fields
**Fixed in PR #202:** Mobile now sends `name` (required), `password_confirmation`, etc.
**No backend change needed.**

### 🟢 POST /api/v1/auth/passkey/authenticate — Mobile now decomposes credentials
**Fixed in PR #202:** Mobile now sends `credential_id`, `client_data_json`, `authenticator_data`, `signature`.
**No backend change needed.**

### 🟢 POST /api/auth/delete-account — Mobile now sends confirmation
**Fixed in PR #202:** Mobile now sends `{ confirmation: "DELETE" }`.
**No backend change needed.**

---

## 3. Relayer Service

### 🔴 POST /api/v1/relayer/estimate-fee — Response structure mismatch
**Current response:**
```json
{ "gas_price": "30", "gas_limit": "0x5208", "total_cost": "0.05", "currency": "MATIC", "sponsored": false }
```
**Mobile expects (GasEstimate type):**
```json
{
  "call_gas_limit": "string",
  "verification_gas_limit": "string",
  "pre_verification_gas": "string",
  "max_fee_per_gas": "string",
  "max_priority_fee_per_gas": "string",
  "paymaster_fee": "string",
  "paymaster_fee_usd": 0.0,
  "total_fee_usd": 0.05
}
```
**Note:** Mobile normalizer maps `gas_price` → `max_fee_per_gas`, `total_cost` → `total_fee_usd`. The ERC-4337 granular fields default to "0". For proper gas estimation, backend should return `total_fee_usd` as a number.

### 🟡 GET /api/v1/relayer/supported-tokens — Return format
**Current:** Returns `[{ symbol, name, sponsored }]` (array of objects).
**Mobile expects:** `string[]` (just symbols).
**Note:** Mobile normalizer extracts symbols. No urgent change needed.

### 🟡 GET /api/v1/relayer/paymaster-data — Structure mismatch
**Current:** Returns `[{ paymaster_address, entry_point, sponsored_tokens, max_gas_sponsored }]`.
**Mobile expects:** `{ paymaster_and_data, gas_token_amount }`.
**Note:** This is a fundamental architecture difference. Mobile may need to adapt further when paymaster integration is finalized.

---

## 4. Commerce Service

### 🟡 POST /api/v1/commerce/payment-requests — Merchant data
**Current:** Returns `merchant_id` as a string ID.
**Mobile expects:** Nested `merchant` object with `{ id, name, display_name, category, accepted_tokens }`.
**Suggestion:** Include a nested `merchant` object in the response, or provide a `?include=merchant` query param.

### 🟢 POST /api/v1/commerce/parse-qr — Field naming
**Current:** Returns `asset` and `network`.
**Mobile expects:** `token` and `chain_id`.
**Note:** Mobile normalizer handles this. Consider standardizing on one naming convention.

---

## 5. Cards Service

### 🟡 GET /api/v1/cards — ID field name
**Current:** Returns `card_token` as the identifier.
**Mobile expects:** `id` field.
**Note:** Mobile normalizer maps `card_token` → `id`. Consider adding an `id` alias.

### 🟡 POST /api/v1/cards/provision — Device ID
**Current:** Requires `device_id` in request body.
**Mobile:** Now includes `device_id` in provisioning request (added to type in PR #202).
**Note:** Mobile needs to know the current device ID at provisioning time.

### 🟢 GET /api/v1/cards — Expiry breakdown
**Current:** Returns `expires_at` as ISO 8601 string.
**Mobile expects:** `expiry_month` and `expiry_year` as separate numbers.
**Note:** Mobile normalizer parses the date. Consider adding the breakdown fields.

---

## 6. Privacy Service

### 🟡 GET /api/v1/privacy/balances — Token type
**Current:** Returns `token` as string (`"USDC"`).
**Mobile expects:** Full `Token` object `{ symbol, name, address, decimals, chain_id, is_stablecoin }`.
**Note:** Mobile normalizer builds a stub Token from the symbol. Consider returning full token objects for richer UI.

---

## 7. TrustCert Service

### 🟡 POST /api/v1/trustcert/check-limit — Missing upgrade info
**Current:** Returns `{ allowed, trust_level, limit, amount, type, remaining }`.
**Mobile expects:** `upgrade_required` field (next trust level needed).
**Note:** Mobile derives this from `trust_level + 1`. Backend should return it explicitly.

### 🟢 GET /api/v1/trustcert/requirements — Field naming
**Current:** Uses `label` instead of `name`, limits in nested `{ daily, monthly, single }` object.
**Note:** Mobile normalizer handles mapping. Consider adding `name` and `description` fields.

---

## 8. Rewards Service

### 🟡 GET /api/v1/rewards/profile — Field naming
**Current:** Returns `xp_for_next` (next level XP threshold).
**Mobile expects:** `target_xp` (same meaning, different name).
**Note:** Mobile normalizer maps this. Consider adding `target_xp` alias.

### 🟡 POST /api/v1/rewards/quests/{id}/complete — Missing total XP
**Current:** Returns `{ quest_id, xp_earned, points_earned, new_level, level_up }`.
**Missing:** `new_total_xp` — the user's updated total XP after completion.
**Suggestion:** Add `new_total_xp` to the response.

### 🔴 POST /api/v1/rewards/shop/{id}/redeem — Semantic mismatch
**Current:** Returns `{ redemption_id, points_spent, points_balance }` (points-based).
**Mobile expects:** `{ item_id, xp_deducted, new_total_xp }` (XP-based).
**Note:** The rewards system uses points vs XP — need to clarify which currency the shop uses. Mobile normalizer maps `points_spent` → `xp_deducted` for now, but this is semantically incorrect if points ≠ XP.

### 🟢 GET /api/v1/rewards/shop — Missing fields
**Current:** Missing `slug` and `description` on some items.
**Note:** Mobile displays these in the shop UI. Consider ensuring all items have slugs and descriptions.

---

## 9. Already Resolved (No Backend Changes Needed)

These were fixed entirely on the mobile side with normalizers:

- ✅ Wallet addresses: `network` → `chain_id`, `deployed` → `is_deployed`
- ✅ Transaction detail: `asset` → `token`, `from_address` → `from`
- ✅ Compliance sanctions check: Perfect match after snake→camel conversion
- ✅ X402 service: Fields match after snake→camel conversion
- ✅ Smart account: `account_address` → `address`, `deployed` → `is_deployed` (PR #201)
- ✅ Data export: Correct endpoints, downloadUrl support (PR #202)
- ✅ Recovery shard: encryptedShard field, retrieve endpoint (PR #202)

---

---

## 10. On/Off Ramp — Stripe Bridge Integration (NEW)

> **PR #722 + #723** — API-first Stripe Bridge integration. Mobile owns the UI (currency picker, quote comparison). Stripe Bridge is used purely as a data API — only the checkout URL opens in a browser for payment/KYC.

### Provider
**Stripe Bridge** — fiat-to-crypto ramp with async webhook processing.
Docs: https://docs.stripe.com/crypto/onramp

### Endpoints

| Method | Endpoint | Auth | Purpose |
|--------|----------|------|---------|
| `GET` | `/api/v1/ramp/supported` | Bearer | Get supported currencies, modes, and limits |
| `GET` | `/api/v1/ramp/quotes` | Bearer | Get quotes from ALL aggregated providers |
| `POST` | `/api/v1/ramp/session` | Bearer | Create session with selected quote (returns checkout URL) |
| `GET` | `/api/v1/ramp/session/{id}` | Bearer | Poll session status |
| `GET` | `/api/v1/ramp/sessions` | Bearer | List user's transaction history |

### Flow

```
1. GET /supported          → populate currency picker, enforce limits
2. GET /quotes             → show ALL provider quotes (mobile renders comparison UI)
3. User selects a quote    → mobile has the quote_id
4. POST /session           → pass quote_id + wallet → get checkout_url
5. Open checkout_url       → only for payment/KYC step (in-app browser)
6. Poll GET /session/{id}  → until status = completed|failed
```

### GET /api/v1/ramp/supported

```json
{
  "data": {
    "provider": "stripe_bridge",
    "fiat_currencies": ["USD", "EUR", "GBP"],
    "crypto_currencies": ["USDC", "USDT", "ETH", "BTC"],
    "modes": ["buy", "sell"],
    "limits": {
      "min_amount": 10,
      "max_amount": 10000,
      "daily_limit": 50000
    }
  }
}
```

### GET /api/v1/ramp/quotes

**Params:** `type=on|off`, `fiat=USD`, `amount=100`, `crypto=USDC`

Returns quotes from **all available providers** so mobile can render a comparison UI:

```json
{
  "data": {
    "quotes": [
      {
        "provider_name": "Simplex",
        "quote_id": "q_12345",
        "fiat_amount": 100,
        "crypto_amount": 0.0025,
        "exchange_rate": 0.000026,
        "fee": 3.50,
        "network_fee": 0.50,
        "fee_currency": "USD",
        "payment_methods": ["credit_card", "bank_transfer"]
      },
      {
        "provider_name": "MoonPay",
        "quote_id": "q_67890",
        "fiat_amount": 100,
        "crypto_amount": 0.0024,
        "exchange_rate": 0.000025,
        "fee": 4.00,
        "network_fee": 0.50,
        "fee_currency": "USD",
        "payment_methods": ["credit_card"]
      }
    ],
    "provider": "stripe_bridge",
    "valid_until": "2026-03-05T17:00:00+00:00"
  }
}
```

### POST /api/v1/ramp/session

**Request:**
```json
{
  "type": "on",
  "fiat_currency": "USD",
  "fiat_amount": 100,
  "crypto_currency": "USDC",
  "wallet_address": "0x1234...",
  "quote_id": "q_12345"
}
```

**Response (201):**
```json
{
  "data": {
    "id": "uuid",
    "provider": "stripe_bridge",
    "type": "on",
    "type_label": "Buy Crypto",
    "fiat_currency": "USD",
    "fiat_amount": 100,
    "crypto_currency": "USDC",
    "crypto_amount": null,
    "status": "pending",
    "status_label": "Pending",
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_tx_789",
    "created_at": "2026-03-05T16:00:00+00:00",
    "updated_at": "2026-03-05T16:00:00+00:00"
  }
}
```

### Key Fields for Mobile

| Field | Usage |
|-------|-------|
| `quotes[].quote_id` | Pass this to `POST /session` to select a specific provider |
| `quotes[].provider_name` | Display name for the provider in the comparison UI |
| `quotes[].fee` + `network_fee` | Show total cost breakdown to user |
| `checkout_url` | **Open in in-app browser** for the payment/KYC step only. `null` in mock mode. |
| `id` | Use to poll `GET /session/{id}` for status updates |
| `status` | `pending` → `processing` → `completed` or `failed` |
| `type` | `on` = buy crypto (fiat → crypto), `off` = sell crypto (crypto → fiat) |
| `crypto_amount` | Populated after transaction completes |

### Mobile Implementation Notes

1. **Native quote comparison UI**: Call `GET /quotes` and render all quotes in a list/card layout. Show provider name, crypto amount, fees, and payment methods. Let the user tap to select a quote.

2. **Session creation**: After the user selects a quote, call `POST /session` with the `quote_id`. The response includes `checkout_url`.

3. **Checkout URL**: Open `checkout_url` in an in-app browser (not a full WebView widget). The user completes payment/KYC at the provider's checkout page. This is the only step not in your native UI.

4. **Status polling**: After the browser closes, poll `GET /session/{id}` every 5-10 seconds until `status` is `completed` or `failed`. Webhooks also update status server-side.

5. **Off-ramp (sell)**: Use `type: "off"` in both `/quotes` and `/session`. The user sends crypto to a provider address and receives fiat.

6. **Currency picker**: Call `GET /supported` on screen load to populate fiat/crypto dropdowns and enforce min/max limits client-side before calling `/quotes`.

7. **Error handling**: `/quotes` and `/session` return `422` with `{ "error": { "code": "...", "message": "..." } }` for validation errors.

8. **Mock mode**: In dev/staging (`RAMP_PROVIDER=mock`), quotes return instant mock data and `checkout_url` is `null`. No browser step needed.

---

## Recommended Backend Priorities

1. **Relayer estimate-fee response** (🔴) — Add `total_fee_usd` as number
2. **Rewards redemption clarity** (🔴) — Clarify points vs XP, add `new_total_xp`
3. **Wallet balances USD value** (🟡) — Add `usd_value` field
4. **Wallet state aggregation** (🟡) — Include `total_usd_value` and `balances`
5. **Commerce payment requests** (🟡) — Nest merchant object
6. **Privacy balances token type** (🟡) — Return full Token objects
7. **TrustCert check-limit** (🟡) — Add `upgrade_required`
