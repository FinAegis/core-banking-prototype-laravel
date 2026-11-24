# Workflow Orchestration with Durable Workflow

## Overview

FinAegis runs multi-step business processes as durable workflows. It uses the `durable-workflow/workflow` package, version 1.0, which was formerly called Laravel Workflow. Its classes live in the `Workflow\` namespace. The Waterline dashboard shows running and finished workflows, and only administrators can open it.

This document covers workflow patterns, saga compensation, activity design and best practices for reliable workflows.

### About the code samples

The samples are sketches that show each pattern. Several of their classes do not exist in the codebase. The package API they use is real:

- `ActivityStub::make()` runs an activity, and `ActivityStub::all()` waits for several activities at once.
- `ChildWorkflowStub::make()` starts a child workflow.
- `WorkflowStub::timer()` pauses a workflow, and `WorkflowStub::awaitWithTimeout()` waits for a signal with a time limit.
- `WorkflowStub::sideEffect()` and `WorkflowStub::now()` keep random values and the current time stable when a workflow is replayed.
- `$this->addCompensation()` and `$this->compensate()` give built-in saga compensation.
- Activities set their own retries and time limits through the `$tries` and `$timeout` properties and the `backoff()` method.

For a complete working example, read `app/Domain/Exchange/Workflows/OrderMatchingWorkflow.php`. It uses `ActivityStub::make()` together with `addCompensation()` and `compensate()`.

Some sketches use `float` for amounts to stay short. Real code must handle money as numeric strings with `bcmath`.

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

use Workflow\ActivityStub;
use Workflow\Workflow;

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
            $validation = yield ActivityStub::make(ValidatePrerequisitesActivity::class, [
                'account_id' => $accountId,
                'amount' => $totalAmount,
            ]);
            
            // Step 2: Execute main business logic
            $allocation = yield ActivityStub::make(AllocateCashActivity::class, [
                'account_id' => $accountId,
                'strategy' => $strategy,
                'amount' => $totalAmount,
            ]);
            
            $this->allocationResults = $allocation;
            
            // Step 3: Optimize results
            $optimization = yield ActivityStub::make(OptimizeYieldActivity::class, [
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
            yield from $this->reverseCompletedSteps();
            throw new \RuntimeException(
                "Workflow failed: {$e->getMessage()}",
                previous: $e
            );
        }
    }
    
    private function reverseCompletedSteps()
    {
        if (!empty($this->allocationResults)) {
            // Reverse the allocation
            yield ActivityStub::make(ReverseAllocationActivity::class, [
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

use Workflow\ActivityStub;
use Workflow\Workflow;

class LoanApplicationWorkflow extends Workflow
{
    public function execute(array $application)
    {
        // Execute activities in parallel
        $promises = [
            'credit_check' => ActivityStub::make(CreditCheckActivity::class, [
                'ssn' => $application['ssn'],
                'income' => $application['income'],
            ]),
            'fraud_check' => ActivityStub::make(FraudDetectionActivity::class, [
                'application' => $application,
            ]),
            'document_verification' => ActivityStub::make(DocumentVerificationActivity::class, [
                'documents' => $application['documents'],
            ]),
        ];
        
        // Wait for all parallel activities
        $results = yield ActivityStub::all($promises);
        
        // Aggregate results
        $riskScore = $this->calculateRiskScore($results);
        
        if ($riskScore > 70) {
            // High risk - manual review required
            yield ActivityStub::make(RequestManualReviewActivity::class, [
                'application_id' => $application['id'],
                'risk_score' => $riskScore,
                'results' => $results,
            ]);
        } else {
            // Auto-approve
            yield ActivityStub::make(ApproveLoanActivity::class, [
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

use Workflow\ActivityStub;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

class OrderMatchingWorkflow extends Workflow
{
    public function execute(array $order)
    {
        // Validate order
        $validation = yield ActivityStub::make(ValidateOrderActivity::class, [
            'order' => $order,
        ]);
        
        if (!$validation['valid']) {
            throw new InvalidOrderException($validation['reason']);
        }
        
        // Execute child workflow for matching
        $matchingResult = yield ChildWorkflowStub::make(
            FindMatchingOrdersWorkflow::class,
            [$order]
        );
        
        // Process matches
        foreach ($matchingResult['matches'] as $match) {
            yield ChildWorkflowStub::make(
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

use Carbon\CarbonInterval;
use Workflow\ActivityStub;
use Workflow\SignalMethod;
use Workflow\Workflow;
use Workflow\WorkflowStub;

class WithdrawalWorkflow extends Workflow
{
    private bool $confirmed = false;

    // The confirmation link in the email sends this signal to the workflow
    #[SignalMethod]
    public function confirm(): void
    {
        $this->confirmed = true;
    }

    public function execute(array $withdrawal)
    {
        // Initial validation
        $validation = yield ActivityStub::make(ValidateWithdrawalActivity::class, [
            'withdrawal' => $withdrawal,
        ]);
        
        // Send confirmation email
        yield ActivityStub::make(SendConfirmationEmailActivity::class, [
            'user_id' => $withdrawal['user_id'],
            'amount' => $withdrawal['amount'],
        ]);
        
        // Wait for user confirmation (with timeout)
        $confirmed = yield WorkflowStub::awaitWithTimeout(
            CarbonInterval::minutes(30),
            fn () => $this->confirmed
        );
        
        if (!$confirmed) {
            // Timeout - cancel withdrawal
            yield ActivityStub::make(CancelWithdrawalActivity::class, [
                'withdrawal_id' => $withdrawal['id'],
                'reason' => 'Confirmation timeout',
            ]);
            
            return ['status' => 'cancelled', 'reason' => 'timeout'];
        }
        
        // Process withdrawal
        yield ActivityStub::make(ProcessWithdrawalActivity::class, [
            'withdrawal' => $withdrawal,
        ]);
        
        // Schedule settlement after clearing period
        yield WorkflowStub::timer(CarbonInterval::days(3));
        
        yield ActivityStub::make(SettleWithdrawalActivity::class, [
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

use Carbon\CarbonInterval;
use Throwable;
use Workflow\ActivityStub;
use Workflow\Workflow;
use Workflow\WorkflowStub;

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
            $transfer = yield ActivityStub::make(TransferFundsActivity::class, [
                'transaction_id' => $transaction['id'],
                'to_account' => $loan['borrower_account'],
                'amount' => $loan['amount'],
            ]);
            $this->completedSteps[] = ['transfer_funds', $transfer];
            
            // Step 4: Update loan status
            yield ActivityStub::make(UpdateLoanStatusActivity::class, [
                'loan_id' => $loan['id'],
                'status' => 'disbursed',
                'disbursed_at' => WorkflowStub::now(),
            ]);
            
            return [
                'success' => true,
                'transaction_id' => $transaction['id'],
                'disbursed_amount' => $loan['amount'],
            ];
            
        } catch (Throwable $e) {
            yield from $this->reverseCompletedSteps();
            
            throw new \RuntimeException(
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
                return yield ActivityStub::make($activityClass, $input);
            } catch (\Exception $e) {
                $lastException = $e;
                $attempt++;
                
                if ($attempt < $maxAttempts) {
                    // Exponential backoff
                    yield WorkflowStub::timer(CarbonInterval::seconds(2 ** $attempt));
                }
            }
        }
        
        throw $lastException;
    }
    
    private function reverseCompletedSteps()
    {
        // Reverse completed steps in reverse order
        foreach (array_reverse($this->completedSteps) as [$step, $data]) {
            switch ($step) {
                case 'transfer_funds':
                    yield ActivityStub::make(ReverseFundsTransferActivity::class, [
                        'transfer_id' => $data['id'],
                    ]);
                    break;
                    
                case 'create_transaction':
                    yield ActivityStub::make(VoidTransactionActivity::class, [
                        'transaction_id' => $data['id'],
                    ]);
                    break;
                    
                case 'reserve_funds':
                    yield ActivityStub::make(ReleaseFundsReservationActivity::class, [
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

The package has a fake mode for tests. `WorkflowStub::fake()` stops real dispatching. `WorkflowStub::mock()` sets the result of an activity, and `WorkflowStub::assertDispatched()` checks that an activity ran. `tests/Domain/Asset/Workflows/AssetWorkflowMoneyTest.php` is a working example.

```php
<?php

use App\Domain\Treasury\Activities\AllocateCashActivity;
use App\Domain\Treasury\Workflows\CashManagementWorkflow;
use Workflow\WorkflowStub;

it('allocates cash through the activity', function () {
    WorkflowStub::fake();

    WorkflowStub::mock(AllocateCashActivity::class, [
        'allocations' => [
            ['type' => 'cash', 'amount' => '20000.00'],
            ['type' => 'bonds', 'amount' => '40000.00'],
            ['type' => 'equities', 'amount' => '40000.00'],
        ],
        'total_allocated' => '100000.00',
    ]);

    $workflow = WorkflowStub::make(CashManagementWorkflow::class);
    $workflow->start('test-account', '100000.00', 'balanced', ['min_yield' => 5.0]);

    WorkflowStub::assertDispatched(AllocateCashActivity::class);
});
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

### Concurrent Activity Execution

Start several activities without waiting for each one, then wait for all of them with `ActivityStub::all()`. Retries and time limits belong on the activity class.

```php
<?php

namespace App\Domain\Reporting\Workflows;

use Workflow\ActivityStub;
use Workflow\Workflow;

class ReportGenerationWorkflow extends Workflow
{
    public function execute(array $params)
    {
        // Both activities run at the same time
        [$report, $metadata] = yield ActivityStub::all([
            ActivityStub::make(GenerateReportActivity::class, $params),
            ActivityStub::make(CollectMetadataActivity::class, $params),
        ]);

        return [
            'report' => $report,
            'metadata' => $metadata,
        ];
    }
}
```

```php
<?php

use Workflow\Activity;

class GenerateReportActivity extends Activity
{
    public $tries = 3;        // The package default is unlimited
    public $timeout = 1800;   // Seconds; the package default is no limit

    public function backoff()
    {
        return [1, 2, 4];     // Seconds between attempts
    }

    public function execute(array $params): array
    {
        // ...
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
An activity can run more than once, so it must be safe to retry:

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
A workflow is replayed from its history, so it must make the same decisions every time it runs:

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
    $random = yield WorkflowStub::sideEffect(fn () => random_int(1, 100));
    $now = WorkflowStub::now();
}
```

### 3. Workflow Versioning
Workflows that are still running must keep working after you change the code. The package offers `WorkflowStub::getVersion()` for this. A simpler approach is a version field in the input:

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

Durable workflows keep multi-step processes running across failures and restarts. Following these patterns keeps the FinAegis workflows reliable and easy to maintain.