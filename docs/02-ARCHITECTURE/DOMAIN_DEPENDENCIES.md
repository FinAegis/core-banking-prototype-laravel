# Domain Dependencies & Architecture

This document maps the dependencies between FinAegis domains, supporting the platform's modularity goals.

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
- **Purpose**: Central ledger and balance management
- **Key Aggregates**: LedgerAggregate, TransactionAggregate, TransferAggregate
- **Why Core**: All financial operations require account balances
- **Import Count**: 406 statements across codebase

#### Compliance Domain
- **Purpose**: Regulatory compliance (KYC/AML)
- **Key Features**: KYC verification, AML screening, SAR/CTR reporting
- **Why Core**: Financial operations require compliance checks
- **Import Count**: 123 statements across codebase

### SUPPORTING (Infrastructure)

These domains provide **infrastructure** that optional features build upon:

#### Banking Domain
- **Purpose**: External bank connectivity
- **Key Features**: SEPA/SWIFT transfers, bank routing
- **Independence**: Zero external domain imports (pure infrastructure)

#### Exchange Domain
- **Purpose**: Trading and liquidity
- **Key Features**: Order matching, AMM, liquidity pools
- **Size**: 144 files (largest domain)

#### Wallet Domain
- **Purpose**: Blockchain integration
- **Key Features**: Multi-chain support, transaction signing
- **Dependencies**: Account, Compliance

### OPTIONAL (Features)

These domains are **independently toggleable** features:

| Domain | Can Disable? | Impact if Disabled |
|--------|--------------|-------------------|
| Basket (GCU) | Yes | No basket currencies |
| Stablecoin | Yes | No token minting/burning |
| Lending | Yes | No P2P loans |
| Treasury | Yes | No portfolio management |
| AI | Yes | No AI agent features |
| AgentProtocol | Yes | No A2A messaging |
| Governance | Yes | No voting (affects Basket) |
| CGO | Yes | No continuous offering |

## Modularity Implications

### Current State

```
┌─────────────────────────────────────────────────────────────┐
│                    FINAEGIS MONOLITH                         │
│                                                              │
│  All 29 domains deployed together                           │
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

To enable modularity, extract interfaces for cross-domain communication:

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

Only one soft circular dependency detected:

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

1. **Document dependencies** in each domain README ✓
2. **Extract shared interfaces** to `App\Domain\Shared\Contracts`
3. **Use events** for cross-domain communication where possible

### Short-term (Medium Effort)

4. **Create domain service providers** that can be conditionally loaded
5. **Isolate configuration** per domain (`config/domains/exchange.php`)
6. **Add feature flags** for optional domains

### Long-term (High Effort)

7. **Extract packages** for truly optional domains
8. **Create plugin system** for third-party extensions
9. **Implement domain versioning** for API stability

---

## Related Documentation

- [ADR-001: Event Sourcing](../ADR/ADR-001-event-sourcing.md)
- [ADR-002: CQRS Pattern](../ADR/ADR-002-cqrs-pattern.md)
- [ARCHITECTURAL_ROADMAP.md](../ARCHITECTURAL_ROADMAP.md)
- [IMPLEMENTATION_PLAN.md](../IMPLEMENTATION_PLAN.md)
