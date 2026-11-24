# Domain Dependencies & Architecture

This document maps the dependencies between the main FinAegis domains. The map helps decide which domains could later become separate packages.

> **Note:** The tiers, dependency counts, file counts and import counts below come from an analysis made when the platform had 29 domains. In v7.18.1 there are 61 domain folders under `app/Domain/`, and the counts are no longer current. The structure of the core and the demo domains has stayed the same.

## Domain Hierarchy

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              TIER 3: OPTIONAL                                │
│   ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌──────────┐         │
│   │Stablecoin│ │    AI    │ │  Lending │ │ Treasury │ │  Basket  │         │
│   │          │ │          │ │          │ │          │ │  (GCU)   │         │
│   └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘ └────┬─────┘         │
│        │            │            │            │            │                │
│   ┌────┴────┐  ┌────┴────┐                              ┌──┴───┐           │
│   │  Agent  │  │   CGO   │                              │Govern│           │
│   │Protocol │  │         │                              │ance  │           │
│   └────┬────┘  └────┬────┘                              └──┬───┘           │
├────────┼────────────┼──────────────────────────────────────┼────────────────┤
│        │            │         TIER 2: SUPPORTING           │                │
│   ┌────▼────────────▼─────────────────────────────────────▼────┐           │
│   │                                                             │           │
│   │  ┌──────────┐     ┌──────────┐     ┌──────────┐            │           │
│   │  │ Exchange │     │  Wallet  │     │  Banking │            │           │
│   │  │          │     │          │     │          │            │           │
│   │  └────┬─────┘     └────┬─────┘     └──────────┘            │           │
│   │       │                │                                    │           │
│   └───────┼────────────────┼────────────────────────────────────┘           │
│           │                │                                                 │
├───────────┼────────────────┼─────────────────────────────────────────────────┤
│           │                │         TIER 1: CORE                            │
│   ┌───────▼────────────────▼───────────────────────────────────────────┐    │
│   │                                                                     │    │
│   │  ┌─────────────────────────────┐  ┌─────────────────────────────┐  │    │
│   │  │           ACCOUNT           │  │         COMPLIANCE          │  │    │
│   │  │  (Central Hub - 17 deps)    │  │   (Cross-cutting - 5 deps)  │  │    │
│   │  │                             │  │                             │  │    │
│   │  │  • Ledger Aggregate         │  │  • KYC Verification         │  │    │
│   │  │  • Transaction Aggregate    │  │  • AML Screening            │  │    │
│   │  │  • Transfer Aggregate       │  │  • Transaction Monitoring   │  │    │
│   │  │  • Balance Management       │  │  • Regulatory Reporting     │  │    │
│   │  └─────────────────────────────┘  └─────────────────────────────┘  │    │
│   │                                                                     │    │
│   └─────────────────────────────────────────────────────────────────────┘    │
│                                                                              │
└──────────────────────────────────────────────────────────────────────────────┘
```

## Dependency Matrix

### Core Domains

| Domain | Files | Depends On | Depended By | Classification |
|--------|-------|------------|-------------|----------------|
| **Account** | 98 | Shared | Exchange, Wallet, Lending, Treasury, Stablecoin, Basket, AI, AgentProtocol, Compliance, Banking, CGO, Governance, Payment, Custodian, Fraud, Batch, Performance | **CORE** |
| **Compliance** | 89 | Account, Shared | Exchange, Lending, AgentProtocol, Stablecoin, Wallet | **CORE** |

### Supporting Domains

| Domain | Files | Depends On | Depended By | Classification |
|--------|-------|------------|-------------|----------------|
| **Banking** | 29 | (none) | Account | Supporting |
| **Exchange** | 144 | Account, Compliance | Basket, Stablecoin | Supporting |
| **Wallet** | 48 | Account, Compliance | Stablecoin, AgentProtocol | Supporting |

### Optional Domains (Features)

| Domain | Files | Depends On | Depended By | Classification |
|--------|-------|------------|-------------|----------------|
| **Basket (GCU)** | 23 | Account, Exchange, Governance | (none) | Optional |
| **Stablecoin** | 96 | Account, Compliance, Wallet, Exchange | (none) | Optional |
| **Lending** | 48 | Account | (none) | Optional |
| **Treasury** | 62 | Account | (none) | Optional |
| **AI** | 79 | Account, AgentProtocol, Compliance, Shared | (none) | Optional |
| **AgentProtocol** | 180 | Account, Compliance | AI | Optional |
| **Governance** | 29 | Account, Shared | Basket | Optional |
| **CGO** | 34 | Account | (none) | Optional |

## Dependency Graph

```
                                    ┌─────────────┐
                                    │   BASKET    │
                                    │    (GCU)    │
                                    └──────┬──────┘
                                           │
                          ┌────────────────┼────────────────┐
                          │                │                │
                          ▼                ▼                ▼
                    ┌──────────┐    ┌──────────┐    ┌──────────┐
                    │ EXCHANGE │    │ ACCOUNT  │    │GOVERNANCE│
                    └────┬─────┘    └────┬─────┘    └────┬─────┘
                         │               │               │
              ┌──────────┴──────────┐    │    ┌──────────┘
              │                     │    │    │
              ▼                     ▼    │    │
        ┌──────────┐          ┌──────────┴────┴─────┐
        │COMPLIANCE│          │       SHARED        │
        └──────────┘          │  (Value Objects,    │
                              │   Interfaces)       │
                              └─────────────────────┘
```

## Domain Classifications

### CORE (Required)

These domains are **required** for any FinAegis installation:

#### Account Domain
The Account domain holds the central ledger and the balances. Every financial operation needs account balances, so it is core.
- **Key Aggregates**: LedgerAggregate, TransactionAggregate, TransferAggregate
- **Import Count**: 406 statements across the codebase at the time of the analysis

#### Compliance Domain
The Compliance domain provides readiness tooling for Know Your Customer (KYC) and anti-money-laundering (AML) checks. Financial operations call these checks, so it is core. It does not make a deployment compliant, and no certification is held.
- **Key Features**: KYC verification, AML screening, draft SAR and CTR reports
- **Import Count**: 123 statements across the codebase at the time of the analysis

### SUPPORTING (Infrastructure)

These domains provide **infrastructure** that optional features build upon:

#### Banking Domain
The Banking domain holds the demo bank connectors and the bank routing logic. It has no live bank connection, and the production Zelta configuration disables it. Bank transfers into Zelta run through Bridge instead. At the time of the analysis it imported no other domain.

#### Exchange Domain
The Exchange domain holds the exchange rates, the demo order book and the demo liquidity pools. The order book, the external exchange reads and the liquidity pools run only when the trading engine is on. `TRADING_ENABLED` switches it, and it is on by default only in the local, testing and demo environments. The rate endpoints stay on, because the mobile app uses them. The add, remove and rebalance liquidity flow was removed in v7.18.0. At the time of the analysis it was the largest domain, with 144 files.

#### Wallet Domain
The Wallet domain connects to blockchains. It runs the Zelta non-custodial wallet, in which the device signs every transaction. It depends on Account and Compliance.

### OPTIONAL (Features)

These domains are **independently toggleable** features:

| Domain | Can Disable? | Impact if Disabled |
|--------|--------------|-------------------|
| Basket (GCU) | Yes | No basket currencies (the GCU demo is off) |
| Stablecoin | Yes | No token minting/burning |
| Lending | Yes | No P2P loans |
| Treasury | Yes | No portfolio management |
| AI | Yes | No AI agent features |
| AgentProtocol | Yes | No A2A messaging |
| Governance | Yes | No voting (affects Basket) |
| CGO | Yes | No continuous offering |

## Modularity Implications

### Current State

All domains ship in one application. A deployment switches domains off with `MODULES_DISABLED`. A disabled domain loses its routes and its GraphQL types, and the demo domains also lose their scheduled jobs.

```
┌─────────────────────────────────────────────────────────────┐
│                    FINAEGIS MONOLITH                         │
│                                                              │
│  All 61 domain folders deployed together                    │
│  Single composer.json, single deployment                    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Target State (Phase 2)

```
┌─────────────────────────────────────────────────────────────┐
│                    CORE PACKAGE                              │
│  ┌──────────┐ ┌──────────┐ ┌──────────┐                     │
│  │ Account  │ │Compliance│ │  Shared  │                     │
│  └──────────┘ └──────────┘ └──────────┘                     │
└─────────────────────────────────────────────────────────────┘
                              │
         ┌────────────────────┼────────────────────┐
         │                    │                    │
         ▼                    ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│  EXCHANGE PKG   │  │   WALLET PKG    │  │  BANKING PKG    │
│                 │  │                 │  │                 │
│ • Exchange      │  │ • Wallet        │  │ • Banking       │
│ • Liquidity     │  │ • Blockchain    │  │ • Connectors    │
└─────────────────┘  └─────────────────┘  └─────────────────┘
         │                    │
         ▼                    ▼
┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐
│   BASKET PKG    │  │ STABLECOIN PKG  │  │  LENDING PKG    │
│   (GCU Example) │  │                 │  │                 │
│                 │  │ • Minting       │  │ • P2P Loans     │
│ • Basket        │  │ • Collateral    │  │ • Credit Score  │
│ • Governance    │  │                 │  │                 │
└─────────────────┘  └─────────────────┘  └─────────────────┘
```

### Interface Extraction Plan

To enable modularity, domains talk to each other through interfaces. These interfaces now exist in `app/Domain/Shared/Contracts/`. The sketch below is the early plan, and the real method signatures differ:

```php
// Core domain contracts (in Shared)

interface AccountOperationsInterface
{
    public function getBalance(string $accountId, string $currency): Money;
    public function credit(string $accountId, Money $amount): void;
    public function debit(string $accountId, Money $amount): void;
    public function transfer(string $from, string $to, Money $amount): void;
}

interface ComplianceCheckInterface
{
    public function checkKYCStatus(string $userId): KYCStatus;
    public function validateTransaction(Transaction $tx): ComplianceResult;
    public function screenAML(string $userId): AMLResult;
}

interface ExchangeRateInterface
{
    public function getRate(string $from, string $to): float;
    public function executeTrade(TradeRequest $request): TradeResult;
}
```

## Risk Analysis

### High Coupling Points

| Coupling | Risk | Mitigation |
|----------|------|------------|
| Account → 17 domains | Changes break many features | Event sourcing, versioned APIs |
| Compliance → 5 domains | Regulatory changes cascade | Abstraction layer |
| Exchange → Basket | Tight integration | Interface extraction |

### Circular Dependencies

The analysis found one soft circular dependency:

```
Governance ←→ Basket
     │            │
     │            └── Uses Governance for voting
     │
     └── Uses Basket for GCU-weighted voting
```

**Resolution**: Use events instead of direct imports:
```php
// Instead of direct import
$governance->createProposal();

// Use domain events
event(new BasketCompositionChangeRequested($newComposition));
// Governance listens and creates proposal
```

## Recommendations

### Immediate (Low Effort)

1. **Document dependencies** in each domain README. Six domains have a README so far.
2. **Extract shared interfaces** to `App\Domain\Shared\Contracts`. This is done for account operations, account queries, asset transfers, compliance checks, exchange rates, governance voting, payment processing and wallet operations.
3. **Use events** for cross-domain communication where possible.

### Short-term (Medium Effort)

4. **Create domain service providers** that can be loaded conditionally. `ModuleRouteLoader` already loads routes only for enabled domains.
5. **Isolate configuration** per domain, for example in `config/domains/exchange.php`. This is not done yet.
6. **Add feature flags** for optional domains. `MODULES_DISABLED` and flags such as `TRADING_ENABLED` now do this.

### Long-term (High Effort)

7. **Extract packages** for the optional domains.
8. **Create a plugin system** for third-party extensions. A plugin manager exists in `app/Infrastructure/Plugins/`.
9. **Version the domains** to keep the APIs stable.

---

## Related Documentation

- [ADR-0007: Event Sourcing](../adr/0007-event-sourcing.md)
- [ADR-0008: CQRS Pattern](../adr/0008-cqrs-pattern.md)
- [ARCHITECTURAL_ROADMAP.md](../ARCHITECTURAL_ROADMAP.md)
- [IMPLEMENTATION_PLAN.md](../archive/IMPLEMENTATION_PLAN.md) (archived — historical v1.x planning)
