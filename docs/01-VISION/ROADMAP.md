# FinAegis Platform Roadmap

**Last Updated:** 2026-11-24
**Current Version:** v7.18.1
**Domains:** 61 domain folders in `app/Domain/`

---

## Vision

FinAegis is an open-source reference platform for core banking software. It shows how Domain-Driven Design, event sourcing and CQRS can be used to build financial systems. It is not a production bank.

Zelta is the hosted non-custodial wallet product built on FinAegis. Many FinAegis modules, such as the banking standards, the exchange and the GCU demo, run in demo mode and are disabled on the production site.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

---

## Platform Overview

### Key Capabilities

| Capability | Description |
|------------|-------------|
| **Event Sourcing v2** | Domain-specific event tables, migration tooling, upcasting, event streaming via Redis Streams |
| **GraphQL API** | Lighthouse PHP with 43 domain schemas, subscriptions, DataLoaders |
| **Plugin Marketplace** | Plugin manager, sandbox, security scanner with example plugins |
| **CQRS** | Command/Query Bus with read/write separation |
| **Multi-Tenancy** | Team-based isolation (stancl/tenancy v3.10) |
| **Event Streaming** | Redis Streams-based real-time event distribution with live dashboard |
| **61 DDD Domains** | Bounded contexts covering banking, trading, compliance, DeFi, wallets, cards and more |

### Domain Architecture (61 Domains)

| Category | Domains |
|----------|---------|
| **Core** | Shared, Account, AccountProvisioning, User, Auth, Compliance |
| **Financial** | Exchange, Lending, Treasury, Stablecoin, Wallet, Payment, Banking, Ramp, Pricing, Subscription |
| **Blockchain & Web3** | CrossChain, DeFi, Relayer, Commerce, TrustCert, Privacy, KeyManagement |
| **Mobile** | Mobile, MobilePayment |
| **AI & Agents** | AI, AgentProtocol, MCP, VirtualsAgent |
| **Compliance & Regulation** | RegTech, Regulatory, Fraud, Security |
| **Infrastructure** | Monitoring, Batch, Webhook, Performance |
| **Payment Protocols** | X402, MachinePay, VisaCli, SMS |
| **Banking Standards (message layer, demo)** | ISO20022, ISO8583, OpenBanking, PaymentRails, Interledger, Ledger, Microfinance |
| **Business** | Governance, Cgo, Basket, Asset, Custodian, FinancialInstitution, CardIssuance |
| **Engagement** | Activity, Contact, Newsletter, Product, Referral, Rewards |

---

## Release History

*Named third-party providers below: integration adapters for third-party APIs; no partnership or endorsement implied.*

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
- AI query endpoints, RegTech adapters, reporting and data-exchange modules modelled on MiFID II, MiCA and Travel Rule requirements

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

### v3.2.0 -- Plugin Architecture
- Module manifests, enable/disable, modular routes
- Module admin API/UI, k6 load tests, query performance middleware

### v3.3.0 -- Event Store Optimization & Observability
- Event replay/rebuild/stats/cleanup commands
- Observability dashboards, structured logging, deep health checks, event archival/compaction

### v3.4.0 -- API Maturity & DX
- API versioning, tier-aware rate limiting, SDK generation
- OpenAPI annotations (143+ endpoints)

### v3.5.0 -- Compliance Readiness Tooling
- SOC 2 / PCI DSS readiness tooling (no certification is held), multi-region deployment
- GDPR enhanced (ROPA, DPIA, breach notification, consent v2, retention)

### v4.0.0 -- Architecture Evolution
- Event Store v2 (domain routing, migration tooling, upcasting)
- GraphQL API (Lighthouse PHP, 45 domain schemas, subscriptions, DataLoaders)
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

### v5.1.5 -- Dependency Cleanup
- Upgrade l5-swagger 9 to 10 (swagger-php 5 to 6, modern architecture)
- Fix PSR-4 autoloading for plugin directories
- Production environment template (`.env.production.example`)
- Card API enhancements: network selection, labels, transactions, biometric cancel

### v5.1.6 -- Security Hardening
- Copyright year updates, accessibility improvements
- CSP headers, email config defaults

### v5.2.0 -- X402 Protocol
- HTTP-native micropayments (USDC on Base)
- Payment gate middleware, facilitator integration
- AI agent payments, spending limits
- GraphQL/REST APIs, MCP tool

### v5.4.0 -- KYC, Sanctions-Screening & Card-Issuing Adapters
- Identity-verification integration adapter (e.g. Ondato API) with TrustCert linkage
- Sanctions-screening adapter (e.g. Chainalysis API), card-issuing integration adapter (e.g. Marqeta API)
- Firebase FCM v1 migration, X402/mobile test hardening, CVE patches

### v5.4.1 -- Platform Hardening
- Dependabot triage (PRs #642-#659)
- IdempotencyMiddleware, E2E tests
- Multi-tenancy isolation tests, docs refresh
- CI reliability improvements

---

### v5.5.0 to v7.10 -- Payment Protocols, Wallets and Distribution
- v5.5.0: ERC-4337 smart accounts through the Pimlico bundler and paymaster
- v5.6.0: RAILGUN privacy through a server-side bridge (being replaced by the non-custodial design from v7.16.0)
- v5.7.0: Rewards domain with quests, points and streaks
- v5.12.0 and v5.14.0: Fiat ramp aggregator, referrals, cached RPC calls and WebSocket balance events
- v6.0.0: Developer portal and public plugin marketplace
- v6.4.0 to v6.7.0: Machine Payments Protocol (MPP), SMS payments, the Zelta CLI and payment SDK, and the A2A agent protocol
- v7.8.0: Seven banking-standards domains that work at the message layer and in demo mode (ISO 20022, Open Banking, ISO 8583, US payment rails, Interledger, double-entry ledger, microfinance)
- v7.9.0 and v7.10: Solana wallet balances and history, push notifications, and per-user webhook endpoints
- v7.10.8: Public SDK and CLI packages on npm, Packagist and PyPI under the `@finaegis` and `finaegis` names

### v7.11.0 to v7.18.1 -- Zelta Wallet
- v7.11.0: Public MCP server at `mcp.zelta.app`
- v7.12.0: Non-custodial wallet send through Privy, with passkey smart accounts on EVM networks and device-bound keys on Solana
- v7.13.0: In-app purchase subscriptions for Apple App Store and Google Play
- v7.14.0: Inbound EVM transaction history and wallet visibility for support staff
- v7.15.0: Bridge.xyz fiat ramp adapter for bank transfers in and USDC on Polygon out
- v7.16.0: Backend groundwork for non-custodial RAILGUN privacy
- v7.17.0 and v7.17.1: Security releases for advisories GHSA-385w-q6j4-5qw6 and GHSA-rrfq-93h6-29wj
- v7.18.0: Virtual cards through Lepo Finance for a closed pilot. Production offers them only to the users listed for the pilot. The release also adds safer wallet send outcomes and an analyzer upgrade, and removes the exchange liquidity add, remove and rebalance flow, which never worked.
- v7.18.1 (current): Fixes for the card pilot's identity check. Asset transfers, currency conversions and GCU buy and sell now book the right amounts. The daily spending limit for AI assistants is saved, and two admin pages that could never open were removed.

The [CHANGELOG](../../CHANGELOG.md) has the full notes for every release, and [VERSION_ROADMAP.md](../VERSION_ROADMAP.md) has the longer history.

---

## Future Roadmap

The open items at v7.18.1 are these.
- Open the virtual card pilot to its users. The card partner has answered how top-up money moves, and the matching fixes are merged for the next release. Card top-ups stay off until then.
- Move FinCard onto the provider-neutral card layer.
- Ship the on-device RAILGUN engine in the mobile app and retire the custodial bridge.

---

## Development Principles

1. **Backward compatibility.** Changes keep existing behaviour working.
2. **Tests first.** Tests are written before the implementation.
3. **Documentation.** Documentation is updated together with the code.
4. **Small increments.** Work ships in small, working steps.
5. **Performance.** Response times are measured with load tests and kept low.

---

## Educational Value

### Architecture Patterns Demonstrated
- **Event Sourcing**: Complete audit trail architecture with domain-specific stores
- **CQRS**: Command and query buses, with read models built by projectors
- **DDD**: 61 bounded contexts with proper domain isolation
- **Saga Pattern**: Compensatable workflows for distributed transactions
- **Plugin Architecture**: Sandboxed plugin execution with security scanning
- **GraphQL**: Type-safe API with subscriptions and DataLoaders
- **Event Streaming**: Real-time event distribution via Redis Streams

---

This roadmap describes the patterns and the architecture that FinAegis implements. It is meant as a learning resource for people who study banking system design.
