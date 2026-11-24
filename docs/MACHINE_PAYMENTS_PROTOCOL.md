# Machine Payments Protocol (MPP) Integration

## Overview

The Machine Payments Protocol (MPP) lets AI agents and services pay for a resource from code. A server that wants payment answers with HTTP 402, and the client pays and retries. x402 settles in USDC on EVM networks and Solana. MPP instead supports several payment rails. They are Stripe shared payment tokens (SPT) for fiat money, Tempo stablecoins, the Lightning Network and card network tokens.

FinAegis implements MPP in the `MachinePay` domain. It is off by default (`MPP_ENABLED=false`).

**Spec**: [paymentauth.org](https://paymentauth.org)
**Co-developed by**: Stripe + Tempo Labs

## How It Works

```
1. Client requests resource          →  GET /api/premium-data
2. Server returns 402 challenge      ←  WWW-Authenticate: Payment <base64url>
3. Client pays via selected rail     →  (Stripe/Tempo/Lightning/Card)
4. Client retries with credential    →  Authorization: Payment <base64url>
5. Server verifies + settles
6. Server returns resource           ←  200 OK + Payment-Receipt header
```

## Headers

| Header | Direction | Purpose |
|--------|-----------|---------|
| `WWW-Authenticate: Payment` | Server → Client | 402 challenge with pricing and available rails |
| `Authorization: Payment` | Client → Server | Payment credential with proof |
| `Payment-Receipt` | Server → Client | Settlement confirmation |

## Configuration

```env
MPP_ENABLED=false
MPP_CHALLENGE_HMAC_KEY=your-secret-key
MPP_STRIPE_API_KEY_ID=
MPP_CLIENT_ENABLED=false
MPP_DAILY_LIMIT=5000      # $50.00 daily
MPP_PER_TX_LIMIT=100       # $1.00 per tx
MPP_MCP_ENABLED=true
```

## Payment Rails

| Rail | Currency | Settlement | Status |
|------|----------|-----------|--------|
| **Stripe SPT** | USD, EUR, GBP | PaymentIntents API | Demo ready |
| **Tempo** | USDC, USDT | On-chain (chain 42431) | Demo ready |
| **Lightning** | BTC | BOLT11 preimage | Demo ready |
| **Card** | USD, EUR, GBP | JWE encrypted network tokens | Demo ready |

## MCP Transport Binding

MPP defines error code **-32042** as the MCP equivalent of HTTP 402:

```json
{
  "error": {
    "code": -32042,
    "message": "Payment Required",
    "data": { "challenge": "..." }
  }
}
```

## API Endpoints

| Method | Path | Auth | Description |
|--------|------|------|-------------|
| GET | `/api/v1/mpp/status` | Public | Protocol status |
| GET | `/api/v1/mpp/supported-rails` | Public | Available payment rails |
| GET | `/.well-known/mpp-configuration` | Public | Discovery document (on the `api.` and `mpp.` subdomains; `/api/.well-known/mpp-configuration` on the main domain) |
| GET | `/api/v1/mpp/resources` | Sanctum | List monetized resources |
| POST | `/api/v1/mpp/resources` | Sanctum | Create monetized resource |
| GET | `/api/v1/mpp/payments` | Sanctum | Payment history |
| GET | `/api/v1/mpp/payments/stats` | Sanctum | Payment statistics |
| GET | `/api/v1/mpp/spending-limits` | Sanctum | List agent spending limits |
| POST | `/api/v1/mpp/spending-limits` | Sanctum | Set agent spending limit |
| DELETE | `/api/v1/mpp/spending-limits/{agentId}` | Sanctum | Remove agent spending limit |

## MCP Tools

- `mpp.payment` handles a 402 challenge. It selects a rail and builds the payment credential. It is a tool of the internal AI framework.
- `mpp.discovery` finds MPP-enabled resources and their configuration. It is also one of the 14 tools on the public MCP server at `mcp.zelta.app`.

## Demo Mode

Outside production, all rail adapters return simulated responses. Testing needs no external API keys.

## Relationship to x402

MPP and x402 are separate protocols that solve the same problem.
- **x402** uses its own headers. It settles in USDC on EVM networks and Solana through a facilitator.
- **MPP** uses the standard HTTP authentication headers. It supports several rails for fiat money and crypto, and it binds each challenge with an HMAC.

AP2, the agent authorisation protocol from Google, can use either one as the payment method inside a mandate.
