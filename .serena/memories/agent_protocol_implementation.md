# Agent Protocol (AP2 & A2A) Implementation

## Overview
Started implementation of Agent Protocols (AP2 & A2A) for AI agent commerce on September 17, 2024. This enables autonomous agents to conduct financial transactions and interact with the FinAegis platform.

## Completed Components (Phase 1)

### Domain Structure
- **AgentProtocol Domain** (`app/Domain/AgentProtocol/`)
  - Event sourcing with dedicated tables (`agent_protocol_events`, `agent_protocol_snapshots`)
  - Repository pattern implementation
  - Events registered in `config/event-sourcing.php`

### Core Aggregates
1. **AgentIdentityAggregate**
   - DID (Decentralized Identifier) support
   - Capability advertisement system
   - Wallet management
   - Reputation scoring (starting at 50.0)
   - Status tracking (active/inactive)

2. **AgentWalletAggregate**
   - Dedicated payment accounts for agents
   - Balance management (available/held/total)
   - Transaction initiation and completion
   - Payment sending/receiving
   - Transaction limits enforcement

### Services
1. **DIDService**
   - DID generation with format: `did:finaegis:{method}:{identifier}`
   - DID validation and resolution
   - DID document creation and storage
   - Support for methods: key, web, agent
   - Base58 encoding for public keys
   - Caching layer for performance

2. **DiscoveryService**
   - AP2 configuration endpoint support
   - Agent discovery by capability
   - Agent search by DID
   - Capability matching algorithm
   - Service endpoint management
   - Cache-optimized queries

### Events
- `AgentRegistered`: New agent registration
- `CapabilityAdvertised`: Capability advertisement
- `AgentWalletCreated`: Wallet creation
- `AgentTransactionInitiated`: Transaction start
- `PaymentSent`: Outgoing payment
- `PaymentReceived`: Incoming payment
- `WalletBalanceUpdated`: Balance changes

### Testing
- Comprehensive test coverage (31 tests, 139 assertions)
- All tests passing
- Coverage includes aggregates and services

## Next Steps (Phase 2)

### Payment Infrastructure
- Escrow service implementation
- Split payment mechanisms
- Payment orchestration workflows

### A2A Messaging
- Message bus with Laravel Horizon
- Protocol negotiation
- Agent authentication (OAuth 2.0)

### Trust & Security
- Reputation system enhancement
- Digital signatures
- Agent-specific fraud detection
- KYC/AML for agents

### API Implementation
- REST endpoints for AP2/A2A
- OpenAPI documentation
- Webhook support

## Technical Notes

### Event Sourcing Configuration
All Agent Protocol events are registered in `config/event-sourcing.php` with aliases:
- `agent_registered`
- `capability_advertised`
- `agent_wallet_created`
- `agent_transaction_initiated`
- `payment_sent`
- `payment_received`
- `wallet_balance_updated`

### DID Format
```
did:finaegis:{method}:{32-char-hex-identifier}
```
Example: `did:finaegis:key:a1b2c3d4e5f6789012345678901234567`

### Capability Format
Capabilities use dot notation:
- `payment.transfer`
- `payment.escrow`
- `messaging.a2a`
- `discovery.search`

### Default Limits
- Daily transaction: $100,000
- Per transaction: $10,000
- Daily withdrawal: $50,000

## Integration Points
- Payment Domain: For transaction processing
- Wallet Domain: For blockchain integration
- Compliance Domain: For KYC/AML
- AI Domain: For agent framework integration
- Treasury Domain: For fund management