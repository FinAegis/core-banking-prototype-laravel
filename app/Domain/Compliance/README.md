# Compliance Domain

## Overview

The Compliance domain handles regulatory compliance, transaction monitoring, and risk management using Spatie Event Sourcing for complete audit trails and event-driven architecture.

## Architecture

### Event Sourcing with Spatie

This domain uses [Spatie Event Sourcing](https://spatie.be/docs/laravel-event-sourcing) for maintaining complete audit trails and implementing CQRS patterns.

#### Key Components

1. **Aggregates** (Write Model)
   - `ComplianceAlertAggregate`: Manages compliance alert lifecycle
   - `TransactionMonitoringAggregate`: Handles transaction risk analysis

2. **Events** (Domain Events)
   - Alert Events: `AlertCreated`, `AlertAssigned`, `AlertResolved`, `AlertEscalatedToCase`
   - Monitoring Events: `TransactionFlagged`, `RiskScoreCalculated`, `PatternDetected`

3. **Projectors** (Read Model Updates)
   - `ComplianceAlertProjector`: Updates compliance_alerts table
   - `TransactionMonitoringProjector`: Updates transaction monitoring read models

4. **Repositories** (Event Storage)
   - `ComplianceEventRepository`: Stores events in compliance_events table
   - `ComplianceSnapshotRepository`: Manages aggregate snapshots

## Event Sourcing Pattern

### Creating New Alerts

```php
use App\Domain\Compliance\Aggregates\ComplianceAlertAggregate;

// Create a new alert
$aggregate = ComplianceAlertAggregate::create(
    type: 'suspicious_activity',
    severity: 'high',
    entityType: 'transaction',
    entityId: $transactionId,
    description: 'Large cash deposit detected',
    details: ['amount' => 50000],
    userId: auth()->id()
);

// Persist the aggregate (saves events)
$aggregate->persist();
```

### Modifying Existing Alerts

```php
// Retrieve existing aggregate
$aggregate = ComplianceAlertAggregate::retrieve($alertId);

// Perform operations
$aggregate->assign('user-123', 'manager-456', 'Assigned for investigation')
    ->addNote('Initial investigation started', 'user-123')
    ->changeStatus('investigating', 'Under review');

// Persist changes
$aggregate->persist();
```

### Transaction Monitoring

```php
use App\Domain\Compliance\Aggregates\TransactionMonitoringAggregate;

// Analyze a transaction
$aggregate = TransactionMonitoringAggregate::analyzeTransaction(
    transactionId: $transaction->id,
    amount: $transaction->amount,
    fromAccount: $transaction->from_account,
    toAccount: $transaction->to_account,
    metadata: ['type' => 'wire_transfer']
);

// Apply rules and detect patterns
$aggregate->triggerRule($ruleId, $ruleName, 'high', $conditions, $matchedData)
    ->detectPattern('structuring', $patternData, 0.85, $relatedTransactions)
    ->exceedThreshold('daily_limit', 100000, 150000, 'critical');

// Flag if suspicious
if ($aggregate->getRiskScore() > 75) {
    $aggregate->flagTransaction('High risk detected', 'critical', 'system');
}

$aggregate->persist();
```

## Domain Events

### Alert Events

- **AlertCreated**: Initial alert creation
- **AlertAssigned**: Alert assigned to user
- **AlertStatusChanged**: Status transition
- **AlertNoteAdded**: Note/comment added
- **AlertResolved**: Alert closed/resolved
- **AlertLinked**: Alerts linked together
- **AlertEscalatedToCase**: Escalated to compliance case

### Monitoring Events

- **RiskScoreCalculated**: Risk assessment completed
- **TransactionFlagged**: Transaction marked suspicious
- **TransactionCleared**: Transaction approved
- **MonitoringRuleTriggered**: Rule matched
- **TransactionPatternDetected**: Pattern identified
- **ThresholdExceeded**: Limit breached
- **TransactionAnalyzed**: Analysis completed

## Services

### AlertManagementService

Handles alert lifecycle management:
- Create alerts with automatic escalation
- Assign to compliance officers
- Link related alerts
- Escalate to cases
- Auto-close old alerts

### TransactionMonitoringService

Real-time transaction analysis:
- Apply monitoring rules
- Detect suspicious patterns
- Calculate risk scores
- Generate SARs (Suspicious Activity Reports)
- Flag high-risk transactions

## Database Schema

### Event Store Tables

**compliance_events**
- `id`: Primary key
- `aggregate_uuid`: Aggregate identifier
- `aggregate_version`: Version number
- `event_version`: Event schema version
- `event_class`: Full event class name
- `event_properties`: Serialized event data
- `meta_data`: Additional metadata
- `created_at`: Event timestamp

**compliance_snapshots**
- `id`: Primary key
- `aggregate_uuid`: Aggregate identifier
- `aggregate_version`: Snapshot version
- `state`: Serialized aggregate state
- `created_at`: Snapshot timestamp

### Read Model Tables

**compliance_alerts**
- Alert details and current state
- Updated by ComplianceAlertProjector

**monitoring_rules**
- Configurable monitoring rules
- Conditions and thresholds

**transaction_monitorings**
- Transaction risk assessments
- Updated by TransactionMonitoringProjector

## Testing

### Unit Tests
```bash
./vendor/bin/pest tests/Unit/Domain/Compliance
```

### Feature Tests
```bash
./vendor/bin/pest tests/Feature/Http/Controllers/Api/Compliance*
```

## Best Practices

1. **Always use aggregates** for state changes
2. **Never modify read models directly** - use projectors
3. **Record all business decisions** as events
4. **Use transactions** when persisting aggregates
5. **Implement idempotency** for event handlers
6. **Version your events** for backward compatibility

## Migration Commands

```bash
# Run migrations
php artisan migrate

# Rebuild projections from events
php artisan event-sourcing:replay App\\Domain\\Compliance\\Projectors\\ComplianceAlertProjector
php artisan event-sourcing:replay App\\Domain\\Compliance\\Projectors\\TransactionMonitoringProjector
```

## Configuration

Event sourcing configuration is managed through Laravel's config system and the Spatie Event Sourcing package configuration.

## Integration Points

- **Account Domain**: Transaction data for monitoring
- **Treasury Domain**: High-value transaction alerts
- **Lending Domain**: Loan fraud detection
- **Exchange Domain**: Market manipulation detection

## Compliance Standards

- **AML (Anti-Money Laundering)**: Transaction monitoring, SAR filing
- **KYC (Know Your Customer)**: Identity verification, risk profiling
- **GDPR**: Data privacy, audit trails
- **PCI DSS**: Payment card security
