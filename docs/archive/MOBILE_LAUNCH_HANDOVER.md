# Mobile Developer Handover — Launch Readiness

## Rewards API (Ready Now)

All endpoints require `Authorization: Bearer {sanctum_token}`.

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/rewards/profile` | User's XP, level, streak, points balance |
| GET | `/api/v1/rewards/quests` | Available quests with completion status |
| POST | `/api/v1/rewards/quests/{id}/complete` | Manual quest completion |
| GET | `/api/v1/rewards/shop` | Shop items with prices and availability |
| POST | `/api/v1/rewards/shop/{id}/redeem` | Redeem item with points |

### Profile Response
```json
{
  "success": true,
  "data": {
    "xp": 125,
    "level": 2,
    "xp_for_next": 200,
    "xp_progress": 0.63,
    "current_streak": 3,
    "longest_streak": 7,
    "points_balance": 450,
    "quests_completed": 5
  }
}
```

### Quest Auto-Completion (v6.5.0)
These quests fire automatically — no manual trigger needed:

| Quest | Trigger | Repeatable |
|-------|---------|------------|
| `daily-login` | User logs in | Yes (daily) |
| `first-payment` | First money transfer | No |
| `daily-transaction` | Any money transfer | Yes (daily) |
| `first-shield` | First privacy proof | No |
| `first-card` | First card provisioned | No |
| `complete-profile` | Manual (user completes profile) | No |

### Error Codes
- `QUEST_NOT_FOUND` (422)
- `QUEST_ALREADY_COMPLETED` (422)
- `ITEM_NOT_FOUND` (422)
- `ITEM_OUT_OF_STOCK` (422)
- `INSUFFICIENT_POINTS` (422)

---

## Fiat Ramp (Stripe Bridge — Ready Now)

### Endpoints

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/ramp/supported` | Supported currencies and limits |
| GET | `/api/v1/ramp/quotes?type=on&fiat=EUR&amount=100&crypto=USDC` | Get quotes |
| POST | `/api/v1/ramp/session` | Create buy/sell session |
| GET | `/api/v1/ramp/session/{id}` | Check session status |
| GET | `/api/v1/ramp/sessions` | List user's sessions |

### Create Session Request
```json
{
  "type": "on",
  "fiat_currency": "EUR",
  "fiat_amount": 100,
  "crypto_currency": "USDC",
  "wallet_address": "0x...",
  "quote_id": "optional-quote-id"
}
```

### Session Response
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "status": "pending",
    "checkout_url": "https://checkout.stripe.com/...",
    "amount": 100,
    "currency": "EUR"
  }
}
```

**Flow**: Create session → open `checkout_url` in WebView → poll status until `completed`/`failed`.

### Status values: `pending`, `processing`, `completed`, `failed`

---

## Solana-Specific Notes

- **Networks**: `SOLANA` is in `MOBILE_PAYMENT_NETWORKS`
- **USDC address**: `EPjFWdd5AufqSSqeM2qN1xzybapC8G4wEGGkZwyTDt1v`
- **Address format**: Base58, 32-44 chars
- **No gas sponsoring** — Solana fees ~$0.001/tx (negligible)
- **No privacy shielding** — RAILGUN is EVM-only. Hide Shield/Unshield when Solana selected
- **Explorer**: `https://solscan.io/tx/{signature}`
- **Confirmations**: 32

---

## Device Attestation (v6.5.0)

### iOS (App Attest)
Send attestation in the biometric JWT request:
```json
{
  "attestation": "<base64-cbor-attestation-from-DCAppAttestService>",
  "device_type": "ios"
}
```

### Android (Play Integrity)
```json
{
  "attestation": "<integrity-token-from-IntegrityManager>",
  "device_type": "android"
}
```

When `MOBILE_ATTESTATION_ENABLED=false` (current), the backend accepts any non-empty attestation. When enabled, real Apple/Google verification runs.

---

## Legal Disclaimer (Must Add to App)

In Settings > About/Legal, display:

> Zelta is a technology platform that provides a user interface enabling access to services offered by independent third-party providers. Zelta does not offer, hold, or transmit funds, crypto-assets, or provide any financial, custodial, or regulated services.
>
> All wallet functionality is powered by Privy embedded wallets — a passkey-controlled smart account on EVM (Polygon, Base, Arbitrum, Ethereum) and a device-bound ed25519 keypair on Solana. Wallets are created and controlled solely by users. Private keys never leave the device or Privy's secure infrastructure; the backend only ever sees public addresses and pre-signed payloads.
>
> Any financial or payment-related services are provided solely by third-party licensed financial service providers operating under their own regulatory authorizations.
>
> The user is responsible for safeguarding their passkeys and any device-bound credentials used to authorize transactions. If passkey enrollments are lost across all of the user's devices, account access may not be recoverable.
>
> All forms of investments carry risks, including the risk of losing all of the invested amount.

---

## MPP / HyperSwitch Endpoints (Agent Dashboard)

If displaying agent payment info:

| Method | Path | Description |
|--------|------|-------------|
| GET | `/api/v1/mpp/status` | MPP protocol status |
| GET | `/api/v1/mpp/supported-rails` | Available payment rails |
| GET | `/api/v1/mpp/payments/stats` | Payment statistics (auth required) |
