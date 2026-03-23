# Virtuals Protocol Agent Integration

**Domain:** `app/Domain/VirtualsAgent/`
**Status:** v6.3.0
**Depends on:** AgentProtocol, X402, VisaCli, CardIssuance, TrustCert, Relayer (Pimlico)

## Business Case

### Problem
Autonomous AI agents on Virtuals Protocol can trade crypto on Base, but cannot:
- Legally purchase real-world services (AWS, SaaS, APIs)
- Hold a compliant identity for merchant acceptance
- Be constrained by enforceable spending budgets
- Have their financial activity audited by a human employer

### Solution
Zelta acts as the **compliant spending bridge** for Virtuals agents: identity via TrustCert, spending limits via X402/Pimlico, real-world purchasing via Marqeta/Rain cards, privacy via ZK shields.

### Monetization
| Revenue Stream | Mechanism | Existing Infrastructure |
|---|---|---|
| Agent setup fee | Per-agent TrustCert issuance | TrustCert domain |
| Card interchange | % of agent card spend | CardIssuance (Marqeta, Rain) |
| BaaS subscription | $99-$1,999/mo partner tiers | PartnerBillingService |
| X402 facilitation | Micro-fee on agent payments | X402 domain |

## Architecture

### Integration Points

```
Virtuals G.A.M.E. Engine
    │
    ▼
ACP Custom Functions (Node.js sidecar or HTTP)
    │
    ├── Check_Spending_Allowance  → GET  /api/v1/x402/spending-limits/{agentId}
    ├── Execute_Payment           → POST /api/v1/x402/payments (or visacli.payment MCP tool)
    ├── Request_Card              → POST /api/v1/cards/create
    ├── Get_Agent_Identity        → GET  /api/v1/trustcert/current
    └── List_Transactions         → GET  /api/v1/wallet/transactions
```

### Domain Structure

```
app/Domain/VirtualsAgent/
  Config/           virtuals-agent.php
  Contracts/        VirtualsAgentClientInterface
  Services/         VirtualsAgentService (orchestrates ACP ↔ Zelta)
                    AgentOnboardingService (TrustCert + wallet + limits)
                    AgentSpendingEnforcementService (X402 + Pimlico bridge)
  DataObjects/      AgentProfile, AgentOnboardingRequest
  Enums/            AgentStatus (registered, active, suspended, deactivated)
  Events/           AgentRegistered, AgentActivated, AgentSuspended
  Models/           VirtualsAgentProfile (links Virtuals agent ID → user + limits)
```

### TrustCert Agent Subject Extension

Current: `subjectId = 'user:' . $user->id`
Extended: `subjectId = 'agent:' . $agentId . ':employer:' . $user->id`

This lets a human employer's KYC umbrella cover their AI agents while maintaining distinct identity per agent for audit.

### On-Chain Spending Enforcement via Pimlico

X402 spending limits are database-enforced (row locks). For agents requiring trustless enforcement:

1. Agent's smart account is deployed via Pimlico ERC-4337
2. A session key module limits the agent's UserOp to approved targets + max value
3. The paymaster sponsors only UserOps within the spending policy
4. If the agent exceeds policy, the bundler rejects the UserOp before it hits chain

This gives cryptographic enforcement without smart contract development — Pimlico's session key infrastructure handles it.

### Filament Admin: Agent Dashboard

New Filament resource: `VirtualsAgentProfileResource`
- List all registered agents with status, employer, spending used/remaining
- View agent transaction history
- Suspend/reactivate agents
- Edit spending limits
- View linked TrustCert and cards

## Implementation Phases

### Phase 1: Domain Foundation + Agent Onboarding
- VirtualsAgentProfile model + migration
- AgentOnboardingService (creates profile, extends TrustCert, sets X402 limits)
- Config file
- Service provider

### Phase 2: TrustCert Agent Subject Extension
- Modify MobileTrustCertController to accept `agent:X:employer:Y` format
- Add agent-aware certificate lookup

### Phase 3: Filament Agent Dashboard
- VirtualsAgentProfileResource with full CRUD
- Transaction history widget
- Spending limit management

### Phase 4: Pimlico Spending Enforcement Bridge
- AgentSpendingEnforcementService wrapping PimlicoBundlerService
- Session key policy for agent UserOps

## Card Issuer Support
| Issuer | Status | Use Case |
|--------|--------|----------|
| Marqeta | Integrated | Primary card issuer for US/EU agents |
| Rain | Integrated | Alternative issuer, crypto-native focus |
| Lithic | Adapter ready | Future option |
| Stripe Issuing | Adapter ready | Future option |

## Security Considerations
- Agent can never exceed X402 daily/per-tx limits (database + optional Pimlico enforcement)
- TrustCert links agent to human employer — regulatory accountability maintained
- Card provisioning requires employer approval (not agent-initiated)
- All agent financial events are event-sourced (immutable audit trail)
- SSRF prevention on all agent-initiated payment URLs
