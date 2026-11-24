# ADR-003: Saga Pattern for Distributed Transactions

## Status

Accepted

## Context

Many financial operations span several aggregates and domains:

1. **Fund transfer.** The source account is debited, then the destination account is credited.
2. **Order execution.** The order is matched, the balances are updated and the trade is recorded.
3. **Loan disbursement.** The loan is approved, an account is created and the funds are transferred.
4. **Stablecoin minting.** Collateral is locked, tokens are minted and the positions are updated.

A normal database transaction does not work across aggregates. Two-phase commit (2PC) is complex and fails badly. A partial failure leaves the system in an inconsistent state, and long-running processes must survive restarts.

## Decision

We will use the **Saga Pattern** with **compensation actions** for distributed transactions.

### Implementation

1. **Durable workflows.** We use the `durable-workflow/workflow` package, formerly called Laravel Workflow, with the Waterline dashboard.

2. **Orchestration.** A central workflow coordinates the steps.

3. **Compensation on failure.** Each step has a matching undo action. The package's `addCompensation()` and `compensate()` methods support this, as `app/Domain/Exchange/Workflows/OrderMatchingWorkflow.php` shows.

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

`config/workflows.php` sets the storage models, the pruning age of finished workflows (one month) and the optional monitoring. It has no retry or timeout settings. Retries and time limits are set on each activity class:

```php
class DebitAccountActivity extends Activity
{
    public $tries = 3;      // The package default is unlimited
    public $timeout = 30;   // Seconds; the package default is no limit

    public function backoff()
    {
        return [1, 2, 4];   // Seconds between attempts
    }
}
```

## Consequences

### Positive

A business operation across aggregates either completes or is compensated. Failures trigger compensation automatically. Execution survives process restarts, and Waterline shows long-running processes. Retries are built in, and steps that wait for a person can be added through signals.

### Negative

The steps are only eventually consistent with each other. Compensation needs careful design. There are many failure scenarios to test, and distributed execution is harder to trace.

### Mitigations

| Challenge | Mitigation |
|-----------|------------|
| Eventual consistency | Design for it, communicate to users |
| Compensation complexity | Thorough design, unit test compensations |
| Testing | Chaos engineering, failure injection |
| Debugging | Structured logging, workflow visualization |

## Design Principles

### Compensation Rules

A compensation must be safe to retry. It must fully reverse the forward action and must not depend on other compensations. Every compensation needs a test.

### Activity Rules

An activity performs one business action. It is safe to retry and either succeeds or fails completely. Each activity has a defined undo action.

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
- [Durable Workflow Documentation (formerly Laravel Workflow)](https://laravel-workflow.com/)
- [Compensating Transaction Pattern](https://docs.microsoft.com/en-us/azure/architecture/patterns/compensating-transaction)
