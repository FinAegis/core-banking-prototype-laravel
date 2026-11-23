# Treasury Management System

## Overview

The Treasury domain is a reference module for cash and liquidity management.
It models treasury accounts, cash allocation strategies, yield optimization, risk assessment and report generation.
It is built with Domain-Driven Design (DDD) and event sourcing.

The module is a demonstration and runs at the message and data layer.
It does not connect to any bank, broker or market data feed.
The production Zelta configuration (`.env.zelta.example`) lists `Treasury` in `MODULES_DISABLED`, so its API routes and GraphQL operations are off on the production site.
The module runs only on deployments that leave it enabled, such as the FinAegis demo.

> FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.

## Architecture

### Event Sourcing Implementation

The Treasury domain stores its events in its own tables.
`treasury_events` holds the domain events.
`treasury_snapshots` holds aggregate snapshots so that long histories load faster.

### Domain Components

#### Aggregates
`TreasuryAggregate` is the aggregate root for treasury accounts.
`PortfolioAggregate` manages investment portfolios and their rebalancing.

#### Value Objects
- **AllocationStrategy** defines four allocation strategies.
  - Conservative: 40% cash, 50% bonds, 10% equities
  - Balanced: 20% cash, 40% bonds, 40% equities
  - Aggressive: 10% cash, 20% bonds, 70% equities
  - Custom: User-defined allocations

- **RiskProfile** maps a score from 0 to 100 to a risk level.
  - Low (0-25 score)
  - Medium (26-50 score)
  - High (51-75 score)
  - Very High (76-100 score)

#### Domain Events
- `TreasuryAccountCreated`: Initial treasury account setup
- `CashAllocated`: Cash allocation based on strategy
- `YieldOptimizationStarted`: Yield optimization initiation
- `RiskAssessmentCompleted`: Risk assessment results
- `RegulatoryReportGenerated`: Regulatory report generation

## Services

### Yield Optimization Service

`YieldOptimizationService` proposes an allocation across a fixed list of sample instruments.
It removes instruments whose risk score is too high for the risk profile.
It then spreads the amount to approach the target yield and reports metrics such as the expected yield and the Sharpe ratio.
The instrument list and yields are sample data in the class, not market data.

**Example Usage:**
```php
$service = app(YieldOptimizationService::class);
$result = $service->optimizePortfolio(
    accountId: 'treasury-001',
    totalAmount: 5000000.0,
    targetYield: 6.5,
    riskProfile: RiskProfile::fromScore(45.0),
    constraints: ['max_equity_exposure' => 0.4]
);
```

### Regulatory Reporting Service

`RegulatoryReportingService` builds report documents in formats modelled on regulatory reporting requirements.
The reports do not by themselves make a deployment compliant, and nothing is filed with a regulator.

| Report type | Sections |
|-------------|----------|
| `BASEL_III` | Capital adequacy, liquidity coverage, leverage ratio |
| `FORM_10Q` | Financial statements, treasury positions, risk disclosures |
| `CALL_REPORT` | Balance sheet, income statement, asset quality |
| `LIQUIDITY_COVERAGE_RATIO` | High-quality liquid assets, net cash outflows, LCR calculation |
| `STRESS_TEST` | Baseline, adverse and severely adverse scenarios |

**Example Usage:**
```php
$service = app(RegulatoryReportingService::class);
$report = $service->generateReport(
    accountId: 'treasury-001',
    reportType: 'BASEL_III',
    period: 'Q1-2024'
);
```

## Workflows

### Cash Management Workflow

`CashManagementWorkflow` runs a cash allocation as a Laravel Workflow.
It can undo completed steps when a later step fails.

**Workflow Steps:**
1. Analyse the current liquidity position.
2. Validate the allocation strategy.
3. Allocate the cash according to the strategy.
4. Optimise the yield for the allocated cash.
5. Record the result on the treasury aggregate.

**Activities:**

| Activity | Purpose |
|----------|---------|
| `AnalyzeLiquidityActivity` | Assesses the current liquidity |
| `ValidateAllocationActivity` | Checks the strategy and constraints |
| `AllocateCashActivity` | Records the cash allocation |
| `OptimizeYieldActivity` | Proposes the yield allocation |

**Example Usage:**
```php
$workflow = WorkflowStub::make(CashManagementWorkflow::class);
$workflow->start(
    'treasury-001',   // accountId
    5000000.0,        // totalAmount
    'balanced',       // strategy
    ['target_yield' => 5.0]
);
```

Other workflows in the domain are `LiquidityForecastingWorkflow`, `PortfolioRebalancingWorkflow` and `PerformanceReportingWorkflow`.

## Sagas

### Risk Management Saga

`RiskManagementSaga` reacts to treasury events and runs risk assessments.
It assesses risk when an account is created and when a yield optimization starts.
For high-risk operations it records an escalation for approval. In the demo this is a log entry.

**Risk Assessment Process:**
1. Score the market, credit, liquidity, operational and regulatory risk factors.
2. Combine them into one weighted risk score.
3. Produce recommendations for the resulting risk level.
4. Record the assessment on the aggregate.

## API Integration

### REST endpoints

The routes are in `app/Domain/Treasury/Routes/api.php` under `/api/treasury`.
They require a Sanctum token with the `treasury` scope and a platform operator role (`admin`, `super_admin` or `bank_admin`).

| Group | Endpoints |
|-------|-----------|
| Portfolios | `/api/treasury/portfolios` with create, read, update, delete, allocate, rebalance, performance, valuation, history and reports |
| Liquidity forecast | `POST /api/treasury/liquidity-forecast/generate`, `GET /{treasuryId}/current`, `POST /workflow/start`, `GET /{treasuryId}/alerts` |
| Yield | `POST /api/treasury/yield/optimize`, `GET /api/treasury/yield/{treasuryId}/portfolio` |

### Creating a Treasury Account
```php
$aggregate = TreasuryAggregate::retrieve($accountId);
$aggregate->createAccount(
    accountId: 'treasury-001',
    name: 'Main Treasury Account',
    currency: 'USD',
    accountType: 'operating',
    initialBalance: 10000000.0,
    metadata: ['region' => 'US', 'branch' => 'HQ']
);
$aggregate->persist();
```

### Allocating Cash
```php
$strategy = new AllocationStrategy(AllocationStrategy::BALANCED);
$aggregate->allocateCash(
    allocationId: Str::uuid()->toString(),
    strategy: $strategy,
    amount: 2000000.0,
    allocatedBy: 'treasury_manager'
);
$aggregate->persist();
```

### Starting Yield Optimization
```php
$riskProfile = RiskProfile::fromScore(45.0);
$aggregate->startYieldOptimization(
    optimizationId: Str::uuid()->toString(),
    strategy: 'balanced_growth',
    targetYield: 6.5,
    riskProfile: $riskProfile,
    constraints: ['max_equity_exposure' => 0.4],
    startedBy: 'system'
);
$aggregate->persist();
```

## Configuration

### Event Class Mapping

`config/event-sourcing.php` already maps the treasury events:
```php
'event_class_map' => [
    'treasury_account_created'     => TreasuryAccountCreated::class,
    'cash_allocated'               => CashAllocated::class,
    'liquidity_forecast_generated' => LiquidityForecastGenerated::class,
    'regulatory_report_generated'  => RegulatoryReportGenerated::class,
    'risk_assessment_completed'    => RiskAssessmentCompleted::class,
    'yield_optimization_started'   => YieldOptimizationStarted::class,
];
```

### Service Provider

`App\Providers\TreasuryServiceProvider` registers the treasury event and snapshot repositories.
It also registers `YieldOptimizationService`, `RegulatoryReportingService` and `RiskManagementSaga` as singletons.

## Testing

The main tests are in `tests/Feature/Treasury/`.
`TreasuryAggregateTest.php` covers account creation, allocation strategies, yield optimization, risk assessment, report generation and the separate event storage.
`PortfolioAggregateTest.php` and the `Services/` and `Workflows/` folders cover portfolios and rebalancing.
`tests/Feature/Domain/Treasury/LiquidityForecastingTest.php` covers liquidity forecasting.

## Security Considerations

1. The API routes need a Sanctum token with the `treasury` scope and an operator role.
2. Every change is stored as an event, which gives an audit trail.
3. Each risk level defines a maximum exposure, from 10% for low risk to 75% for very high risk.
4. The report formats are modelled on banking reporting requirements. They do not by themselves make a deployment compliant.
5. Amounts in this module are floats. A deployment that uses it for real money must move them to `bcmath` first.

## Performance Optimization

1. Snapshots reduce how many events must be replayed.
2. Separate event tables keep treasury events apart from other domains.
3. Aggregates are loaded only when needed.
4. Service results can be cached for repeated queries.

## Monitoring and Alerts

The events and the liquidity alerts can be used to monitor risk level changes, allocation deviations, yield targets and report status.
`SendLiquidityAlertActivity` raises alerts from the liquidity forecasting workflow.

## Future Enhancements

1. Predictive models for yield optimization
2. Real market data feeds instead of the sample instrument list
3. Value at Risk (VaR) and Monte Carlo risk models
4. Allocation strategies across currencies
5. Integration with decentralised finance (DeFi) protocols