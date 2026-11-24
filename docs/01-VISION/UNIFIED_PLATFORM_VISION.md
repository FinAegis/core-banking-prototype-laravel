# FinAegis Unified Platform Vision

## Overview

FinAegis is an open-source reference platform. It shows how several financial products can be built on one shared core. It is not a production bank. Zelta is the hosted non-custodial wallet product built on FinAegis, and it uses only part of the platform.

**Core concept demonstration**
- **GCU demo.** A reference implementation of a basket-referenced unit built with FinAegis.

**Additional pattern demonstrations**
- **FinAegis Exchange.** An example architecture for multi-currency and crypto trading.
- **FinAegis Lending.** Patterns and workflows for peer-to-peer lending.
- **FinAegis Stablecoins.** A demonstration of stable token issuance concepts.
- **FinAegis Treasury.** Examples of multi-bank allocation patterns.

All demonstrations use the same core platform, so they show how code can be reused across products.

These modules run in demo mode. The production site disables the Banking, Basket, Governance, Lending, Stablecoin and Treasury modules through `MODULES_DISABLED`, and it turns off the exchange through `TRADING_ENABLED=false`.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis.
> It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or
> basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a
> basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under
> MiCA (Title III); no such authorisation is held.

## Shared Core Components

### 1. Multi-Asset Ledger
- **GCU Use**: Manages basket of currencies (USD, EUR, GBP, CHF, JPY, Gold)
- **Exchange Use**: Handles crypto assets (BTC, ETH) and fiat currencies for trading
- **Lending Use**: Manages loan assets, collateral, and repayment tracking
- **Stablecoins Use**: Tracks stablecoin issuance, reserves, and redemptions
- **Treasury Use**: Multi-currency balance management and allocation tracking
- **Shared**: Event-sourced ledger, multi-balance accounts, atomic transactions

### 2. Exchange Engine
- **GCU Use**: Currency-to-currency exchanges within basket rebalancing
- **Exchange Use**: Multi-asset trading with order matching and settlement
- **Lending Use**: Asset conversions for loan disbursement and collection
- **Stablecoins Use**: Reserve asset management and stability mechanisms
- **Treasury Use**: Currency conversions for optimal allocation
- **Shared**: Order matching, liquidity pools, real-time rate feeds

### 3. Stablecoin Infrastructure
- **GCU Use**: GCU demo units valued against the basket (simulated)
- **Exchange Use**: Multiple stablecoin pairs for trading and liquidity
- **Lending Use**: Stable tokens for loan disbursement and collection
- **Stablecoins Use**: EUR-pegged and multi-backed stable token issuance
- **Treasury Use**: Stable value preservation across currency allocations
- **Shared**: Token minting/burning, reserve management, redemption systems

### 4. Governance System
- **GCU Use**: Simulated monthly polls on basket composition (demo)
- **Exchange Use**: Community governance for trading parameters and listings
- **Lending Use**: Loan approval voting and risk parameter governance
- **Stablecoins Use**: Reserve composition and stability mechanism governance
- **Treasury Use**: Allocation strategy voting and rebalancing triggers
- **Shared**: Voting engine, weighted voting, proposal management, poll system

### 5. Banking Integration
- **GCU Use**: simulated multi-custodian allocation (demo)
- **Exchange Use**: Fiat on/off ramps and settlement banking
- **Lending Use**: Loan disbursement and collection banking services
- **Stablecoins Use**: Reserve banking integration
- **Treasury Use**: Multi-bank connector patterns
- **Shared**: Bank connectors, payment processing, reconciliation, custody services

### 6. Compliance Framework
- **GCU Use**: regulatory-reporting modules exercised in the demo (no licence held)
- **Exchange Use**: reporting and data-exchange modules modelled on MiCA requirements (no registration or authorisation held)
- **Lending Use**: credit-reporting modules (no lending licence held)
- **Stablecoins Use**: reserve-reporting modules (demo)
- **Treasury Use**: cross-border and tax reporting modules
- **Shared**: KYC/AML, transaction monitoring, audit trails, regulatory reporting

## Unique Components by Product

### GCU-Specific
- Currency basket management algorithms
- Simulated multi-custodian allocation page (for example a 40/30/30 split)
- Simulated basket composition voting page
- Basket rebalancing workflows

### FinAegis Exchange-Specific
- Crypto wallet infrastructure (hot/cold storage)
- Blockchain integration (BTC/ETH nodes)
- Multi-asset order book and matching engine
- Crypto-fiat bridge services
- Advanced trading interface and tools
- Market making and liquidity pools (adding and removing liquidity was removed in v7.18.0)

### FinAegis Lending-Specific
- P2P lending marketplace and matching
- Credit scoring and risk assessment
- Loan origination and approval workflows
- Automated loan servicing and collection
- Borrower and lender dashboards
- Default management and recovery

### FinAegis Stablecoins-Specific
- Multiple stablecoin framework (EUR, USD, basket-backed)
- Reserve composition and management
- Automated stability mechanisms
- Cross-chain token deployment
- Redemption and minting interfaces
- E-money-token reporting patterns (demo)

### FinAegis Treasury-Specific
- Advanced allocation algorithms and optimization
- Multi-bank relationship management
- Treasury analytics and forecasting
- Cash flow management tools
- Risk-adjusted return optimization
- Corporate treasury interfaces

## Technical Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    FinAegis Platform                         │
├─────────────────────────────────────────────────────────────┤
│                    Shared Core Layer                         │
│  - Multi-Asset Ledger    - Exchange Engine                  │
│  - Account Management    - Transaction Processing           │
│  - Compliance (KYC/AML)  - Banking Connectors              │
│  - API Framework         - Event Sourcing                   │
├─────────────────────────────────────────────────────────────┤
│     GCU          │    Exchange    │    Lending    │Treasury │
│  - Basket Mgmt   │ - Crypto/Fiat  │ - P2P Market  │- Multi- │
│  - Bank Alloc    │ - Order Book   │ - Credit      │  Bank   │
│  - Voting        │ - Wallets      │ - Servicing   │- FX Opt │
├─────────────────────────────────────────────────────────────┤
│                     Stablecoins                              │
│         EUR/USD/Basket-backed token framework                │
└─────────────────────────────────────────────────────────────┘
```

## Development History

### Phases 1 to 6 (completed)
These phases built the core platform architecture, the GCU concept and the first exchange patterns.

### Phase 7: platform pattern unification (completed)
1. **Platform settings management.** Sub-products and features can be configured and switched on or off.
2. **Sub-product framework.** Products are independent modules that share core services.
3. **Asset extension.** The ledger supports fiat, crypto and commodity assets, and every product can use them.

### Phase 8: additional pattern demonstrations (completed as demo modules)
1. **FinAegis Exchange.** A multi-currency trading engine with an order book and matching. It runs only when `TRADING_ENABLED` is on.
2. **FinAegis Stablecoins.** A demo module for an EUR-pegged stablecoin with reserve management.
3. **FinAegis Treasury.** Multi-bank allocation, FX optimisation and corporate treasury features.
4. **FinAegis Lending.** A demo peer-to-peer lending marketplace with credit scoring and loan servicing.

## Educational Benefits of Unified Architecture

### Technical learning
- **Code reuse.** The products share one codebase and one set of core services.
- **Maintenance.** One platform is maintained instead of several.
- **Testing.** The products share test patterns and test infrastructure.
- **Deployment.** One deployment serves every product.

### Business architecture
- **Service integration.** The demo shows how products could work together.
- **Shared resources.** The exchange demo shows liquidity pool patterns.
- **Compliance patterns.** The compliance modules share one framework. They are readiness tooling, and no certification or licence is held.
- **Operations.** Monitoring and support use the same architecture for every product.

### User experience
- **One account.** A person uses one account for every product.
- **Interoperability.** The products can pass funds and data to each other.
- **Wallet architecture.** One wallet shows every asset.
- **Consistent interface.** The products follow the same interface patterns.

## Configuration Pattern Demonstration

`config/sub_products.php` shows how several products can be switched on and off in one codebase. This is a shortened excerpt.

```php
// config/sub_products.php
return [
    'exchange' => [
        'enabled'  => env('SUB_PRODUCT_EXCHANGE_ENABLED', true),
        'features' => [
            'crypto_trading'  => env('EXCHANGE_CRYPTO_ENABLED', false),
            'fiat_pairs'      => env('EXCHANGE_FIAT_ENABLED', true),
            'advanced_orders' => env('EXCHANGE_ADVANCED_ORDERS_ENABLED', true),
        ],
        'licenses' => ['vasp', 'mica'],
    ],
    'lending' => [
        'enabled'  => env('SUB_PRODUCT_LENDING_ENABLED', false),
        'features' => [
            'sme_loans'         => env('LENDING_SME_ENABLED', true),
            'invoice_financing' => env('LENDING_INVOICE_ENABLED', true),
        ],
        'licenses' => ['lending_license', 'credit_intermediary'],
    ],
    'stablecoins' => [
        'enabled'  => env('SUB_PRODUCT_STABLECOINS_ENABLED', true),
        'features' => [
            'eur_stablecoin'    => env('STABLECOIN_EUR_ENABLED', true),
            'basket_stablecoin' => env('STABLECOIN_BASKET_ENABLED', false),
        ],
        'licenses' => ['emi_license', 'e_money_issuer'],
    ],
    'treasury' => [
        'enabled'  => env('SUB_PRODUCT_TREASURY_ENABLED', true),
        'features' => [
            'multi_bank'      => env('TREASURY_MULTI_BANK_ENABLED', true),
            'fx_optimization' => env('TREASURY_FX_OPTIMIZATION_ENABLED', true),
        ],
        'licenses' => ['payment_services', 'investment_services'],
    ],
];
```

The `licenses` key is a label that the admin panel shows. It names the licences an operator would need before offering that product. FinAegis holds none of them.

## Educational Value

This platform shows several architecture patterns.
- **Infrastructure sharing.** Products reuse the same core services.
- **Component reuse.** New products are built from existing components.
- **Service integration.** Products are designed to work together.
- **Full-stack architecture.** The patterns cover the database, the domain logic, the APIs and the user interface.

The unified platform is a learning resource. It shows how financial services could be built on one integrated design, from traditional banking concepts to crypto and lending.
