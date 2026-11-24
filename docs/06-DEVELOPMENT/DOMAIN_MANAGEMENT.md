# Domain Management Guide

This guide covers the modular domain system that was introduced in v1.3.0. It explains how to list, install, verify, enable and disable domains.

## Overview

FinAegis uses Domain-Driven Design (DDD). The platform has 61 domain folders in `app/Domain/`, and each one is a bounded context. Each domain describes itself in a `module.json` manifest with its name, version, type and dependencies. The `DomainManager` in `app/Infrastructure/Domain/` reads these manifests.

## Domain Types

| Type | Description | Examples |
|------|-------------|----------|
| **Core** | Always installed and cannot be removed | `shared`, `account`, `user`, `compliance` |
| **Optional** | Can be installed or removed as needed | `exchange`, `lending`, `treasury`, `wallet` |

Eighteen manifests use a third type, `feature`. The `DomainType` enum accepts only `core` and `optional`, so `DomainManager` logs a warning and skips those manifests. As a result, the `domain:*` commands do not list or manage them. Their routes and services still load as usual.

## Installing and enabling are different things

All 61 domains ship in the codebase. Their migrations live in `database/migrations/` and run with `php artisan migrate`. Many manifests declare their own migrations directory or config file, but none of those paths exist. `domain:install` and `domain:remove` therefore only change the list of installed domains. That list is kept in the cache for one hour.

Turning a domain off is done with `MODULES_DISABLED`. It takes a comma-separated list of domain folder names, for example `MODULES_DISABLED="Lending,Treasury"`. The match ignores case and also accepts full manifest names such as `finaegis/lending`. A disabled domain keeps its tables, but `ModuleRouteLoader` does not load its `Routes/api.php`. The demo-domain jobs in `routes/console.php` check the same flag, so they are not scheduled either. The production Zelta deployment disables the standards and demo domains this way:

```env
MODULES_DISABLED="Banking,Basket,Cgo,CrossChain,DeFi,FinancialInstitution,Governance,ISO20022,ISO8583,Interledger,Ledger,Lending,Microfinance,OpenBanking,PaymentRails,Stablecoin,Treasury"
```

## Available Commands

### List All Domains

```bash
php artisan domain:list
php artisan domain:list --type=optional
php artisan domain:list --status=disabled
php artisan domain:list --json
```

The list shows each domain with its status, type, version and dependencies. The status is one of `installed`, `available`, `disabled` or `missing_dependencies`.

### Show Domain Health

```bash
php artisan domain:status
php artisan domain:status Exchange --models --events
```

`domain:status` gives a health overview of the domains with their models, events and projectors.

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
php artisan domain:dependencies exchange --flat
php artisan domain:dependencies exchange --json
```

This shows the dependency tree of a domain, which means the other domains it requires.

### Verify Domain Health

```bash
php artisan domain:verify
php artisan domain:verify exchange --json
```

Without an argument, the command checks all domains. For each domain it checks that the manifest is valid, that the domain is installed and that its required dependencies are installed. It also checks that a service provider exists, that the declared interfaces exist, and that any declared routes or config file exists.

### Remove a Domain

```bash
php artisan domain:remove newsletter
php artisan domain:remove newsletter --dry-run
```

The command checks dependencies first. It refuses to remove a core domain. It also refuses to remove a domain that other installed domains depend on, unless you pass `--force`. Removing a domain does not delete its code.

### Enable or Disable a Domain at Runtime

```bash
php artisan module:disable Lending
php artisan module:enable Lending
```

`module:disable` stops a domain's routes from loading and keeps its migrations. `module:enable` turns it back on. Both commands write the `domain_manager_disabled` cache key for one hour. While that key is set, the route loader and the module status use it instead of `MODULES_DISABLED`. For a lasting change, set `MODULES_DISABLED` in `.env`.

The matching admin endpoints (`/api/v2/modules/{name}/enable` and `/disable`) and the admin panel actions are guarded by `ADMIN_RUNTIME_MANAGEMENT_ENABLED`. It is on by default only for `APP_ENV=local`, `testing` and `demo`.

### Create a New Domain

```bash
php artisan domain:create Subscription
php artisan domain:create Subscription --type=optional --description="Subscriptions" --with-events --with-workflows --with-tests
```

The command scaffolds a new domain in `app/Domain/<Name>/`. It creates a `module.json` manifest, a service provider, a base exception, a sample service and a sample contract:
```
app/Domain/MyDomain/
├── Contracts/
├── DataObjects/
├── Enums/
├── Exceptions/
├── Models/
├── Providers/
├── Repositories/
├── Services/
└── module.json
```

`--with-events` adds an `Events/` directory with a sample event. `--with-workflows` adds `Workflows/` and `Workflows/Activities/`. `--with-tests` creates `tests/Domain/<Name>/`.

After creating a domain, register its service provider in `bootstrap/providers.php` and run `php artisan domain:verify`. Add the domain's GraphQL schema to `graphql/schema.graphql` if it has one, and add its environment variables to `.env.production.example` and `.env.zelta.example`.

## Domain Registry

These are all 61 domains with the required dependencies from their manifests.

*Named third-party providers: Integration adapters for third-party APIs; no partnership or endorsement implied.*

### Core domains

| Domain | Required dependencies | Description |
|--------|-----------------------|-------------|
| Account | Shared | Accounts, balances and transactions |
| AccountProvisioning | Shared, User | Operator-only reviewer and demo accounts with scoped bypasses |
| Compliance | Shared, Account, User | KYC and AML checks, risk assessment and regulatory monitoring |
| KeyManagement | Shared, Account | Shamir's Secret Sharing, HSM adapters and key rotation |
| Ledger | Shared | Double-entry ledger with pluggable drivers |
| Mobile | Shared, Account, User | Mobile devices, biometrics, passkeys, push notifications and sessions |
| RegTech | Shared, Compliance, Account | Reporting modules modelled on MiFID II, MiCA and Travel Rule requirements |
| Security | Shared, Account | Security audit checks and scanning |
| Shared | - | Shared contracts, CQRS infrastructure and utilities |
| User | Shared | User identity, authentication and profiles |

### Optional domains

| Domain | Required dependencies | Description |
|--------|-----------------------|-------------|
| AI | Shared | AI framework with LLM integration, MCP tools and conversation management |
| Activity | Shared, User | Activity logging and user action tracking |
| AgentProtocol | Shared, Account, Compliance | Agent payment protocol (AP2) with escrow, reputation and verification |
| Asset | Shared, Account | Assets, asset transfers and exchange rates |
| Banking | Shared, Account, Compliance | Bank transfer adapters for SEPA, SWIFT and Open Banking |
| Basket | Shared, Account, Asset | Asset baskets, including the GCU demo basket |
| Batch | Shared | Batch processing for bulk operations and scheduled jobs |
| CardIssuance | Shared, Wallet, Banking, Payment | Virtual card issuing through provider adapters |
| Cgo | Shared, Account, Compliance | CGO demo module (not an offer of securities) |
| Commerce | Shared, Wallet, Account, Compliance | Soulbound tokens, merchant onboarding and payment attestations |
| Contact | Shared, User | Contact management and address book |
| CrossChain | Shared, Wallet, Asset | Bridge protocol adapters and cross-chain swaps |
| Custodian | Shared, Account | Custodian and bank integration adapters and their webhooks |
| DeFi | Shared, Wallet, Asset | DEX aggregation, lending and staking adapters and position tracking |
| Exchange | Shared, Account, Compliance | Order matching, liquidity pools and market making |
| FinancialInstitution | Shared, Compliance | Financial institution onboarding and partner management |
| Fraud | Shared, Account, Compliance | Fraud detection, behavioural profiling and anomaly checks |
| Governance | Shared, Account | Polls, proposals and weighted voting |
| Lending | Shared, Account, Compliance | Loan origination and repayment |
| MobilePayment | Shared, Mobile, Wallet, Payment, Account | Payment intents, receipts, activity feed and receive addresses |
| Monitoring | Shared | Metrics, alerts and health checks |
| Newsletter | Shared | Newsletter subscriptions and campaigns |
| Payment | Shared, Account | Payment processing with several gateways |
| Performance | Shared | Performance tracking and profiling |
| Privacy | Shared, Account, Wallet | ZK-KYC, Proof of Innocence, Merkle trees and RAILGUN support services |
| Product | Shared | Financial product catalog |
| Regulatory | Shared, Compliance | Regulatory reports and simulated filing |
| Relayer | Shared, Wallet, Account | ERC-4337 gas sponsorship, smart accounts and paymaster integration |
| Stablecoin | Shared, Account, Compliance | Stablecoin minting, burning and reserve tracking |
| Treasury | Shared, Account | Portfolio management and cash allocation |
| TrustCert | Shared, Account, Wallet, Compliance | W3C Verifiable Credentials and certificate authority |
| Wallet | Shared, Account | Multi-chain blockchain wallets and non-custodial wallet send |
| Webhook | Shared | Webhook delivery with retries |

### Domains with type "feature"

| Domain | Required dependencies | Description |
|--------|-----------------------|-------------|
| Auth | Shared | Privy JWT verification, biometric JWT and device binding |
| ISO20022 | Shared | ISO 20022 message construction and validation |
| ISO8583 | Shared | ISO 8583 card message packing, parsing and validation |
| Interledger | Shared | Interledger STREAM payments and SPSP endpoints |
| MCP | Shared | Public MCP server with OAuth-scoped tools at mcp.zelta.app |
| MachinePay | Shared | Machine Payments Protocol (MPP) with HTTP 402 payments |
| Microfinance | Shared | Group lending, savings circles and micro-loans |
| OpenBanking | Shared | Open Banking account information and payment initiation messages |
| PaymentRails | Shared | US instant payment rail messages (FedNow, RTP) over ISO 20022 |
| Pricing | Shared | Subscription price quotes signed with HMAC |
| Ramp | Shared | Fiat to crypto on-ramp and off-ramp (Bridge.xyz in production) |
| Referral | Shared | Referral codes, tracking and rewards |
| Rewards | Shared | Quests, points and a rewards shop |
| SMS | Shared, MachinePay | Paid SMS sending gated by x402 or MPP |
| Subscription | Shared | Subscriptions and in-app purchases (Apple and Google) |
| VirtualsAgent | Shared | Virtuals Protocol agent commerce with spending limits |
| VisaCli | Shared | Programmatic Visa card payments for AI agents |
| X402 | Shared | x402 HTTP 402 payments with USDC |

## Best Practices

### Standard Installation

In a standard setup all domains are present. Run the migrations once:

```bash
php artisan migrate --seed
```

To turn domains off, list them in `MODULES_DISABLED`.

### After Installation or Updates

```bash
php artisan domain:verify
php artisan config:cache
php artisan route:cache
```

## Troubleshooting

### Missing Dependencies

If `domain:list` shows the status `missing_dependencies`, check what is missing and install it:

```bash
# Check what's missing
php artisan domain:dependencies <domain>

# Install missing dependencies
php artisan domain:install <dependency>
```

### Service Provider Not Registered

1. Check that the domain has a `Providers/` directory or a `DomainServiceProvider.php`.
2. Check that the provider is registered in `bootstrap/providers.php`.
3. Run `php artisan config:cache`.

### Routes Still Missing After Enabling a Domain

Check `MODULES_DISABLED` in `.env`. Then check whether a runtime `module:disable` left the `domain_manager_disabled` cache key behind. Clear it with `php artisan cache:clear` or run `php artisan module:enable <domain>`.

### Migration Issues

```bash
# Check migration status
php artisan migrate:status

# Run pending migrations
php artisan migrate
```

## Related Documentation

- [Domain Dependencies](../02-ARCHITECTURE/DOMAIN_DEPENDENCIES.md) describes the dependency hierarchy in detail.
- [Architecture Overview](../02-ARCHITECTURE/README.md) describes the DDD architecture patterns.
- [v1.3.0 Implementation Plan](../archive/V1.3.0_IMPLEMENTATION_PLAN.md) is the archived design of the modular system.
