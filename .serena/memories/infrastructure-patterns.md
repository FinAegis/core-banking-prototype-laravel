# Infrastructure Patterns - FinAegis Core Banking

## CQRS Implementation

### Location
- **Interfaces**: `app/Domain/Shared/CQRS/` - Command, Query, CommandBus, QueryBus interfaces
- **Implementations**: `app/Infrastructure/CQRS/` - Laravel implementations of buses
- **Provider**: `app/Providers/DomainServiceProvider.php` - Registration and configuration

### Command Bus Pattern
```php
// Interface: app/Domain/Shared/CQRS/CommandBus.php
- dispatch(Command $command): mixed - Synchronous execution
- dispatchAsync(Command $command, int $delay): void - Queue-based async
- dispatchTransaction(array $commands): array - Transactional batch
- register(string $commandClass, string|callable $handler): void

// Implementation: app/Infrastructure/CQRS/LaravelCommandBus.php
- Uses Laravel container for handler resolution
- Queue integration via AsyncCommandJob
- Database transactions for batch commands
```

### Query Bus Pattern
```php
// Interface: app/Domain/Shared/CQRS/QueryBus.php
- ask(Query $query): mixed - Execute query
- askCached(Query $query, int $ttl): mixed - Cached query execution
- askMultiple(array $queries): array - Batch queries
- register(string $queryClass, string|callable $handler): void

// Implementation: app/Infrastructure/CQRS/LaravelQueryBus.php
- Laravel cache integration for cached queries
- Container-based handler resolution
- MD5-based cache key generation
```

## Domain Event Bus

### Location
- **Interface**: `app/Domain/Shared/Events/DomainEventBus.php`
- **Implementation**: `app/Infrastructure/Events/LaravelDomainEventBus.php`
- **Job**: `app/Infrastructure/Events/AsyncDomainEventJob.php`

### Event Bus Features
```php
// Core publishing
- publish(DomainEvent $event): void
- publishMultiple(array $events): void
- publishAsync(DomainEvent $event, int $delay): void

// Subscription management
- subscribe(string $eventClass, callable|string $handler, int $priority): void
- unsubscribe(string $eventClass, callable|string $handler): void
- getSubscribers(string $eventClass): array

// Transaction support
- record(DomainEvent $event): void - Record for later
- dispatchRecorded(): void - Dispatch all recorded
- clearRecorded(): void - Clear without dispatching
```

### Integration with Laravel
- Bridges with Laravel's native event system
- Supports both domain handlers and Laravel listeners
- Priority-based handler execution (higher priority first)
- Queue integration for async events

## Configuration

### Environment Variables
```bash
# Enable/disable handler registration (for demo vs production)
DOMAIN_ENABLE_HANDLERS=false  # Demo mode
DOMAIN_ENABLE_HANDLERS=true   # Production mode
```

### Service Provider Registration
Located in `app/Providers/DomainServiceProvider.php`:

1. **Repositories**: Bound in `registerRepositories()`
2. **CQRS Infrastructure**: Registered as singletons in `registerCQRSInfrastructure()`
3. **Event Bus**: Singleton registration in `registerDomainEventBus()`
4. **Sagas**: Tagged for workflow engine in `registerSagas()`
5. **Handlers**: Conditionally registered based on environment in boot methods

## Demo vs Production

### Demo Environment (finaegis.org)
- Infrastructure enabled but handlers optional
- Demo services provide simulated responses
- No external dependencies required
- Predictable test data for demonstrations

### Production Environment
- Full handler registration required
- Real external service integrations
- Complete event sourcing and CQRS flow
- Production-grade queue and cache usage

## Handler Implementation Pattern

### Command Handler Example
```php
class PlaceOrderHandler
{
    public function handle(PlaceOrderCommand $command): Order
    {
        // Business logic
        // Return result
    }
}
```

### Query Handler Example
```php
class GetOrderBookHandler
{
    public function handle(GetOrderBookQuery $query): OrderBook
    {
        // Fetch and return data
    }
}
```

### Event Handler Example
```php
class OrderPlacedHandler
{
    public function handle(OrderPlaced $event): void
    {
        // React to event
    }
}
```

## Key Files to Remember

1. **DomainServiceProvider.php** - Central registration point
2. **app/Infrastructure/CQRS/` - Command and Query bus implementations
3. **app/Infrastructure/Events/` - Event bus implementation
4. **app/Domain/Shared/` - Domain interfaces and contracts
5. **app/Domain/*/Sagas/` - Saga workflow definitions

## Testing Considerations

- Infrastructure classes are automatically discovered by Spatie Event Sourcing
- Unit tests should mock bus interfaces, not implementations
- Demo mode allows testing without external dependencies
- Handlers can be tested in isolation