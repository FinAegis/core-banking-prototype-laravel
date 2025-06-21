# FinAegis Workflow Patterns and Best Practices

## Overview

This document outlines the workflow patterns, saga implementations, and best practices used in the FinAegis core banking platform. The platform uses the Laravel Workflow package to implement the saga pattern for complex business processes.

## Saga Pattern Implementation

### What is a Saga?

A saga is a sequence of transactions that can be individually committed or compensated. In distributed systems like core banking, sagas ensure data consistency across multiple operations that might fail at any point.

### Types of Workflows in FinAegis

#### 1. Simple Workflows (Single Activity)
For straightforward operations that don't require compensation:

```php
class DepositAccountWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid, Money $money): \Generator
    {
        return yield ActivityStub::make(
            DepositAccountActivity::class,
            $uuid,
            $money
        );
    }
}
```

#### 2. Compensatable Workflows (Saga Pattern)
For complex operations that require rollback capabilities:

```php
class TransferWorkflow extends Workflow
{
    public function execute(AccountUuid $from, AccountUuid $to, Money $money): \Generator
    {
        try {
            // Step 1: Withdraw from source account
            yield ChildWorkflowStub::make(
                WithdrawAccountWorkflow::class, $from, $money
            );
            $this->addCompensation(fn() => ChildWorkflowStub::make(
                DepositAccountWorkflow::class, $from, $money
            ));

            // Step 2: Deposit to destination account
            yield ChildWorkflowStub::make(
                DepositAccountWorkflow::class, $to, $money
            );
            $this->addCompensation(fn() => ChildWorkflowStub::make(
                WithdrawAccountWorkflow::class, $to, $money
            ));
            
        } catch (\Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
```

#### 3. Bulk Processing Workflows
For batch operations with partial failure handling:

```php
class BulkTransferWorkflow extends Workflow
{
    public function execute(AccountUuid $from, array $transfers): \Generator
    {
        $completedTransfers = [];
        
        try {
            foreach ($transfers as $transfer) {
                $result = yield ChildWorkflowStub::make(
                    TransferWorkflow::class,
                    $from,
                    $transfer['to'],
                    $transfer['amount']
                );
                
                $completedTransfers[] = $transfer;
                
                // Add compensation for each completed transfer
                $this->addCompensation(function() use ($from, $transfer) {
                    return ChildWorkflowStub::make(
                        TransferWorkflow::class,
                        $transfer['to'],
                        $from,
                        $transfer['amount']
                    );
                });
            }
            
            return $completedTransfers;
        } catch (\Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
```

## Workflow Categories

### 1. Account Management Workflows

#### Account Creation
```php
class CreateAccountWorkflow extends Workflow
{
    public function execute(Account $account): \Generator
    {
        return yield ActivityStub::make(
            CreateAccountActivity::class,
            $account
        );
    }
}

class CreateAccountActivity extends Activity
{
    public function execute(Account $account, LedgerAggregate $ledger): string
    {
        $uuid = Str::uuid();
        
        $ledger->retrieve($uuid)
               ->createAccount(__account($account))
               ->persist();
        
        return $uuid;
    }
}
```

#### Account Lifecycle Management
```php
// Freeze Account (Compliance)
class FreezeAccountWorkflow extends Workflow
{
    public function execute(
        AccountUuid $uuid, 
        string $reason, 
        ?string $authorizedBy = null
    ): \Generator {
        return yield ActivityStub::make(
            FreezeAccountActivity::class,
            $uuid,
            $reason,
            $authorizedBy
        );
    }
}

// Unfreeze Account (Compliance)
class UnfreezeAccountWorkflow extends Workflow
{
    public function execute(
        AccountUuid $uuid, 
        string $reason, 
        ?string $authorizedBy = null
    ): \Generator {
        return yield ActivityStub::make(
            UnfreezeAccountActivity::class,
            $uuid,
            $reason,
            $authorizedBy
        );
    }
}
```

### 2. Transaction Processing Workflows

#### Money Movement Operations
```php
class WithdrawAccountWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid, Money $money): \Generator
    {
        return yield ActivityStub::make(
            WithdrawAccountActivity::class,
            $uuid,
            $money
        );
    }
}

class WithdrawAccountActivity extends Activity
{
    public function execute(
        AccountUuid $uuid, 
        Money $money, 
        TransactionAggregate $transaction
    ): bool {
        $transaction->retrieve($uuid->getUuid())
                   ->debit($money)
                   ->persist();
        
        return true;
    }
}
```

#### Transaction Reversal
```php
class TransactionReversalWorkflow extends Workflow
{
    public function execute(
        AccountUuid $accountUuid,
        Money $originalAmount,
        string $transactionType,
        string $reversalReason,
        ?string $authorizedBy = null
    ): \Generator {
        try {
            $result = yield ActivityStub::make(
                TransactionReversalActivity::class,
                $accountUuid,
                $originalAmount,
                $transactionType,
                $reversalReason,
                $authorizedBy
            );
            
            return $result;
        } catch (\Throwable $th) {
            // Log reversal failure for audit
            logger()->error('Transaction reversal failed', [
                'account_uuid' => $accountUuid->getUuid(),
                'amount' => $originalAmount->getAmount(),
                'type' => $transactionType,
                'reason' => $reversalReason,
                'error' => $th->getMessage(),
            ]);
            
            throw $th;
        }
    }
}
```

### 3. Inquiry and Validation Workflows

#### Balance Inquiry with Audit Trail
```php
class BalanceInquiryWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid, ?string $requestedBy = null): \Generator
    {
        return yield ActivityStub::make(
            BalanceInquiryActivity::class,
            $uuid,
            $requestedBy
        );
    }
}

class BalanceInquiryActivity extends Activity
{
    public function execute(
        AccountUuid $uuid, 
        ?string $requestedBy, 
        TransactionAggregate $transaction
    ): array {
        $aggregate = $transaction->retrieve($uuid->getUuid());
        $account = Account::where('uuid', $uuid->getUuid())->first();
        
        // Log the inquiry for audit purposes
        $this->logInquiry($uuid, $requestedBy);
        
        return [
            'account_uuid' => $uuid->getUuid(),
            'balance' => $aggregate->balance,
            'account_name' => $account?->name,
            'status' => $account?->status ?? 'unknown',
            'inquired_at' => now()->toISOString(),
            'inquired_by' => $requestedBy,
        ];
    }
}
```

#### KYC/Compliance Validation
```php
class AccountValidationWorkflow extends Workflow
{
    public function execute(
        AccountUuid $uuid, 
        array $validationChecks, 
        ?string $validatedBy = null
    ): \Generator {
        return yield ActivityStub::make(
            AccountValidationActivity::class,
            $uuid,
            $validationChecks,
            $validatedBy
        );
    }
}
```

### 4. System Operation Workflows

#### Batch Processing
```php
class BatchProcessingWorkflow extends Workflow
{
    public function execute(array $operations, ?string $batchId = null): \Generator
    {
        $batchId = $batchId ?? \Illuminate\Support\Str::uuid();
        
        try {
            $results = yield ActivityStub::make(
                BatchProcessingActivity::class,
                $operations,
                $batchId
            );
            
            return $results;
        } catch (\Throwable $th) {
            logger()->error('Batch processing failed', [
                'batch_id' => $batchId,
                'operations' => $operations,
                'error' => $th->getMessage(),
            ]);
            
            throw $th;
        }
    }
}
```

## Best Practices

### 1. Workflow Design Principles

#### Idempotency
All activities should be idempotent - executing them multiple times should have the same effect:

```php
class DepositAccountActivity extends Activity
{
    public function execute(AccountUuid $uuid, Money $money, TransactionAggregate $transaction): bool
    {
        // Check if transaction already exists (idempotency check)
        $existingTransaction = Transaction::where([
            'account_uuid' => $uuid->getUuid(),
            'amount' => $money->getAmount(),
            'idempotency_key' => $this->getIdempotencyKey()
        ])->exists();
        
        if ($existingTransaction) {
            return true; // Already processed
        }
        
        $transaction->retrieve($uuid->getUuid())
                   ->credit($money)
                   ->persist();
        
        return true;
    }
}
```

#### Compensation Logic
Always design compensation that undoes the work of the original activity:

```php
// Original: Withdraw money
yield ChildWorkflowStub::make(WithdrawAccountWorkflow::class, $from, $money);

// Compensation: Deposit the same amount back
$this->addCompensation(fn() => ChildWorkflowStub::make(
    DepositAccountWorkflow::class, $from, $money
));
```

#### Error Handling
```php
class TransferWorkflow extends Workflow
{
    public function execute(AccountUuid $from, AccountUuid $to, Money $money): \Generator
    {
        try {
            // Business logic here
            
        } catch (NotEnoughFunds $e) {
            // Handle insufficient funds
            logger()->warning('Transfer failed: insufficient funds', [
                'from' => $from->getUuid(),
                'to' => $to->getUuid(),
                'amount' => $money->getAmount(),
            ]);
            throw $e;
            
        } catch (\Throwable $th) {
            // Handle unexpected errors
            yield from $this->compensate();
            
            logger()->error('Transfer failed: unexpected error', [
                'from' => $from->getUuid(),
                'to' => $to->getUuid(),
                'amount' => $money->getAmount(),
                'error' => $th->getMessage(),
            ]);
            
            throw $th;
        }
    }
}
```

### 2. Activity Design Principles

#### Single Responsibility
Each activity should have a single, well-defined responsibility:

```php
// Good: Single purpose
class ValidateAccountActivity extends Activity
{
    public function execute(AccountUuid $uuid): bool
    {
        // Only validates account
    }
}

// Bad: Multiple responsibilities
class ValidateAndUpdateAccountActivity extends Activity
{
    public function execute(AccountUuid $uuid, array $updates): bool
    {
        // Validates AND updates - should be separate
    }
}
```

#### Dependency Injection
Use Laravel's dependency injection in activities:

```php
class CreateAccountActivity extends Activity
{
    public function execute(
        Account $account, 
        LedgerAggregate $ledger,              // Injected
        AccountRepository $repository,         // Injected
        EventDispatcher $dispatcher           // Injected
    ): string {
        // Implementation
    }
}
```

### 3. Testing Workflows

#### Unit Testing Workflows
```php
it('can execute transfer workflow', function () {
    WorkflowStub::fake();
    WorkflowStub::mock(WithdrawAccountActivity::class, true);
    WorkflowStub::mock(DepositAccountActivity::class, true);
    
    $fromAccount = new AccountUuid('from-uuid');
    $toAccount = new AccountUuid('to-uuid');
    $money = new Money(1000);
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    $workflow->start($fromAccount, $toAccount, $money);
    
    WorkflowStub::assertDispatched(WithdrawAccountActivity::class);
    WorkflowStub::assertDispatched(DepositAccountActivity::class);
});
```

#### Integration Testing
```php
it('can complete full transfer process', function () {
    // Create test accounts
    $fromAccount = Account::factory()->create(['balance' => 5000]);
    $toAccount = Account::factory()->create(['balance' => 1000]);
    
    // Execute transfer
    $transferService = app(TransferService::class);
    $transferService->transfer($fromAccount->uuid, $toAccount->uuid, 2000);
    
    // Assert final state
    expect($fromAccount->fresh()->balance)->toBe(3000);
    expect($toAccount->fresh()->balance)->toBe(3000);
});
```

### 4. Monitoring and Observability

#### Workflow Logging
```php
class TransferWorkflow extends Workflow
{
    public function execute(AccountUuid $from, AccountUuid $to, Money $money): \Generator
    {
        $this->logWorkflowStart($from, $to, $money);
        
        try {
            // Business logic
            
            $this->logWorkflowSuccess($from, $to, $money);
        } catch (\Throwable $th) {
            $this->logWorkflowFailure($from, $to, $money, $th);
            throw $th;
        }
    }
    
    private function logWorkflowStart(AccountUuid $from, AccountUuid $to, Money $money): void
    {
        logger()->info('Transfer workflow started', [
            'workflow_id' => $this->workflowId(),
            'from_account' => $from->getUuid(),
            'to_account' => $to->getUuid(),
            'amount' => $money->getAmount(),
        ]);
    }
}
```

#### Metrics Collection
```php
class MetricsCollector
{
    public function recordWorkflowExecution(string $workflowClass, float $duration, bool $success): void
    {
        // Record metrics for monitoring
    }
    
    public function recordCompensationExecution(string $workflowClass, int $compensationCount): void
    {
        // Record compensation metrics
    }
}
```

### 5. Performance Considerations

#### Workflow Timeouts
```php
class LongRunningWorkflow extends Workflow
{
    public function execute(): \Generator
    {
        // Set timeout for the entire workflow
        $this->setExecutionTimeout(minutes: 30);
        
        // Set timeout for individual activities
        yield ActivityStub::make(
            LongRunningActivity::class
        )->withTimeout(minutes: 10);
    }
}
```

#### Parallel Execution
```php
class ParallelValidationWorkflow extends Workflow
{
    public function execute(AccountUuid $uuid): \Generator
    {
        // Execute validations in parallel
        $validations = yield [
            ActivityStub::make(KycValidationActivity::class, $uuid),
            ActivityStub::make(CreditCheckActivity::class, $uuid),
            ActivityStub::make(ComplianceCheckActivity::class, $uuid),
        ];
        
        return array_combine([
            'kyc_result',
            'credit_result', 
            'compliance_result'
        ], $validations);
    }
}
```

### 6. Security Considerations

#### Authorization in Activities
```php
class WithdrawAccountActivity extends Activity
{
    public function execute(
        AccountUuid $uuid, 
        Money $money, 
        TransactionAggregate $transaction,
        AuthorizationService $auth
    ): bool {
        // Check permissions
        if (!$auth->canWithdraw($uuid, $money)) {
            throw new UnauthorizedException('Insufficient permissions for withdrawal');
        }
        
        // Proceed with withdrawal
        $transaction->retrieve($uuid->getUuid())
                   ->debit($money)
                   ->persist();
        
        return true;
    }
}
```

#### Audit Logging
```php
trait AuditableActivity
{
    protected function logAuditEvent(string $action, array $data): void
    {
        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'data' => $data,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'timestamp' => now(),
        ]);
    }
}
```

### 7. Common Patterns

#### Circuit Breaker Pattern
```php
class ResilientActivity extends Activity
{
    public function execute(mixed $data, CircuitBreakerService $circuitBreaker): mixed
    {
        return $circuitBreaker->call('external-service', function() use ($data) {
            // Call external service
            return $this->callExternalService($data);
        });
    }
}
```

#### Retry Pattern
```php
class RetryableActivity extends Activity
{
    public function execute(mixed $data): mixed
    {
        return retry(3, function() use ($data) {
            return $this->performOperation($data);
        }, 1000); // 1 second delay between retries
    }
}
```

These patterns provide a solid foundation for building robust, scalable, and maintainable workflows in the FinAegis core banking platform. Always consider the specific requirements of your banking operations when implementing these patterns.