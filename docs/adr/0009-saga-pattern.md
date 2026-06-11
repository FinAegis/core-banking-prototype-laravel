# ADR-003: Saga Pattern for Distributed Transactions

## Status

Accepted

## Context

Financial operations often span multiple aggregates and domains:

1. **Fund Transfer** - Debit source account → Credit destination account
2. **Order Execution** - Match order → Update balances → Record trade
3. **Loan Disbursement** - Approve loan → Create account → Transfer funds
4. **Stablecoin Minting** - Lock collateral → Mint tokens → Update positions

Challenges:
- Traditional database transactions don't work across aggregates
- Two-phase commit (2PC) is complex and has poor failure modes
- Partial failures leave system in inconsistent state
- Long-running processes need durability

## Decision

We will use the **Saga Pattern** with **compensation actions** for distributed transactions.

### Implementation

1. **Laravel Workflow** - Durable workflow engine with Waterline

2. **Orchestration-Based Sagas** - Central coordinator manages steps

3. **Compensation on Failure** - Each step has a corresponding undo action

### Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      Transfer Saga                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Step 1: Debit Source Account                                   │
│  ┌─────────────────┐    Success    ┌─────────────────┐         │
│  │  DebitAccount   │──────────────▶│  Step 2         │         │
│  │    Activity     │               │                 │         │
│  └────────┬────────┘               └─────────────────┘         │
│           │ Failure                                             │
│           ▼                                                     │
│  ┌─────────────────┐                                           │
│  │  Saga Failed    │ (No compensation needed - nothing done)   │
│  └─────────────────┘                                           │
│                                                                  │
│  Step 2: Credit Destination Account                             │
│  ┌─────────────────┐    Success    ┌─────────────────┐         │
│  │ CreditAccount   │──────────────▶│  Saga Complete  │         │
│  │    Activity     │               │                 │         │
│  └────────┬────────┘               └─────────────────┘         │
│           │ Failure                                             │
│           ▼                                                     │
│  ┌─────────────────┐                                           │
│  │ Compensate:     │                                           │
│  │ Credit Source   │ (Reverse the debit)                       │
│  │ Account Back    │                                           │
│  └─────────────────┘                                           │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

### Code Example

```php
class TransferWorkflow extends Workflow
{
    public function execute(TransferRequest $request): Generator
    {
        // Step 1: Validate and reserve funds
        $reservation = yield ActivityStub::make(
            ReserveFundsActivity::class,
            $request->sourceAccountId,
            $request->amount,
        );

        try {
            // Step 2: Compliance check
            yield ActivityStub::make(
                ComplianceCheckActivity::class,
                $request->sourceAccountId,
                $request->destinationAccountId,
                $request->amount,
            );

            // Step 3: Execute transfer
            yield ActivityStub::make(
                ExecuteTransferActivity::class,
                $reservation->id,
                $request->destinationAccountId,
            );

            // Step 4: Send notifications
            yield ActivityStub::make(
                SendNotificationsActivity::class,
                $request->sourceAccountId,
                $request->destinationAccountId,
                $request->amount,
            );

            return new TransferResult(success: true);

        } catch (ComplianceRejectedException $e) {
            // Compensate: Release the reserved funds
            yield ActivityStub::make(
                ReleaseFundsActivity::class,
                $reservation->id,
            );

            return new TransferResult(
                success: false,
                reason: 'Compliance check failed',
            );

        } catch (TransferFailedException $e) {
            // Compensate: Release the reserved funds
            yield ActivityStub::make(
                ReleaseFundsActivity::class,
                $reservation->id,
            );

            throw $e;
        }
    }
}
```

### Activity Example

```php
class DebitAccountActivity extends Activity
{
    public function execute(string $accountId, Money $amount): DebitResult
    {
        $aggregate = AccountAggregate::retrieve($accountId);
        $aggregate->debit($amount, 'Transfer out');
        $aggregate->persist();

        return new DebitResult(
            accountId: $accountId,
            amount: $amount,
            transactionId: $aggregate->lastTransactionId(),
        );
    }
}
```

### Saga Configuration

```php
// config/workflows.php
return [
    'storage' => 'database', // Durable storage
    'retry' => [
        'max_attempts' => 3,
        'backoff' => 'exponential',
        'initial_interval' => 1000, // 1 second
    ],
    'timeout' => [
        'activity' => 30000, // 30 seconds
        'workflow' => 300000, // 5 minutes
    ],
];
```

## Consequences

### Positive

- **Atomic business operations** across aggregates
- **Automatic compensation** on failures
- **Durable execution** survives process restarts
- **Visibility** into long-running processes
- **Retry logic** built-in
- **Human tasks** can be integrated

### Negative

- **Eventual consistency** between steps
- **Compensation complexity** must be carefully designed
- **Testing difficulty** many failure scenarios
- **Debugging** distributed execution harder to trace

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Eventual consistency | Design for it, communicate to users |
| Compensation complexity | Thorough design, unit test compensations |
| Testing | Chaos engineering, failure injection |
| Debugging | Structured logging, workflow visualization |

## Design Principles

### Compensation Rules

1. **Idempotent** - Can be safely retried
2. **Complete** - Fully reverses the forward action
3. **Independent** - Doesn't depend on other compensations
4. **Tested** - Every compensation has test coverage

### Activity Rules

1. **Single responsibility** - One business action
2. **Idempotent** - Safe to retry
3. **Atomic** - Succeeds or fails completely
4. **Compensatable** - Has a defined undo action

## Alternatives Considered

### 1. Two-Phase Commit (2PC)

**Pros**: Strong consistency, ACID guarantees
**Cons**: Poor failure handling, blocking, doesn't work with event sourcing

**Rejected because**: Doesn't fit event-sourced architecture

### 2. Event Choreography

**Pros**: Loose coupling, simple implementation
**Cons**: Hard to track, difficult to coordinate, implicit flow

**Rejected because**: Financial operations need explicit coordination

### 3. Outbox Pattern Only

**Pros**: Simpler than full sagas
**Cons**: No compensation, one-way operations only

**Used alongside**: Outbox for event publishing, sagas for coordination

## References

- [Microservices Patterns - Saga Pattern](https://microservices.io/patterns/data/saga.html)
- [Laravel Workflow Documentation](https://laravel-workflow.com/)
- [Compensating Transaction Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/compensating-transaction)
