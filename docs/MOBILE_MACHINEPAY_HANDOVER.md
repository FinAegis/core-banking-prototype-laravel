# Mobile Developer Handover — Machine Payments Protocol & AP2

## Summary

Backend v6.4.0 adds two new payment protocols alongside x402:
1. **MPP (Machine Payments Protocol)** — multi-rail fiat+crypto payments
2. **AP2 Mandates** — Google's agent authorization protocol

## What Mobile Needs to Know

### No Mobile Changes Required Yet

MPP is server-to-server/agent-to-service. The mobile app does not initiate MPP payments directly. However, the agent dashboard may want to display:

- Agent spending limits for MPP (alongside x402 limits)
- Payment history showing MPP transactions
- Supported payment rails

### Future: AP2 Cart Mandate Approval

When AP2 v1.0 ships human-present flows, mobile will need:
- Cart Mandate approval screen (merchant, items, total, expiry)
- Biometric confirmation for mandate signing
- Payment method selection (x402 USDC vs Stripe card)

### New API Endpoints

**MPP Status (Public)**
```
GET /api/v1/mpp/status
GET /api/v1/mpp/supported-rails
```

**MPP Payments (Authenticated)**
```
GET /api/v1/mpp/payments       → Payment history
GET /api/v1/mpp/payments/stats → Aggregate stats
GET /api/v1/mpp/spending-limits → Agent budgets
POST /api/v1/mpp/spending-limits → Set limits
```

### Solana x402 Support

x402 now supports Solana mainnet and devnet alongside EVM chains. If the mobile app displays supported networks, add:
- Solana Mainnet (`solana:mainnet`)
- Solana Devnet (`solana:devnet`)

### Legal Disclaimers (IMPORTANT)

The following key phrases must appear in the mobile app's Settings > About/Legal section:

1. **"Technology platform"** — NOT "wallet" or "financial service"
2. **"Non-custodial"** + **"keys under exclusive user control"**
3. **"Third-party licensed service providers"** for financial operations
4. **Recovery phrase responsibility disclaimer**

Example text for mobile Settings > Legal:

> Zelta is a technology platform that provides a user interface enabling access to services offered by independent third-party providers. Zelta does not offer, hold, or transmit funds, crypto-assets, or provide any financial, custodial, or regulated services.
>
> All wallet functionality is powered by Privy embedded wallets — a passkey-controlled smart account on EVM (Polygon, Base, Arbitrum, Ethereum) and a device-bound ed25519 keypair on Solana. Wallets are created and controlled solely by users. Private keys never leave the device or Privy's secure infrastructure; the backend only ever sees public addresses and pre-signed payloads.
>
> Any financial or payment-related services are provided solely by third-party licensed financial service providers operating under their own regulatory authorizations.
>
> The user is responsible for safeguarding their passkeys and any device-bound credentials used to authorize transactions. If passkey enrollments are lost across all of the user's devices, account access may not be recoverable.
>
> All forms of investments carry risks, including the risk of losing all of the invested amount. Such activities may not be suitable for everyone.
