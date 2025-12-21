# FinAegis Core Banking Platform

[![CI Pipeline](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml/badge.svg)](https://github.com/finaegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com/)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

**An open-source core banking platform built with event sourcing, domain-driven design, and modern financial patterns.**

FinAegis provides the foundation for building digital banking applications. The **Global Currency Unit (GCU)** serves as a complete reference implementation demonstrating how to build basket currencies, governance systems, and democratic financial instruments on this platform.

[Live Demo](https://finaegis.org) | [Documentation](docs/README.md) | [Quick Start](#quick-start) | [Contributing](CONTRIBUTING.md)

---

## Why FinAegis?

| Challenge | FinAegis Solution |
|-----------|-------------------|
| Building financial systems from scratch | 29 production-ready domain modules |
| Audit trail requirements | Event sourcing captures every state change |
| Complex multi-step transactions | Saga pattern with automatic compensation |
| Regulatory compliance | Built-in KYC/AML workflows |
| Learning modern architecture | Complete DDD + CQRS + Event Sourcing example |

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

**Requirements**: PHP 8.4+, MySQL 8.0+/PostgreSQL 13+, Redis 6.0+, Node.js 18+

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
| **Exchange** | Order matching, liquidity pools, AMM, external connectors |
| **Stablecoin** | Multi-collateral minting, burning, liquidation |
| **Wallet** | Multi-chain support (BTC, ETH, Polygon, BSC) |
| **Basket (GCU)** | Weighted currency basket, NAV calculation, rebalancing |

### Platform Services

| Domain | Capabilities |
|--------|-------------|
| **Governance** | Democratic voting, proposals, asset-weighted strategies |
| **Lending** | P2P loans, credit scoring, risk assessment |
| **AI Framework** | MCP server, 20+ banking tools, event-sourced interactions |
| **Agent Protocol** | A2A messaging, escrow, reputation system |

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
- **Event Sourcing** - Complete audit trail, temporal queries, replay capability
- **CQRS** - Separated read/write models for optimal performance
- **Saga Pattern** - Distributed transactions with automatic rollback
- **DDD** - 29 bounded contexts with clear boundaries

See [Architecture Decision Records](docs/ADR/) for detailed design rationale.

---

## Documentation

| Category | Links |
|----------|-------|
| **Getting Started** | [Quick Start](#quick-start) · [User Guides](docs/05-USER-GUIDES/) |
| **Architecture** | [Overview](docs/02-ARCHITECTURE/) · [ADRs](docs/ADR/) · [Roadmap](docs/ARCHITECTURAL_ROADMAP.md) |
| **API** | [REST Reference](docs/04-API/REST_API_REFERENCE.md) · [OpenAPI](/api/documentation) |
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

**Standards**: PSR-12 · PHPStan Level 5 · 50%+ Coverage · Conventional Commits

This project supports AI coding assistants. Look for `AGENTS.md` files for context-aware guidance.

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 12, PHP 8.4+ |
| **Event Sourcing** | Spatie Event Sourcing |
| **Workflows** | Laravel Workflow (Waterline) |
| **Database** | MySQL 8.0+ / PostgreSQL 13+ |
| **Cache/Queue** | Redis, Laravel Horizon |
| **Testing** | Pest PHP (parallel) |
| **Admin** | Filament v3 |
| **Frontend** | Livewire, Tailwind CSS |

---

## Project Status

This is a **demonstration platform** showcasing modern banking architecture. Use it for:

- Learning event sourcing and DDD patterns
- Understanding core banking concepts
- Building proof-of-concepts
- Contributing to open-source fintech
- Studying GCU as a basket currency reference

**Not recommended** for production use without security audit and compliance review.

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
