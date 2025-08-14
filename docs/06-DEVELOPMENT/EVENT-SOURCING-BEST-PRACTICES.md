# Event Sourcing Best Practices Guide

## Overview

This guide provides best practices for implementing and maintaining event sourcing in the FinAegis platform. Event sourcing is a critical architectural pattern that stores all changes to application state as a sequence of immutable events.

## Table of Contents

1. [Core Principles](#core-principles)
2. [Event Design](#event-design)
3. [Aggregate Design](#aggregate-design)
4. [Projector Patterns](#projector-patterns)
5. [Testing Strategies](#testing-strategies)
6. [Performance Optimization](#performance-optimization)
7. [Migration & Versioning](#migration--versioning)
8. [Common Pitfalls](#common-pitfalls)
9. [FinAegis Patterns](#finaegis-patterns)

## Core Principles

### 1. Events are Immutable

Once stored, events must never be modified or deleted:

```php
// ❌ WRONG - Never modify stored events
$event = StoredEvent::find($id);
$event->event_properties = $newProperties;
$event->save();

// ✅ CORRECT - Create compensating events
$aggregate->recordThat(new TransactionReversed($originalTransaction));
```

### 2. Events Represent Facts

Events describe what happened, not what should happen:

```php
// ❌ WRONG - Command-like event
class DepositMoney extends ShouldBeStored {}

// ✅ CORRECT - Past-tense fact
class MoneyDeposited extends ShouldBeStored {}
```

### 3. Events are Business-Focused

Name events using business language, not technical terms:

```php
// ❌ WRONG - Technical naming
class DatabaseRecordUpdated extends ShouldBeStored {}

// ✅ CORRECT - Business naming
class AccountBalanceIncreased extends ShouldBeStored {}
```

## Event Design

### Event Structure

Every event should contain:

```php
namespace App\Domain\Account\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Shared\ValueObjects\Hash;

class MoneyDeposited extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountUuid,    // Aggregate ID
        public readonly Money $amount,          // Value object
        public readonly string $currency,       // Context
        public readonly Hash $hash,            // Security
        public readonly string $depositedBy,   // Actor
        public readonly DateTimeImmutable $depositedAt, // Timestamp
        public readonly array $metadata = []   // Extensibility
    ) {}
}
```

### Value Objects in Events

Always use value objects for domain concepts:

```php
// ❌ WRONG - Primitive types
class TransferInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly float $amount,  // Loses precision
        public readonly string $from,   // No validation
        public readonly string $to      // No type safety
    ) {}
}

// ✅ CORRECT - Value objects
class TransferInitiated extends ShouldBeStored
{
    public function __construct(
        public readonly Money $amount,
        public readonly AccountUuid $from,
        public readonly AccountUuid $to
    ) {}
}
```

### Event Granularity

Find the right balance between too many and too few events:

```php
// ❌ WRONG - Too granular
class FirstNameChanged extends ShouldBeStored {}
class LastNameChanged extends ShouldBeStored {}
class EmailChanged extends ShouldBeStored {}

// ❌ WRONG - Too coarse
class EverythingChanged extends ShouldBeStored {
    public function __construct(
        public readonly array $allTheThings
    ) {}
}

// ✅ CORRECT - Business-meaningful granularity
class ProfileUpdated extends ShouldBeStored {
    public function __construct(
        public readonly ?string $firstName,
        public readonly ?string $lastName,
        public readonly ?string $email,
        public readonly array $changedFields
    ) {}
}
```

## Aggregate Design

### Aggregate Boundaries

Keep aggregates small and focused:

```php
// ❌ WRONG - Too large aggregate
class BankAggregate extends AggregateRoot
{
    public function handleAllAccounts() {}
    public function handleAllTransactions() {}
    public function handleAllCustomers() {}
}

// ✅ CORRECT - Focused aggregates
class AccountAggregate extends AggregateRoot
{
    public function deposit(Money $amount): void
    {
        $this->recordThat(new MoneyDeposited($this->uuid(), $amount));
    }
}

class TransactionAggregate extends AggregateRoot
{
    public function process(): void
    {
        $this->recordThat(new TransactionProcessed($this->uuid()));
    }
}
```

### Command Handling

Validate commands before recording events:

```php
class AccountAggregate extends AggregateRoot
{
    private int $balance = 0;
    private bool $frozen = false;

    public function withdraw(Money $amount): void
    {
        // Validate business rules
        if ($this->frozen) {
            throw new AccountFrozenException();
        }

        if ($this->balance < $amount->inCents()) {
            throw new InsufficientFundsException();
        }

        // Record event only after validation
        $this->recordThat(new MoneyWithdrawn(
            $this->uuid(),
            $amount,
            Hash::generate($amount)
        ));
    }

    protected function applyMoneyWithdrawn(MoneyWithdrawn $event): void
    {
        // Apply state changes
        $this->balance -= $event->amount->inCents();
    }
}
```

### Snapshot Strategy

Use snapshots for aggregates with many events:

```php
class LiquidityPoolAggregate extends AggregateRoot
{
    use SnapshottableAggregate;

    // Snapshot every 100 events
    protected function shouldSnapshot(): bool
    {
        return $this->aggregateVersion % 100 === 0;
    }

    public function getSnapshot(): array
    {
        return [
            'base_reserve' => $this->baseReserve,
            'quote_reserve' => $this->quoteReserve,
            'total_shares' => $this->totalShares,
            'fee_rate' => $this->feeRate,
        ];
    }

    public function restoreFromSnapshot(array $snapshot): void
    {
        $this->baseReserve = $snapshot['base_reserve'];
        $this->quoteReserve = $snapshot['quote_reserve'];
        $this->totalShares = $snapshot['total_shares'];
        $this->feeRate = $snapshot['fee_rate'];
    }
}
```

## Projector Patterns

### Idempotent Projectors

Ensure projectors can be replayed safely:

```php
class AccountProjector extends Projector
{
    public function onMoneyDeposited(MoneyDeposited $event): void
    {
        // ❌ WRONG - Not idempotent
        Account::where('uuid', $event->accountUuid)
            ->increment('balance', $event->amount->inCents());

        // ✅ CORRECT - Idempotent using event metadata
        AccountTransaction::updateOrCreate(
            [
                'event_id' => $event->aggregateRootUuid(),
                'event_version' => $event->aggregateRootVersion(),
            ],
            [
                'account_uuid' => $event->accountUuid,
                'type' => 'deposit',
                'amount' => $event->amount->inCents(),
                'processed_at' => now(),
            ]
        );

        // Recalculate balance from all events
        $this->recalculateBalance($event->accountUuid);
    }

    private function recalculateBalance(string $accountUuid): void
    {
        $balance = AccountTransaction::where('account_uuid', $accountUuid)
            ->sum('amount');

        Account::where('uuid', $accountUuid)
            ->update(['balance' => $balance]);
    }
}
```

### Async Projectors

Use queued projectors for heavy operations:

```php
class NotificationProjector extends QueuedProjector
{
    // Process on separate queue
    public string $queue = 'projectors';

    public function onLargeTransferInitiated(LargeTransferInitiated $event): void
    {
        // Heavy operations that shouldn't block
        $this->sendEmailNotification($event);
        $this->sendSmsAlert($event);
        $this->notifyComplianceTeam($event);
        $this->updateRiskMetrics($event);
    }
}
```

### Read Model Updates

Keep read models eventually consistent:

```php
class BalanceProjector extends Projector
{
    public function onMoneyDeposited(MoneyDeposited $event): void
    {
        DB::transaction(function () use ($event) {
            // Update read model
            Balance::updateOrCreate(
                ['account_uuid' => $event->accountUuid],
                ['amount' => DB::raw("amount + {$event->amount->inCents()}")]
            );

            // Track projection status
            ProjectionStatus::create([
                'projector' => static::class,
                'event_id' => $event->storedEventId(),
                'processed_at' => now(),
            ]);
        });
    }
}
```

## Testing Strategies

### Testing Aggregates

Test behavior, not implementation:

```php
test('account can process deposit', function () {
    $account = AccountAggregate::retrieve($uuid);
    
    // Act
    $account->deposit(Money::fromCents(10000));
    
    // Assert recorded events
    $events = $account->getRecordedEvents();
    expect($events)->toHaveCount(1);
    expect($events[0])->toBeInstanceOf(MoneyDeposited::class);
    expect($events[0]->amount->inCents())->toBe(10000);
    
    // Persist and verify
    $account->persist();
    
    // Load fresh and verify state
    $account = AccountAggregate::retrieve($uuid);
    expect($account->getBalance())->toBe(10000);
});
```

### Testing Projectors

Test idempotency and correctness:

```php
test('projector is idempotent', function () {
    $event = new MoneyDeposited($accountUuid, Money::fromCents(5000));
    
    // Project once
    $projector = new AccountProjector();
    $projector->onMoneyDeposited($event);
    
    $balance1 = Account::find($accountUuid)->balance;
    
    // Project again (replay)
    $projector->onMoneyDeposited($event);
    
    $balance2 = Account::find($accountUuid)->balance;
    
    // Balance should not change
    expect($balance2)->toBe($balance1);
});
```

### Testing Event Flows

Test complete workflows:

```php
test('transfer workflow completes successfully', function () {
    $from = AccountAggregate::retrieve($fromUuid);
    $to = AccountAggregate::retrieve($toUuid);
    
    // Setup initial state
    $from->deposit(Money::fromCents(10000));
    $from->persist();
    
    // Execute transfer
    $transfer = TransferAggregate::retrieve($transferUuid);
    $transfer->initiate($fromUuid, $toUuid, Money::fromCents(5000));
    $transfer->persist();
    
    // Process projections
    Artisan::call('event-sourcing:replay', [
        'projector' => AccountProjector::class,
    ]);
    
    // Verify final state
    expect(Account::find($fromUuid)->balance)->toBe(5000);
    expect(Account::find($toUuid)->balance)->toBe(5000);
});
```

## Performance Optimization

### Event Store Indexing

Optimize database queries:

```sql
-- Essential indexes for event store
CREATE INDEX idx_stored_events_aggregate ON stored_events(aggregate_uuid, aggregate_version);
CREATE INDEX idx_stored_events_created ON stored_events(created_at);
CREATE INDEX idx_stored_events_class ON stored_events(event_class);

-- Composite index for replay queries
CREATE INDEX idx_stored_events_replay ON stored_events(
    aggregate_uuid, 
    aggregate_version, 
    created_at
);
```

### Partition Large Event Tables

For high-volume systems:

```sql
-- Partition by month
CREATE TABLE stored_events_2025_01 PARTITION OF stored_events
    FOR VALUES FROM ('2025-01-01') TO ('2025-02-01');

CREATE TABLE stored_events_2025_02 PARTITION OF stored_events
    FOR VALUES FROM ('2025-02-01') TO ('2025-03-01');
```

### Caching Strategies

Cache aggregate state:

```php
class CachedAccountAggregate extends AccountAggregate
{
    protected function loadEvents(): Collection
    {
        return Cache::remember(
            "aggregate:{$this->uuid()}:events",
            300, // 5 minutes
            fn() => parent::loadEvents()
        );
    }

    public function persist(): self
    {
        parent::persist();
        Cache::forget("aggregate:{$this->uuid()}:events");
        return $this;
    }
}
```

### Async Event Processing

Process events asynchronously:

```php
class AsyncEventDispatcher
{
    public function dispatch(StoredEvent $event): void
    {
        // Critical projectors - synchronous
        $this->dispatchToCriticalProjectors($event);
        
        // Non-critical projectors - async
        DispatchProjectorsJob::dispatch($event)
            ->onQueue('projectors')
            ->delay(now()->addSeconds(1));
    }
}
```

## Migration & Versioning

### Event Versioning

Handle event schema changes:

```php
class MoneyDeposited extends ShouldBeStored
{
    public int $version = 2;

    public static function fromV1(array $data): self
    {
        // Migrate from version 1
        return new self(
            accountUuid: $data['account_id'], // renamed field
            amount: Money::fromCents($data['amount_cents']),
            currency: $data['currency'] ?? 'USD', // new field with default
            hash: Hash::generate($data['amount_cents']),
            depositedBy: $data['user_id'],
            depositedAt: new DateTimeImmutable($data['created_at'])
        );
    }
}
```

### Upcasting Events

Transform old events to new format:

```php
class MoneyDepositedUpcaster extends Upcaster
{
    public function canUpcast(StoredEvent $event): bool
    {
        return $event->event_class === MoneyDeposited::class
            && $event->event_version === 1;
    }

    public function upcast(StoredEvent $event): StoredEvent
    {
        $data = $event->event_properties;
        
        // Transform to v2 structure
        $data['accountUuid'] = $data['account_id'];
        $data['currency'] = 'USD';
        unset($data['account_id']);
        
        $event->event_properties = $data;
        $event->event_version = 2;
        
        return $event;
    }
}
```

## Common Pitfalls

### 1. Mutating Events

```php
// ❌ WRONG - Never change event data
class FixEventDataCommand
{
    public function handle()
    {
        StoredEvent::where('event_class', SomeEvent::class)
            ->update(['event_properties->amount' => 1000]);
    }
}

// ✅ CORRECT - Create compensating events
class FixAmountCommand
{
    public function handle()
    {
        $aggregate = AccountAggregate::retrieve($uuid);
        $aggregate->recordThat(new AmountCorrected($correctAmount));
        $aggregate->persist();
    }
}
```

### 2. Fat Events

```php
// ❌ WRONG - Event with too much data
class OrderPlaced extends ShouldBeStored
{
    public function __construct(
        public readonly Order $entireOrder, // Full ORM model
        public readonly Customer $customer, // Another model
        public readonly array $allProducts // All product details
    ) {}
}

// ✅ CORRECT - Minimal event data
class OrderPlaced extends ShouldBeStored
{
    public function __construct(
        public readonly OrderId $orderId,
        public readonly CustomerId $customerId,
        public readonly Money $totalAmount,
        public readonly int $itemCount
    ) {}
}
```

### 3. Synchronous Everything

```php
// ❌ WRONG - All projectors synchronous
class OrderProjector extends Projector
{
    public function onOrderPlaced($event)
    {
        $this->updateInventory($event);      // Slow
        $this->sendEmails($event);           // Slower
        $this->generateReports($event);      // Slowest
        $this->notifyWarehouse($event);      // External API
    }
}

// ✅ CORRECT - Separate by priority
class CriticalOrderProjector extends Projector
{
    public function onOrderPlaced($event)
    {
        $this->updateInventory($event); // Must be immediate
    }
}

class AsyncOrderProjector extends QueuedProjector
{
    public function onOrderPlaced($event)
    {
        $this->sendEmails($event);
        $this->generateReports($event);
        $this->notifyWarehouse($event);
    }
}
```

## FinAegis Patterns

### Multi-Asset Events

Handle multi-asset transactions:

```php
class AssetTransferred extends ShouldBeStored
{
    public function __construct(
        public readonly AccountUuid $from,
        public readonly AccountUuid $to,
        public readonly string $assetCode,
        public readonly BigDecimal $amount,
        public readonly ExchangeRate $rate,
        public readonly Hash $hash
    ) {}
}

class MultiAssetProjector extends Projector
{
    public function onAssetTransferred(AssetTransferred $event): void
    {
        DB::transaction(function () use ($event) {
            // Update sender balance
            AccountBalance::where('account_uuid', $event->from)
                ->where('asset_code', $event->assetCode)
                ->decrement('balance', $event->amount->toScale(0));

            // Update receiver balance
            AccountBalance::where('account_uuid', $event->to)
                ->where('asset_code', $event->assetCode)
                ->increment('balance', $event->amount->toScale(0));

            // Record transaction
            AssetTransaction::create([
                'from_account' => $event->from,
                'to_account' => $event->to,
                'asset_code' => $event->assetCode,
                'amount' => $event->amount->toScale(0),
                'exchange_rate' => $event->rate->value,
                'hash' => $event->hash->value,
            ]);
        });
    }
}
```

### Liquidity Pool Events

Complex DeFi operations:

```php
class LiquidityAdded extends ShouldBeStored
{
    public function __construct(
        public readonly PoolId $poolId,
        public readonly ProviderId $providerId,
        public readonly BigDecimal $baseAmount,
        public readonly BigDecimal $quoteAmount,
        public readonly BigDecimal $sharesIssued,
        public readonly BigDecimal $newK, // Constant product
        public readonly DateTimeImmutable $addedAt
    ) {}
}

class LiquidityPoolProjector extends Projector
{
    public function onLiquidityAdded(LiquidityAdded $event): void
    {
        // Update pool state
        $pool = LiquidityPool::find($event->poolId);
        $pool->base_reserve = $pool->base_reserve->add($event->baseAmount);
        $pool->quote_reserve = $pool->quote_reserve->add($event->quoteAmount);
        $pool->total_shares = $pool->total_shares->add($event->sharesIssued);
        $pool->k_value = $event->newK;
        $pool->save();

        // Update provider position
        LiquidityPosition::updateOrCreate(
            [
                'pool_id' => $event->poolId,
                'provider_id' => $event->providerId,
            ],
            [
                'shares' => DB::raw("shares + {$event->sharesIssued}"),
                'base_deposited' => DB::raw("base_deposited + {$event->baseAmount}"),
                'quote_deposited' => DB::raw("quote_deposited + {$event->quoteAmount}"),
            ]
        );
    }
}
```

### Compliance Events

Regulatory and audit events:

```php
class ComplianceEventTrait
{
    public function recordComplianceEvent(
        string $action,
        array $data,
        string $userId,
        string $reason
    ): void {
        $this->recordThat(new ComplianceEventRecorded(
            aggregateId: $this->uuid(),
            action: $action,
            data: $data,
            userId: $userId,
            reason: $reason,
            ipAddress: request()->ip(),
            userAgent: request()->userAgent(),
            timestamp: now()
        ));
    }
}

class AccountAggregate extends AggregateRoot
{
    use ComplianceEventTrait;

    public function freeze(string $reason, string $authorizedBy): void
    {
        $this->recordComplianceEvent(
            'account_frozen',
            ['previous_status' => $this->status],
            $authorizedBy,
            $reason
        );

        $this->recordThat(new AccountFrozen(
            $this->uuid(),
            $reason,
            $authorizedBy
        ));
    }
}
```

## Monitoring & Observability

### Event Metrics

Track event sourcing health:

```php
class EventMetricsCollector
{
    public function collect(): array
    {
        return [
            'total_events' => StoredEvent::count(),
            'events_today' => StoredEvent::whereDate('created_at', today())->count(),
            'events_per_minute' => $this->calculateEventRate(),
            'largest_aggregate' => $this->findLargestAggregate(),
            'projection_lag' => $this->calculateProjectionLag(),
            'failed_projections' => $this->countFailedProjections(),
        ];
    }
}
```

### Health Checks

Monitor system health:

```php
class EventSourcingHealthCheck extends HealthCheck
{
    public function check(): Result
    {
        // Check event store connectivity
        try {
            StoredEvent::latest()->first();
        } catch (\Exception $e) {
            return Result::failed('Event store unreachable');
        }

        // Check projection lag
        $lag = ProjectionStatus::max('lag_seconds');
        if ($lag > 60) {
            return Result::warning("Projection lag: {$lag} seconds");
        }

        // Check failed events
        $failed = FailedEvent::where('created_at', '>', now()->subHour())->count();
        if ($failed > 0) {
            return Result::warning("{$failed} failed events in last hour");
        }

        return Result::ok();
    }
}
```

## Conclusion

Event sourcing provides powerful capabilities for audit, debugging, and temporal queries, but requires careful design and implementation. Follow these best practices to build maintainable, performant event-sourced systems in FinAegis.

Key takeaways:
- Events are immutable facts
- Keep aggregates small and focused  
- Design for eventual consistency
- Test thoroughly, including idempotency
- Monitor and measure everything
- Plan for versioning from the start

For FinAegis-specific patterns, refer to the domain documentation in `/docs/02-ARCHITECTURE/` and example implementations in `/app/Domain/*/`.