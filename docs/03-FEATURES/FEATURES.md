# FinAegis Platform Features

**Version:** 7.18.1
**Last Updated:** 2026-11-24
**Documentation Status:** Open-source reference platform, v7.18.1

This document lists the features implemented in the FinAegis Core Banking Platform. It includes the GCU demo, a reference implementation of a basket-referenced unit built with FinAegis, and the sub-product modules. Many of these modules run only in demo mode, so read the status section first.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
>
> Named third-party providers in this document: Integration adapters for third-party APIs; no partnership or endorsement implied.

## Table of Contents

- [Where Each Feature Runs](#where-each-feature-runs)
- [Core Banking Features](#core-banking-features)
- [Multi-Asset Support](#multi-asset-support)
- [Exchange Rate Management](#exchange-rate-management)
- [Basket Assets](#basket-assets)
- [Global Currency Unit (GCU)](#global-currency-unit-gcu)
- [Custodian Integration](#custodian-integration)
- [Governance System](#governance-system)
- [Admin Dashboard](#admin-dashboard)
- [API Endpoints](#api-endpoints)
- [Transaction Processing](#transaction-processing-2)
- [Performance Testing](#performance-testing)
- [Caching & Performance](#caching--performance)
- [Security Features](#security-features)
- [Compliance Features](#compliance-features)
- [Export & Reporting](#export--reporting)
- [Webhooks & Events](#webhooks--events)
- [September 2024 Feature Additions](#september-2024-feature-additions)
- [Getting Started](#getting-started)
- [Unified Platform Features (Phase 8)](#unified-platform-features-phase-8)
- [Liquidity Pools](#liquidity-pools)

---

## Where Each Feature Runs

FinAegis is an open-source reference platform, not a production bank. Zelta is the hosted non-custodial wallet product built on it. The production site runs only part of the platform, and the rest works in demo mode.

The production template `.env.zelta.example` sets these switches.

| Area | Production (Zelta) | Demo and local |
|------|--------------------|----------------|
| Zelta wallet API (Privy sign-in, send, receive, activity) | On | On |
| Bank transfer on-ramp (`RAMP_PROVIDER=bridge`, Bridge.xyz adapter). The off-ramp is not available yet. | On | Configurable |
| In-app purchase subscriptions (Apple, Google) | On | On |
| MCP server (14 catalog tools) | On, with 10 tools; `account.create`, `payment.transfer`, `exchange.trade` and `sms.send` are switched off | All tools configurable |
| Virtual cards (Lepo Finance closed pilot) | Closed pilot (`CARD_PROVIDERS_ENABLED=lepo`, `CARD_PROVIDER_LEPO_PILOT_ONLY=true`). Only users listed in `CARD_PROVIDER_LEPO_PILOT_USERS` can get a card. | Configurable (`CARD_PROVIDERS_ENABLED` empty means cards are not offered) |
| Card top-ups from the wallet | Off (`CARD_TOPUPS_ENABLED=false`) | Configurable |
| HyperSwitch card deposits | Off (`HYPERSWITCH_ENABLED=false`) | Configurable |
| Exchange and liquidity pools | Off (`TRADING_ENABLED=false`) | On |
| Direct ledger funding (deposit, withdraw, reverse and convert endpoints and the matching web wallet pages) | Off (`LEDGER_DIRECT_FUNDING_ENABLED=false`) | On |
| Promotional feature pages | Off (`SHOW_PROMO_PAGES=false`) | On |
| Banking standards: ISO 20022, ISO 8583, Open Banking, payment rails, Interledger, Ledger, Microfinance | Disabled (`MODULES_DISABLED`) | Message layer and demo |
| Banking, Basket, Cgo, CrossChain, DeFi, FinancialInstitution, Governance, Lending, Stablecoin, Treasury | Disabled (`MODULES_DISABLED`) | Demo |

The banking standards modules build and parse messages and run in demo mode. They are not connected to any bank, card scheme or payment network.

Any card services will be provided by licensed third parties under their own terms. `CARD_ISSUER` selects the Demo, Marqeta or FinCard adapter, and the Lepo provider runs through the provider-neutral card layer. A Rain adapter class exists but is not wired in. These are integration adapters; no partnership or endorsement implied.

---

## Core Banking Features

### User Management & Authentication
- **User registration** with email verification and hashed passwords
- **Sign-in** with Laravel Sanctum access and refresh tokens
- **Privy sign-in** for the Zelta app, which exchanges a Privy token for a Sanctum token
- **Passkeys** through WebAuthn
- **Two-factor authentication (2FA)** with recovery codes
- **Password reset** by email
- **Social login** with Google, Facebook and GitHub
- **Session management** with expiry and refresh
- **Role-based access control (RBAC)**
- **API key management** in the admin panel
- **Activity logging** for user actions
- **Last sign-in time** recorded in `users.last_login_at` since v7.18.0

### Account Management
- **Multi-user accounts** with authenticated access
- **Account lifecycle**: create, freeze, unfreeze and close
- **User-to-account relationships**
- **Account metadata** for custom information
- **Balance tracking** per asset

New accounts open empty. The public `POST /api/accounts` endpoint rejects a non-zero opening balance with an error.

### Transaction Processing
- **Deposits and withdrawals** with balance checks
- **Transfers** between accounts
- **Transaction history** with an audit trail
- **Event sourcing**, so every transaction can be rebuilt from events
- **Transaction reversal** for error correction

Asset transfers between accounts work since v7.18.1. Before that release they failed on a missing method, and their amounts used the wrong units. The direct deposit, withdrawal and reversal endpoints are switched off on the production site through `LEDGER_DIRECT_FUNDING_ENABLED=false`.

### Account States
- **Active** accounts work normally.
- **Frozen** accounts are held for compliance or security reasons.
- **Closed** accounts are terminated.
- State changes are validated.

---

## Multi-Asset Support

### Asset Types
- **Fiat currencies**: USD, EUR, GBP and CHF with 2 decimals, and JPY with none
- **Cryptocurrencies**: BTC with 8 decimals and ETH with 18
- **Commodities**: XAU (gold) with 3 decimals
- **Custom assets** through an extensible asset model

### Asset Management
- **Asset registration** with code, name, type and precision
- **Asset validation** for format and constraints
- **Asset metadata** for extra properties
- **Activation and deactivation** of assets

### Multi-Asset Balances
- **Balance tracking per asset** for each account
- **Automatic USD balance creation** for backward compatibility
- **Balance aggregation** across assets
- **Precision handling** per asset

### Cross-Asset Operations
- **Cross-asset transfers** that apply an exchange rate
- **Rate validation** that rejects stale rates
- **Transaction linking** for related cross-asset operations
- **Reference currency tracking** for audit

---

## Exchange Rate Management

### Rate Storage
- **Stored exchange rates** with timestamps
- **Bid and ask spreads**
- **Rate sources** such as manual, API, oracle and market
- **Rate expiry** with validation
- **Rate history** for audit

### Rate Providers
- **Manual rate entry** in the admin panel
- **Provider interface** for external feeds
- **Fixer.io adapter** for forex rates, and a mock provider for tests

The rate model can also label a rate as coming from an oracle or a market feed. No oracle or market provider is built for it.

### Rate Validation
- **Age checks** that reject stale rates
- **Active status checks** for disabled rates
- **Pair validation** for asset combinations
- **Reasonableness checks** against errors

### Caching
- **Redis rate caching** with a TTL
- **Cache invalidation** when rates change
- **Fallback** when the cache fails

---

## Basket Assets

The `Basket` module is disabled on the production site.

### Core Functionality
- **Composite assets** built from several underlying assets
- **Fixed and dynamic baskets** with different rebalancing rules
- **Weighted composition** with percentage allocations
- **Scheduled rebalancing** for dynamic baskets
- **Valuation** from component exchange rates

### Basket Management
- **Basket definition** with code, name and description
- **Components** with weights and minimum and maximum bounds
- **Rebalancing schedules**: daily, weekly, monthly or quarterly
- **Active and inactive status**
- **Metadata** for custom properties

### Performance Tracking
- **Metrics** including returns, volatility and the Sharpe ratio
- **Time periods** from an hour to a year
- **Component attribution** for each asset
- **Maximum drawdown**
- **Comparison** with other baskets

### API Endpoints
- **GET /api/v2/baskets** lists baskets.
- **GET /api/v2/baskets/{code}** returns one basket.
- **POST /api/v2/baskets** creates a basket (admin).
- **POST /api/v2/baskets/{code}/rebalance** triggers rebalancing.
- **GET /api/v2/baskets/{code}/value** returns the current value.
- **GET /api/v2/baskets/{code}/history** returns historical values.
- **POST /api/v2/baskets/{code}/performance/calculate** calculates performance.
- **GET /api/v2/baskets/{code}/performance** returns performance metrics.
- **GET /api/v2/baskets/{code}/performance/components** returns the component breakdown.

### Admin Features
- **Basket asset resource** for create, read, update and delete
- **Performance widgets** for returns and volatility
- **Component weight charts**
- **Rebalancing history**

### Basket Operations
- **Decomposition** turns basket holdings into the underlying assets.
- **Composition** combines underlying assets into basket units.
- **Value calculation** prices the basket from its components.
- **Performance calculation** generates the metrics.
- **Rebalancing** adjusts the weights.

---

## Global Currency Unit (GCU)

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

The GCU demo uses the `Basket` and `Governance` modules, which are disabled on the production site.

### GCU Demo
- **GCU demo**: reference implementation of a basket-referenced unit built with FinAegis
- **Simulated governance**: demo basket "votes" on composition
- **Simulated multi-custodian allocation** (demo only)
- **Value calculation** from the basket composition

### GCU Wallet Interface
- **Demo dashboard** with a simulated GCU balance
- **Basket component breakdown**
- **Quick actions** for simulated conversion and transfer
- **Transaction history** for GCU operations
- **Value history charts**

### Bank Allocation Management (demo)
- **Allocation page** with sliders
- **Multi-custodian preference interface** (demo; simulated allocations)
- **Primary custodian designation** (demo)
- **Validation** that the allocation adds up to 100%

### Simulated Voting Module (demo)
- **Monthly basket composition polls** (simulated); `voting:setup` creates next month's poll on the 20th
- **Voting weight** based on simulated demo balances
- **Voting page** with allocation sliders
- **Live results** during active polls
- **Scheduled rebalancing** by `baskets:rebalance` on the 1st of each month
- **Vote history**

### Basket Composition
`GCUBasketSeeder` seeds the demo basket with six components.
- USD (US Dollar): 40%, allowed range 30–50%
- EUR (Euro): 30%, allowed range 20–40%
- GBP (British Pound): 15%, allowed range 10–20%
- CHF (Swiss Franc): 10%, allowed range 5–15%
- JPY (Japanese Yen): 3%, allowed range 0–10%
- XAU (Gold): 2%, allowed range 0–5%

The simulated votes are combined into a weighted average, and the basket is rebalanced from the result.

### GCU Operations
- **Simulated GCU conversions** (demo balances only)
- **GCU demo transfers** between demo users
- **Cross-currency conversion module** with a configurable fee

Since v7.18.1, a simulated GCU buy or sell books the right amounts on the demo balances. The quote and fee that the trading API shows still differ from what the conversion books. Aligning them needs a product decision.

### GCU API Endpoints
- **GET /api/v2/gcu** returns GCU information.
- **GET /api/v2/gcu/composition** returns the basket composition.
- **GET /api/v2/gcu/value-history** returns historical values.
- **GET /api/v2/gcu/quote** returns a simulated conversion quote.
- **POST /api/v2/gcu/buy** runs a simulated conversion into GCU demo units.
- **POST /api/v2/gcu/sell** runs a simulated conversion out of GCU demo units.
- **GET /api/voting/polls** lists governance polls.
- **POST /api/voting/polls/{uuid}/vote** submits a simulated basket composition vote.

---

## Custodian Integration

The custodian layer is a framework for bank connector adapters. FinAegis has no live connection to any bank. The mock bank connector runs only in local and test environments, or in demo mode when `CUSTODIAN_MOCK_ENABLED=true` is set, and never in production.

### Custodian Abstraction
- **`ICustodianConnector` interface** for a common integration shape
- **Balance checks** across custodians
- **Transfer initiation** with authorisation
- **Status tracking** for asynchronous operations

### Mock Implementations
- **`MockBankConnector`** for development and testing
- **Simulated delays** for realistic tests
- **Error simulation** for failure tests
- **Transaction receipts** for tracking

### Registry System
- **Runtime registration** of custodians
- **Discovery** of available connectors
- **Configuration** per custodian
- **Health monitoring** per custodian

### Transaction Processing
- **Saga pattern** for consistency
- **Compensation logic** for failed transactions
- **Retries** with exponential backoff
- **Detailed error logging**

---

## Governance System

The `Governance` module is disabled on the production site.

### Poll Management
- **Poll creation** with several question types
- **Poll lifecycle**: draft, pending, active, closed, executed, cancelled and failed
- **Scheduling** with start and end dates
- **Poll metadata**

### Voting System
- **Authenticated voting**
- **Voting power strategies**: one user one vote, or weighted by assets
- **Double-vote prevention**

### Poll Types
- **Single choice**
- **Multiple choice**
- **Weighted choice**
- **Yes or no**
- **Ranked choice**

### Result Processing
- **Live result calculation** as votes come in
- **Participation tracking** with thresholds
- **Winning threshold checks**
- **Result caching**

### Workflow Integration
- **Execution of poll results** through workflows
- **Asset addition workflows** triggered by polls
- **Configuration changes** from governance decisions
- **Audit trail** for governance actions

---

## Admin Dashboard

The admin panel at `/admin` is built with Filament 3.3. Only administrators can open it, and other users are sent back to `/dashboard`. Resources and widgets are grouped by module, and `ADMIN_MODULES` controls which groups appear.

### Overview Dashboard
- **System health** metrics
- **Account statistics**
- **Transaction volume** charts
- **Asset distribution**

### Account Management
- **Account list** with filters
- **Account details** with history
- **Multi-asset balances**
- **Bulk actions**

### User Wallet Visibility
- **Read-only relation managers** on the user page for wallet addresses, blockchain transactions and wallet sends

### Asset Administration
- **Asset create, read, update and delete** with validation
- **Exchange rate monitoring** with age indicators
- **Asset statistics**

### Transaction Monitoring
- **Event-sourced transaction history** read from the event store
- **Multi-asset filters**
- **Hash verification** of the audit trail

### Governance Interface
- **Poll creation** with form validation
- **Poll management** with status tracking
- **Results** and participation metrics

### Export Functionality
- **Account, transaction and user exports** through Filament exporters

---

## API Endpoints

The full, generated API reference is at `/api/documentation`.

### Authentication APIs
```
POST /api/auth/register # Register new user
POST /api/auth/login # User login
POST /api/auth/logout # User logout
POST /api/auth/logout-all # Sign out of every session
POST /api/auth/refresh # Refresh access token
POST /api/auth/forgot-password # Request password reset
POST /api/auth/reset-password # Reset password with token
GET /api/auth/verify-email/{id}/{hash} # Verify email address
POST /api/auth/resend-verification # Resend verification email
GET /api/auth/user # Get current user profile
GET /api/auth/me # Get current user
POST /api/auth/2fa/enable # Enable two-factor auth
POST /api/auth/2fa/confirm # Confirm two-factor setup
POST /api/auth/2fa/verify # Verify 2FA code
POST /api/auth/2fa/disable # Disable two-factor auth
POST /api/auth/2fa/recovery-codes # Regenerate recovery codes
POST /api/v1/auth/privy-login # Exchange a Privy token for a Sanctum token
```

### Account APIs
```
GET /api/accounts # List accounts
POST /api/accounts # Create account
GET /api/accounts/{uuid} # Get account details
DELETE /api/accounts/{uuid} # Delete account
POST /api/accounts/{uuid}/deposit # Deposit to account
POST /api/accounts/{uuid}/withdraw # Withdraw from account
POST /api/accounts/{uuid}/freeze # Freeze account
POST /api/accounts/{uuid}/unfreeze # Unfreeze account
GET /api/accounts/{uuid}/balance # Get account balance
```

### Asset APIs
```
GET /api/v2/assets # List available assets
GET /api/v2/assets/{code} # Get asset details
POST /api/v2/assets # Create new asset (admin)
PUT /api/v2/assets/{code} # Update asset (admin)
DELETE /api/v2/assets/{code} # Delete asset (admin)
GET /api/v1/assets # List assets (v1)
```

### Exchange Rate APIs
```
GET /api/exchange-rates # List exchange rates
GET /api/exchange-rates/{from}/{to} # Get specific rate
GET /api/exchange-rates/{from}/{to}/convert # Convert amounts
```

### Balance APIs
```
GET /api/accounts/{uuid}/balances # Multi-asset balances
GET /api/accounts/{uuid}/balance/summary # Balance summary
GET /api/balances # All balances (admin)
```

### Transaction APIs
```
GET /api/accounts/{uuid}/transactions # List transactions
POST /api/accounts/{uuid}/transactions/reverse # Reverse transaction
GET /api/accounts/{uuid}/transactions/reversals # List reversals
GET /api/transactions/reversals/{reversalId}/status # Reversal status
```

### Transfer APIs
```
POST /api/transfers # Create transfer
GET /api/transfers/{uuid} # Get transfer status
GET /api/accounts/{uuid}/transfers # Transfer history
```

### Governance APIs
```
GET /api/polls # List polls
GET /api/polls/active # List active polls
POST /api/polls # Create poll
GET /api/polls/{uuid} # Get poll details
POST /api/polls/{uuid}/vote # Cast vote
GET /api/polls/{uuid}/results # Get poll results
POST /api/polls/{uuid}/activate # Activate poll
GET /api/polls/{uuid}/voting-power # Get voting power
```

Deposits, withdrawals and reversals through these endpoints move ledger balances directly. They are switched off on the production site through `LEDGER_DIRECT_FUNDING_ENABLED=false`, together with `POST /api/custodians/{custodian}/transfer`.

### Custodian APIs
```
GET /api/custodians # List custodians
GET /api/custodians/{custodian}/account-info # Get custodian account info
GET /api/custodians/{custodian}/balance # Get custodian balance
POST /api/custodians/{custodian}/transfer # Initiate transfer
GET /api/custodians/{custodian}/transactions # Get transaction history
GET /api/custodians/{custodian}/transactions/{transactionId} # Get one transaction
```

---

## Transaction Processing

### Event Sourcing Architecture
- **Audit trail** of financial operations stored as immutable events
- **Event replay** for recovery and debugging
- **Events as the primary data store**
- **Event versioning** for backward compatibility

### Transaction History from Events
- **Event store queries** for transaction history
- **Multi-asset events** such as `AssetBalanceAdded` and `AssetTransferred`
- **Legacy events** such as `MoneyAdded` and `MoneySubtracted`
- **No duplicate read model**, because the events are the source

### CQRS with Projections
- **Command side** writes through aggregates.
- **Read models** exist only where aggregation is needed, such as turnover summaries.
- **Queries** read directly from the event store.
- **Balance projections** keep the current state.

### Saga Pattern
- **Coordination** of multi-step transactions
- **Compensation** for failed steps
- **State management** for long-running workflows
- **Rollback** on errors

---

## Performance Testing

### Load Testing Framework
- **Load test suite** with benchmarks
- **`test:load` command** for isolated performance tests
- **`test:compare-benchmarks` command** for comparing runs
- **k6 scripts** in `tests/k6/`

### Performance Targets
- **Account creation**: under 100 ms on average
- **Transfer processing**: under 200 ms on average
- **Exchange rate lookup**: under 50 ms on average
- **Cache reads and writes**: under 1 ms

These are targets, not measured guarantees.

### Optimization Features
- **Eager loading** in queries
- **Database indexes** on critical paths
- **Response compression** for API endpoints

---

## Caching & Performance

### Redis Integration
- **Account balance caching** with a TTL
- **Transaction caching** for frequent reads
- **Exchange rate caching**
- **Governance result caching**

### Cache Strategies
- **Cache invalidation** when data changes
- **Cache warming** for critical data
- **Fallback** when the cache fails

### Monitoring
- **Cache hit rate** metrics
- **Response time** monitoring

---

## Security Features

### Authentication & Authorization
- **Laravel Sanctum** for API authentication with access and refresh tokens
- **Token abilities** (`read`, `write`, `delete`) checked per HTTP method
- **Token expiry** with refresh and revocation
- **Session limits** per user
- **IP blocking** with an allowlist
- **API rate limiting** per user and IP address
- **Social login** with Google, Facebook and GitHub
- **Passkeys** through WebAuthn

### Access Control
- **Role-based access control (RBAC)** with roles and permissions
- **Resource-level authorisation**
- **Admin-only operations** behind middleware
- **User data isolation** through scoped queries
- **Team-based multi-tenancy** with stancl/tenancy 3.10

### Cryptographic Security
- **SHA3-512 hashes** for transaction integrity
- **HMAC signatures** for webhook security
- **Encryption** for sensitive stored data
- **Post-quantum cryptography** with ML-KEM-768, ML-DSA-65 and hybrid encryption

### Security Testing Suite
- **SQL injection tests**
- **XSS protection tests**
- **CSRF protection tests**
- **Authentication tests** for brute force and timing attacks
- **Authorisation tests** for RBAC, privilege escalation and IDOR
- **API security tests** for rate limiting, input validation and error handling
- **Cryptography tests** for password hashing and encryption
- **Input validation tests** for boundary values, Unicode and file uploads

### Security Practice
- **Security headers** such as CSP, X-Frame-Options and HSTS
- **Automated security checks** in CI
- **Security fixes** listed per release in [CHANGELOG.md](../../CHANGELOG.md)

No external security certification is held.

### Audit Trails
- **Operation logging**
- **User action tracking** with timestamps
- **Security event monitoring**

---

## Compliance Features

Compliance modules are readiness tooling. They help an operator build compliance processes. No certification or licence is held, and the reports are not filed with any regulator.

### KYC (Know Your Customer)
- **Verification levels**: basic, enhanced and full
- **Document management** for identity checks
- **Status tracking** for documents
- **Account limits** linked to the verification level
- **Periodic review scheduling**
- **Identity-verification adapters** such as Ondato (`users.kyc_status`) and the Bridge.xyz KYC link (`bridge_customers.kyc_status`)

### AML (Anti-Money Laundering)
- **Transaction monitoring** with pattern rules
- **Suspicious activity detection** with a rule engine
- **Sanctions screening adapters**
- **Risk scoring** for customers and transactions
- **Fraud cases** on the `fraud_cases` table, keeping the fraud score that opened each case

### GDPR Tooling
- **Data export** for user requests
- **Deletion** with anonymisation
- **Consent management**
- **Data retention policies** with cleanup
- **Audit trail** for data access

### Regulatory Reporting
- **Currency Transaction Report (CTR)** drafts for the daily threshold
- **Suspicious Activity Report (SAR)** drafts for review
- **Scheduled report generation**
- **Compliance dashboard**
- **Report archive**

### Audit & Compliance
- **Audit trail** for all operations
- **Compliance dashboard** with key metrics
- **Alerts** for threshold breaches
- **Access reviews** that use `users.last_login_at`

---

## Export & Reporting

### Export Formats
- **CSV** for spreadsheets
- **XLSX** with formatting
- **JSON** for system integration

### Export Types
- **Account data** with balances and metadata
- **Transaction history**
- **User information** with privacy controls
- **Audit logs** through `GET /api/audit/logs/export`
- **GDPR exports** through `POST /api/compliance/gdpr/export`

### Data Privacy
- **Anonymisation** options
- **Consent tracking**
- **GDPR tooling**
- **Data retention** policies

---

## Webhooks & Events

### Webhook Management
- **Create, read, update and delete** webhooks through `/api/v2/webhooks`
- **Event subscriptions** for chosen event types (`GET /api/v2/webhooks/events` lists them)
- **URL validation** for endpoints
- **Secrets** for signature verification, stored encrypted
- **Custom headers** per webhook
- **Active and inactive status**
- **Delivery history** through `GET /api/v2/webhooks/{id}/deliveries`

### Webhook Delivery
- **Queued delivery** through the queue workers
- **Retries** with exponential backoff, up to the webhook's `retry_attempts`
- **Delivery tracking** for every attempt
- **HMAC-SHA256 signatures**
- **Timeouts** per webhook

### Event Types
`GET /api/v2/webhooks/events` lists these event types.
- **Account events**: created, updated, frozen, unfrozen and closed
- **Transaction events**: created, completed, failed and reversed
- **Transfer events**: initiated, completed and failed
- **Basket events**: created, rebalanced and decomposed
- **Governance events**: poll created, poll activated, poll completed and vote cast
- **Exchange rate events**: rate updated and rate stale

### Inbound Webhooks
The platform also receives webhooks from integration adapters. Examples are Helius (`/api/webhooks/helius/solana`), Alchemy (`/api/webhooks/alchemy/address-activity`), Bridge.xyz (`/api/v1/webhooks/bridge`), Lepo Finance (`/api/v1/webhooks/lepo`), FinCard (`/api/v1/webhooks/fincard`), Apple App Store and Google Play.

---

## September 2024 Feature Additions

This section is a historical record of the September 2024 work.

### GCU Demo Voting Module
- **Monthly voting templates** for the basket composition
- **Asset-weighted voting module** (simulated demo votes)
- **Voting dashboard**
- **Basket rebalancing** from vote results
- **REST API** for voting

### Security Implementation
- **Two-factor authentication (2FA)**
- **OAuth2 social login**
- **Password reset flow**
- **Email verification**

### GCU Demo Conversion Module
- **Simulated conversions** for the GCU demo
- **Order management**
- **Trading history**
- **Price updates**

### Subscriber Management System
- **Newsletter subscribers**
- **Campaign management**
- **Subscriber metrics**
- **Email preferences**

### Platform Improvements
- **Browser tests** for critical paths
- **Navigation reorganisation**

---

## Getting Started

### Prerequisites
- PHP 8.4
- Laravel 12
- MySQL 8.0 or MariaDB
- Redis 7
- Node.js 20

### Installation
```bash
# Clone repository
git clone https://github.com/FinAegis/core-banking-prototype-laravel.git

# Install dependencies
composer install
npm install

# Configure environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Optional: create the demo users (demo.user@gcu.global and others, password demo123)
php artisan demo:populate

# Build assets
npm run build

# Start services
php artisan serve
php artisan queue:work
```

`php artisan migrate --seed` does not create demo users. `php artisan demo:populate` does.

### API Authentication Examples
```bash
# Register new user
curl -X POST /api/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Login to get access token
curl -X POST /api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "john@example.com", "password": "password123"}'

# Response includes a token pair:
# {
#   "success": true,
#   "data": {
#     "user": { ... },
#     "access_token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...",
#     "refresh_token": "2|...",
#     "token_type": "Bearer",
#     "expires_in": 86400,
#     "refresh_expires_in": 2592000
#   }
# }

# Get user profile
curl -X GET /api/auth/user \
  -H "Authorization: Bearer TOKEN"

# Create account (authenticated; accounts open empty)
curl -X POST /api/accounts \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"name": "My Savings Account"}'

# Check account balance
curl -X GET /api/accounts/UUID/balance \
  -H "Authorization: Bearer TOKEN"

# Logout
curl -X POST /api/auth/logout \
  -H "Authorization: Bearer TOKEN"

# Reset password
curl -X POST /api/auth/forgot-password \
  -H "Content-Type: application/json" \
  -d '{"email": "john@example.com"}'
```

---

## Unified Platform Features (Phase 8)

The Phase 8 modules are built, but they run as demo modules. The production site disables them or keeps them behind switches that are off.

### Exchange
- **Multi-asset support** for fiat currencies and BTC and ETH
- **Exchange engine** with an event-sourced order book and workflow-based matching
- **External exchange connectors** for the Binance and Kraken APIs (integration adapters for third-party APIs; no partnership or endorsement implied)
- **Liquidity pools** with an automated market maker, without the add and remove liquidity flow that was removed in v7.18.0
- **Rate feeds** from several crypto and forex sources
- **Arbitrage detection** across exchanges
- **Price alignment** with external markets

The exchange runs only when `TRADING_ENABLED` is on. The arbitrage and price alignment web pages do not work at v7.18.1, and switching on the scheduled arbitrage check breaks the application start. See [EXCHANGE.md](EXCHANGE.md), [EXTERNAL-EXCHANGE-CONNECTORS.md](EXTERNAL-EXCHANGE-CONNECTORS.md) and [LIQUIDITY-POOLS.md](LIQUIDITY-POOLS.md).

### Stablecoin Module
- **Multi-token framework** for several stablecoins
- **Collateralised positions** with health monitoring
- **Minting and burning** with limits
- **Liquidation** at configurable thresholds
- **Oracle price aggregation**
- **Emergency pause**

The `Stablecoin` module is disabled on the production site.

### P2P Lending Module
- **Loan marketplace** workflows for lenders and borrowers
- **Loan origination** with applications, credit scoring and approval
- **Risk scoring** and categorisation
- **Interest calculation** based on the risk profile
- **Repayment schedules**
- **Default management** and recovery workflows

The `Lending` module is disabled on the production site.

### Blockchain Infrastructure
- **Multi-chain wallets** for Bitcoin, Ethereum, Polygon and BSC
- **HD wallet generation** following BIP44
- **Hardware wallets**: Ledger Nano S and X, Trezor One and Model T
- **Multi-signature wallets** with 2 to 10 signers
- **Key encryption** with password-based derivation
- **Transaction signing** and broadcasting
- **Gas estimation** with EIP-1559 support
- **Balance monitoring** across chains

These wallet services belong to the reference platform, and the Zelta wallet does not use them. Zelta is non-custodial. The person's device signs every transaction, and the server never sees private keys.

### Platform Integration
All modules live in one codebase and share the user accounts, the ledger and the compliance services. Modules exchange information through domain events, and the ledger records them with event sourcing.

---

## Support & Documentation

- **API documentation**: `/api/documentation`
- **GraphQL endpoint**: `/graphql`
- **Admin dashboard**: `/admin`
- **Release notes**: [CHANGELOG.md](../../CHANGELOG.md)
- **GitHub repository**: https://github.com/FinAegis/core-banking-prototype-laravel
- **Issue tracker**: https://github.com/FinAegis/core-banking-prototype-laravel/issues
- **Discussions**: https://github.com/FinAegis/core-banking-prototype-laravel/discussions

---

## Liquidity Pools

Liquidity pools are part of the demo exchange and run only when `TRADING_ENABLED` is on. See [LIQUIDITY-POOLS.md](LIQUIDITY-POOLS.md) for details.

### What stays
- **Pool creation** for an asset pair with a configurable fee
- **Swaps** priced with the constant product formula (`x * y = k`)
- **Positions** with shares and pending rewards
- **Rewards** distributed every hour by `liquidity:distribute-rewards`
- **Impermanent-loss tracking and protection**
- **Pool analytics**
- **Market-making orders** placed every five minutes by `liquidity:update-market-making`

### Removed in v7.18.0
The flow for adding, removing and rebalancing liquidity was removed in v7.18.0. It never worked, because it used a class, a table and columns that do not exist. The `liquidity:rebalance` command and `PoolRebalancingService` were removed with it.

A new pool starts empty, and since v7.18.0 no API adds liquidity to it. A swap therefore needs a pool whose reserves were set up some other way.

### Not built
Weighted pools, stable pools and concentrated liquidity are not implemented.

---

**Last Updated**: 2026-11-24
