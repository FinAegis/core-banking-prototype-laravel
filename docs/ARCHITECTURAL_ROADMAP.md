# FinAegis Core Banking Platform - Architectural Roadmap

## Vision Statement

FinAegis is an open-source reference platform for core banking software. It is not a production bank. The roadmap aims for a platform that:
- Provides open-source reference core banking software
- Demonstrates best practices with the GCU (Global Currency Unit) reference implementation
- Enables financial institutions to build custom digital banking solutions
- Includes open-source compliance tooling (KYC/AML modules); using it does not make a deployment compliant
- Offers cross-chain DeFi, privacy-preserving identity, and Banking-as-a-Service capabilities as reference modules

Zelta is the hosted non-custodial wallet product built on FinAegis. The production site runs the Zelta wallet scope and switches the other modules off.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

---

## Current Architecture Assessment (v7.18.1)

### Where each area runs

The platform has many modules, but only part of them runs on the production site. The standards modules, such as ISO 20022, ISO 8583, Open Banking, the payment rails, Interledger, Ledger and Microfinance, work at the message layer and in demo mode. They are not connected to any bank, card scheme or regulator. Production switches them off with `MODULES_DISABLED`, and trading stays off with `TRADING_ENABLED=false`.

| Area | Domains | Production (Zelta) |
|------|---------|--------------------|
| Wallet and payments | Wallet, Mobile, MobilePayment, Relayer, Payment, X402, MachinePay, Commerce, Rewards, Referral | Runs. Wallet send is non-custodial. x402 is on (`X402_ENABLED=true`); MPP is off (`MPP_ENABLED` defaults to `false`). |
| Fiat on-ramp | Ramp | Runs with `RAMP_PROVIDER=bridge` (bank transfer in, USDC out). |
| Subscriptions and pricing | Subscription, Pricing | Runs (App Store and Google Play subscriptions). |
| Cards | CardIssuance | Built for a closed pilot with Lepo Finance. Production lists Lepo in `CARD_PROVIDERS_ENABLED` but offers cards only to listed pilot users (`CARD_PROVIDER_LEPO_PILOT_ONLY=true`). Top-ups stay off (`CARD_TOPUPS_ENABLED=false`), and the older card issuer adapter stays on `CARD_ISSUER=demo`. |
| Privacy | Privacy | Backend groundwork for the non-custodial model. The custodial endpoints return `501` in production. |
| Identity and compliance | Compliance, TrustCert, Fraud, RegTech, Regulatory | Runs as readiness tooling. No certification is held. |
| Agents and AI | AI, AgentProtocol, MCP, VirtualsAgent, VisaCli, SMS | The MCP server at `mcp.zelta.app` runs. Its catalog has 14 tools, and production switches off `account.create`, `payment.transfer`, `exchange.trade` and `sms.send`. SMS is off (`SMS_ENABLED=false`). |
| Core banking and trading | Account, Banking, Asset, Product, Exchange, Basket (GCU demo), Treasury, Lending, Custodian, Stablecoin, Governance, Cgo | Reference and demo only. Most are in `MODULES_DISABLED`, and trading is off. |
| Standards modules | ISO20022, ISO8583, OpenBanking, PaymentRails, Interledger, Ledger, Microfinance | Message layer and demo only. Disabled in production. |
| Cross-chain and DeFi | CrossChain, DeFi | Reference and demo only. Disabled in production. |
| Banking-as-a-Service | FinancialInstitution | Reference and demo only. Disabled in production. |
| Platform | Auth, User, AccountProvisioning, KeyManagement, Security, Monitoring, Performance, Webhook, Activity, Batch, Contact, Newsletter, Shared | Supporting code used by the areas above. |

*Named third-party protocols and providers: integration adapters for third-party APIs; no partnership or endorsement implied.*

Asset transfers between accounts, wallet currency conversions and GCU buy and sell work since v7.18.1. Earlier releases stopped on a missing method before any balance moved.

### Infrastructure

The platform uses event sourcing (Spatie, with domain-specific event tables) and a Command and Query Bus in `app/Infrastructure/`. It runs sagas and workflows for multi-step flows. It supports team-based multi-tenancy, Kubernetes deployment through a Helm chart, WebSockets through Soketi, Redis and MySQL. Demo mode replaces outside services with simulated ones.

### Domain Inventory (61 Bounded Contexts)

`app/Domain/` has 61 domain folders.

| Category | Domains |
|----------|---------|
| **Core Banking** | Account, Banking, Asset, Product, Ledger, Microfinance |
| **Trading** | Exchange, Basket (GCU demo) |
| **Compliance & RegTech** | Compliance, Fraud, Regulatory, RegTech |
| **Digital Assets** | Stablecoin, Wallet, Governance |
| **Cross-Chain & DeFi** | CrossChain, DeFi |
| **Privacy & Identity** | Privacy, KeyManagement, Commerce, TrustCert |
| **Mobile & Payments** | Mobile, MobilePayment, Relayer, Payment, X402, MachinePay, Ramp, Rewards, Referral |
| **Standards and payment rails** | ISO20022, ISO8583, OpenBanking, PaymentRails, Interledger |
| **Financial Services** | Treasury, Lending, Custodian, CardIssuance |
| **Commercial** | Subscription, Pricing |
| **Platform & AI** | AI, AgentProtocol, MCP, VirtualsAgent, VisaCli, SMS, Monitoring, Performance, Security |
| **BaaS** | FinancialInstitution |
| **Supporting** | Auth, User, AccountProvisioning, Contact, Newsletter, Webhook, Activity, Batch, Cgo, Shared |

The RegTech modules provide reporting and data exchange modelled on MiFID II, MiCA and Travel Rule requirements. They do not by themselves make a deployment compliant.

### Key Metrics (as of v7.18.1)

| Metric | Value |
|--------|-------|
| Bounded Contexts | 61 |
| Services (`*Service.php`) | 390+ |
| Controllers (`*Controller.php`) | 245 |
| API and web routes | 1,500+ |
| PHPStan Level | **8** (larastan 3.15, phpstan 2.3.7) |
| Test Files | about 1,300 |
| GraphQL domain schemas | 43 |
| GraphQL `#import` lines | 45 (43 domain schemas plus `directives.graphql` and `subscriptions.graphql`) |
| MCP tools | 14 |

---

## Strategic Roadmap

### Phase 1: Open Source Foundation
**Goal: Make the platform welcoming to contributors**

#### 1.1 Documentation Excellence
- [x] OpenAPI documentation for core endpoints
- [x] Create CONTRIBUTING.md with detailed workflow
- [x] Write Architecture Decision Records (ADRs) for key decisions
- [ ] Complete domain onboarding guides for each bounded context
- [ ] Finish OpenAPI documentation for **all** endpoints (v3.1.0 target: 90%+)
- [ ] Create video walkthroughs of key features

#### 1.2 Developer Experience
- [x] Code generation commands for new domains (`php artisan domain:create`)
- [x] Kubernetes deployment (Helm charts, HPA, Istio)
- [ ] Streamline local development setup (single command)
- [ ] Create development containers (devcontainer.json)
- [ ] Create interactive API playground

#### 1.3 Community Infrastructure
- [x] Define versioning and release strategy
- [x] CI/CD pipeline with GitHub Actions
- [x] Set up GitHub Discussions for Q&A
- [x] Create issue templates for bugs/features
- [ ] Establish code review guidelines

### Phase 2: Platform Modularity (completed in v1.3.0)
**Goal: Enable pick-and-choose domain installation**

- [x] Domain decoupling with interface-based contracts
- [x] Module manifest system (module.json per domain)
- [x] Domain installation commands (`php artisan domain:install`)
- [x] GCU reference separation

### Phase 3: GCU Reference Implementation (completed)
**Goal: Position GCU as the showcase of platform capabilities**

- [x] GCU basket framework with rebalancing
- [x] NAV calculation methodology
- [x] Multi-basket support on single platform

### Phase 4: Production Hardening (largely completed, v2.1.0 to v2.9.1)
**Goal: Give operators the tools to deploy and run the platform safely**

- [x] OWASP Top 10 automated security audit (`php artisan security:audit`)
- [x] Kubernetes Helm charts (v2.1.0)
- [x] CI/CD pipeline with GitHub Actions
- [x] Monitoring dashboards (Grafana) + alerting rules
- [x] Health check endpoints
- [x] HSM integration (AWS KMS, Azure Key Vault) (v2.9.1)
- [ ] Penetration testing
- [ ] GDPR compliance documentation
- [ ] PCI-DSS assessment guide

### Phase 5: Zelta Non-Custodial Wallet (v7.12.0 onwards)
**Goal: Run the hosted Zelta wallet on a non-custodial model**

- [x] Non-custodial wallet send through Privy, with prepare and submit endpoints (v7.12.0)
- [x] App Store and Google Play subscriptions (v7.13.0)
- [x] Bank transfer on-ramp through Bridge.xyz (v7.15.0)
- [x] Backend groundwork for non-custodial RAILGUN privacy (v7.16.0)
- [x] Security releases for advisories GHSA-385w-q6j4-5qw6 and GHSA-rrfq-93h6-29wj (v7.17.0, v7.17.1)
- [x] Virtual cards through Lepo Finance for a closed pilot, with wallet top-ups and 3-D Secure codes (v7.18.0; offered only to listed pilot users, with top-ups off)
- [ ] On-device RAILGUN engine in the mobile app, then removal of the custodial privacy bridge
- [ ] Move FinCard onto the provider-neutral card layer
- [ ] Bank transfer off-ramp

---

## Architecture Improvements

### Completed Improvements

#### Multi-Tenancy (v2.0.0)
- Team-based tenant isolation at database level
- Per-tenant configuration and branding
- Cross-tenant compliance boundaries
- 83 models scoped, 14 tenant migrations

#### Real-Time Infrastructure (v2.1.0+)
- WebSocket event streaming via Soketi
- Real-time order book updates
- Push notifications (FCM/APNS)

#### Privacy-Preserving Architecture (v2.4.0-v2.6.0)
- Zero-Knowledge KYC proofs
- Merkle tree privacy pools
- Delegated proof generation
- ERC-4337 account abstraction

Privacy is moving to a non-custodial model in which the device generates its own proofs. Delegated proof generation is switched off in production and will be removed in RAILGUN Phase 3.

#### Cross-Chain Architecture (v3.0.0)
- Multi-provider bridge orchestration
- DEX aggregation across protocols
- Multi-chain portfolio tracking
- Cross-chain yield optimization

The cross-chain and DeFi modules are reference code. They are disabled on the production site.

### Completed Improvements (v4.0.0-v5.0.0)

#### Event Store v2 (v4.0.0)
- Domain-specific routing (33 domains) with configurable event tables
- Upcasting pipeline for event schema evolution
- Migration tooling for event store upgrades

#### GraphQL API (v4.0.0-v5.0.0)
- Schema-first approach with Lighthouse PHP
- 33 domains exposed via GraphQL at the time (Account, AgentProtocol, AI, Asset, Banking, Basket, Batch, CardIssuance, Cgo, Commerce, Compliance, CrossChain, Custodian, DeFi, Exchange, FinancialInstitution, Fraud, Governance, KeyManagement, Lending, Mobile, MobilePayment, Payment, Privacy, Product, RegTech, Regulatory, Relayer, Stablecoin, Treasury, TrustCert, User, Wallet). There are 43 domain schemas at v7.18.1.
- Real-time subscriptions for live data
- DataLoaders for N+1 query prevention

#### Plugin Marketplace (v4.0.0)
- Plugin manager and loader architecture
- Sandbox execution environment for plugin isolation
- Security scanner for plugin vetting

#### Event Streaming (v5.0.0)
- Redis Streams publisher/consumer for real-time event distribution
- Live dashboard with 5 metrics endpoints
- Multi-channel notification system (email, push, in-app, webhook, SMS)

### Removed in v7.18.0 and v7.18.1

The exchange's add, remove and rebalance liquidity flow was removed in v7.18.0, because it never worked. Pool lists, pool creation, swaps, positions, rewards and impermanent-loss protection stay, all behind `TRADING_ENABLED`. The unused contact ticket service and the saved bank accounts on the bank withdrawal page were removed in the same release. Version 7.18.1 removed the admin Bank Operations and Audit Log pages, which could never work.

### Planned Improvements

#### CQRS Enhancement
```php
// Add async query caching
interface CachingQueryBus extends QueryBus
{
    public function query(Query $query, ?CacheStrategy $cache = null): mixed;
}
```

---

## Success Metrics

### Open Source Health
| Metric | Current (2026-11-24) | Target |
|--------|---------|--------|
| GitHub Stars | 212 | 1,000+ |
| Contributors | 3 | 20+ |
| Forks | 59 | 100+ |
| Documentation Coverage | 52% | 90% |

### Code Quality
| Metric | Current | Target |
|--------|---------|--------|
| Test Coverage | 50%+ | 80% |
| PHPStan Level | **8** | 8 (met) |
| Bounded Contexts | 61 | — |
| CI Pipeline Pass Rate | 99% | 99% (met) |

### Community Engagement
| Metric | Current | Target |
|--------|---------|--------|
| Issues Response Time | - | <24h |
| PR Review Time | - | <48h |
| Documentation Contributions | 0 | 50+ |
| Community Plugins | 0 | 10+ |

---

## Risk Assessment

### High Risk
1. **Regulatory compliance.** Financial software needs careful compliance work. The platform ships compliance documentation and reporting and data-exchange modules modelled on MiFID II, MiCA and Travel Rule requirements. They do not by themselves make a deployment compliant.

2. **Security vulnerabilities.** A banking platform is a valuable target for attackers. The platform runs an automated security audit and OWASP checks, and it supports HSM key storage and zero-knowledge KYC proofs.

### Medium Risk
3. **Complexity.** Domain-driven design, event sourcing and 61 domains make the code hard to learn. Good documentation, tutorials and the developer portal reduce that barrier.

4. **Maintenance burden.** Open-source software needs ongoing support. A sustainable community and a modular architecture spread that load.

### Low Risk
5. **Technology obsolescence.** The Laravel ecosystem is mature. Regular dependency updates keep the stack current (PHP 8.4, Laravel 12).

---

## Conclusion

FinAegis started as a core banking prototype. It now spans 61 domains and serves as an open-source reference platform. Its main capabilities are these:

1. **GraphQL API.** Lighthouse PHP serves 43 domain schemas with subscriptions and DataLoaders.
2. **Event streaming.** A Redis Streams publisher and consumer feed a live dashboard and multi-channel notifications.
3. **Plugin marketplace.** A plugin manager runs plugins in a sandbox and scans them for security issues.
4. **Cross-chain and DeFi.** Reference modules cover bridge protocols, DEX aggregation and multi-chain portfolios.
5. **Privacy and identity.** The platform has zero-knowledge KYC, Merkle trees, soulbound tokens and verifiable credentials.
6. **Mobile payments.** The platform has payment intents, passkeys, ERC-4337 gas sponsorship and non-custodial wallet send.
7. **RegTech.** Reporting and data-exchange modules are modelled on MiFID II, MiCA and Travel Rule requirements, with adapters for several jurisdictions.
8. **Banking-as-a-Service.** The platform has partner APIs, SDK generation and embeddable widgets.
9. **AI framework.** The platform has MCP tools, natural language queries and machine learning anomaly detection.

The current focus is the Zelta wallet. Version 7.18.0 added virtual cards through Lepo Finance for a closed pilot, wallet top-ups for those cards and 3-D Secure codes for online card payments. Cards are offered only to listed pilot users, and top-ups stay off. The same release keeps a wallet send in the submitted state when its outcome is unclear, instead of marking it failed. Version 7.18.1 fixed the card pilot's identity check and made asset transfers and currency conversions book the right amounts. Any card services will be provided by licensed third parties under their own terms. The named providers are integration adapters for third-party APIs; no partnership or endorsement implied.

---

*Document Version: 7.18.1*
*Last Updated: November 24, 2026*
*Author: Architecture Review*
