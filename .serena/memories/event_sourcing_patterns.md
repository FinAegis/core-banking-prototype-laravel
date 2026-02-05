# Event Sourcing Patterns

> **Version Context**: Core event sourcing patterns established in early development. As of v2.6.0, the platform has 37+ domains, many of which use event sourcing with dedicated event tables. The patterns below remain the standard for all new domains.

This memory documents the event sourcing implementation patterns used in FinAegis.

## Standard Structure

Every event-sourced domain follows this pattern:

### 1. Event Store Tables

```sql
CREATE TABLE domain_events (
    id BIGINT PRIMARY KEY,
    aggregate_uuid UUID,
    aggregate_version INT,
    event_version INT DEFAULT 1,
    event_class VARCHAR(255),
    event_properties JSON,
    meta_data JSON,
    created_at TIMESTAMP
);

CREATE TABLE domain_snapshots (
    id BIGINT PRIMARY KEY,
    aggregate_uuid UUID,
    aggregate_version INT,
    state JSON,
    created_at TIMESTAMP
);
```

### 2. Domain Models

```php
class DomainEvent extends EloquentStoredEvent
{
    protected $table = 'domain_events';
}

class DomainSnapshot extends EloquentSnapshot
{
    protected $table = 'domain_snapshots';
}
```

### 3. Repositories

```php
class DomainEventRepository extends EloquentStoredEventRepository
{
    protected string $storedEventModel = DomainEvent::class;
}

class DomainSnapshotRepository extends EloquentSnapshotRepository
{
    protected string $snapshotModel = DomainSnapshot::class;
}
```

### 4. Aggregates

```php
class DomainAggregate extends AggregateRoot
{
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(DomainEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(DomainSnapshotRepository::class);
    }

    public function performAction(): self
    {
        $this->recordThat(new ActionPerformed(...));
        return $this;
    }

    protected function applyActionPerformed(ActionPerformed $event): void
    {
        // Update aggregate state
    }
}
```

### 5. Projectors

```php
class DomainProjector extends Projector
{
    public function onEventOccurred(EventOccurred $event): void
    {
        ReadModel::create([...]);
    }
}
```

## Working with Aggregates

```php
// Create new aggregate
$aggregate = DomainAggregate::retrieve($uuid);
$aggregate->performAction($data);
$aggregate->persist();

// Chaining operations
$aggregate = DomainAggregate::retrieve($uuid)
    ->performFirstAction($data1)
    ->performSecondAction($data2);
$aggregate->persist();
```

## Event Sourcing Commands

```bash
php artisan event-sourcing:replay                    # Rebuild all projections
php artisan event-sourcing:replay "App\\...\\Projector"  # Specific projector
php artisan event-sourcing:create-snapshot {uuid}   # Create snapshot
```

## Implemented Domains

> **Note**: This table shows the original core event-sourced domains. Additional domains added in later versions (AgentProtocol, Privacy, Relayer, Commerce, TrustCert, Monitoring, etc.) also follow these patterns with their own dedicated event/snapshot tables. The platform now has 37+ domains total.

| Domain | Event Table | Snapshot Table |
|--------|-------------|----------------|
| Treasury | `treasury_events` | `treasury_snapshots` |
| Compliance | `compliance_events` | `compliance_snapshots` |
| Exchange | `exchange_events` | `exchange_snapshots` |
| Lending | `lending_events` | `lending_snapshots` |
| Stablecoin | `stablecoin_events` | `stablecoin_snapshots` |
| Wallet | `wallet_events` | N/A |
| CGO | `cgo_events` | N/A |
| AgentProtocol | `agent_protocol_events` | `agent_protocol_snapshots` |
| Monitoring | `monitoring_events` | N/A |

## Best Practices

1. Always use aggregates for state changes - never modify read models directly
2. Use projectors for read models - keep write and read models separate
3. Record business decisions as events - not just CRUD operations
4. Version your events using `event_version` field
5. Use transactions around aggregate operations
6. Implement snapshots for aggregates with >100 events
7. Test event handlers for idempotency
