# Version Roadmap Strategic Decisions

> **Purpose**: Record key decisions made during roadmap planning. Reference when making future architectural choices.

## Version Philosophy

### Semantic Versioning Strategy
- **MAJOR (x.0.0)**: Breaking changes, significant architecture shifts
- **MINOR (x.y.0)**: New features, non-breaking enhancements  
- **PATCH (x.y.z)**: Bug fixes, security updates, documentation

### Release Cadence
- Minor releases: Every 8-12 weeks
- Patch releases: As needed (security within 24-48 hours)
- Major releases: Every 6-12 months
- LTS: Major versions get 2 years security support

---

## v1.2.0 Decisions (Feature Completion)

### Why Agent Bridges First?
1. Agent Protocol is core differentiator
2. Integration gaps block real-world usage
3. Three bridges complete the feature set:
   - Payment: Agents can transact
   - Compliance: Agents are regulated
   - MCP: Agents use AI tools

### Why Yield Optimization?
- Existing TODO in controller
- Treasury domain is 85% complete
- Completes a major feature set

### Why Observability Now?
- Production readiness requirement
- No dashboards currently exist
- Enables debugging of future issues

---

## v1.3.0 Decisions (Platform Modularity)

### Domain Decoupling Rationale
- Current: Tight coupling between domains
- Target: Interface-based contracts
- Benefit: Pick-and-choose installation

### Module System Design
- Each domain gets `module.json` manifest
- Dependencies declared explicitly
- Installation via artisan commands

### GCU Separation Strategy
- Move to `examples/gcu-basket/`
- Serves as reference implementation
- Shows how to build custom baskets
- Reduces core complexity

---

## v2.0.0 Decisions (Major Evolution)

### Multi-Tenancy Justification
- Enterprise customers need isolation
- Database-level tenant scoping
- Per-tenant configuration

### Hardware Wallet Priority
- Ledger + Trezor first (market leaders)
- Security requirement for institutional
- Multi-signature enables corporate use

### Kubernetes-Native Approach
- Helm charts for deployment
- HPA for auto-scaling
- Service mesh ready (Istio)

---

## Completed Versions (Summary)

- **v2.9.0** (Feb 10, 2026): ML Anomaly Detection & BaaS (Statistical/Behavioral/Velocity/Geo anomaly detection, Partner SDKs, Billing, Widgets, Marketplace, 26 Partner API endpoints)
- **v2.7.0** (Feb 8, 2026): Mobile Payment API & Enhanced Authentication (Payment Intents, Passkey Auth, P2P Transfer Helpers, TrustCert Export, Security Hardening)
- **v2.6.0** (Feb 2, 2026): Privacy Layer & ERC-4337 (Merkle Trees, Smart Accounts, Delegated Proofs, UserOp Signing with Biometric JWT, Production-Ready Gas Station)
- **v2.5.0**: Mobile App Launch (Expo/React Native)
- **v2.4.0**: Privacy & Identity (Key Management, ZK-KYC, Commerce, TrustCert)
- **v2.3.0**: AI Framework, RegTech Foundation, BaaS Configuration
- **v2.2.0**: Mobile Backend (Device Management, Biometrics, Push Notifications)
- **v2.1.0**: Security Hardening, Hardware Wallets, WebSocket, Kubernetes
- **v2.0.0**: Multi-Tenancy with Team-Based Isolation

- **v2.9.1** (Feb 10, 2026): Production Hardening (On-Chain SBT, snarkjs, AWS KMS, Azure Key Vault, Security Audit)
- **v2.10.0** (Feb 10, 2026): Mobile API Compatibility (~30 mobile-facing API endpoints)
- **v3.0.0** (Feb 10, 2026): Cross-Chain & DeFi (Bridge protocols: Wormhole/LayerZero/Axelar, DeFi: Uniswap/Aave/Curve/Lido, cross-chain swaps, multi-chain portfolio)
- **v3.1.0** (Feb 11, 2026): Consolidation, Documentation & UI Completeness (8 phases, PRs #456-#465)

- **v3.2.0 — Production Readiness & Plugin Architecture (COMPLETED)
Status: Released (2026-02-11)
Phases: 6 phases across 6 PRs (#466-#471)
Key deliverables:
- 41 domain module manifests with enable/disable toggle
- ModuleRouteLoader extracting 1,646-line api.php into 24 per-domain route files
- Module REST API + Filament admin page + health widget
- k6 load test suite (smoke/load/stress), QueryPerformanceMiddleware, performance:report command
- GitHub community files (Dependabot, issue templates, PR template)
- SPDX license headers, 24 integration tests covering plugin system
v3.3.0 — Event Store Optimization & Observability (COMPLETED)
Status: Released (2026-02-12)
Patch: v3.3.1 (2026-02-12) — PHPStan fixes, checkProjectorLag bug fix, security hardening, performance optimizations (PR #499)
Phases: 6 phases across 6 PRs (#493-#498)
Key deliverables:
- EventStoreService centralizing event store operations for 21 domains
- event:stats, event:replay, event:rebuild, snapshot:cleanup commands with --dry-run
- EventStoreDashboard Filament page with 5 widgets (stats, throughput, aggregate health, system metrics, domain health)
- StructuredJsonFormatter, StructuredLoggingMiddleware, LogsWithDomainContext trait
- EventStoreHealthCheck with connectivity, projector lag, snapshot freshness, growth rate checks
- EventArchivalService with archive, compact, restore methods; archived_events table; event-store config
- 3 integration test suites covering all features

v3.5.0 — Compliance Certification (COMPLETED)
Status: Released (2026-02-12)
Phases: 4 phases across 5 PRs (#511-#516)
Key deliverables:
- SOC 2 Type II: evidence collection, access reviews, incident response
- PCI DSS: data classification, encryption verification, key rotation
- Multi-Region: data residency, region-aware storage, geo-routing
- GDPR Enhanced: Article 30 ROPA, DPIA, breach notification (72h), consent v2, retention enforcement
- 10 services, 10 models, 10 migrations, 6 events, 6 commands, 2 controllers
- 90+ tests across all certification domains

v4.0.0 — Architecture Evolution (COMPLETED)
Status: Released (2026-02-13)
Phases: 7 phases across 7 PRs (#517-#523)
Key deliverables:
- Event Store v2: EventRouter for namespace-based domain table routing (21 domains)
- Event Store v2: Batch migration tooling with validation (event:migrate, event:migrate:rollback)
- Event Store v2: Schema evolution with chained upcasters (EventUpcastingService, EventVersionRegistry)
- GraphQL API: Lighthouse-PHP foundation, Account domain, custom @tenant directive
- GraphQL API: Wallet, Exchange, Compliance schemas, DataLoaders, subscription stubs
- Plugin Marketplace: PluginManager with semver dependency resolver, 6 Artisan commands
- Plugin Marketplace: Permission sandbox, security scanner, marketplace REST API, Filament admin
- 80+ tests across all features

v4.1.0 — GraphQL Expansion & Projector Health (COMPLETED)
Status: Released (2026-02-13)
Key deliverables:
- GraphQL API expansion to 10 domains (Account, Wallet, Exchange, Compliance, Lending, Treasury, Stablecoin + 3 more)
- Projector health monitoring and management
- DataLoader optimizations for N+1 prevention

v4.2.0 — Real-Time Subscriptions & Plugin Hooks (COMPLETED)
Status: Released (2026-02-13)
Key deliverables:
- Real-time GraphQL subscriptions for live data
- Plugin hook system for extensibility
- Webhook plugin and Audit plugin implementations

v4.3.0 — GraphQL Security & Domain Expansion (COMPLETED)
Status: Released (2026-02-13)
Key deliverables:
- GraphQL Fraud, Banking, Mobile, TrustCert domain schemas
- CLI commands for GraphQL management
- GraphQL security middleware (depth limiting, complexity analysis, introspection control)
- Total GraphQL domains: 24 (expanded from 14 in v4.3.0 to 24 with AI, Asset, Commerce, Custodian, Governance, KeyManagement, Privacy, RegTech, Relayer, Banking additions)

v5.0.0 — Event Streaming, Live Dashboard, Notifications, API Gateway (COMPLETED — MAJOR)
Status: Released (2026-02-13)
Key deliverables:
- Event Streaming: Redis Streams publisher/consumer for real-time event distribution
- Live Dashboard: 5 metrics endpoints for real-time system monitoring
- Notification System: 5 channels for alerts and notifications
- API Gateway Middleware: Centralized request routing, rate limiting, authentication
- 41 domains total, 775+ test files, 6300+ tests, PHPStan Level 8

v5.1.0 — Mobile API Completeness & GraphQL Full Coverage (COMPLETED)
Status: Released (2026-02-16)
Key deliverables:
- 21 missing mobile API endpoints (Privacy 11, Commerce 4, Card Issuance 3, Mobile 2, Wallet 1)
- GraphQL schemas for 9 remaining domains (completing 33-domain coverage)
- GraphQL integration tests for 14 domains
- BlockchainAddress/BlockchainTransaction Eloquent models with UUID support
- 42 new feature tests, 9 pre-existing test failures fixed
- CI hardening: k6 non-blocking, PHPStan bootstrap, PHPCS fixes
- Security: axios CVE-2025-27152 fix, PHPStan generic types, MariaDB timestamp fixes

v5.1.3 — Mobile API Compatibility (COMPLETED)
Status: Released (2026-02-17)
Key deliverables:
- Optional `owner_address` for `POST /api/v1/relayer/account` — mobile onboarding fix
- Auth response standardization (register, passkey) — `{ success, data }` envelope with full User model
- Token refresh endpoint (`POST /api/auth/refresh`) and logout-all (`POST /api/auth/logout-all`)
- Rate limiter crash fix for unknown transaction types

v5.1.4 — Refresh Token Mechanism (COMPLETED)
Status: Released (2026-02-18)
Key deliverables:
- Proper access/refresh token pairs using Sanctum `abilities` column — no DB migration
- Token rotation on refresh (old pair revoked, new pair issued)
- `POST /api/auth/refresh` moved to public route group (works after access tokens expire)
- `refresh_token` and `refresh_expires_in` in all auth responses
- `sanctum.refresh_token_expiration` config (default: 30 days)
- PHPStan `config/sanctum.php` type error fixed
- OpenAPI/Swagger annotations updated for login/register endpoints
- 5 new security tests for refresh token flows

v5.1.5 — Dependency Cleanup & Production Readiness (COMPLETED)
Status: Released (2026-02-21)
Key deliverables:
- l5-swagger upgrade 9.0.1 → 10.1.0 (swagger-php 5 → 6)
- doctrine/annotations as direct dependency (docblock OA support)
- PSR-4 fix: plugin directories renamed to PascalCase
- .env.production.example for mobile backend deployment
- PasskeyAuthenticationServiceTest fix (v5.1.4 token pair alignment)
- Roadmap updated with v5.1.0–v5.1.4 entries

v5.2.0 — X402 Protocol: HTTP-Native Micropayments (COMPLETED)
Status: Released
Key deliverables:
- HTTP 402 payment protocol for USDC on Base
- Payment gate middleware, facilitator integration
- AI agent payments, spending limits
- GraphQL/REST APIs, MCP tool

v5.4.0 — Ondato KYC, Sanctions Screening & Card Issuing (COMPLETED)
Status: Released
Key deliverables:
- Ondato identity verification with TrustCert linkage
- Chainalysis sanctions adapter
- Marqeta card issuing adapter
- Firebase FCM v1 migration
- X402/mobile test hardening, CVE patches

Platform Hardening (Post-v5.4.0, COMPLETED)
Status: All 6 phases merged (#641, #654, #655, #656, #657, TBD)
Key deliverables:
- Dependabot: 4 safe PRs merged, 5 breaking PRs closed with ignore rules
- CI: Removed process-level max_execution_time=300, optimized GC to every 50 tests
- IdempotencyMiddleware: Applied to ~24 financial mutation routes across 11 domain route files
- E2E Tests: 6 banking flow tests (deposit-transfer, exchange, lending, overdraft, withdrawal, frozen)
- Multi-Tenancy: 5 isolation tests (auto-skip on SQLite, MySQL-only)
- Documentation: "prototype" references updated to "platform" across 11 doc files

Future roadmap:
- OpenAPI Attribute Migration (10,385 @OA\ docblocks → PHP 8 #[OA\] attributes, drop doctrine/annotations), Laravel 13 upgrade when available, PHP 8.5 features

---

## Architecture Principles (for decision-making)

1. **Interface First**: Extract contracts before implementations
2. **Event Sourcing Everywhere**: All state changes through events
3. **Demo Mode Parity**: Every feature works in demo
4. **Test Coverage**: Minimum 50%, 80%+ for financial logic
5. **PHPStan Level 8**: No regressions allowed
6. **Backward Compatibility**: Until major versions

---

## Key Files for Roadmap
- `docs/VERSION_ROADMAP.md` - Full roadmap document
- `docs/ARCHITECTURAL_ROADMAP.md` - Architecture vision
- `CHANGELOG.md` - Version history
- This memory - Decision rationale
