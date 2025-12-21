# ADR-001: Event Sourcing for Financial Transactions

## Status

Accepted

## Context

Financial applications require:

1. **Complete Audit Trail** - Regulators require knowing every state change
2. **Temporal Queries** - "What was the balance on date X?"
3. **Dispute Resolution** - Ability to reconstruct past states
4. **Compliance** - Prove what happened and when
5. **Debugging** - Understand how a state was reached

Traditional CRUD approaches:
- Lose history when records are updated
- Require separate audit tables (error-prone)
- Cannot easily answer temporal queries
- Make debugging state issues difficult

## Decision

We will use **Event Sourcing** as the primary persistence pattern for all financial domains.

### Implementation

1. **Spatie Event Sourcing** - Laravel-native package with proven reliability

2. **Domain-Specific Event Stores** - Each domain has its own event table:
   ```
   exchange_events
   lending_events
   wallet_events
   treasury_events
   compliance_events
   ```

3. **Aggregate Pattern** - Business logic contained in aggregate roots:
   ```php
   class AccountAggregate extends AggregateRoot
   {
       public function credit(Money $amount): self
       {
           $this->recordThat(new AccountCredited($amount));
           return $this;
       }

       protected function applyAccountCredited(AccountCredited $event): void
       {
           $this->balance = $this->balance->add($event->amount);
       }
   }
   ```

4. **Projectors for Read Models** - Separate read-optimized models:
   ```php
   class AccountBalanceProjector extends Projector
   {
       public function onAccountCredited(AccountCredited $event): void
       {
           AccountBalance::where('account_id', $event->accountId)
               ->increment('balance', $event->amount->getAmount());
       }
   }
   ```

5. **Snapshots** - For aggregates with many events (>100)

### Event Design Principles

- Events are **immutable facts** - never modified or deleted
- Events are **past tense** - `AccountCredited`, not `CreditAccount`
- Events contain **all necessary data** - self-describing
- Events are **versioned** - support schema evolution

## Consequences

### Positive

- **Complete audit trail** without extra code
- **Temporal queries** are natural ("replay to date X")
- **Debugging** is straightforward (replay events)
- **Regulatory compliance** built-in
- **Event replay** for fixing projector bugs
- **CQRS enablement** - natural fit for read/write separation

### Negative

- **Learning curve** - team needs event sourcing knowledge
- **Storage growth** - events accumulate (mitigated by snapshots)
- **Eventual consistency** - read models may lag slightly
- **Complexity** - more moving parts than CRUD
- **Migration difficulty** - harder to change event schemas

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Storage growth | Snapshots every 100 events |
| Eventual consistency | Sync projectors for critical reads |
| Event schema changes | Event upcasters for versioning |
| Learning curve | Comprehensive documentation |

## Alternatives Considered

### 1. Traditional CRUD with Audit Tables

**Pros**: Simpler, familiar pattern
**Cons**: Duplicate logic, incomplete history, audit drift

**Rejected because**: Financial domain requires complete, reliable audit trail

### 2. Change Data Capture (CDC)

**Pros**: Works with existing CRUD, automatic capture
**Cons**: External dependency, limited to database changes

**Rejected because**: Doesn't capture business intent, only data changes

### 3. Hybrid (Event Sourcing for critical, CRUD for others)

**Pros**: Simpler for non-financial domains
**Cons**: Inconsistent patterns, team confusion

**Adopted partially**: Supporting domains (User, Contact) use traditional Eloquent

## References

- [Spatie Event Sourcing Documentation](https://spatie.be/docs/laravel-event-sourcing)
- [Martin Fowler - Event Sourcing](https://martinfowler.com/eaaDev/EventSourcing.html)
- [Microsoft - Event Sourcing Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/event-sourcing)
