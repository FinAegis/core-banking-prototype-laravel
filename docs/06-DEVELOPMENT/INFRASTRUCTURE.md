# Infrastructure Development Guide

**Last Updated:** 2024-09-08  
**Status:** Production Ready

## Overview

The FinAegis platform uses a complete CQRS and Event Sourcing infrastructure built on Laravel. This guide covers the infrastructure components, patterns, and development practices.

## Architecture Components

### Domain Layer (`app/Domain/`)

```
Domain/
├── Shared/                 # Shared domain interfaces
│   ├── CQRS/              # Command & Query interfaces
│   │   ├── Command.php
│   │   ├── CommandBus.php
│   │   ├── Query.php
│   │   └── QueryBus.php
│   └── Events/            # Domain event interfaces
│       ├── DomainEvent.php
│       └── DomainEventBus.php
└── [DomainName]/          # Domain-specific implementations
    ├── Commands/
    ├── Queries/
    ├── Handlers/
    └── Events/
```

### Infrastructure Layer (`app/Infrastructure/`)

```
Infrastructure/
├── CQRS/
│   ├── LaravelCommandBus.php    # Command bus implementation
│   ├── LaravelQueryBus.php      # Query bus with caching
│   └── AsyncCommandJob.php      # Queue job for async commands
└── Events/
    ├── LaravelDomainEventBus.php # Event bus implementation
    └── AsyncDomainEventJob.php   # Queue job for async events
```

## CQRS Implementation

### Command Pattern

#### 1. Define Command

```php
namespace App\Domain\Exchange\Commands;

use App\Domain\Shared\CQRS\Command;

class PlaceOrderCommand implements Command
{
    public function __construct(
        public readonly string $userId,
        public readonly string $type,      // market, limit
        public readonly string $side,      // buy, sell
        public readonly float $amount,
        public readonly string $pair,
        public readonly ?float $price = null
    ) {}
}
```

#### 2. Create Handler

```php
namespace App\Domain\Exchange\Handlers;

use App\Domain\Exchange\Commands\PlaceOrderCommand;
use App\Domain\Exchange\Services\OrderService;

class PlaceOrderHandler
{
    public function __construct(
        private OrderService $orderService
    ) {}

    public function handle(PlaceOrderCommand $command): Order
    {
        return $this->orderService->placeOrder(
            userId: $command->userId,
            type: $command->type,
            side: $command->side,
            amount: $command->amount,
            pair: $command->pair,
            price: $command->price
        );
    }
}
```

#### 3. Register Handler

```php
// app/Providers/DomainServiceProvider.php
private function registerCommandHandlers(): void
{
    if (config('domain.enable_handlers', false)) {
        $commandBus = $this->app->make(CommandBus::class);
        
        $commandBus->register(
            PlaceOrderCommand::class,
            PlaceOrderHandler::class
        );
    }
}
```

#### 4. Dispatch Command

```php
// In controller or service
$commandBus = app(CommandBus::class);

$order = $commandBus->dispatch(new PlaceOrderCommand(
    userId: auth()->id(),
    type: 'market',
    side: 'buy',
    amount: 0.1,
    pair: 'BTC/USD'
));

// Async dispatch
$commandBus->dispatchAsync(new PlaceOrderCommand(...), delay: 60);

// Transactional batch
$results = $commandBus->dispatchTransaction([
    new PlaceOrderCommand(...),
    new UpdateBalanceCommand(...),
]);
```

### Query Pattern

#### 1. Define Query

```php
namespace App\Domain\Exchange\Queries;

use App\Domain\Shared\CQRS\Query;

class GetOrderBookQuery implements Query
{
    public function __construct(
        public readonly string $pair,
        public readonly int $depth = 20
    ) {}
}
```

#### 2. Create Handler

```php
namespace App\Domain\Exchange\Handlers;

class GetOrderBookHandler
{
    public function handle(GetOrderBookQuery $query): OrderBook
    {
        return OrderBook::where('pair', $query->pair)
            ->with(['bids', 'asks'])
            ->limit($query->depth)
            ->first();
    }
}
```

#### 3. Execute Query

```php
$queryBus = app(QueryBus::class);

// Simple query
$orderBook = $queryBus->ask(new GetOrderBookQuery('BTC/USD'));

// Cached query (1 hour TTL)
$orderBook = $queryBus->askCached(
    new GetOrderBookQuery('BTC/USD'),
    ttl: 3600
);

// Multiple queries
$results = $queryBus->askMultiple([
    'btc' => new GetOrderBookQuery('BTC/USD'),
    'eth' => new GetOrderBookQuery('ETH/USD'),
]);
```

## Domain Events

### Event Definition

```php
namespace App\Domain\Exchange\Events;

use App\Domain\Shared\Events\DomainEvent;

class OrderPlaced extends DomainEvent
{
    public function __construct(
        public readonly string $orderId,
        public readonly string $userId,
        public readonly array $orderData,
        public readonly \DateTimeImmutable $occurredAt
    ) {}
}
```

### Event Publishing

```php
$eventBus = app(DomainEventBus::class);

// Publish single event
$eventBus->publish(new OrderPlaced(
    orderId: $order->id,
    userId: $order->user_id,
    orderData: $order->toArray(),
    occurredAt: now()->toDateTimeImmutable()
));

// Publish multiple events
$eventBus->publishMultiple([
    new OrderPlaced(...),
    new BalanceUpdated(...),
]);

// Async publishing
$eventBus->publishAsync(new OrderPlaced(...), delay: 10);

// Transaction support
$eventBus->record(new OrderPlaced(...));
$eventBus->record(new BalanceUpdated(...));
// ... do work ...
$eventBus->dispatchRecorded(); // or clearRecorded() to cancel
```

### Event Handling

```php
namespace App\Domain\Exchange\Handlers;

class OrderPlacedHandler
{
    public function handle(OrderPlaced $event): void
    {
        // Update order book
        // Send notifications
        // Update statistics
    }
}

// Register in DomainServiceProvider
$eventBus->subscribe(
    OrderPlaced::class,
    OrderPlacedHandler::class,
    priority: 10  // Higher priority executes first
);
```

## Configuration

### Environment Variables

```bash
# .env configuration

# Enable/disable handler registration
DOMAIN_ENABLE_HANDLERS=true  # false for demo mode

# Queue configuration for async operations
QUEUE_CONNECTION=redis
QUEUE_DRIVER=redis

# Cache configuration for query bus
CACHE_DRIVER=redis
```

### Configuration File

```php
// config/domain.php
return [
    'enable_handlers' => env('DOMAIN_ENABLE_HANDLERS', true),
    
    'command_bus' => [
        'async_queue' => 'commands',
        'transaction_timeout' => 30,
    ],
    
    'query_bus' => [
        'cache_prefix' => 'query',
        'default_ttl' => 3600,
    ],
    
    'event_bus' => [
        'async_queue' => 'events',
        'priority_levels' => 10,
    ],
];
```

## Testing

### Unit Testing Commands

```php
class PlaceOrderCommandTest extends TestCase
{
    public function test_place_order_command()
    {
        $commandBus = $this->mock(CommandBus::class);
        
        $commandBus->shouldReceive('dispatch')
            ->once()
            ->with(Mockery::type(PlaceOrderCommand::class))
            ->andReturn(new Order(['id' => 'test-123']));
        
        $result = $commandBus->dispatch(new PlaceOrderCommand(
            userId: 'user-1',
            type: 'market',
            side: 'buy',
            amount: 0.1,
            pair: 'BTC/USD'
        ));
        
        $this->assertEquals('test-123', $result->id);
    }
}
```

### Testing Event Publishing

```php
public function test_event_publishing()
{
    $eventBus = app(DomainEventBus::class);
    
    $handled = false;
    $eventBus->subscribe(OrderPlaced::class, function ($event) use (&$handled) {
        $handled = true;
    });
    
    $eventBus->publish(new OrderPlaced(
        orderId: 'order-123',
        userId: 'user-1',
        orderData: [],
        occurredAt: now()->toDateTimeImmutable()
    ));
    
    $this->assertTrue($handled);
}
```

## Best Practices

### 1. Command Design
- Keep commands immutable (readonly properties)
- Include all data needed for execution
- Use value objects for complex data
- Validate in command constructor

### 2. Query Optimization
- Use caching for expensive queries
- Design queries for specific use cases
- Avoid N+1 problems with eager loading
- Consider read models for complex queries

### 3. Event Handling
- Keep handlers idempotent
- Handle events asynchronously when possible
- Use priority for ordering dependent handlers
- Log all event processing

### 4. Error Handling
```php
try {
    $commandBus->dispatch($command);
} catch (CommandValidationException $e) {
    // Handle validation errors
} catch (CommandExecutionException $e) {
    // Handle execution errors
}
```

### 5. Transaction Management
```php
DB::transaction(function () use ($commandBus, $eventBus) {
    // Record events for later dispatch
    $eventBus->record(new OrderCreated(...));
    
    // Execute commands
    $commandBus->dispatch(new UpdateInventoryCommand(...));
    
    // Dispatch recorded events on success
    $eventBus->dispatchRecorded();
});
```

## Performance Considerations

### Command Bus
- Use async dispatch for non-critical commands
- Batch related commands in transactions
- Monitor queue depth and processing time

### Query Bus
- Cache frequently accessed data
- Use appropriate cache TTLs
- Consider cache warming strategies
- Monitor cache hit rates

### Event Bus
- Process events asynchronously when possible
- Use priority queues for critical events
- Monitor event processing lag
- Implement retry mechanisms

## Monitoring

### Metrics to Track
- Command execution time
- Query cache hit rate
- Event processing lag
- Queue depth
- Error rates

### Logging
```php
// All infrastructure components log automatically
[2024-09-08 10:00:00] domain.INFO: Command dispatched {"command":"PlaceOrderCommand","user":"123"}
[2024-09-08 10:00:01] domain.INFO: Query executed {"query":"GetOrderBookQuery","cached":true}
[2024-09-08 10:00:02] domain.INFO: Event published {"event":"OrderPlaced","async":false}
```

## Migration Guide

### From Direct Service Calls to CQRS

Before:
```php
$orderService->placeOrder($userId, $type, $side, $amount);
```

After:
```php
$commandBus->dispatch(new PlaceOrderCommand($userId, $type, $side, $amount));
```

### Adding Event Sourcing

1. Create event classes extending `DomainEvent`
2. Publish events after state changes
3. Create projectors for read models
4. Subscribe handlers for side effects

## Troubleshooting

### Commands Not Executing
- Check `DOMAIN_ENABLE_HANDLERS` is true
- Verify handler registration in provider
- Check queue workers are running

### Queries Not Caching
- Verify cache driver is configured
- Check cache permissions
- Monitor cache memory usage

### Events Not Processing
- Check event subscriptions
- Verify queue workers for async events
- Check handler exceptions in logs