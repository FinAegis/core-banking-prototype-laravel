# FinAegis Platform Roadmap

**Last Updated:** 2026-02-21
**Current Version:** v5.1.5
**Domains:** 41 bounded contexts

---

## Vision

**FinAegis** is a comprehensive banking platform demonstrating advanced financial architecture patterns. Built with Domain-Driven Design, Event Sourcing, and CQRS, it serves as an educational resource and foundation for modern banking system design.

---

## Platform Overview

### Key Capabilities

| Capability | Description |
|------------|-------------|
| **Event Sourcing v2** | Domain-specific event tables, migration tooling, upcasting, event streaming via Redis Streams |
| **GraphQL API** | Lighthouse PHP with 33 domain schemas, subscriptions, DataLoaders |
| **Plugin Marketplace** | Plugin manager, sandbox, security scanner with example plugins |
| **CQRS** | Command/Query Bus with read/write separation across all domains |
| **Multi-Tenancy** | Team-based isolation (stancl/tenancy v3.9) |
| **Event Streaming** | Redis Streams-based real-time event distribution with live dashboard |
| **41 DDD Domains** | Comprehensive bounded contexts covering banking, trading, compliance, DeFi, and more |

### Domain Architecture (41 Domains)

| Category | Domains |
|----------|---------|
| **Core** | Shared, Account, User, Compliance |
| **Financial** | Exchange, Lending, Treasury, Stablecoin, Wallet, Payment, Banking |
| **Blockchain & Web3** | CrossChain, DeFi, Relayer, Commerce, TrustCert, Privacy, KeyManagement |
| **Mobile** | Mobile, MobilePayment |
| **AI & Agents** | AI, AgentProtocol |
| **Compliance & Regulation** | RegTech, Regulatory, Fraud, Security |
| **Infrastructure** | Monitoring, Batch, Webhook, Performance |
| **Business** | Governance, Cgo, Basket, Asset, Custodian, FinancialInstitution, CardIssuance |
| **Engagement** | Activity, Contact, Newsletter, Product |

---

## Release History

### v1.x -- Foundational Architecture
- Multi-asset ledger core, custodian abstraction layer
- Event sourcing with CQRS and saga pattern
- Governance and polling engine, admin dashboard (Filament)
- Bank integration patterns (Paysera, Deutsche Bank, Santander mocks)
- GCU concept demonstration with currency basket voting

### v2.0.0 -- Multi-Tenancy
- Team-based multi-tenancy via stancl/tenancy v3.9

### v2.1.0 -- Security Hardening
- Hardware wallets, WebSocket support, Kubernetes readiness

### v2.2.0 -- Mobile Backend
- Device management, biometrics, push notifications

### v2.3.0 -- AI & RegTech Foundation
- AI framework, RegTech foundation, BaaS configuration

### v2.4.0 -- Privacy & Identity
- Key management (Shamir's Secret Sharing, HSM)
- Privacy layer (ZK-KYC, Proof of Innocence)
- Commerce (Soulbound Tokens, merchants, attestations)
- TrustCert (W3C Verifiable Credentials, Certificate Authority)

### v2.5.0 -- Mobile App Launch
- Expo/React Native mobile application (separate repo)

### v2.6.0 -- Privacy Layer & ERC-4337
- Merkle trees, smart accounts, delegated proofs
- UserOp signing with biometric JWT, gas station

### v2.7.0 -- Mobile Payment API
- Passkey auth, P2P transfer helpers, TrustCert export, security hardening

### v2.8.0 -- AI & RegTech Maturity
- AI query endpoints, RegTech adapters, MiFID II/MiCA/Travel Rule services

### v2.9.0 -- BaaS & Production Hardening
- BaaS implementation, SDK generation, production hardening

### v2.10.0 -- Mobile API Compatibility
- Wallet, TrustCert, Commerce, Relayer mobile endpoints

### v3.0.0 -- Cross-Chain & DeFi
- Bridge protocols (Wormhole, LayerZero, Axelar)
- DeFi connectors (Uniswap, Aave, Curve, Lido)
- Cross-chain swaps, multi-chain portfolio

### v3.1.0 -- Documentation & UI Completeness
- Swagger annotations, 7 feature pages, 15 Filament admin resources
- 4 user-facing views, developer portal update

### v3.2.0 -- Production Readiness & Plugin Architecture
- Module manifests, enable/disable, modular routes
- Module admin API/UI, k6 load tests, query performance middleware

### v3.3.0 -- Event Store Optimization & Observability
- Event replay/rebuild/stats/cleanup commands
- Observability dashboards, structured logging, deep health checks, event archival/compaction

### v3.4.0 -- API Maturity & DX
- API versioning, tier-aware rate limiting, SDK generation
- OpenAPI annotations (143+ endpoints)

### v3.5.0 -- Compliance Certification
- SOC 2 Type II, PCI DSS readiness, multi-region deployment
- GDPR enhanced (ROPA, DPIA, breach notification, consent v2, retention)

### v4.0.0 -- Architecture Evolution
- Event Store v2 (domain routing, migration tooling, upcasting)
- GraphQL API (Lighthouse PHP, 33 domain schemas, subscriptions, DataLoaders)
- Plugin Marketplace (manager, sandbox, security scanner)

### v4.1.0 -- GraphQL Expansion
- 6 new GraphQL domains (Treasury, Payment, Lending, Stablecoin, CrossChain, DeFi)

### v4.2.0 -- Real-time Subscriptions + Plugin Hooks
- GraphQL subscriptions, plugin hook system, webhook/audit plugins

### v4.3.0 -- Developer Experience + Security Hardening
- 4 new GraphQL domains, CLI commands, GraphQL security hardening

### v5.0.0 -- Event Streaming & Live Dashboard
- Redis Streams-based event streaming for real-time distribution
- Live monitoring dashboard with 5 endpoints
- Notification service, enhanced observability

### v5.0.1 -- Platform Hardening
- GraphQL CQRS alignment (21 mutations), OpenAPI 100% coverage
- Plugin Marketplace UI, PHP 8.4 CI, 97 test conversions, documentation refresh

### v5.1.0 -- Mobile API Completeness & GraphQL Full Coverage
- 21 missing mobile API endpoints (Privacy, Commerce, Card Issuance, Mobile, Wallet)
- GraphQL schemas for 9 remaining domains (completing 33-domain coverage)
- BlockchainAddress/BlockchainTransaction Eloquent models
- CI hardening, axios CVE fix, 42 new feature tests

### v5.1.1 -- Mobile App Landing Page
- `/app` teaser page with email signup and feature showcase
- Flaky Azure HSM test fix

### v5.1.2 -- Production Landing Page Fix
- Standalone pre-compiled CSS for `/app` (CSP-compliant, Vite-independent)

### v5.1.3 -- Mobile API Compatibility
- Optional `owner_address` for smart account onboarding
- Auth response standardization, token refresh/logout-all endpoints
- Rate limiter crash fix for unknown transaction types

### v5.1.4 -- Refresh Token Mechanism
- Access/refresh token pairs with rotation via Sanctum abilities
- `POST /api/auth/refresh` on public route, PHPStan fix, OpenAPI update

### v5.1.5 -- Dependency Cleanup & Production Readiness (Current)
- Upgrade l5-swagger 9 to 10 (swagger-php 5 to 6, modern architecture)
- Fix PSR-4 autoloading for plugin directories
- Production environment template (`.env.production.example`)
- Card API enhancements: network selection, labels, transactions, biometric cancel

---

## Future Roadmap

### v5.2.0 -- OpenAPI Attribute Migration (Planned)
- Migrate 10,000+ `@OA\` docblock annotations to PHP 8 `#[OA\]` attributes
- Drop `doctrine/annotations` dependency entirely
- Laravel 13 upgrade when available, PHP 8.5 features

---

## Development Principles

1. **Backward Compatibility**: All changes maintain existing functionality
2. **Test-Driven Development**: Write tests before implementation
3. **Documentation First**: Update docs before coding
4. **Incremental Delivery**: Ship small, working increments
5. **Performance Focus**: Maintain sub-100ms response times

---

## Educational Value

### Architecture Patterns Demonstrated
- **Event Sourcing**: Complete audit trail architecture with domain-specific stores
- **CQRS**: Full command/query separation with read models
- **DDD**: 41 bounded contexts with proper domain isolation
- **Saga Pattern**: Compensatable workflows for distributed transactions
- **Plugin Architecture**: Sandboxed plugin execution with security scanning
- **GraphQL**: Type-safe API with subscriptions and DataLoaders
- **Event Streaming**: Real-time event distribution via Redis Streams

---

**This roadmap reflects the technical patterns and architecture implemented in the FinAegis platform, serving as an educational resource and foundation for understanding modern banking system design.**
