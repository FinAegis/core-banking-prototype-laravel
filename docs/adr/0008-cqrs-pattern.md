# ADR-002: CQRS Pattern Implementation

## Status

Accepted

## Context

The platform handles two very different kinds of work:

1. **Writes** run complex business logic and validation, and they record events.
2. **Reads** serve many queries, reports and dashboards.
3. **Scaling needs differ.** Reads usually outnumber writes by 10 to 1 or even 100 to 1.
4. **Data shapes differ.** The write models (aggregates) look different from the read models (views).

A single model has to compromise between fast reads and safe writes. It makes both queries and commands more complicated, makes scaling harder and creates performance bottlenecks.

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

1. **Command bus.** It routes each command to its handler.
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

2. **Query bus.** It routes each query to its handler and can cache the result.
   ```php
   interface QueryBus
   {
       public function ask(Query $query): mixed;
   }

   // Usage
   $balance = $queryBus->ask(new GetAccountBalanceQuery($accountId));
   ```

3. **Command handlers.** They process commands through aggregates.
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

4. **Query handlers.** They read from projections that are shaped for the query.
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

5. **Projectors.** They build the read models from events.
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

### Where It Lives

The bus interfaces are `App\Domain\Shared\CQRS\CommandBus` and `App\Domain\Shared\CQRS\QueryBus`. The implementations are `LaravelCommandBus` and `LaravelQueryBus` in `app/Infrastructure/CQRS/`, and `DomainServiceProvider` registers them. There is no `config/cqrs.php` file.

The command bus offers `dispatch()`, `dispatchAsync()` for queued commands and `dispatchTransaction()`, which runs several commands inside one `DB::transaction()`. The query bus offers `ask()`, `askCached()` with a default cache time of 3600 seconds and `askMultiple()`.

At v7.18.1 no command or query handler is registered with the buses. Controllers still call services, aggregates and workflows directly, so the handler examples above show the intended design.

## Consequences

### Positive

Read models are shaped for specific queries, and aggregates focus on business logic. The read side and the write side can scale separately. Each side has a single responsibility, which keeps the code simpler. Read models are easy to cache, and the pattern fits well with event-sourced aggregates.

### Negative

Read models can lag behind writes. There are more classes and more infrastructure. The read models must be kept up to date, and a problem can span several components, which makes debugging harder.

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

GraphQL was later added next to CQRS, not instead of it. Lighthouse serves 43 domain schemas at `/graphql`.

### 3. Separate Read/Write Databases

**Pros**: Maximum isolation, independent scaling
**Cons**: Complex replication, higher infrastructure cost

**Considered for future**: May implement for extreme scale

## References

- [Martin Fowler - CQRS](https://martinfowler.com/bliki/CQRS.html)
- [Microsoft - CQRS Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/cqrs)
- [Greg Young - CQRS Documents](https://cqrs.files.wordpress.com/2010/11/cqrs_documents.pdf)
