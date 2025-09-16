# Treasury Management System

## Overview

The Treasury Management System provides comprehensive cash and liquidity management capabilities for financial institutions. Built using Domain-Driven Design (DDD) principles with event sourcing, it offers sophisticated allocation strategies, risk management, yield optimization, and regulatory reporting.

## Architecture

### Event Sourcing Implementation

The Treasury domain uses event sourcing with separate storage tables:
- **treasury_events**: Stores all domain events
- **treasury_snapshots**: Stores aggregate snapshots for performance

### Domain Components

#### Aggregates
- **TreasuryAggregate**: Core aggregate root managing treasury operations

#### Value Objects
- **AllocationStrategy**: Defines investment allocation strategies
  - Conservative: 40% cash, 50% bonds, 10% equities
  - Balanced: 20% cash, 40% bonds, 40% equities
  - Aggressive: 10% cash, 20% bonds, 70% equities
  - Custom: User-defined allocations

- **RiskProfile**: Risk assessment levels
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

Optimizes portfolio yields based on risk constraints and market conditions.

**Features:**
- Risk-adjusted allocation
- Instrument filtering by risk score
- Dynamic rebalancing
- Performance metrics calculation
- Sharpe ratio optimization

**Example Usage:**
```php
$service = app(YieldOptimizationService::class);
$result = $service->optimizeYield(
    accountId: 'treasury-001',
    currentAllocations: [...],
    targetYield: 6.5,
    riskProfile: RiskProfile::fromScore(45.0),
    constraints: ['max_equity_exposure' => 0.4]
);
```

### Regulatory Reporting Service

Generates comprehensive regulatory reports for compliance.

**Supported Reports:**
- **BASEL III**: Capital adequacy, liquidity coverage, leverage ratio
- **FORM 10Q**: Financial statements, treasury positions, risk disclosures
- **CALL Report**: Balance sheet, income statement, asset quality
- **Liquidity Coverage Ratio**: HQLA, net cash outflows, LCR calculation
- **Stress Test**: Baseline, adverse, and severely adverse scenarios

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

Orchestrates cash allocation with compensation support for failed operations.

**Workflow Steps:**
1. Analyze current liquidity position
2. Validate allocation strategy
3. Allocate cash based on strategy
4. Optimize yield for allocated cash
5. Update Treasury Aggregate

**Activities:**
- `AnalyzeLiquidityActivity`: Assess current liquidity
- `ValidateAllocationActivity`: Validate strategy and constraints
- `AllocateCashActivity`: Execute cash allocation
- `OptimizeYieldActivity`: Optimize portfolio yield

**Example Usage:**
```php
$workflow = new CashManagementWorkflow();
$result = $workflow->execute(
    accountId: 'treasury-001',
    totalAmount: 5000000.0,
    strategy: 'balanced',
    constraints: ['target_yield' => 5.0]
);
```

## Sagas

### Risk Management Saga

Continuously monitors treasury operations and triggers risk assessments.

**Features:**
- Automatic risk assessment on account creation
- Risk monitoring during yield optimization
- Escalation for high-risk operations
- Risk mitigation workflows
- Compensation for failed operations

**Risk Assessment Process:**
1. Analyze risk factors (market, credit, liquidity, operational, regulatory)
2. Calculate weighted risk score
3. Generate recommendations based on risk level
4. Update aggregate with assessment results

## API Integration

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

Add to `config/event-sourcing.php`:
```php
'event_class_map' => [
    'treasury_account_created'    => TreasuryAccountCreated::class,
    'cash_allocated'              => CashAllocated::class,
    'yield_optimization_started'  => YieldOptimizationStarted::class,
    'risk_assessment_completed'   => RiskAssessmentCompleted::class,
    'regulatory_report_generated' => RegulatoryReportGenerated::class,
];
```

### Service Provider

The `TreasuryServiceProvider` registers:
- Treasury event repository
- Treasury snapshot repository
- Yield optimization service
- Regulatory reporting service
- Risk management saga

## Testing

Comprehensive test coverage is provided in `tests/Feature/Treasury/TreasuryAggregateTest.php`.

**Test Coverage:**
- Treasury account creation
- Cash allocation strategies
- Yield optimization with risk constraints
- Risk assessment and profiling
- Regulatory report generation
- Event sourcing with separate storage
- Workflow compensation

## Security Considerations

1. **Access Control**: All treasury operations require proper authentication and authorization
2. **Audit Trail**: Complete event sourcing provides immutable audit log
3. **Risk Limits**: Automatic enforcement of risk thresholds
4. **Regulatory Compliance**: Built-in compliance with banking regulations
5. **Data Integrity**: Event sourcing ensures data consistency

## Performance Optimization

1. **Snapshot Storage**: Reduces event replay overhead
2. **Separate Event Tables**: Isolates treasury events for better performance
3. **Lazy Loading**: Aggregates loaded only when needed
4. **Caching**: Service results can be cached for repeated queries

## Monitoring and Alerts

The system provides monitoring capabilities for:
- Risk level changes
- Allocation deviations
- Yield target achievement
- Regulatory compliance status
- System performance metrics

## Future Enhancements

1. **Machine Learning**: Predictive analytics for yield optimization
2. **Real-time Market Data**: Integration with market data feeds
3. **Advanced Risk Models**: Value at Risk (VaR), Monte Carlo simulations
4. **Multi-currency Support**: Cross-currency allocation strategies
5. **Blockchain Integration**: DeFi protocol integration for yield farming