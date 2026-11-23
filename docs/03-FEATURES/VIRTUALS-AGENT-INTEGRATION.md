# Virtuals Protocol Agent Integration

**Domain:** `app/Domain/VirtualsAgent/`
**Status:** Added in v6.3.0. `VIRTUALS_AGENT_ENABLED` defaults to `false`, but no code reads it at present. The API routes load whenever the VirtualsAgent module is enabled.
**Depends on:** AgentProtocol, X402, VisaCli, CardIssuance, TrustCert, Relayer (Pimlico)

Virtuals Protocol is a third-party platform for autonomous AI agents. This module is an integration adapter for its Agent Commerce Protocol (ACP). No partnership or endorsement is implied.

## Business Case

This section is the business case written when the module was designed in v6.3.0. None of the revenue streams below is live.

### Problem

AI agents on Virtuals Protocol can trade crypto on Base.
They cannot buy ordinary services such as cloud hosting, software subscriptions or APIs.
They have no identity attestation that others can check.
Their budgets are not enforced, and the person who runs them cannot easily audit their spending.

### Solution

The module gives each agent a profile linked to the person who employs it.
It gives the agent a TrustCert subject id that links the agent to that person.
It sets a daily and a per-transaction spending limit for the agent, stored as a Visa CLI spending limit.
It can connect the agent to card-issuing integration adapters, such as the Marqeta API.
Virtual cards are planned, to be issued by a licensed card-issuing partner. Availability is subject to partner approval and jurisdiction.

### Monetization
| Revenue Stream | Mechanism | Existing Infrastructure |
|---|---|---|
| Agent setup fee | Per-agent TrustCert issuance | TrustCert domain |
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
    ├── Execute_Payment           → x402 payment flow (HTTP 402 + payment header), or the visacli.payment tool
    ├── Request_Card              → POST /api/v1/cards
    ├── Get_Agent_Identity        → GET  /api/v1/trustcert/current
    └── List_Transactions         → GET  /api/v1/virtuals-agents/{id}/transactions
```

The diagram shows the planned mapping of the ACP functions. No sidecar is part of this repository.
Onboarding stores the agent's limit as a Visa CLI spending limit, so the x402 spending-limit endpoint does not return it.

### Domain Structure

```
app/Domain/VirtualsAgent/
  DataObjects/      AgentOnboardingRequest, AgentTokenBalance, AgdpMetrics
  Enums/            AgentStatus (registered, active, suspended, deactivated)
  Events/           VirtualsAgentRegistered, VirtualsAgentActivated, VirtualsAgentSuspended
  Models/           VirtualsAgentProfile (links Virtuals agent ID → user + limits)
  Routes/           api.php
  Services/         VirtualsAgentService (orchestrates ACP ↔ Zelta)
                    AgentOnboardingService (TrustCert + wallet + limits)
                    AgentSpendingEnforcementService (spending check, Pimlico bridge planned)
                    AcpServiceProviderBridge (maps ACP jobs to service categories)
                    AgentTokenTrackingService, AgdpReportingService
config/virtuals-agent.php
```

### REST endpoints

The routes are in `app/Domain/VirtualsAgent/Routes/api.php`. They need a Sanctum token and allow 60 requests a minute.

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/api/v1/virtuals-agents` | List the caller's agents |
| POST | `/api/v1/virtuals-agents/onboard` | Register an agent |
| GET | `/api/v1/virtuals-agents/agdp` | Agent activity metrics |
| GET | `/api/v1/virtuals-agents/{id}` | Show one agent |
| PUT | `/api/v1/virtuals-agents/{id}/suspend` | Suspend an agent |
| PUT | `/api/v1/virtuals-agents/{id}/activate` | Reactivate an agent |
| GET | `/api/v1/virtuals-agents/{id}/transactions` | The agent's transactions |

### Configuration

```env
VIRTUALS_AGENT_ENABLED=false
VIRTUALS_ACP_BASE_URL=https://api.virtuals.io
VIRTUALS_ACP_API_KEY=
VIRTUALS_AGENT_DAILY_LIMIT=50000        # $500.00/day in cents
VIRTUALS_AGENT_PER_TX_LIMIT=10000       # $100.00/tx in cents
VIRTUALS_AGENT_MAX_DAILY_LIMIT=10000000
VIRTUALS_AGENT_MAX_PER_TX_LIMIT=1000000
VIRTUALS_AGENT_AUTO_PROVISION_CARD=false
```

### TrustCert Agent Subject Extension

Current: `subjectId = 'user:' . $user->id`
Extended: `subjectId = 'agent:' . $agentId . ':employer:' . $user->id`

This links each agent to the account of the person who employs it.
Each agent still has its own identity for audit.
`AgentOnboardingService` and `MobileTrustCertController` both build this subject id.

Zelta Trust levels and on-chain attestations are in-app features. They are not a regulated identity-verification or KYC service, not an eIDAS electronic identification or trust service, and are not intended to satisfy any third party's legal KYC or AML obligations.

### On-Chain Spending Enforcement via Pimlico

Today the spending limits are enforced in the database with row locks.
`AgentSpendingEnforcementService::evaluateAgentUserOp()` checks an agent's limit before a sponsored operation.
`getEnforcementPolicy()` returns the policy values that an on-chain session key would use.

On-chain enforcement through Pimlico is planned and not built yet. The bundler and paymaster are injected but unused.
The planned design has four steps.

1. The agent's smart account is deployed through Pimlico (ERC-4337).
2. A session key module limits the agent's operations to approved targets and a maximum value.
3. The paymaster only sponsors operations inside the spending policy.
4. The bundler rejects an operation that breaks the policy before it reaches the chain.

### Filament Admin: Agent Dashboard

The Filament resource is `App\Filament\Admin\Resources\VirtualsAgentResource` at `/admin/virtuals-agents`.
It lists the registered agents with their status, employer, chain, linked spending limit and card.
Staff can view, create and edit agent profiles there, and suspend or reactivate agents.

## Implementation Phases

The phases below are the original plan.
Phases 1 and 2 are built.
Phase 3 is built as `VirtualsAgentResource`, without the transaction history widget and without editing spending limits from the dashboard.
Phase 4 is partly built. The spending check exists, but the Pimlico session key enforcement does not.

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
Virtual cards are planned, to be issued by a licensed card-issuing partner. Availability is subject to partner approval and jurisdiction. Integration adapters for third-party APIs; no partnership or endorsement implied.

| Issuer | Status | Use Case |
|--------|--------|----------|
| Marqeta | Adapter (API integration) | — |
| Rain | Adapter (API integration) | — |
| FinCard | Adapter (API integration) | — |
| Lithic | Not implemented (config placeholder) | — |
| Stripe Issuing | Not implemented (config placeholder) | — |

The table lists the `CardIssuerInterface` adapters selected by `CARD_ISSUER`, which defaults to `demo`.
v7.18.0 added a separate provider-neutral card layer in `app/Domain/CardIssuance/Providers/`, with Lepo Finance as its first provider for a closed pilot.
Cards stay off until `CARD_PROVIDERS_ENABLED` names a provider, and the agent flow does not use that layer yet.

## Security Considerations
The daily and per-transaction limits are enforced in the database with row locks.
The TrustCert subject links each agent to the person who employs it, for audit.
Cards are not created automatically for agents while `VIRTUALS_AGENT_AUTO_PROVISION_CARD=false`.
Agent registration, activation and suspension are stored as events, which gives an audit trail.
Visa CLI payment URLs are checked against internal and cloud metadata addresses.
