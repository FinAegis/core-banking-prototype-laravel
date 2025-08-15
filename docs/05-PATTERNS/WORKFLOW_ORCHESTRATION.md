# Workflow Orchestration with Laravel Workflow

## Overview

The FinAegis platform uses Laravel Workflow for orchestrating complex business processes. This document covers workflow implementation patterns, saga compensation, activity design, and best practices for building reliable distributed workflows.

## Architecture

### Core Components

```
app/Domain/[Domain]/
├── Workflows/
│   ├── CashManagementWorkflow.php       # Main workflow definition
│   ├── LoanApplicationWorkflow.php      # Multi-step loan process
│   └── WithdrawalWorkflow.php           # Withdrawal orchestration
├── Activities/
│   ├── AllocateCashActivity.php         # Individual workflow step
│   ├── ValidateAllocationActivity.php   # Validation activity
│   └── OptimizeYieldActivity.php        # Business logic activity
└── Sagas/
    ├── RiskManagementSaga.php           # Long-running saga
    └── ComplianceSaga.php               # Compliance orchestration
```

## Workflow Implementation

### Basic Workflow Structure

```php
<?php

namespace App\Domain\Treasury\Workflows;

use Workflow\Workflow;
use Workflow\Activity;

class CashManagementWorkflow extends Workflow
{
    private string $accountId;
    private float $totalAmount;
    private array $allocationResults = [];
    
    public function execute(
        string $accountId,
        float $totalAmount,
        string $strategy,
        array $constraints = []
    ) {
        $this->accountId = $accountId;
        $this->totalAmount = $totalAmount;
        
        try {
            // Step 1: Validate prerequisites
            $validation = yield Activity::make(ValidatePrerequisitesActivity::class, [
                'account_id' => $accountId,
                'amount' => $totalAmount,
            ]);
            
            // Step 2: Execute main business logic
            $allocation = yield Activity::make(AllocateCashActivity::class, [
                'account_id' => $accountId,
                'strategy' => $strategy,
                'amount' => $totalAmount,
            ]);
            
            $this->allocationResults = $allocation;
            
            // Step 3: Optimize results
            $optimization = yield Activity::make(OptimizeYieldActivity::class, [
                'allocations' => $allocation,
                'constraints' => $constraints,
            ]);
            
            return [
                'success' => true,
                'allocation' => $allocation,
                'optimization' => $optimization,
            ];
            
        } catch (\Exception $e) {
            // Trigger compensation
            yield from $this->compensate();
            throw new WorkflowException(
                "Workflow failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }
    
    public function compensate()
    {
        if (!empty($this->allocationResults)) {
            // Reverse the allocation
            yield Activity::make(ReverseAllocationActivity::class, [
                'account_id' => $this->accountId,
                'allocations' => $this->allocationResults,
            ]);
        }
    }
}
```

### Activity Implementation

```php
<?php

namespace App\Domain\Treasury\Activities;

use Workflow\Activity;

class AllocateCashActivity extends Activity
{
    public function execute(array $input): array
    {
        $accountId = $input['account_id'];
        $strategy = $input['strategy'];
        $amount = $input['amount'];
        $reverse = $input['reverse'] ?? false;
        
        if ($reverse) {
            return $this->reverseAllocation($accountId, $input['allocations']);
        }
        
        // Perform allocation logic
        $allocations = $this->calculateAllocations($strategy, $amount);
        
        // Apply allocations
        foreach ($allocations as $allocation) {
            $this->applyAllocation($accountId, $allocation);
        }
        
        return [
            'allocations' => $allocations,
            'total_allocated' => $amount,
            'timestamp' => now()->toIso8601String(),
        ];
    }
    
    private function calculateAllocations(string $strategy, float $amount): array
    {
        return match($strategy) {
            'conservative' => [
                ['type' => 'cash', 'amount' => $amount * 0.4],
                ['type' => 'bonds', 'amount' => $amount * 0.5],
                ['type' => 'equities', 'amount' => $amount * 0.1],
            ],
            'balanced' => [
                ['type' => 'cash', 'amount' => $amount * 0.2],
                ['type' => 'bonds', 'amount' => $amount * 0.4],
                ['type' => 'equities', 'amount' => $amount * 0.4],
            ],
            'aggressive' => [
                ['type' => 'cash', 'amount' => $amount * 0.1],
                ['type' => 'bonds', 'amount' => $amount * 0.2],
                ['type' => 'equities', 'amount' => $amount * 0.7],
            ],
            default => [
                ['type' => 'cash', 'amount' => $amount],
            ],
        };
    }
    
    private function reverseAllocation(string $accountId, array $allocations): array
    {
        foreach ($allocations as $allocation) {
            // Reverse each allocation
            DB::table('allocations')
                ->where('account_id', $accountId)
                ->where('type', $allocation['type'])
                ->decrement('amount', $allocation['amount']);
        }
        
        return [
            'reversed' => true,
            'allocations' => $allocations,
        ];
    }
}
```

## Complex Workflow Patterns

### Parallel Execution

```php
<?php

namespace App\Domain\Lending\Workflows;

use Workflow\Workflow;
use Workflow\Activity;
use Workflow\Promise;

class LoanApplicationWorkflow extends Workflow
{
    public function execute(array $application)
    {
        // Execute activities in parallel
        $promises = [
            'credit_check' => Activity::make(CreditCheckActivity::class, [
                'ssn' => $application['ssn'],
                'income' => $application['income'],
            ]),
            'fraud_check' => Activity::make(FraudDetectionActivity::class, [
                'application' => $application,
            ]),
            'document_verification' => Activity::make(DocumentVerificationActivity::class, [
                'documents' => $application['documents'],
            ]),
        ];
        
        // Wait for all parallel activities
        $results = yield Promise::all($promises);
        
        // Aggregate results
        $riskScore = $this->calculateRiskScore($results);
        
        if ($riskScore > 70) {
            // High risk - manual review required
            yield Activity::make(RequestManualReviewActivity::class, [
                'application_id' => $application['id'],
                'risk_score' => $riskScore,
                'results' => $results,
            ]);
        } else {
            // Auto-approve
            yield Activity::make(ApproveLoanActivity::class, [
                'application_id' => $application['id'],
                'amount' => $application['amount'],
                'terms' => $this->calculateTerms($riskScore),
            ]);
        }
        
        return [
            'approved' => $riskScore <= 70,
            'risk_score' => $riskScore,
            'results' => $results,
        ];
    }
}
```

### Child Workflows

```php
<?php

namespace App\Domain\Exchange\Workflows;

use Workflow\Workflow;
use Workflow\ChildWorkflow;

class OrderMatchingWorkflow extends Workflow
{
    public function execute(array $order)
    {
        // Validate order
        $validation = yield Activity::make(ValidateOrderActivity::class, [
            'order' => $order,
        ]);
        
        if (!$validation['valid']) {
            throw new InvalidOrderException($validation['reason']);
        }
        
        // Execute child workflow for matching
        $matchingResult = yield ChildWorkflow::make(
            FindMatchingOrdersWorkflow::class,
            [$order]
        );
        
        // Process matches
        foreach ($matchingResult['matches'] as $match) {
            yield ChildWorkflow::make(
                ExecuteTradeWorkflow::class,
                [$order, $match]
            );
        }
        
        return [
            'order_id' => $order['id'],
            'matches' => count($matchingResult['matches']),
            'status' => 'completed',
        ];
    }
}
```

### Temporal Workflows (Timers and Delays)

```php
<?php

namespace App\Domain\Wallet\Workflows;

use Workflow\Workflow;
use Workflow\Timer;
use Carbon\CarbonInterval;

class WithdrawalWorkflow extends Workflow
{
    public function execute(array $withdrawal)
    {
        // Initial validation
        $validation = yield Activity::make(ValidateWithdrawalActivity::class, [
            'withdrawal' => $withdrawal,
        ]);
        
        // Send confirmation email
        yield Activity::make(SendConfirmationEmailActivity::class, [
            'user_id' => $withdrawal['user_id'],
            'amount' => $withdrawal['amount'],
        ]);
        
        // Wait for user confirmation (with timeout)
        $confirmed = yield Timer::awaitWithTimeout(
            CarbonInterval::minutes(30),
            Activity::make(WaitForConfirmationActivity::class, [
                'withdrawal_id' => $withdrawal['id'],
            ])
        );
        
        if (!$confirmed) {
            // Timeout - cancel withdrawal
            yield Activity::make(CancelWithdrawalActivity::class, [
                'withdrawal_id' => $withdrawal['id'],
                'reason' => 'Confirmation timeout',
            ]);
            
            return ['status' => 'cancelled', 'reason' => 'timeout'];
        }
        
        // Process withdrawal
        yield Activity::make(ProcessWithdrawalActivity::class, [
            'withdrawal' => $withdrawal,
        ]);
        
        // Schedule settlement after clearing period
        yield Timer::sleep(CarbonInterval::days(3));
        
        yield Activity::make(SettleWithdrawalActivity::class, [
            'withdrawal_id' => $withdrawal['id'],
        ]);
        
        return ['status' => 'completed'];
    }
}
```

## Saga Pattern Implementation

### Long-Running Saga with State Management

```php
<?php

namespace App\Domain\Treasury\Sagas;

use Spatie\EventSourcing\EventHandlers\Reactors\Reactor;
use Workflow\WorkflowStub;

class RiskManagementSaga extends Reactor
{
    private array $sagaState = [];
    
    public function onTreasuryAccountCreated(TreasuryAccountCreated $event): void
    {
        // Initialize saga state
        $this->sagaState[$event->accountId] = [
            'id' => Str::uuid()->toString(),
            'status' => 'initiated',
            'steps' => [],
            'created_at' => now(),
        ];
        
        // Start risk assessment workflow
        $workflow = WorkflowStub::make(RiskAssessmentWorkflow::class);
        
        $workflow->start([
            'account_id' => $event->accountId,
            'initial_balance' => $event->initialBalance,
            'account_type' => $event->accountType,
        ]);
        
        $this->sagaState[$event->accountId]['workflow_id'] = $workflow->id();
    }
    
    public function onRiskAssessmentCompleted(RiskAssessmentCompleted $event): void
    {
        $state = &$this->sagaState[$event->accountId];
        $state['steps'][] = 'risk_assessment';
        
        if ($event->riskScore > 70) {
            // High risk - trigger mitigation workflow
            $this->startMitigationWorkflow($event->accountId, $event->riskScore);
        } else {
            // Normal risk - schedule periodic monitoring
            $this->scheduleMonitoring($event->accountId);
        }
    }
    
    private function startMitigationWorkflow(string $accountId, float $riskScore): void
    {
        $workflow = WorkflowStub::make(RiskMitigationWorkflow::class);
        
        $workflow->start([
            'account_id' => $accountId,
            'risk_score' => $riskScore,
            'mitigation_strategies' => $this->determineMitigationStrategies($riskScore),
        ]);
        
        $this->sagaState[$accountId]['mitigation_workflow'] = $workflow->id();
    }
    
    public function compensate(string $accountId): void
    {
        $state = $this->sagaState[$accountId];
        
        // Cancel running workflows
        if (isset($state['workflow_id'])) {
            WorkflowStub::load($state['workflow_id'])->cancel();
        }
        
        // Reverse completed steps
        foreach (array_reverse($state['steps']) as $step) {
            $this->reverseStep($accountId, $step);
        }
        
        $this->sagaState[$accountId]['status'] = 'compensated';
    }
}
```

## Error Handling and Compensation

### Comprehensive Error Handling

```php
<?php

namespace App\Domain\Lending\Workflows;

use Workflow\Workflow;
use Workflow\Activity;
use Workflow\Exception\ActivityFailedException;
use Workflow\Exception\WorkflowExecutionException;

class LoanDisbursementWorkflow extends Workflow
{
    private array $completedSteps = [];
    
    public function execute(array $loan)
    {
        try {
            // Step 1: Reserve funds
            $reservation = yield $this->executeWithRetry(
                ReserveFundsActivity::class,
                ['amount' => $loan['amount']],
                maxAttempts: 3
            );
            $this->completedSteps[] = ['reserve_funds', $reservation];
            
            // Step 2: Create transaction
            $transaction = yield $this->executeWithRetry(
                CreateTransactionActivity::class,
                ['loan' => $loan, 'reservation' => $reservation],
                maxAttempts: 2
            );
            $this->completedSteps[] = ['create_transaction', $transaction];
            
            // Step 3: Transfer funds
            $transfer = yield Activity::make(TransferFundsActivity::class, [
                'transaction_id' => $transaction['id'],
                'to_account' => $loan['borrower_account'],
                'amount' => $loan['amount'],
            ]);
            $this->completedSteps[] = ['transfer_funds', $transfer];
            
            // Step 4: Update loan status
            yield Activity::make(UpdateLoanStatusActivity::class, [
                'loan_id' => $loan['id'],
                'status' => 'disbursed',
                'disbursed_at' => now(),
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $transaction['id'],
                'disbursed_amount' => $loan['amount'],
            ];
            
        } catch (ActivityFailedException $e) {
            Log::error('Activity failed in loan disbursement', [
                'loan_id' => $loan['id'],
                'activity' => $e->getActivityType(),
                'error' => $e->getMessage(),
            ]);
            
            yield from $this->compensate();
            
            throw new WorkflowExecutionException(
                "Loan disbursement failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }
    
    private function executeWithRetry(
        string $activityClass,
        array $input,
        int $maxAttempts = 3
    ) {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $maxAttempts) {
            try {
                return yield Activity::make($activityClass, $input);
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                if ($attempt < $maxAttempts) {
                    // Exponential backoff
                    yield Timer::sleep(CarbonInterval::seconds(2 ** $attempt));
                }
            }
        }
        
        throw $lastException;
    }
    
    public function compensate()
    {
        // Reverse completed steps in reverse order
        foreach (array_reverse($this->completedSteps) as [$step, $data]) {
            switch ($step) {
                case 'transfer_funds':
                    yield Activity::make(ReverseFundsTransferActivity::class, [
                        'transfer_id' => $data['id'],
                    ]);
                    break;
                    
                case 'create_transaction':
                    yield Activity::make(VoidTransactionActivity::class, [
                        'transaction_id' => $data['id'],
                    ]);
                    break;
                    
                case 'reserve_funds':
                    yield Activity::make(ReleaseFundsReservationActivity::class, [
                        'reservation_id' => $data['id'],
                    ]);
                    break;
            }
        }
    }
}
```

## Testing Workflows

### Unit Testing Workflows

```php
<?php

namespace Tests\Feature\Domain\Treasury\Workflows;

use App\Domain\Treasury\Workflows\CashManagementWorkflow;
use App\Domain\Treasury\Activities\AllocateCashActivity;
use Workflow\WorkflowStub;
use Workflow\Testing\WorkflowTestCase;

class CashManagementWorkflowTest extends WorkflowTestCase
{
    public function test_successful_cash_allocation()
    {
        // Arrange
        $workflow = WorkflowStub::make(CashManagementWorkflow::class);
        
        $this->mockActivity(AllocateCashActivity::class)
            ->with([
                'account_id' => 'test-account',
                'strategy' => 'balanced',
                'amount' => 100000,
            ])
            ->andReturn([
                'allocations' => [
                    ['type' => 'cash', 'amount' => 20000],
                    ['type' => 'bonds', 'amount' => 40000],
                    ['type' => 'equities', 'amount' => 40000],
                ],
                'total_allocated' => 100000,
            ]);
        
        // Act
        $result = $workflow->execute(
            'test-account',
            100000,
            'balanced',
            ['min_yield' => 5.0]
        );
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(100000, $result['allocation']['total_allocated']);
        $this->assertCount(3, $result['allocation']['allocations']);
    }
    
    public function test_workflow_compensation_on_failure()
    {
        // Arrange
        $workflow = WorkflowStub::make(CashManagementWorkflow::class);
        
        $this->mockActivity(AllocateCashActivity::class)
            ->andThrow(new \Exception('Allocation failed'));
        
        $this->expectCompensation(ReverseAllocationActivity::class);
        
        // Act & Assert
        $this->expectException(WorkflowException::class);
        
        $workflow->execute('test-account', 100000, 'balanced');
    }
}
```

### Integration Testing

```php
<?php

namespace Tests\Integration\Workflows;

use Tests\TestCase;
use Workflow\WorkflowStub;
use App\Domain\Treasury\Workflows\CashManagementWorkflow;

class CashManagementIntegrationTest extends TestCase
{
    public function test_end_to_end_cash_management_workflow()
    {
        // Arrange
        $account = Account::factory()->create([
            'balance' => 1000000,
        ]);
        
        // Act
        $workflow = WorkflowStub::make(CashManagementWorkflow::class);
        
        $result = $workflow->execute(
            $account->uuid,
            500000,
            'balanced',
            ['target_yield' => 6.0]
        );
        
        // Assert
        $this->assertTrue($result['success']);
        
        // Verify database changes
        $this->assertDatabaseHas('allocations', [
            'account_id' => $account->uuid,
            'status' => 'active',
        ]);
        
        // Verify events were recorded
        $events = TreasuryAggregate::retrieve($account->uuid)
            ->getRecordedEvents();
        
        $this->assertCount(2, $events);
        $this->assertInstanceOf(CashAllocated::class, $events[0]);
        $this->assertInstanceOf(YieldOptimizationStarted::class, $events[1]);
    }
}
```

## Performance Optimization

### Activity Batching

```php
<?php

namespace App\Domain\Exchange\Activities;

class BatchOrderProcessingActivity extends Activity
{
    public function execute(array $input): array
    {
        $orders = $input['orders'];
        $results = [];
        
        // Process orders in batches
        foreach (array_chunk($orders, 100) as $batch) {
            $batchResults = DB::transaction(function () use ($batch) {
                return array_map(
                    fn($order) => $this->processOrder($order),
                    $batch
                );
            });
            
            $results = array_merge($results, $batchResults);
        }
        
        return [
            'processed' => count($results),
            'results' => $results,
        ];
    }
}
```

### Async Activity Execution

```php
<?php

namespace App\Domain\Reporting\Workflows;

use Workflow\Workflow;
use Workflow\Activity;
use Workflow\ActivityOptions;

class ReportGenerationWorkflow extends Workflow
{
    public function execute(array $params)
    {
        // Configure async execution
        $options = ActivityOptions::new()
            ->withStartToCloseTimeout(CarbonInterval::minutes(30))
            ->withRetryPolicy(
                maxAttempts: 3,
                backoffCoefficient: 2.0,
                initialInterval: CarbonInterval::seconds(1)
            );
        
        // Start async activity
        $reportGeneration = yield Activity::makeAsync(
            GenerateReportActivity::class,
            $params,
            $options
        );
        
        // Do other work while report generates
        $metadata = yield Activity::make(CollectMetadataActivity::class, $params);
        
        // Wait for async activity to complete
        $report = yield $reportGeneration;
        
        return [
            'report' => $report,
            'metadata' => $metadata,
        ];
    }
}
```

## Monitoring and Observability

### Workflow Metrics

```php
<?php

namespace App\Infrastructure\Workflow;

use Illuminate\Support\Facades\Redis;

class WorkflowMetricsCollector
{
    public function recordWorkflowStart(string $workflowType, string $workflowId): void
    {
        Redis::hincrby('workflow:metrics:started', $workflowType, 1);
        Redis::hset("workflow:instances:{$workflowId}", 'started_at', now());
    }
    
    public function recordWorkflowComplete(string $workflowType, string $workflowId): void
    {
        Redis::hincrby('workflow:metrics:completed', $workflowType, 1);
        
        $startedAt = Redis::hget("workflow:instances:{$workflowId}", 'started_at');
        $duration = now()->diffInSeconds($startedAt);
        
        Redis::lpush("workflow:durations:{$workflowType}", $duration);
        Redis::ltrim("workflow:durations:{$workflowType}", 0, 999); // Keep last 1000
    }
    
    public function recordWorkflowError(string $workflowType, string $error): void
    {
        Redis::hincrby('workflow:metrics:errors', $workflowType, 1);
        Redis::lpush("workflow:errors:{$workflowType}", json_encode([
            'error' => $error,
            'timestamp' => now()->toIso8601String(),
        ]));
    }
    
    public function getMetrics(): array
    {
        return [
            'started' => Redis::hgetall('workflow:metrics:started'),
            'completed' => Redis::hgetall('workflow:metrics:completed'),
            'errors' => Redis::hgetall('workflow:metrics:errors'),
            'active' => $this->getActiveWorkflows(),
        ];
    }
}
```

### Workflow History Tracking

```php
<?php

namespace App\Domain\Shared\Workflows;

use Workflow\Workflow;

abstract class TrackedWorkflow extends Workflow
{
    protected function trackExecution(string $step, array $data = []): void
    {
        DB::table('workflow_history')->insert([
            'workflow_id' => $this->workflowId(),
            'workflow_type' => static::class,
            'step' => $step,
            'data' => json_encode($data),
            'timestamp' => now(),
        ]);
    }
    
    protected function getHistory(): array
    {
        return DB::table('workflow_history')
            ->where('workflow_id', $this->workflowId())
            ->orderBy('timestamp')
            ->get()
            ->map(fn($row) => [
                'step' => $row->step,
                'data' => json_decode($row->data, true),
                'timestamp' => $row->timestamp,
            ])
            ->toArray();
    }
}
```

## Best Practices

### 1. Idempotent Activities
Ensure activities can be safely retried:

```php
class TransferFundsActivity extends Activity
{
    public function execute(array $input): array
    {
        $idempotencyKey = $input['idempotency_key'];
        
        // Check if already processed
        if ($existing = $this->findByIdempotencyKey($idempotencyKey)) {
            return $existing;
        }
        
        // Process transfer
        $result = $this->processTransfer($input);
        
        // Store with idempotency key
        $this->storeWithIdempotencyKey($idempotencyKey, $result);
        
        return $result;
    }
}
```

### 2. Deterministic Workflows
Workflows must be deterministic for replay:

```php
// ❌ Non-deterministic
public function execute($input)
{
    $random = rand(1, 100); // Non-deterministic!
    $now = now(); // Non-deterministic!
}

// ✅ Deterministic
public function execute($input)
{
    $random = yield Activity::make(GenerateRandomActivity::class);
    $now = yield Activity::make(GetCurrentTimeActivity::class);
}
```

### 3. Workflow Versioning
Handle workflow evolution:

```php
class LoanApplicationWorkflowV2 extends Workflow
{
    public function execute($input)
    {
        $version = $input['version'] ?? 1;
        
        if ($version === 1) {
            // Handle V1 workflow
            return $this->executeV1($input);
        }
        
        // V2 workflow logic
        // New steps...
    }
}
```

## Conclusion

Laravel Workflow provides powerful orchestration capabilities for complex business processes. By following these patterns and best practices, you can build reliable, maintainable, and scalable workflow systems in the FinAegis platform.