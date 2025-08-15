# Event Sourcing Best Practices

## Overview

This guide provides comprehensive best practices for implementing event sourcing in the FinAegis platform. Event sourcing stores all changes to application state as a sequence of events, providing a complete audit trail and enabling powerful features like temporal queries and event replay.

## Core Principles

### 1. Events are Immutable
- Once stored, events should never be modified or deleted
- Corrections are made by appending compensating events
- This ensures a complete and trustworthy audit trail

### 2. Events Represent Facts
- Events describe something that has already happened
- Use past tense naming: `AccountCreated`, `FundsTransferred`, `OrderPlaced`
- Events cannot be rejected once they've occurred

### 3. Events are Business-Focused
- Events should represent business concepts, not technical implementation
- Good: `CustomerRegistered`, `PaymentProcessed`
- Bad: `DatabaseUpdated`, `CacheInvalidated`

## Event Design Best Practices

### Event Structure

```php
<?php

namespace App\Domain\Treasury\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class TreasuryAccountCreated extends ShouldBeStored
{
    public function __construct(
        public readonly string $accountId,
        public readonly string $name,
        public readonly string $currency,
        public readonly string $accountType,
        public readonly float $initialBalance,
        public readonly array $metadata,
        public readonly string $createdBy,
        public readonly DateTimeImmutable $createdAt
    ) {}
}
```

### Event Naming Conventions

1. **Use Domain Language**: Events should use ubiquitous language from the domain
2. **Past Tense**: Always use past tense to indicate something has happened
3. **Specific and Descriptive**: Be specific about what occurred

Examples:
- ✅ `LoanApplicationSubmitted`
- ✅ `RiskAssessmentCompleted`
- ✅ `YieldOptimizationStarted`
- ❌ `ProcessLoan`
- ❌ `UpdateStatus`
- ❌ `DataChanged`

### Event Versioning

```php
<?php

namespace App\Domain\Exchange\Events;

use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class OrderPlacedV2 extends ShouldBeStored
{
    public const VERSION = 2;
    
    public function __construct(
        // Original fields from V1
        public readonly string $orderId,
        public readonly string $userId,
        public readonly string $type,
        public readonly float $amount,
        // New field in V2
        public readonly ?string $referralCode = null
    ) {}
    
    /**
     * Upgrade from V1 to V2
     */
    public static function fromV1(OrderPlacedV1 $v1Event): self
    {
        return new self(
            orderId: $v1Event->orderId,
            userId: $v1Event->userId,
            type: $v1Event->type,
            amount: $v1Event->amount,
            referralCode: null // Default value for new field
        );
    }
}
```

## Aggregate Implementation

### Separate Event Storage per Aggregate

Each aggregate should have its own event storage table for better performance and isolation:

```php
<?php

namespace App\Domain\Treasury\Aggregates;

use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class TreasuryAggregate extends AggregateRoot
{
    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app()->make(TreasuryEventRepository::class);
    }
    
    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app()->make(TreasurySnapshotRepository::class);
    }
}
```

### Migration for Separate Tables

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('treasury_events', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->integer('event_version')->default(1);
            $table->string('event_class');
            $table->json('event_properties');
            $table->json('meta_data');
            $table->timestamp('created_at');
            
            $table->unique(['aggregate_uuid', 'aggregate_version']);
            $table->index('event_class');
            $table->index('created_at');
        });
    }
};
```

### Aggregate Business Logic

```php
<?php

namespace App\Domain\Treasury\Aggregates;

class TreasuryAggregate extends AggregateRoot
{
    protected float $balance = 0;
    protected string $status = 'active';
    protected array $allocations = [];
    
    public function createAccount(
        string $accountId,
        string $name,
        string $currency,
        float $initialBalance
    ): void {
        // Guards - Business rule validation
        if ($this->status !== 'new') {
            throw new DomainException('Account already exists');
        }
        
        if ($initialBalance < 0) {
            throw new DomainException('Initial balance cannot be negative');
        }
        
        // Record event - Single source of truth
        $this->recordThat(new TreasuryAccountCreated(
            accountId: $accountId,
            name: $name,
            currency: $currency,
            accountType: 'treasury',
            initialBalance: $initialBalance,
            metadata: [],
            createdBy: auth()->id() ?? 'system',
            createdAt: now()
        ));
    }
    
    // Apply event to update state
    protected function applyTreasuryAccountCreated(
        TreasuryAccountCreated $event
    ): void {
        $this->balance = $event->initialBalance;
        $this->status = 'active';
    }
}
```

## Snapshot Strategy

### When to Snapshot

```php
<?php

namespace App\Domain\Treasury\Aggregates;

class TreasuryAggregate extends AggregateRoot
{
    protected int $snapshotEveryNEvents = 100;
    
    public function persist(): self
    {
        parent::persist();
        
        // Create snapshot every 100 events
        if ($this->aggregateVersion % $this->snapshotEveryNEvents === 0) {
            $this->snapshot();
        }
        
        return $this;
    }
    
    protected function getState(): array
    {
        return [
            'balance' => $this->balance,
            'status' => $this->status,
            'allocations' => $this->allocations,
            'risk_profile' => $this->riskProfile,
            'metadata' => $this->metadata,
        ];
    }
    
    protected function restoreFromSnapshot(array $state): void
    {
        $this->balance = $state['balance'];
        $this->status = $state['status'];
        $this->allocations = $state['allocations'];
        $this->riskProfile = $state['risk_profile'];
        $this->metadata = $state['metadata'];
    }
}
```

## Projections and Read Models

### Creating Projections

```php
<?php

namespace App\Domain\Treasury\Projectors;

use App\Domain\Treasury\Events\CashAllocated;
use App\Domain\Treasury\Events\YieldOptimizationStarted;
use App\Domain\Treasury\ReadModels\PortfolioReadModel;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class PortfolioProjector extends Projector
{
    public function onCashAllocated(CashAllocated $event): void
    {
        PortfolioReadModel::updateOrCreate(
            ['account_id' => $event->accountId],
            [
                'total_allocated' => DB::raw("total_allocated + {$event->amount}"),
                'allocation_strategy' => $event->strategy,
                'last_allocation_at' => $event->allocatedAt,
                'allocations' => json_encode($event->allocations),
            ]
        );
    }
    
    public function onYieldOptimizationStarted(YieldOptimizationStarted $event): void
    {
        PortfolioReadModel::where('account_id', $event->accountId)
            ->update([
                'optimization_status' => 'in_progress',
                'target_yield' => $event->targetYield,
                'risk_profile' => $event->riskProfile,
            ]);
    }
    
    public function resetState(): void
    {
        PortfolioReadModel::truncate();
    }
}
```

### Async Projections with Queues

```php
<?php

namespace App\Domain\Treasury\Projectors;

use Spatie\EventSourcing\EventHandlers\Projectors\QueuedProjector;

class AsyncPortfolioProjector extends QueuedProjector
{
    protected string $queue = 'projections';
    protected int $retries = 3;
    
    public function onCashAllocated(CashAllocated $event): void
    {
        // Heavy computation in background
        $this->calculatePortfolioMetrics($event->accountId);
        $this->updateRiskScores($event->accountId);
        $this->generateReports($event->accountId);
    }
}
```

## Saga Implementation

### Multi-Step Workflows with Compensation

```php
<?php

namespace App\Domain\Treasury\Sagas;

use App\Domain\Treasury\Events\RiskAssessmentCompleted;
use App\Domain\Treasury\Events\TreasuryAccountCreated;
use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;

class RiskManagementSaga extends Reactor
{
    private array $sagaData = [];
    
    public function onTreasuryAccountCreated(TreasuryAccountCreated $event): void
    {
        $this->sagaData[$event->accountId] = [
            'status' => 'initiated',
            'steps_completed' => [],
        ];
        
        try {
            // Step 1: Perform risk assessment
            $this->performRiskAssessment($event->accountId);
            
            // Step 2: Set up monitoring
            $this->setupRiskMonitoring($event->accountId);
            
            // Step 3: Generate initial report
            $this->generateRiskReport($event->accountId);
            
        } catch (\Exception $e) {
            // Compensate on failure
            $this->compensate($event->accountId);
            throw $e;
        }
    }
    
    private function compensate(string $accountId): void
    {
        Log::info('Compensating risk management saga', [
            'account_id' => $accountId,
            'completed_steps' => $this->sagaData[$accountId]['steps_completed'],
        ]);
        
        // Reverse completed steps
        foreach (array_reverse($this->sagaData[$accountId]['steps_completed']) as $step) {
            match($step) {
                'risk_assessment' => $this->rollbackRiskAssessment($accountId),
                'monitoring_setup' => $this->rollbackMonitoring($accountId),
                'report_generation' => $this->deleteReport($accountId),
            };
        }
    }
}
```

## Testing Event Sourced Systems

### Testing Aggregates

```php
<?php

namespace Tests\Feature\Domain\Treasury;

use App\Domain\Treasury\Aggregates\TreasuryAggregate;
use App\Domain\Treasury\Events\CashAllocated;
use App\Domain\Treasury\Events\TreasuryAccountCreated;

it('records events when allocating cash', function () {
    // Arrange
    $aggregate = TreasuryAggregate::fake();
    
    // Act
    $aggregate->createAccount('test-id', 'Test Account', 'USD', 100000);
    $aggregate->allocateCash('alloc-1', 'balanced', 50000, 'user-1');
    
    // Assert
    $aggregate->assertRecorded([
        new TreasuryAccountCreated(
            accountId: 'test-id',
            name: 'Test Account',
            currency: 'USD',
            accountType: 'treasury',
            initialBalance: 100000,
            metadata: [],
            createdBy: 'system',
            createdAt: now()
        ),
        new CashAllocated(
            accountId: 'test-id',
            allocationId: 'alloc-1',
            strategy: 'balanced',
            amount: 50000,
            allocations: [...],
            allocatedBy: 'user-1',
            allocatedAt: now()
        ),
    ]);
});
```

### Testing Projections

```php
<?php

namespace Tests\Feature\Domain\Treasury;

use App\Domain\Treasury\Events\CashAllocated;
use App\Domain\Treasury\Projectors\PortfolioProjector;
use App\Domain\Treasury\ReadModels\PortfolioReadModel;

it('projects cash allocation to read model', function () {
    // Arrange
    $event = new CashAllocated(
        accountId: 'test-account',
        allocationId: 'alloc-1',
        strategy: 'balanced',
        amount: 50000,
        allocations: [...],
        allocatedBy: 'user-1',
        allocatedAt: now()
    );
    
    // Act
    $projector = new PortfolioProjector();
    $projector->onCashAllocated($event);
    
    // Assert
    $portfolio = PortfolioReadModel::where('account_id', 'test-account')->first();
    
    expect($portfolio)
        ->total_allocated->toBe(50000.0)
        ->allocation_strategy->toBe('balanced')
        ->allocations->toBeJson();
});
```

## Performance Optimization

### 1. Event Storage Optimization

```sql
-- Optimize event queries with proper indexes
CREATE INDEX idx_treasury_events_aggregate_version 
ON treasury_events(aggregate_uuid, aggregate_version);

CREATE INDEX idx_treasury_events_created_at 
ON treasury_events(created_at);

CREATE INDEX idx_treasury_events_event_class 
ON treasury_events(event_class);

-- Partition large event tables by date
ALTER TABLE treasury_events 
PARTITION BY RANGE (YEAR(created_at)) (
    PARTITION p2024 VALUES LESS THAN (2025),
    PARTITION p2025 VALUES LESS THAN (2026),
    PARTITION p_future VALUES LESS THAN MAXVALUE
);
```

### 2. Batch Event Processing

```php
<?php

namespace App\Infrastructure\EventSourcing;

class BatchEventProcessor
{
    public function processBatch(array $events): void
    {
        DB::transaction(function () use ($events) {
            foreach (array_chunk($events, 100) as $chunk) {
                $this->processChunk($chunk);
            }
        });
    }
    
    private function processChunk(array $events): void
    {
        $inserts = array_map(fn($event) => [
            'aggregate_uuid' => $event->aggregateUuid(),
            'aggregate_version' => $event->aggregateVersion(),
            'event_class' => get_class($event),
            'event_properties' => json_encode($event),
            'meta_data' => json_encode($event->metaData()),
            'created_at' => now(),
        ], $events);
        
        DB::table('events')->insert($inserts);
    }
}
```

### 3. Caching Strategies

```php
<?php

namespace App\Domain\Treasury\Services;

use Illuminate\Support\Facades\Cache;

class CachedAggregateRepository
{
    public function retrieve(string $uuid): TreasuryAggregate
    {
        // Check cache first
        $cacheKey = "aggregate:treasury:{$uuid}";
        
        if ($cached = Cache::get($cacheKey)) {
            return $cached;
        }
        
        // Load from event store
        $aggregate = TreasuryAggregate::retrieve($uuid);
        
        // Cache for 5 minutes
        Cache::put($cacheKey, $aggregate, 300);
        
        return $aggregate;
    }
    
    public function persist(TreasuryAggregate $aggregate): void
    {
        $aggregate->persist();
        
        // Invalidate cache
        Cache::forget("aggregate:treasury:{$aggregate->uuid()}");
    }
}
```

## Monitoring and Debugging

### Event Store Metrics

```php
<?php

namespace App\Infrastructure\Monitoring;

class EventStoreMetrics
{
    public function collectMetrics(): array
    {
        return [
            'total_events' => DB::table('treasury_events')->count(),
            'events_today' => DB::table('treasury_events')
                ->whereDate('created_at', today())
                ->count(),
            'unique_aggregates' => DB::table('treasury_events')
                ->distinct('aggregate_uuid')
                ->count(),
            'avg_events_per_aggregate' => DB::table('treasury_events')
                ->selectRaw('AVG(event_count) as avg')
                ->from(DB::raw('(SELECT COUNT(*) as event_count 
                                FROM treasury_events 
                                GROUP BY aggregate_uuid) as counts'))
                ->value('avg'),
            'largest_aggregate' => DB::table('treasury_events')
                ->selectRaw('aggregate_uuid, COUNT(*) as event_count')
                ->groupBy('aggregate_uuid')
                ->orderByDesc('event_count')
                ->first(),
        ];
    }
}
```

### Event Replay Tool

```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ReplayEventsCommand extends Command
{
    protected $signature = 'events:replay 
                            {--from= : Start date}
                            {--to= : End date}
                            {--aggregate= : Specific aggregate UUID}
                            {--projector= : Specific projector class}';
    
    public function handle(): void
    {
        $query = DB::table('treasury_events');
        
        if ($from = $this->option('from')) {
            $query->where('created_at', '>=', $from);
        }
        
        if ($to = $this->option('to')) {
            $query->where('created_at', '<=', $to);
        }
        
        if ($aggregate = $this->option('aggregate')) {
            $query->where('aggregate_uuid', $aggregate);
        }
        
        $events = $query->orderBy('created_at')->cursor();
        
        $this->info('Replaying ' . $query->count() . ' events...');
        
        $progressBar = $this->output->createProgressBar($query->count());
        
        foreach ($events as $storedEvent) {
            $event = $this->deserializeEvent($storedEvent);
            
            if ($projector = $this->option('projector')) {
                app($projector)->handle($event);
            } else {
                event($event);
            }
            
            $progressBar->advance();
        }
        
        $progressBar->finish();
        $this->info("\nReplay completed!");
    }
}
```

## Common Pitfalls and Solutions

### 1. Event Granularity
**Problem**: Too many fine-grained events
**Solution**: Combine related changes into meaningful business events

```php
// ❌ Too granular
$this->recordThat(new FieldUpdated('name', $name));
$this->recordThat(new FieldUpdated('email', $email));
$this->recordThat(new FieldUpdated('phone', $phone));

// ✅ Business-focused
$this->recordThat(new CustomerProfileUpdated($name, $email, $phone));
```

### 2. Event Payload Size
**Problem**: Large events with unnecessary data
**Solution**: Store only essential data, use references for large objects

```php
// ❌ Storing entire objects
$this->recordThat(new ReportGenerated($fullReportContent));

// ✅ Store reference
$this->recordThat(new ReportGenerated($reportId, $reportUrl));
```

### 3. Synchronous Projections
**Problem**: Slow projections blocking main flow
**Solution**: Use queued projectors for heavy computations

```php
// ❌ Synchronous heavy computation
class SlowProjector extends Projector
{
    public function onOrderPlaced($event): void
    {
        $this->calculateComplexMetrics($event); // Takes 5 seconds
    }
}

// ✅ Asynchronous processing
class FastProjector extends QueuedProjector
{
    protected string $queue = 'projections';
    
    public function onOrderPlaced($event): void
    {
        dispatch(new CalculateMetricsJob($event))->onQueue('analytics');
    }
}
```

## Migration Strategy

### Moving from CRUD to Event Sourcing

1. **Parallel Run**: Run event sourcing alongside existing system
2. **Gradual Migration**: Migrate one aggregate at a time
3. **Event Generation**: Generate initial events from existing data
4. **Verification**: Ensure read models match existing data
5. **Cutover**: Switch to event-sourced system

```php
<?php

namespace App\Console\Commands;

class MigrateToEventSourcingCommand extends Command
{
    public function handle(): void
    {
        Account::chunk(100, function ($accounts) {
            foreach ($accounts as $account) {
                $aggregate = TreasuryAggregate::retrieve($account->uuid);
                
                // Generate initial event from existing data
                $aggregate->recordThat(new TreasuryAccountMigrated(
                    accountId: $account->uuid,
                    name: $account->name,
                    balance: $account->balance,
                    status: $account->status,
                    migratedAt: now()
                ));
                
                $aggregate->persist();
            }
        });
    }
}
```

## Conclusion

Event sourcing provides powerful capabilities but requires careful design and implementation. Follow these best practices to build maintainable, performant, and reliable event-sourced systems in the FinAegis platform.