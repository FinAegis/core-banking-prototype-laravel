# FinAegis Services Reference

This document describes the main backend services behind the core banking reference API. It lists where each service lives and which public methods it offers in v7.18.1. It does not cover every service in the 61 domains under `app/Domain/`.

Several of these services belong to demo modules (Basket, Governance, Stablecoin, Banking and others) that the production Zelta configuration disables through `MODULES_DISABLED`.

> The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.

## Table of Contents
- [Account Services](#account-services)
- [Compliance Services](#compliance-services)
- [Custodian Services](#custodian-services)
- [Governance Services](#governance-services)
- [Performance Services](#performance-services)
- [Basket Services](#basket-services)
- [Stablecoin Services](#stablecoin-services)
- [Transaction Services](#transaction-services)
- [Cache Services](#cache-services)

## Account Services

### AccountService
**Location**: `app/Domain/Account/Services/AccountService.php`

This service starts the account workflows. Each method starts a Laravel Workflow, and the workflow records the events on the account aggregate.

**Key Methods**:
- `create()` starts `CreateAccountWorkflow`.
- `destroy()` starts `DestroyAccountWorkflow`.
- `deposit()` starts `DepositAccountWorkflow`.
- `withdraw()` starts `WithdrawAccountWorkflow`.

**Events Recorded**: `AccountCreated`, `AccountDeleted`, `MoneyAdded`, `MoneySubtracted`, `AccountFrozen` and `AccountUnfrozen`, among others in `app/Domain/Account/Events/`.

### BankAllocationService
**Location**: `app/Domain/Account/Services/BankAllocationService.php`

This service manages how a user's funds are spread across banks in the demo.

**Key Methods**:
- `setupDefaultAllocations()` creates the default allocation for a new user.
- `updateAllocations()` replaces the allocation percentages.
- `addBank()` and `removeBank()` change the list of banks.
- `setPrimaryBank()` marks one bank as primary.
- `getDistributionSummary()` shows how an amount would be split.

**API Endpoints**: `/api/bank-allocations/*`

## Compliance Services

The compliance services are readiness tooling. They help an operator build Know Your Customer (KYC), anti-money-laundering and data protection processes. No certification is held, and nothing is filed with a regulator automatically.

### RegulatoryReportingService
**Location**: `app/Domain/Compliance/Services/RegulatoryReportingService.php`

This service generates draft regulatory reports from the event store.

**Key Methods**:
- `generateCTR()` builds a currency transaction report.
- `generateSARCandidates()` lists transactions that may need a suspicious activity report.
- `generateComplianceSummary()` builds a compliance summary.
- `generateKycReport()` builds a KYC status report.

**Reporting Thresholds**: A currency transaction report covers transactions of $10,000 or more. Suspicious activity candidates include patterns such as amounts just below that threshold.

**API Endpoints**: `/api/regulatory/reports/*`

### KycService
**Location**: `app/Domain/Compliance/Services/KycService.php`

This service runs the internal KYC process and its document handling.

**Key Methods**:
- `submitKyc()` stores the submitted documents.
- `verifyKyc()` and `rejectKyc()` record the review decision.
- `checkExpiredKyc()` finds verifications that have expired.
- `getRequirements()` returns the documents and limits for a level.
- `getKycStatus()` and `isKycApproved()` report the user's status.

**KYC Levels**:
- The `basic` level needs a national ID and a selfie. It allows $1,000 a day, $5,000 a month and a $10,000 balance.
- The `enhanced` level needs a passport, a utility bill and a selfie. It allows $10,000 a day, $50,000 a month and a $100,000 balance.
- The `full` level adds a bank statement and proof of income, and it has no limits.

The Zelta wallet uses separate identity checks. The bank ramp uses Bridge's own KYC, and cards use the card provider's check.

### GdprService
**Location**: `app/Domain/Compliance/Services/GdprService.php`

This service handles data export, deletion and consent under the General Data Protection Regulation (GDPR).

**Key Methods**:
- `exportUserData()` builds a full data export.
- `canDeleteUserData()` checks whether an account can be erased.
- `deleteUserData()` erases or anonymises the user's data.
- `notifyProcessorsOfErasure()` tells data processors about an erasure.
- `updateConsent()` records the user's consent choices.

## Custodian Services

Custodian connectors are integration adapters for third-party APIs; no partnership or endorsement implied.

### SettlementService
**Location**: `app/Domain/Custodian/Services/SettlementService.php`

This service settles pending transfers between custodians.

**Key Methods**:
- `processPendingSettlements()` settles the transfers that are waiting.
- `getSettlementStatistics()` reports settlement figures.

### MultiCustodianTransferService
**Location**: `app/Domain/Custodian/Services/MultiCustodianTransferService.php`

This service routes a transfer to a custodian and falls back to another one when the first is not available.

**Key Methods**:
- `transfer()` routes and runs a transfer.
- `getTransferStatistics()` reports transfer figures.

### BankAlertingService
**Location**: `app/Domain/Custodian/Services/BankAlertingService.php`

This service watches custodian health and raises alerts.

**Key Methods**:
- `performHealthCheck()` checks every custodian.
- `handleHealthChange()` raises an alert when a custodian's health changes.
- `getAlertHistory()` returns past alerts.

### DailyReconciliationService
**Location**: `app/Domain/Custodian/Services/DailyReconciliationService.php`

This service compares internal balances with custodian balances once a day.

**Key Methods**:
- `performDailyReconciliation()` runs the comparison and records any differences.
- `getLatestReport()` returns the latest reconciliation report.

**Reconciliation Process**:
1. The service fetches the balances from all custodians.
2. It compares them with the internal records.
3. It flags differences for review.
4. It writes a report.

### CircuitBreakerService
**Location**: `app/Domain/Custodian/Services/CircuitBreakerService.php`

This service protects calls to custodian APIs with a circuit breaker. After repeated failures it stops calls for a while, then lets a test call through.

**Key Methods**:
- `execute()` runs a call through the breaker.
- `getState()` and `isAvailable()` report the breaker state.
- `reset()` closes the breaker by hand.
- `getMetrics()` returns failure and success figures.

**Circuit States**: A closed breaker lets calls through. An open breaker blocks them. A half-open breaker lets a test call through to check recovery.

The Exchange domain has its own `CircuitBreakerService` in `app/Domain/Exchange/Services/`.

## Governance Services

The governance services run the polls of the GCU demo. Any basket "votes" are simulated.

### GovernanceService
**Location**: `app/Domain/Governance/Services/GovernanceService.php`

This service manages polls and votes.

**Key Methods**:
- `createPoll()`, `activatePoll()`, `completePoll()` and `cancelPoll()` move a poll through its lifecycle.
- `castVote()` records a vote.
- `getActivePolls()` and `getPollResults()` read polls and results.
- `getUserVotingPower()` and `canUserVote()` check a user's voting rights.
- `getVotingStrategy()` and `getAvailableVotingStrategies()` return the voting power strategies (`one_user_one_vote` and `asset_weighted_vote`).

### VotingTemplateService
**Location**: `app/Domain/Governance/Services/VotingTemplateService.php`

This service creates polls from templates.

**Key Methods**:
- `createMonthlyBasketVotingPoll()` creates the monthly basket composition poll.
- `createAddCurrencyPoll()` creates a poll on adding a currency.
- `createEmergencyRebalancingPoll()` creates a poll on an urgent rebalance.
- `scheduleYearlyVotingPolls()` creates the polls for a year ahead.

## Performance Services

### TransferOptimizationService
**Location**: `app/Domain/Performance/Services/TransferOptimizationService.php`

This service speeds up transfers by caching account data and checking transfers before they run.

**Key Methods**:
- `getAccountWithCache()` and `getBalanceWithCache()` read accounts and balances through the cache.
- `preValidateTransfer()` and `batchValidateTransfers()` check transfers before they run.
- `warmUpCaches()` and `clearTransferCaches()` fill and clear the caches.

## Basket Services

These services back the GCU demo baskets in `app/Domain/Basket/Services/`.

- `BasketService` composes and decomposes basket holdings and calculates the components a basket needs.
- `BasketRebalancingService` checks whether a basket needs rebalancing, rebalances it and keeps the history.
- `BasketValueCalculationService` calculates the current and historical value of a basket.
- `BasketPerformanceService` calculates performance over several periods and compares it with benchmarks.

## Stablecoin Services

These services back the Stablecoin demo module in `app/Domain/Stablecoin/Services/`.

- `StablecoinIssuanceService` mints and burns stablecoins and adds collateral (`mint()`, `burn()`, `addCollateral()`).
- `CollateralService` values collateral and finds positions at risk.
- `LiquidationService` finds and runs liquidations and can simulate a mass liquidation.
- `StabilityMechanismService`, `PriceOracleService` and `OracleAggregator` keep the peg and the prices.

## Transaction Services

### Transaction Reversal
**Location**: `app/Domain/Account/Workflows/TransactionReversalWorkflow.php`

Reversals run as a workflow with `TransactionReversalActivity`, not as a separate service. `TransactionReversalController` starts the workflow. Creating a reversal answers `404` while `LEDGER_DIRECT_FUNDING_ENABLED` is off.

### BatchProcessingService
**Location**: `app/Domain/Batch/Services/BatchProcessingService.php`

This service runs batch jobs.

**Key Methods**:
- `createBatchJob()` creates a batch job.
- `processBatch()` runs it.
- `cancelBatchJob()` cancels it.
- `retryFailedItems()` retries the items that failed.

## Cache Services

### TransactionCacheService
**Location**: `app/Domain/Account/Services/Cache/TransactionCacheService.php`

This service caches recent and paginated transactions and daily summaries.

### AccountCacheService
**Location**: `app/Domain/Account/Services/Cache/AccountCacheService.php`

This service caches accounts and their balances.

### PollCacheService
**Location**: `app/Domain/Governance/Services/Cache/PollCacheService.php`

This service caches polls, poll results, voting power and vote status.

## Service Integration Patterns

### Event Sourcing Integration
Account and transfer changes are recorded as events on aggregates. The events give an audit trail and drive the projectors that build the read models. The snippet is a simplified illustration, not code from the repository:

```php
// Example service event emission
$this->eventStore->store(new TransactionCompleted(
    $transaction->uuid,
    $transaction->amount,
    $transaction->asset_code
));
```

### Workflow Integration
Multi-step processes run as Laravel Workflow workflows with activities. A failed step can run a compensation, as described in [WORKFLOW_PATTERNS.md](../02-ARCHITECTURE/WORKFLOW_PATTERNS.md). The snippet is a simplified illustration, not the Laravel Workflow API:

```php
// Example workflow integration
$workflow = new TransferWorkflow($transferData);
$workflow->addStep(new ValidateTransferStep())
         ->addStep(new ExecuteTransferStep())
         ->addCompensation(new ReverseTransferStep());
```

### API Authentication
Authorisation is checked in the HTTP layer, through Sanctum token abilities, role middleware and policies, before a service is called. The snippet is a simplified illustration:

```php
// Illustration of a permission check
if (!$this->authService->canPerformAction($user, 'create_transfer')) {
    throw new UnauthorizedException();
}
```

## Performance Considerations

### Caching Strategy
- Redis holds sessions and temporary data.
- Query results are cached where reads are expensive.
- Stable data, such as exchange rates, is cached for API responses.

### Async Processing
- Heavy operations run on queues.
- Events decouple the domains from each other.
- Reports are built in background jobs.

### Monitoring
- Health endpoints live under `/api/monitoring/...`.
- Metrics are exported for Prometheus at `/api/monitoring/prometheus`.
- Circuit breakers report their own metrics.

## Error Handling

### Standard Error Responses
The error formats that the API returns are described in [REST_API_REFERENCE.md](REST_API_REFERENCE.md#error-responses).

### Compensation Patterns
When an operation fails halfway, the workflow undoes the steps that already ran. Typical compensations are transaction reversals, balance corrections, state rollbacks and the cleanup of notifications.

## Testing Strategy

The services are tested with Pest. Unit tests cover single methods, edge cases and errors with mocked dependencies. Feature and integration tests cover workflows across services, database access and the events that are recorded. The `tests/MultiConnection/` suite runs against real MySQL to catch problems that SQLite hides.

## Security Considerations

### Data Protection
- Sensitive fields are encrypted with Laravel's `encrypted` cast.
- Personal data is anonymised on erasure.
- Access to sensitive data is logged.

### API Security
- Authenticated endpoints need a token, and the token abilities are checked.
- Requests are rate limited.
- Input is validated and sanitised.
- Database access uses the query builder and Eloquent, which bind parameters.

### Compliance
The services include tooling for data protection requests and financial record keeping. They do not make a deployment compliant with GDPR, PCI DSS or SOX, and no certification is held. Card data is handled by the licensed card provider, not by FinAegis.
