# AP2 Mandates — Agent Payments Protocol

## Overview

AP2 (Agent Payments Protocol) is a protocol published by Google. It lets a user give an AI agent verifiable permission to buy or pay on the user's behalf. The protocol defines three mandate types. Each one binds agents, users and merchants together with cryptographic credentials.

FinAegis implements AP2 in the `AgentProtocol` domain. AP2 does not move money itself. It authorises a payment and then hands it to x402 or MPP, which carry the payment.

**Spec**: [github.com/google-agentic-commerce/AP2](https://github.com/google-agentic-commerce/AP2)
**Version**: v0.1-alpha

## Mandate Types

### Cart Mandate (Human-Present)
A cart mandate covers shopping where the user confirms the purchase.
- Cart items follow the W3C PaymentRequest format.
- The mandate binds the merchant's DID to the shopping agent's DID.
- A person must confirm the purchase before it runs.

### Intent Mandate (Human-Not-Present)
An intent mandate lets an agent act on its own within limits the user set.
- The user describes the intent in natural language.
- The mandate carries a budget and an expiry time.
- No person needs to be present when the agent acts.

### Payment Mandate (Direct Payment)
A payment mandate authorises a direct payment between two parties.
- The mandate binds the payer's DID to the payee's DID.
- It states the preferred payment method, x402 or MPP.
- It can be used once or for a session.

## Lifecycle

```
DRAFT → ISSUED → ACCEPTED → EXECUTED → COMPLETED
                    ↓            ↓
                 EXPIRED      DISPUTED
                    ↓            ↓
                 REVOKED      REVOKED
```

## Verifiable Digital Credentials (VDCs)

Each mandate can be backed by an SD-JWT-VC credential.
- A **Cart VDC** binds the merchant's cart contents to the user's approval.
- An **Intent VDC** binds the agent's intent to the approval of the person who delegated it.
- A **Payment VDC** binds the payment authorisation to the payer's identity.

## AP2 Roles

| Role | Description |
|------|-------------|
| Shopping Agent | AI that discovers products and builds carts |
| Credentials Provider | Secure entity managing payment credentials |
| Merchant Endpoint | Seller-side agent or web interface |
| Payment Processor | Processes payment via card networks or blockchain |

## Implementation

### Services
- `MandateService` creates, accepts, executes, revokes and disputes mandates.
- `VdcService` issues and verifies SD-JWT-VC credentials.
- `AP2PaymentBridgeService` passes an authorised mandate to x402 or MPP for payment.

### MCP Tools
These tools belong to the internal AI framework in `app/Domain/AI/MCP/`. They are not part of the 14 tools on the public MCP server at `mcp.zelta.app`.
- `agent_protocol.mandate` creates, accepts, executes, revokes and disputes mandates.
- `agent_protocol.vdc` issues and verifies Verifiable Digital Credentials.

### Discovery
The AP2 discovery document is served at `/.well-known/ap2-configuration` on the `api.` subdomain and at `/api/.well-known/ap2-configuration` on the main domain.

### A2A Message Types
The mandate lifecycle uses six A2A message types.
- `mandate.create`, `mandate.accept`, `mandate.execute`
- `mandate.complete`, `mandate.revoke`, `mandate.dispute`

## Payment Method Integration

AP2 uses x402 and MPP as its payment methods.
```php
AP2PaymentMethod::x402('eip155:8453')  // USDC on Base
AP2PaymentMethod::mpp('stripe')         // Stripe SPT via MPP
```
