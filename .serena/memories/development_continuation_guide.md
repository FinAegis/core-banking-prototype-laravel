# FinAegis Development Continuation Guide

> **Purpose**: Master handoff document for session continuity. **READ THIS FIRST** when resuming development.
> **Last Updated**: February 26, 2026 (Platform Hardening — Dependabot triage, CI reliability, IdempotencyMiddleware, E2E tests, multi-tenancy isolation, docs refresh)

---

## Quick Recovery Protocol

### First 3 Things to Do When Resuming
```bash
# 1. Check git state and open PRs
git status && git log --oneline -5
gh pr list --state open

# 2. Check current branch
git branch --show-current

# 3. Run quick health check
./vendor/bin/pest --parallel --stop-on-failure
```

### Current Session State (Update After Each Session)
| Item | Status |
|------|--------|
| Current Branch | `main` |
| Open PRs | None |
| Open Issues | None |
| Last Action | Platform Hardening (6 phases): Dependabot triage (#641), CI timeout fix (#654), IdempotencyMiddleware wiring (#655), E2E banking tests (#656), Multi-tenancy isolation tests (#657), Docs prototype→platform refresh |

---

## Architecture Quick Reference

### Domain Structure (41 domains)
```
app/Domain/
├── Account/        # Core accounts
├── AgentProtocol/  # AI agent payments (AP2 & A2A)
├── AI/             # AI Framework, MCP tools (v2.3.0)
├── Banking/        # SEPA, SWIFT connectors
├── Commerce/       # SBT, Merchants, Attestations (v2.4.0)
├── Compliance/     # KYC/AML
├── Custodian/      # Bank integrations, webhooks
├── Exchange/       # Trading engine
├── KeyManagement/  # Shamir's Secret Sharing, HSM (v2.4.0)
├── Lending/        # P2P lending
├── Mobile/         # Mobile wallet backend (v2.2.0)
├── Monitoring/     # Distributed tracing, metrics
├── Privacy/        # ZK-KYC, Merkle Trees, Delegated Proofs (v2.4.0+v2.6.0)
├── MobilePayment/  # Payment Intents, Receipts, Activity Feed (v2.7.0)
├── RegTech/        # MiFID II, MiCA, Travel Rule, Jurisdiction Adapters (v2.8.0)
├── Relayer/        # ERC-4337 Gas Abstraction, Smart Accounts (v2.6.0)
├── CrossChain/     # Bridge protocols (Wormhole/LayerZero/Axelar), cross-chain swaps (v3.0.0)
├── DeFi/           # DEX aggregation (Uniswap/Aave/Curve/Lido), flash loans (v3.0.0)
├── Stablecoin/     # Token lifecycle
├── Treasury/       # Portfolio, yield optimization
├── TrustCert/      # W3C VCs, Certificate Authority (v2.4.0)
├── Wallet/         # Blockchain wallets, HW wallets (v2.1.0)
└── ... (+ Batch, CGO, Fraud, Governance, FinancialInstitution/BaaS, etc.)
```

### Patterns
- **Event Sourcing**: Spatie v7.7+ with domain-specific tables, Event Store v2 (domain routing, upcasting)
- **CQRS**: Custom bus in `app/Infrastructure/`
- **Sagas**: Laravel Workflow with compensation
- **DDD**: Aggregates, Value Objects, Domain Events
- **GraphQL API**: Lighthouse-PHP, 33 domains (Account, AgentProtocol, AI, Asset, Banking, Basket, Batch, CardIssuance, Cgo, Commerce, Compliance, CrossChain, Custodian, DeFi, Exchange, FinancialInstitution, Fraud, Governance, KeyManagement, Lending, Mobile, MobilePayment, Payment, Privacy, Product, RegTech, Regulatory, Relayer, Stablecoin, Treasury, TrustCert, User, Wallet), subscriptions, DataLoaders
- **Event Streaming**: Redis Streams publisher/consumer for real-time event distribution
- **Plugin Marketplace**: PluginManager with semver dependency resolver, permission sandbox, security scanner
- **Live Dashboard**: 5 metrics endpoints for real-time system monitoring
- **Notification System**: 5 channels for alerts and notifications
- **API Gateway Middleware**: Centralized request routing, rate limiting, auth

### Stack
- PHP 8.4+ / Laravel 12
- MySQL 8.0 / Redis (+ Redis Streams for event streaming)
- Pest PHP (849+ test files, 3099+ assertions) / PHPStan Level 8
- Filament 3.0 / Livewire
- Lighthouse-PHP (GraphQL)

---

## Memory Hierarchy

### Tier 1: Read First (This Document)
- `development_continuation_guide` ← YOU ARE HERE

### Tier 2: Reference When Needed
- `project_architecture_overview` - Deep architecture
- `task_completion_checklist` - Quality workflow
- `version_roadmap_decisions` - Strategic rationale

### Tier 3: Historical (Feature-Specific)
- `v2.6.0_privacy_relayer_implementation` - v2.6.0 Privacy & Relayer details
- `v2.2.0_mobile_backend_implementation` - Mobile backend details (consolidated with planning)
- `ai_framework_consolidated` - AI implementation history
- `treasury_management_implementation` - Treasury history
- `agent_protocol_implementation` - Agent protocol details
- Date-specific memories - Point-in-time fixes

### When to Update This Memory
- ✅ After each session (update "Current Session State")
- ✅ After completing major features
- ✅ After discovering reusable patterns
- ✅ After version releases
