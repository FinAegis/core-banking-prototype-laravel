# ADR-002: CQRS Pattern Implementation

## Status

Accepted

## Context

The platform handles diverse workloads:

1. **Write Operations** - Complex business logic, validation, event generation
2. **Read Operations** - High-volume queries, reporting, dashboards
3. **Different Scaling Needs** - Reads vastly outnumber writes (typically 10:1 to 100:1)
4. **Different Data Shapes** - Write models (aggregates) differ from read models (views)

Traditional single-model approaches:
- Force compromise between read and write optimization
- Complicate both query and command handling
- Make scaling difficult
- Create performance bottlenecks

## Decision

We will implement **Command Query Responsibility Segregation (CQRS)** to separate read and write concerns.

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                         API Layer                                │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐                    ┌─────────────────┐     │
│  │   Command Bus   │                    │   Query Bus     │     │
│  └────────┬────────┘                    └────────┬────────┘     │
│           │                                      │               │
│  ┌────────▼────────┐                    ┌────────▼────────┐     │
│  │ Command Handler │                    │  Query Handler  │     │
│  └────────┬────────┘                    └────────┬────────┘     │
│           │                                      │               │
│  ┌────────▼────────┐                    ┌────────▼────────┐     │
│  │   Aggregates    │                    │   Read Models   │     │
│  │  (Write Model)  │                    │   (Projections) │     │
│  └────────┬────────┘                    └─────────────────┘     │
│           │                                      ▲               │
│  ┌────────▼────────┐                             │               │
│  │   Event Store   │─────────────────────────────┘               │
│  └─────────────────┘         (Projectors)                        │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Implementation

1. **Command Bus** - Routes commands to handlers
   ```php
   interface CommandBus
   {
       public function dispatch(Command $command): mixed;
   }

   // Usage
   $result = $commandBus->dispatch(new PlaceOrderCommand(
       accountId: $accountId,
       symbol: 'BTC/USD',
       side: OrderSide::BUY,
       amount: Money::of(1000, 'USD'),
   ));
   ```

2. **Query Bus** - Routes queries to handlers with caching
   ```php
   interface QueryBus
   {
       public function ask(Query $query): mixed;
   }

   // Usage
   $balance = $queryBus->ask(new GetAccountBalanceQuery($accountId));
   ```

3. **Command Handlers** - Process commands through aggregates
   ```php
   class PlaceOrderHandler
   {
       public function handle(PlaceOrderCommand $command): Order
       {
           $aggregate = OrderAggregate::retrieve($command->orderId);
           $aggregate->place(
               $command->accountId,
               $command->symbol,
               $command->side,
               $command->amount,
           );
           $aggregate->persist();

           return Order::find($command->orderId);
       }
   }
   ```

4. **Query Handlers** - Read from optimized projections
   ```php
   class GetAccountBalanceHandler
   {
       public function handle(GetAccountBalanceQuery $query): Money
       {
           return AccountBalance::where('account_id', $query->accountId)
               ->value('balance');
       }
   }
   ```

5. **Projectors** - Build read models from events
   ```php
   class OrderBookProjector extends Projector
   {
       public function onOrderPlaced(OrderPlaced $event): void
       {
           OrderBookEntry::create([
               'order_id' => $event->orderId,
               'symbol' => $event->symbol,
               'side' => $event->side,
               'price' => $event->price,
               'amount' => $event->amount,
           ]);
       }
   }
   ```

### Configuration

```php
// config/cqrs.php
return [
    'command_bus' => [
        'middleware' => [
            TransactionalMiddleware::class,
            LoggingMiddleware::class,
            ValidationMiddleware::class,
        ],
    ],
    'query_bus' => [
        'cache' => [
            'enabled' => true,
            'ttl' => 60,
        ],
    ],
];
```

## Consequences

### Positive

- **Optimized reads** - Read models shaped for specific queries
- **Optimized writes** - Aggregates focused on business logic
- **Independent scaling** - Scale read and write sides separately
- **Simpler code** - Each side has single responsibility
- **Cache-friendly** - Read models can be heavily cached
- **Event sourcing synergy** - Natural fit with event-sourced aggregates

### Negative

- **Eventual consistency** - Read models may lag writes
- **Increased complexity** - More classes and infrastructure
- **Synchronization** - Must keep read models updated
- **Debugging** - Issues span multiple components

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Eventual consistency | Sync projectors for critical data |
| Complexity | Clear conventions and code generation |
| Debugging | Comprehensive logging and tracing |

## Alternatives Considered

### 1. Single Model (Active Record / Repository)

**Pros**: Simple, familiar, fewer classes
**Cons**: Compromises both read and write optimization

**Rejected because**: Financial platform needs optimized reads AND writes

### 2. GraphQL with DataLoaders

**Pros**: Flexible queries, automatic batching
**Cons**: Doesn't address write side complexity

**Rejected because**: Only solves read side, not full separation

### 3. Separate Read/Write Databases

**Pros**: Maximum isolation, independent scaling
**Cons**: Complex replication, higher infrastructure cost

**Considered for future**: May implement for extreme scale

## References

- [Martin Fowler - CQRS](https://martinfowler.com/bliki/CQRS.html)
- [Microsoft - CQRS Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/cqrs)
- [Greg Young - CQRS Documents](https://cqrs.files.wordpress.com/2010/11/cqrs_documents.pdf)
