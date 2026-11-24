# FinAegis Core Banking Platform

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

[![CI Pipeline](https://github.com/FinAegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml/badge.svg)](https://github.com/FinAegis/core-banking-prototype-laravel/actions/workflows/ci-pipeline.yml)
[![Version](https://img.shields.io/github/v/release/FinAegis/core-banking-prototype-laravel?sort=semver&label=release)](CHANGELOG.md)
[![License: Apache-2.0](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://opensource.org/licenses/Apache-2.0)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.4-8892BF.svg)](https://php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x-FF2D20.svg)](https://laravel.com/)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](CONTRIBUTING.md)

FinAegis is an open-source reference platform for core banking software. It is written in PHP with Laravel and uses event sourcing and domain-driven design. Developers can use it to study how accounts, payments, compliance tooling and wallets fit together, or as a starting point for their own system. It is not a bank and it is not a licensed financial service.

The current release is v7.18.1. The [changelog](CHANGELOG.md) lists what each release changed.

FinAegis is this open-source platform. Zelta is the hosted non-custodial wallet product and mobile app built on top of it. That is why production and hosted surfaces, for example the MCP server at `mcp.zelta.app`, carry the Zelta brand.

The **GCU demo** is a reference implementation of a basket-referenced unit built with FinAegis. It shows how the basket valuation and governance modules fit together.

[Live Demo](https://finaegis.org) | [Documentation](docs/README.md) | [Quick Start](#quick-start) | [Contributing](CONTRIBUTING.md)

---

## What the platform includes

The code is split into 61 domain modules under `app/Domain/`. Each module is a bounded context with its own models, services, routes and events.

Many modules implement financial standards. These standards modules (ISO 20022, ISO 8583, SWIFT, Open Banking, SEPA, US payment rails, Interledger, the double-entry ledger and microfinance) work at the message and logic layer. They run in demo mode and are switched off on the production Zelta site. They do not connect to any live bank, card scheme or settlement system.

### Core banking

| Module | What it does |
|--------|-------------|
| **Account** | Multi-asset accounts with deposits, withdrawals and statements. |
| **Banking** | Models SEPA and SWIFT transfers, routing across several banks and reconciliation. Bank connections are adapters and demo implementations. |
| **Compliance** | Know Your Customer (KYC) checks in three tiers, anti-money-laundering (AML) screening and suspicious activity and currency transaction reports. |
| **Treasury** | Portfolio management and cash-allocation modelling. |
| **Lending** | Peer-to-peer loan applications, credit scoring and risk assessment. |
| **Governance** | Proposals and voting with asset-weighted strategies. |
| **Fraud** | Rule-based, behavioural and statistical fraud checks, device fingerprints and fraud cases. |
| **Multi-tenancy** | Team-based data isolation with tenant-aware event sourcing, built on stancl/tenancy. |

### Digital assets

| Module | What it does |
|--------|-------------|
| **Exchange** | A demo trading engine with order matching, external exchange connectors and WebSocket streaming. Liquidity pools support pool lists, swaps, positions, rewards and impermanent-loss protection. The flow for adding, removing and rebalancing liquidity never worked and was removed in v7.18.0. The trading engine is off unless `TRADING_ENABLED` is set. |
| **Stablecoin** | Minting, burning and liquidation against several kinds of collateral. |
| **Wallet** | Non-custodial sends on Polygon, Base, Arbitrum and Solana. Incoming transfers to a user's registered addresses are recorded through Helius webhooks for Solana and Alchemy webhooks for EVM networks. The module also integrates Ledger and Trezor hardware wallets with multi-signature approval. |
| **Basket (GCU demo)** | A weighted currency basket with value calculation and rebalancing. The rebalancing is simulated in the demo. |
| **Cross-chain and DeFi** | Adapters for bridge protocols, DEX aggregation and DeFi protocols. |

### Mobile wallet backend

These modules serve the Zelta mobile app.

| Module | What it does |
|--------|-------------|
| **Non-custodial wallet send** | The device holds the keys through Privy, using passkey-controlled smart accounts on EVM networks and a device-bound key on Solana. The backend prepares an unsigned transaction and the device signs it. Gas on EVM networks and the Solana fee can be sponsored, with daily caps per user and for the whole platform. A send with an unclear network result stays submitted until the chain settles it. |
| **Mobile and passkeys** | Biometric sign-in, WebAuthn passkeys, push notifications and device management. |
| **Mobile payments** | Payment intents, an activity feed and receipts for USDC and USDT. |
| **Fiat ramp** | A Bridge.xyz API adapter for bank transfers in and USDC on Polygon out, with a hosted identity check and virtual accounts. Only the on-ramp is available. |
| **Subscriptions** | Verification of Apple App Store and Google Play in-app purchases, with pseudonymised receipts and a revenue outbox. |
| **Virtual cards (closed pilot)** | A provider-neutral card layer with Lepo Finance as the first provider. A person in the pilot passes an identity check, opens a card and sees its details on the card partner's secure page. 3-D Secure codes for online payments are shown in the app. Only users on the pilot list can open a card. Topping up a card from the wallet is built but stays switched off until a real top-up has been tested in the pilot. |
| **Card issuance adapters** | Adapters for the Marqeta and FinCard card-issuing APIs and a demo issuer, with card lifecycle and just-in-time (JIT) funding. `CARD_ISSUER` selects one of them. A Rain adapter class exists but is not wired in. |
| **Privacy** | Zero-knowledge KYC proofs, Merkle trees, proof of innocence and selective disclosure. RAILGUN privacy is moving to a non-custodial design in which the device holds every key. The backend part shipped in v7.16.0 and the on-device engine is still in progress. |
| **Gas relayer** | ERC-4337 meta-transactions that let users pay fees in USDC. |
| **TrustCert** | W3C verifiable credentials with QR code and deep link verification. |
| **Rewards** | Quests, experience points and levels, a points shop and streaks. |
| **Key management** | Shamir secret sharing (two of three) and hardware security module (HSM) integration. |

### Agents, payments and developer tools

| Module | What it does |
|--------|-------------|
| **Public MCP server** | An OAuth-protected Model Context Protocol server at `https://mcp.zelta.app/mcp`. Its catalog has 14 tools, and the production server exposes 10 of them. The `@finaegis/mcp` npm package lets desktop clients connect to it. |
| **AI framework** | An internal tool registry and agent workflows for banking tasks. |
| **Agent protocol** | Agent-to-agent messaging, decentralised identifiers, escrow and a reputation system. |
| **x402** | HTTP 402 micropayments in USDC for paid API calls. Base is the default network, and other EVM networks and Solana are configured too. |
| **Machine payments** | The Machine Payments Protocol (MPP) with Stripe, Tempo, Lightning, card and x402 rails, and AP2 mandates. |
| **Payment orchestration** | HyperSwitch routing for card deposits. It is experimental and off by default through `HYPERSWITCH_ENABLED`, so Stripe stays the default deposit rail. |
| **SMS payments** | Paid SMS sending through the VertexSMS API, with an MCP tool for AI agents. |
| **RegTech** | Report formats and data exchange modelled on MiFID II, MiCA and the Travel Rule, with four jurisdiction adapters (ESMA, FCA, FinCEN and MAS). They do not connect to any regulator and do not by themselves make a deployment compliant. |
| **Post-quantum cryptography** | ML-KEM-768, ML-DSA-65 and hybrid encryption with key rotation. |
| **Monitoring** | OpenTelemetry tracing, a Prometheus exporter, health checks and event store tooling for archiving and migration. |
| **Developer ecosystem** | A plugin manager with a sandbox and a security scanner, a developer portal, a command-line tool and SDKs for JavaScript, PHP and Python. |

*Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.*

### Compliance tooling

The platform includes tooling that helps a team prepare for audits. It collects evidence for a SOC 2 audit. Its card design keeps the PCI DSS scope small, because cards are tokenised and no card numbers are stored. **No SOC 2 or PCI DSS certification is held.** GDPR data export and erasure are implemented and working.

---

## Modules

Each domain is a module that can be listed, installed, enabled and disabled.

```bash
php artisan domain:list              # List all 61 domain modules with status
php artisan module:enable exchange   # Enable a module
php artisan module:disable exchange  # Disable a module (preserves data)
php artisan domain:verify exchange   # Verify module health
php artisan performance:report       # Generate performance baseline
```

- Each module has a `module.json` manifest that lists its dependencies, interfaces, events and commands.
- Each module loads its own `Routes/api.php` through `ModuleRouteLoader`.
- Administrators can enable and disable modules on the Filament page at `/admin/modules`.
- Programs can manage modules through `GET /api/v2/modules` and the related endpoints.
- A deployment can switch modules off with `MODULES_DISABLED`. The production Zelta configuration in `.env.zelta.example` uses it to switch off the standards and demo modules.

---

## GraphQL API

FinAegis has a schema-first GraphQL API built with [Lighthouse PHP](https://lighthouse-php.com/). It is served at `/graphql`.

```bash
curl -X POST http://localhost:8000/graphql \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"query": "{ accounts { id name balance currency } }"}'
```

- [`graphql/schema.graphql`](graphql/schema.graphql) imports 45 files. They are 43 domain schemas plus `directives` and `subscriptions`.
- Subscriptions send real-time updates over WebSocket for account updates, wallet changes, compliance alerts and order matching.
- DataLoaders batch related queries so that lists do not cause N+1 database queries.
- Queries are protected by `@guard(with: ["sanctum"])`, query cost analysis and introspection control.

---

## Event streaming

The platform publishes domain events to Redis Streams for real-time data pipelines.

```bash
php artisan event-stream:monitor    # Monitor stream health, lag, throughput
```

- The publisher writes domain events to 15 Redis Streams.
- Consumer groups read the streams, acknowledge messages and move failed messages to a dead letter stream.
- A live dashboard reports projector lag, event throughput and domain health.
- Notifications can go out by email, push, in-app message, webhook or SMS.

---

## GCU: The Reference Implementation

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

<table>
<tr>
<td width="60%">

The **GCU demo** is a reference implementation of a basket-referenced unit built with FinAegis. It uses these modules:

- **Multi-asset basket.** The basket holds USD (40%), EUR (30%), GBP (15%), CHF (10%), JPY (3%) and XAU (2%).
- **Governance module.** Votes on the basket composition are simulated and exist only in the demo.
- **Automatic rebalancing.** A monthly adjustment keeps the target weights. It is simulated in the demo.
- **Basket valuation.** The basket value is calculated in real time from the component rates.
- **Integration.** The demo uses the Exchange, Governance, Compliance and Treasury domains.

The GCU demo shows how FinAegis primitives fit together.

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

See [ADR-0010: GCU Basket Design](docs/adr/0010-gcu-basket-design.md) for the architecture.

---

## Quick Start

### Demo mode (recommended)

Demo mode runs everything locally and needs no external services.

```bash
git clone https://github.com/FinAegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install
cp .env.demo .env
php artisan key:generate
php artisan migrate
php artisan demo:populate
npm install && npm run build
php artisan serve
```

`php artisan demo:populate` runs the standard seeders and creates the demo users. Open `http://localhost:8000` and sign in with one of them. Every demo user has the password `demo123`.

- `demo.user@gcu.global`
- `demo.business@gcu.global`
- `demo.investor@gcu.global`
- `demo.argentina@gcu.global`
- `demo.nomad@gcu.global`

### Full installation

```bash
git clone https://github.com/FinAegis/core-banking-prototype-laravel.git
cd core-banking-prototype-laravel
composer install && npm install
cp .env.example .env
php artisan key:generate
# Configure the database and Redis in .env
php artisan migrate --seed
npm run build
php artisan serve
php artisan horizon
```

`php artisan horizon` starts the queue workers for every queue defined in `config/horizon.php`.

You need PHP 8.4 or newer, MySQL 8.0 or MariaDB, Redis and Node.js 20.19 or newer. CI runs the tests on MySQL 8.0 and on MariaDB 11.4.

### Modular installation

You can install only the domains you need.

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

| Domain type | Examples | Installation |
|-------------|----------|--------------|
| **Core** (always installed) | `account`, `user`, `compliance`, `shared` | Automatic |
| **Financial** | `exchange`, `lending`, `treasury`, `wallet` | `domain:install` |
| **AI and agents** | `ai`, `agent-protocol`, `governance` | `domain:install` |
| **Infrastructure** | `monitoring`, `fraud`, `batch` | `domain:install` |

The [Domain Management Guide](docs/06-DEVELOPMENT/DOMAIN_MANAGEMENT.md) has the details.

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

The platform uses these patterns:

- **Event sourcing.** Domains store their events in domain-specific tables. The event store supports replay and upcasting of old event versions.
- **CQRS.** Commands that change state are separated from the queries that read it.
- **Sagas.** A multi-step transaction runs as a workflow, and a failed step triggers compensation for the steps that already ran.
- **Domain-driven design.** The 61 domains are bounded contexts with clear boundaries.
- **Multi-tenancy.** Team-based data isolation uses stancl/tenancy.
- **GraphQL.** The schema-first Lighthouse API supports subscriptions.
- **Event streaming.** Redis Streams carry domain events to consumers and the live dashboard.

The [Architecture Decision Records](docs/adr/) explain the design choices.

---

## Documentation

| Category | Links |
|----------|-------|
| **Getting started** | [Quick Start](#quick-start) · [User Guides](docs/05-USER-GUIDES/) |
| **Architecture** | [Overview](docs/02-ARCHITECTURE/) · [ADRs](docs/adr/) · [Roadmap](docs/ARCHITECTURAL_ROADMAP.md) |
| **API** | [REST Reference](docs/04-API/REST_API_REFERENCE.md) · OpenAPI at `/api/documentation` · GraphQL at `/graphql` |
| **Version history** | [Changelog](CHANGELOG.md) · [Version Roadmap](docs/VERSION_ROADMAP.md) |
| **Development** | [Contributing](CONTRIBUTING.md) · [Dev Guides](docs/06-DEVELOPMENT/) |
| **Operations** | [Runbooks](docs/operations/) · [Deployment Guide](docs/10-OPERATIONS/DEPLOYMENT_GUIDE.md) |
| **Reference** | [GCU Design](docs/adr/0010-gcu-basket-design.md) · [Event Sourcing](docs/adr/0007-event-sourcing.md) |

---

## Contributing

Contributions are welcome. [CONTRIBUTING.md](CONTRIBUTING.md) has the guidelines.

```bash
# Fork, clone, then:
git checkout -b feature/your-feature
# Make changes with tests
./bin/pre-commit-check.sh --fix
# Submit PR
```

Code follows PSR-12 and passes PHPStan at level 8. Commit messages follow Conventional Commits.

The project supports AI coding assistants. `AGENTS.md` files in the repository give them context for each area.

---

## Deployment

### Kubernetes

The Helm chart in `helm/finaegis` deploys the platform to a Kubernetes cluster.

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

The chart includes these parts:

- a multi-stage Docker build on `php:8.4-fpm-alpine`,
- horizontal pod autoscalers for the web pods and for Horizon, which scales on queue depth,
- Istio resources for mutual TLS and traffic rules,
- External Secrets for Vault or AWS,
- a Prometheus `ServiceMonitor`,
- network policies that isolate the pods.

The [Infrastructure and Deployment Guide](docs/06-DEVELOPMENT/INFRASTRUCTURE.md) has the details.

---

## MCP (Model Context Protocol)

Claude Desktop, Cursor and Continue.dev can connect to a Zelta account through the public MCP server.

```bash
npx -y @finaegis/mcp
```

Clients that support remote servers can use `https://mcp.zelta.app/mcp` directly. The [MCP quickstart](docs/13-AI-FRAMEWORK/03-MCP-Quickstart.md) shows both ways.

---

## Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | Laravel 12, PHP 8.4 |
| **Event sourcing** | Spatie Laravel Event Sourcing 7 with domain routing and upcasting |
| **GraphQL** | Lighthouse PHP 6, schema-first, with subscriptions |
| **Workflows** | Laravel Workflow with the Waterline dashboard |
| **Multi-tenancy** | stancl/tenancy 3 |
| **Database** | MySQL 8.0 or MariaDB |
| **Cache, queues and streaming** | Redis for cache, queues and Streams, with Laravel Horizon |
| **Real-time** | Soketi (Pusher-compatible), Laravel Echo and Redis Streams |
| **Testing** | Pest PHP with parallel runs and about 1,300 test files, plus PHPStan level 8 with larastan |
| **Admin** | Filament 3 |
| **Frontend** | Livewire and Tailwind CSS |
| **Deployment** | Docker, Kubernetes with Helm, and Istio |

---

## Project Status

FinAegis is an open-source reference platform. People use it to:

- learn event sourcing and domain-driven design,
- understand how core banking systems work,
- build proofs of concept,
- contribute to open-source financial software,
- study the GCU demo as a basket reference implementation.

**Before any production use**, a security audit and a compliance review are required. Licensing and regulatory compliance are the responsibility of the operator of each deployment. **No third-party SOC 2 or PCI DSS certification is held.** The compliance modules are audit-readiness tooling. The payment-standards domains (ISO 20022, ISO 8583, SWIFT, Open Banking, SEPA, US rails and Interledger) are **reference implementations** at the message and logic layer. They do not provide live scheme connectivity or settlement. The [Security Policy](SECURITY.md) explains how to report a vulnerability.

---

## Community

- [GitHub Discussions](https://github.com/FinAegis/core-banking-prototype-laravel/discussions) for questions and ideas
- [GitHub Issues](https://github.com/FinAegis/core-banking-prototype-laravel/issues) for bug reports
- [Security Policy](SECURITY.md) for vulnerability reports
- [Code of Conduct](CODE_OF_CONDUCT.md) for community guidelines
- [Changelog](CHANGELOG.md) for the version history

---

## License

[Apache License 2.0](LICENSE)
