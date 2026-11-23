# ADR-001: Event Sourcing for Financial Transactions

## Status

Accepted

## Context

Financial software has to answer five kinds of questions:

1. **Audit trail.** Auditors and regulators expect a record of every state change.
2. **Temporal queries.** People ask what the balance was on a given date.
3. **Dispute resolution.** The team must be able to rebuild a past state.
4. **Compliance.** The system must show what happened and when.
5. **Debugging.** Engineers need to see how a state was reached.

A traditional create-read-update-delete (CRUD) design loses history when a record is updated. It needs separate audit tables, which are easy to get wrong. It cannot easily answer temporal queries, and it makes state problems hard to debug.

## Decision

We will use **Event Sourcing** as the primary persistence pattern for all financial domains.

### Implementation

1. **Spatie Event Sourcing.** We use `spatie/laravel-event-sourcing`, a Laravel package with a long track record. The current version is 7.15.

2. **Domain-specific event stores.** Many domains have their own event table. Account events stay in the shared `stored_events` table:
   ```
   exchange_events
   lending_events
   stablecoin_events
   treasury_events
   compliance_events
   ```

3. **Aggregate pattern.** Business logic lives in aggregate roots:
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

4. **Projectors for read models.** Projectors build separate models that are shaped for reading:
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

5. **Snapshots.** Aggregates with more than 100 events get snapshots. `php artisan snapshot:create` creates them and `snapshot:cleanup` removes old ones.

### Event Design Principles

Events are immutable facts, so they are never changed or deleted. Their names are in the past tense, such as `AccountCredited` rather than `CreditAccount`. Each event carries all the data it needs, so it describes itself. Events are versioned, which lets their schema change over time.

## Consequences

### Positive

The audit trail comes without extra code. Temporal queries become natural, because events can be replayed up to a given date. Debugging is easier for the same reason. The event history supports audit and compliance work, although it does not make a deployment compliant by itself. Replaying events fixes projector bugs, and the design fits well with separate read and write models (CQRS).

### Negative

The team needs to learn event sourcing. Events pile up over time, which snapshots help with. Read models can lag slightly behind the events. There are more moving parts than in a CRUD design, and event schemas are harder to change.

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Storage growth | Snapshots every 100 events |
| Eventual consistency | Sync projectors for critical reads |
| Event schema changes | Event upcasters for versioning (`php artisan event:upcast`) |
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

**Adopted partially**: Supporting domains (User, Contact) use traditional Eloquent models

## References

- [Spatie Event Sourcing Documentation](https://spatie.be/docs/laravel-event-sourcing)
- [Martin Fowler - Event Sourcing](https://martinfowler.com/eaaDev/EventSourcing.html)
- [Microsoft - Event Sourcing Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/event-sourcing)
