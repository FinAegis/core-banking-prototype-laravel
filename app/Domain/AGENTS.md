# AGENTS.md - Domain Layer

This directory contains all business logic following Domain-Driven Design principles.

## Structure

Each domain is a bounded context with:
- `Aggregates/` - Event sourcing aggregates
- `Events/` - Domain events (must extend ShouldBeStored)
- `Services/` - Domain services
- `Sagas/` - Multi-step workflows with compensation
- `Workflows/` - Laravel Workflow implementations
- `ValueObjects/` - Immutable value objects
- `Contracts/` - Domain interfaces

## Domain Guidelines

### Event Sourcing
All state changes MUST go through events:
```php
DomainAggregate::retrieve($id)
    ->recordEvent(new EventHappened(...))
    ->persist();
```

### Saga Pattern
For multi-step operations with compensation:
```php
class PaymentSaga extends Reactor
{
    public function onPaymentInitiated($event): void
    {
        // Step 1: Validate
        // Step 2: Process
        // Step 3: Notify
    }
}
```

### Service Layer
Domain services should be stateless and focused:
```php
class ExchangeService
{
    public function __construct(
        private readonly Repository $repository
    ) {}
}
```

## Testing Requirements
- Each domain must have >50% test coverage
- Test aggregates with event assertions
- Test sagas with mock event dispatching
- Test services with dependency injection

## Common Patterns
- Use `BigDecimal` for all monetary calculations
- Implement `Jsonable` for API responses
- Use UUID for all identifiers
- Validate in value objects, not services