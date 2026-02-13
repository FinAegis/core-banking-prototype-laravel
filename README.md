# FinAegis Core Banking Platform

[![CI Pipeline](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml/badge.svg)](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml)
[![Version](https://img.shields.io/badge/version-5.0.1-blue.svg)](CHANGELOG.md)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com/)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)
[![Mobile Ready](https://img.shields.io/badge/mobile-ready-green.svg)](docs/MOBILE_APP_SPECIFICATION.md)

**An open-source core banking platform built with event sourcing, domain-driven design, and modern financial patterns.**

FinAegis provides the foundation for building digital banking applications. The **Global Currency Unit (GCU)** serves as a complete reference implementation demonstrating how to build basket currencies, governance systems, and democratic financial instruments on this platform.

[Live Demo](https://finaegis.org) | [Documentation](docs/README.md) | [Quick Start](#quick-start) | [Contributing](CONTRIBUTING.md)

---

## Why FinAegis?

| Challenge | FinAegis Solution |
|-----------|-------------------|
| Building financial systems from scratch | 41 production-ready domain modules |
| Audit trail requirements | Event sourcing with domain-specific event tables |
| Complex multi-step transactions | Saga pattern with automatic compensation |
| Regulatory compliance | Built-in KYC/AML, SOC 2, PCI DSS, GDPR (v3.5.0) |
| Multi-tenant SaaS deployment | Team-based tenant isolation (v2.0.0) |
| Hardware wallet security | Ledger/Trezor support with multi-sig (v2.1.0) |
| Mobile wallet backend | Biometric auth, passkeys, push notifications (v2.2.0+) |
| Privacy-preserving transactions | ZK-KYC, Merkle trees, ERC-4337 gas abstraction (v2.4.0-v2.6.0) |
| Multi-jurisdiction RegTech | MiFID II, MiCA, FATF Travel Rule, 4-jurisdiction adapters (v2.8.0) |
| Cross-chain & DeFi | Bridge protocols, DEX aggregation, yield optimization (v3.0.0) |
| Modular plugin architecture | 41 domains with manifests, enable/disable, dependency resolution (v3.2.0) |
| Compliance certification | SOC 2 Type II, PCI DSS readiness, multi-region deployment (v3.5.0) |
| GraphQL API | Schema-first Lighthouse PHP, 24 domains, subscriptions (v4.0.0+) |
| Event Store v2 | Domain routing (33 domains), upcasting, migration tooling (v4.0.0) |
| Plugin Marketplace | Manager, loader, sandbox, security scanner (v4.0.0) |
| Event streaming | Redis Streams publisher/consumer, live dashboard (v5.0.0) |
| Learning modern architecture | Complete DDD + CQRS + Event Sourcing example |

---

## Plugin Architecture (v3.2.0)

FinAegis uses a modular plugin system where each domain is a self-contained module:

```bash
php artisan domain:list              # List all 41 domain modules with status
php artisan module:enable exchange   # Enable a module
php artisan module:disable exchange  # Disable a module (preserves data)
php artisan domain:verify exchange   # Verify module health
php artisan performance:report       # Generate performance baseline
```

- **Module manifests** (`module.json`) define dependencies, interfaces, events, and commands
- **Route isolation** — each domain loads its own `Routes/api.php` via `ModuleRouteLoader`
- **Admin UI** — Filament page at `/admin/modules` with search, filters, enable/disable actions
- **REST API** — `GET /api/v2/modules` for programmatic module management

---

## GraphQL API (v4.0.0-v4.3.0)

FinAegis provides a schema-first GraphQL API via [Lighthouse PHP](https://lighthouse-php.com/) covering 24 domains:

```bash
# Available at /graphql
# Interactive playground at /graphql-playground

# Example query
curl -X POST http://localhost:8000/graphql \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"query": "{ accounts { id name balance currency } }"}'
```

- **24 domain schemas** — Account, AI, Asset, Banking, Commerce, Compliance, CrossChain, Custodian, DeFi, Exchange, Fraud, Governance, KeyManagement, Lending, Mobile, MobilePayment, Payment, Privacy, RegTech, Relayer, Stablecoin, Treasury, TrustCert, Wallet
- **Subscriptions** — Real-time updates via WebSocket (account updates, wallet changes, compliance alerts, order matching)
- **DataLoaders** — N+1 query prevention with batched loading
- **Security** — `@guard(with: ["sanctum"])`, query cost analysis, introspection control

---

## Event Streaming (v5.0.0)

Redis Streams-based event streaming for real-time data pipelines:

```bash
php artisan event-stream:monitor    # Monitor stream health, lag, throughput
```

- **Event publisher** — Publishes domain events to 15 Redis Streams with XADD
- **Consumer groups** — XREADGROUP-based consumers with acknowledgement and dead letter handling
- **Live dashboard** — 5 REST endpoints for real-time metrics (projector lag, event throughput, domain health)
- **Notification system** — Multi-channel notifications (email, push, in-app, webhook, SMS)
- **API gateway** — Unified middleware with request ID tracing, timing headers

---

## GCU: The Reference Implementation

<table>
<tr>
<td width="60%">

The **Global Currency Unit (GCU)** demonstrates FinAegis capabilities through a democratic basket currency:

- **Multi-Asset Basket** - USD (40%), EUR (30%), GBP (15%), CHF (10%), JPY (3%), XAU (2%)
- **Democratic Governance** - Community votes on basket composition
- **Automatic Rebalancing** - Monthly adjustment to maintain target weights
- **Transparent NAV** - Real-time Net Asset Value calculation
- **Full Integration** - Uses Exchange, Governance, Compliance, and Treasury domains

GCU shows how to build complex financial products using FinAegis primitives.

</td>
<td width="40%">

```
┌─────────────────────┐
│   GCU Basket        │
├─────────────────────┤
│ USD ████████░░ 40%  │
│ EUR ██████░░░░ 30%  │
│ GBP ███░░░░░░░ 15%  │
│ CHF ██░░░░░░░░ 10%  │
│ JPY █░░░░░░░░░  3%  │
│ XAU █░░░░░░░░░  2%  │
└─────────────────────┘
```

</td>
</tr>
</table>

See [ADR-004: GCU Basket Design](docs/ADR/ADR-004-gcu-basket-design.md) for architecture details.

---

## Quick Start

### Demo Mode (Recommended)

No external dependencies - everything runs locally:

```bash
git clone https://github.com/finaegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install
cp .env.demo .env
php artisan key:generate
php artisan migrate --seed
npm install && npm run build
php artisan serve
```

Visit `http://localhost:8000` with demo credentials:
- `demo.user@gcu.global` / `demo123`
- `demo.business@gcu.global` / `demo123`
- `demo.investor@gcu.global` / `demo123`

### Full Installation

```bash
git clone https://github.com/finaegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install && npm install
cp .env.example .env
php artisan key:generate
# Configure MySQL/PostgreSQL and Redis in .env
php artisan migrate --seed
npm run build
php artisan serve
php artisan queue:work --queue=events,ledger,transactions,transfers,webhooks
```

**Requirements**: PHP 8.4+, MySQL 8.0+ / MariaDB 10.3+ / PostgreSQL 13+, Redis 6.0+, Node.js 18+

### Modular Installation (v1.3.0+)

Install only the domains you need:

```bash
# List available domains
php artisan domain:list

# Install specific domains
php artisan domain:install exchange
php artisan domain:install lending

# Check dependencies
php artisan domain:dependencies exchange

# Verify domain health
php artisan domain:verify
```

| Domain Type | Examples | Installation |
|-------------|----------|--------------|
| **Core** (always installed) | `account`, `user`, `compliance`, `shared` | Automatic |
| **Financial** | `exchange`, `lending`, `treasury`, `wallet` | `domain:install` |
| **AI/Agent** | `ai`, `agent-protocol`, `governance` | `domain:install` |
| **Infrastructure** | `monitoring`, `fraud`, `batch` | `domain:install` |

See [Domain Management Guide](docs/06-DEVELOPMENT/DOMAIN_MANAGEMENT.md) for details.

---

## Platform Capabilities

### Core Banking

| Domain | Capabilities |
|--------|-------------|
| **Account** | Multi-asset accounts, deposits, withdrawals, statements |
| **Banking** | SEPA/SWIFT transfers, multi-bank routing, reconciliation |
| **Compliance** | 3-tier KYC, AML screening, SAR/CTR reporting |
| **Treasury** | Portfolio management, cash allocation, yield optimization |

### Digital Assets

| Domain | Capabilities |
|--------|-------------|
| **Exchange** | Order matching, liquidity pools, AMM, external connectors, WebSocket streaming |
| **Stablecoin** | Multi-collateral minting, burning, liquidation |
| **Wallet** | Multi-chain (BTC, ETH, Polygon, BSC), Hardware wallets (Ledger, Trezor), Multi-sig (M-of-N) |
| **Basket (GCU)** | Weighted currency basket, NAV calculation, rebalancing |

### Platform Services

| Domain | Capabilities |
|--------|-------------|
| **Governance** | Democratic voting, proposals, asset-weighted strategies |
| **Lending** | P2P loans, credit scoring, risk assessment |
| **AI Framework** | MCP server, 20+ banking tools, natural language queries, pattern analysis (v2.8.0) |
| **Agent Protocol** | A2A messaging, escrow, reputation system |
| **RegTech** | MiFID II reporting, MiCA compliance, FATF Travel Rule, 4-jurisdiction adapters (v2.8.0) |
| **Multi-Tenancy** | Team-based isolation, tenant-aware event sourcing |

### Mobile Backend (v2.4.0+)

| Domain | Capabilities |
|--------|-------------|
| **Key Management** | Shamir's Secret Sharing (2-of-3), HSM integration |
| **Privacy** | ZK-KYC verification, Proof of Innocence, selective disclosure |
| **Card Issuance** | Virtual cards for Apple Pay/Google Pay, JIT funding |
| **Gas Relayer** | ERC-4337 meta-transactions, pay fees in USDC |
| **TrustCert** | W3C Verifiable Credentials, QR/deep link verification |
| **Mobile** | Biometric auth, push notifications, device management |
| **Mobile Payments** | Payment intents, activity feed, receipts, USDC on Solana/Tron (v2.7.0) |
| **Passkey Auth** | WebAuthn/FIDO2 challenge-response authentication (v2.7.0) |
| **P2P Transfers** | Address validation, name resolution, fee quotes (v2.7.0) |

---

## Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                         API / Admin Panel                           │
├─────────────────────────────────────────────────────────────────────┤
│  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐  │
│  │ Account  │ │ Exchange │ │ Basket   │ │Compliance│ │ Treasury │  │
│  │  Domain  │ │  Domain  │ │  (GCU)   │ │  Domain  │ │  Domain  │  │
│  └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘  │
│       │            │            │            │            │         │
│  ┌────▼────────────▼────────────▼────────────▼────────────▼─────┐  │
│  │                    CQRS + Event Sourcing                      │  │
│  │  Commands → Aggregates → Events → Projectors → Read Models   │  │
│  └──────────────────────────────┬────────────────────────────────┘  │
│                                 │                                    │
│  ┌──────────────────────────────▼────────────────────────────────┐  │
│  │                    Saga / Workflow Engine                      │  │
│  │         Multi-step transactions with compensation              │  │
│  └───────────────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────────┘
```

**Key Patterns:**
- **Event Sourcing** - Domain-specific event tables with Event Store v2, replay, and upcasting (v4.0.0)
- **CQRS** - Separated read/write models for optimal performance
- **Saga Pattern** - Distributed transactions with automatic rollback
- **DDD** - 41 bounded contexts with clear boundaries
- **Multi-Tenancy** - Team-based data isolation with stancl/tenancy v3.9
- **GraphQL** - Schema-first Lighthouse PHP across 24 domains with subscriptions (v4.0.0+)
- **Event Streaming** - Redis Streams publisher/consumer with live dashboard (v5.0.0)

See [Architecture Decision Records](docs/ADR/) for detailed design rationale.

---

## Documentation

| Category | Links |
|----------|-------|
| **Getting Started** | [Quick Start](#quick-start) · [User Guides](docs/05-USER-GUIDES/) |
| **Architecture** | [Overview](docs/02-ARCHITECTURE/) · [ADRs](docs/ADR/) · [Roadmap](docs/ARCHITECTURAL_ROADMAP.md) |
| **API** | [REST Reference](docs/04-API/REST_API_REFERENCE.md) · [OpenAPI](/api/documentation) · [GraphQL](/graphql-playground) |
| **Version History** | [Changelog](CHANGELOG.md) · [Version Roadmap](docs/VERSION_ROADMAP.md) |
| **Development** | [Contributing](CONTRIBUTING.md) · [Dev Guides](docs/06-DEVELOPMENT/) |
| **Reference** | [GCU Design](docs/ADR/ADR-004-gcu-basket-design.md) · [Event Sourcing](docs/ADR/ADR-001-event-sourcing.md) |

---

## Contributing

We welcome contributions! See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.

```bash
# Fork, clone, then:
git checkout -b feature/your-feature
# Make changes with tests
./bin/pre-commit-check.sh --fix
# Submit PR
```

**Standards**: PSR-12 · PHPStan Level 8 · 50%+ Coverage · Conventional Commits

This project supports AI coding assistants. Look for `AGENTS.md` files for context-aware guidance.

---

## Deployment

### Kubernetes (v2.1.0+)

Deploy to any Kubernetes cluster with Helm:

```bash
# Add Bitnami repo for dependencies
helm repo add bitnami https://charts.bitnami.com/bitnami

# Install with staging values
helm upgrade --install finaegis ./helm/finaegis \
  --values ./helm/finaegis/values-staging.yaml \
  --namespace finaegis-staging \
  --create-namespace

# Install with production values
helm upgrade --install finaegis ./helm/finaegis \
  --values ./helm/finaegis/values-production.yaml \
  --namespace finaegis
```

**Features:**
- Multi-stage Docker build (PHP 8.4-fpm-alpine)
- Horizontal Pod Autoscaler (CPU, memory, queue depth)
- Istio service mesh compatible (mTLS, circuit breaker)
- External Secrets for Vault/AWS integration
- Prometheus ServiceMonitor for observability
- Network Policies for pod isolation

See [Kubernetes Deployment Guide](docs/06-DEVELOPMENT/KUBERNETES.md) for details.

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 12, PHP 8.4+ |
| **Event Sourcing** | Spatie Event Sourcing with Event Store v2 (domain routing, upcasting) |
| **GraphQL** | Lighthouse PHP (schema-first, 24 domains, subscriptions) |
| **Workflows** | Laravel Workflow (Waterline) |
| **Multi-Tenancy** | stancl/tenancy v3.9 |
| **Database** | MySQL 8.0+ / MariaDB 10.3+ / PostgreSQL 13+ |
| **Cache/Queue/Streaming** | Redis (cache, queues, Streams), Laravel Horizon |
| **Real-time** | Soketi (Pusher-compatible), Laravel Echo, Redis Streams |
| **Testing** | Pest PHP (parallel, 775+ test files, 6,300+ tests), PHPStan Level 8 |
| **Admin** | Filament v3 |
| **Frontend** | Livewire, Tailwind CSS |
| **Deployment** | Docker, Kubernetes (Helm), Istio |

---

## Project Status

This is a **demonstration platform** showcasing modern banking architecture. Use it for:

- Learning event sourcing and DDD patterns
- Understanding core banking concepts
- Building proof-of-concepts
- Contributing to open-source fintech
- Studying GCU as a basket currency reference

**Production Readiness**: The codebase includes production-grade infrastructure (CQRS, event sourcing, multi-tenancy, GraphQL API, event streaming, 50%+ test coverage, PHPStan Level 8, 6,300+ tests). However, **a security audit and compliance review are required** before any production deployment. See [Security Policy](SECURITY.md) for vulnerability reporting.

---

## Community

- [GitHub Discussions](https://github.com/finaegis/core-banking-prototype-laravel/discussions) - Questions & Ideas
- [GitHub Issues](https://github.com/finaegis/core-banking-prototype-laravel/issues) - Bug Reports
- [Security Policy](SECURITY.md) - Vulnerability Reporting
- [Code of Conduct](CODE_OF_CONDUCT.md) - Community Guidelines
- [Changelog](CHANGELOG.md) - Version History

---

## License

[Apache License 2.0](LICENSE)

---

<p align="center">
<strong>Built for the open-source financial community</strong>
</p>
