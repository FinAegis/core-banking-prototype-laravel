# Multi-Protocol Payment Architecture

FinAegis supports three protocols for payments by AI agents. x402 and the Machine Payments Protocol (MPP) carry payments. The Agent Payments Protocol (AP2) authorises them and then hands the payment to x402 or MPP. See [MACHINE_PAYMENTS_PROTOCOL.md](MACHINE_PAYMENTS_PROTOCOL.md) and [AP2_MANDATES.md](AP2_MANDATES.md) for details.

## Protocol Comparison

| Feature | x402 | MPP | AP2 |
|---------|------|-----|-----|
| **Type** | Payment protocol | Payment protocol | Authorization protocol |
| **Maintained by** | Coinbase | Stripe + Tempo | Google |
| **HTTP mechanism** | Custom headers | Standard HTTP auth | A2A extension |
| **Payment rails** | USDC on EVM and Solana | Stripe, Tempo, Lightning, Card | Wraps x402 + MPP |
| **Fiat support** | No | Yes (Stripe, Card) | Via wrapped methods |
| **MCP binding** | No | Yes (-32042) | Via A2A |
| **Human presence** | Not required | Not required | Both modes |
| **Trust model** | On-chain verification | HMAC challenge binding | Verifiable Digital Credentials |

## Decision Tree

```
Agent needs to pay for a service?
├── Is it a crypto/USDC-only endpoint?
│   └── Use x402 (direct USDC settlement)
├── Does the endpoint accept fiat?
│   └── Use MPP with Stripe rail
├── Does the agent need user authorization?
│   ├── Human present? → AP2 Cart Mandate
│   └── Autonomous? → AP2 Intent Mandate
└── Direct agent-to-agent payment?
    └── AP2 Payment Mandate → x402 or MPP as rail
```

## Architecture

```
                    ┌─────────────┐
                    │   AP2       │  Authorization layer
                    │  Mandates   │  (Cart/Intent/Payment)
                    └──────┬──────┘
                           │
              ┌────────────┼────────────┐
              ▼            ▼            ▼
        ┌─────────┐  ┌─────────┐  ┌─────────┐
        │  x402   │  │   MPP   │  │  Fiat   │  Payment rails
        │  USDC   │  │ Stripe  │  │Transfer │
        └─────────┘  └─────────┘  └─────────┘
```

## Configuration

Each protocol can be switched on or off on its own. x402 and MPP are both off by default (`X402_ENABLED=false`, `MPP_ENABLED=false`). Both production templates, `.env.production.example` and `.env.zelta.example`, switch x402 on.

```env
# x402 (USDC payments)
X402_ENABLED=true

# MPP (multi-rail: Stripe, Tempo, Lightning, Card)
MPP_ENABLED=true

# AP2 mandates (via AgentProtocol domain)
# Always available when AgentProtocol is enabled
```
