# Domain Management Guide

This guide covers the modular domain system introduced in v1.3.0, allowing you to install only the domains you need.

## Overview

FinAegis uses Domain-Driven Design (DDD) with 56 bounded contexts. The platform supports modular installation where you can choose which domains to enable based on your requirements.

## Domain Types

| Type | Description | Examples |
|------|-------------|----------|
| **Core** | Always installed, required for platform operation | `shared`, `account`, `user`, `compliance` |
| **Optional** | Can be installed based on requirements | `exchange`, `lending`, `treasury`, `wallet` |

## Available Commands

### List All Domains

```bash
php artisan domain:list
```

Shows all available domains with:
- Installation status (✓ installed, ○ available, ✗ missing dependencies)
- Domain type (core/optional)
- Version
- Dependencies

### Install a Domain

```bash
# Install with dependencies (recommended)
php artisan domain:install exchange

# Preview what will be installed
php artisan domain:install lending --dry-run

# Install without auto-installing dependencies (advanced)
php artisan domain:install treasury --no-dependencies
```

### Show Domain Dependencies

```bash
php artisan domain:dependencies exchange
```

Displays the dependency tree for a domain, showing what other domains it requires.

### Verify Domain Health

```bash
php artisan domain:verify
```

Checks all installed domains for:
- Missing migrations
- Configuration issues
- Service provider registration
- Model relationships

### Remove a Domain

```bash
php artisan domain:remove newsletter
```

Safely removes a domain with dependency checking. Will refuse to remove if other installed domains depend on it.

### Create a New Domain

```bash
php artisan domain:create my-domain
```

Scaffolds a new domain with standard directory structure:
```
app/Domain/MyDomain/
├── Aggregates/
├── Commands/
├── Contracts/
├── DataObjects/
├── Enums/
├── Events/
├── Models/
├── Projectors/
├── Queries/
├── Repositories/
├── Services/
└── Workflows/
```

## Domain Registry

All 56 domains and their dependencies:

### Core Domains (Always Installed)

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| Shared | - | CQRS interfaces, events, base classes |
| Account | Shared | Core banking account management |
| User | Shared | User management |
| Compliance | Shared, Account, User | KYC, AML, regulatory reporting |

### Financial Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| Exchange | Shared, Account, Compliance | Trading engine, order matching |
| Lending | Shared, Account, Compliance | P2P lending, credit scoring |
| Treasury | Shared, Account | Portfolio management, yield optimization |
| Stablecoin | Shared, Account, Compliance | Token minting, burning, liquidation |
| Wallet | Shared, Account | Multi-chain blockchain wallets |
| Payment | Shared, Account | Payment processing |
| Banking | Shared, Account, Compliance | SEPA/SWIFT transfers |
| CardIssuance | Shared, Account | Card-issuing integration adapters |

### Blockchain & Web3 Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| CrossChain | Shared, Wallet | Bridge protocol adapters (e.g. Wormhole/LayerZero/Axelar APIs; no partnership or endorsement implied), cross-chain swaps |
| DeFi | Shared, Wallet | DEX aggregation, lending, staking, yield optimization |
| Relayer | Shared, Wallet | ERC-4337 gas abstraction, smart accounts |
| Commerce | Shared, Account | Soulbound tokens, merchant onboarding, attestations |
| TrustCert | Shared, Compliance | W3C Verifiable Credentials, Certificate Authority |
| Privacy | Shared, Compliance | ZK-KYC, Proof of Innocence, Merkle trees |
| KeyManagement | Shared | Shamir's Secret Sharing, HSM integration |

### Mobile Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| Mobile | Shared, User | Device management, biometrics, push notifications |
| MobilePayment | Shared, Account | Payment intents, receipts, activity feed |

### AI & Agent Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| AI | Shared | MCP server, LLM integration |
| AgentProtocol | Shared, Account, Compliance | A2A messaging, escrow |

### Compliance & Regulation Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| RegTech | Shared, Compliance | Reporting and data-exchange modules modelled on MiFID II, MiCA and Travel Rule requirements |
| Regulatory | Shared, Compliance | Regulatory reporting |
| Fraud | Shared, Account, Compliance | Fraud detection |
| Security | Shared | Security scanning and hardening |

### Infrastructure Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| Monitoring | Shared | Observability dashboards, structured logging, health checks |
| Batch | Shared | Batch processing |
| Webhook | Shared | Webhook management |
| Performance | Shared | Performance tracking |

### Business Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| Governance | Shared, Account | Voting, proposals |
| Cgo | Shared, Account, Compliance | Continuous Growth Offering (demo module; not an offer of securities) |
| Basket | Shared, Account, Asset | Currency baskets (GCU demo) |
| Asset | Shared, Account | Asset management |
| Custodian | Shared, Account | Custodian integration adapters |
| FinancialInstitution | Shared, Compliance | FI management |

### Engagement Domains

| Domain | Dependencies | Description |
|--------|--------------|-------------|
| Activity | Shared, User | Activity logging |
| Contact | Shared, User | Contact management |
| Newsletter | Shared | Email newsletters |
| Product | Shared | Product catalog |

## Best Practices

### Starting Fresh

For a minimal installation:

```bash
# Core domains are always installed
# Add only what you need:
php artisan domain:install exchange
php artisan domain:install wallet
```

### Full Installation

For complete platform functionality:

```bash
# All domains are installed by default in standard setup
php artisan migrate --seed
```

### Domain Health Verification

After installation or updates:

```bash
php artisan domain:verify
php artisan config:cache
php artisan route:cache
```

## Troubleshooting

### Missing Dependencies

If you see "✗ Missing dependencies" in `domain:list`:

```bash
# Check what's missing
php artisan domain:dependencies <domain>

# Install missing dependencies
php artisan domain:install <dependency>
```

### Service Provider Not Registered

1. Verify domain's `ServiceProvider.php` exists
2. Check `config/app.php` for provider registration
3. Run `php artisan config:cache`

### Migration Issues

```bash
# Check migration status
php artisan migrate:status

# Run pending migrations
php artisan migrate
```

## Related Documentation

- [Domain Dependencies](../02-ARCHITECTURE/DOMAIN_DEPENDENCIES.md) - Detailed dependency hierarchy
- [Architecture Overview](../02-ARCHITECTURE/README.md) - DDD architecture patterns
- [v1.3.0 Implementation Plan](../V1.3.0_IMPLEMENTATION_PLAN.md) - Modular system design
