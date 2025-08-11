# Workflow Development Guide

## Overview

FinAegis uses Laravel Workflow with Waterline for orchestrating complex AI operations. Workflows provide multi-step orchestration, compensation handling, human-in-the-loop capabilities, and complete event sourcing integration.

## Workflow Architecture

```
┌────────────────────────────────────────────┐
│             Parent Workflow                 │
│  (e.g., CustomerServiceWorkflow)           │
├────────────────────────────────────────────┤
│  • Orchestration Logic                     │
│  • State Management                        │
│  • Compensation Handlers                   │
│  • Event Recording                         │
└───────────┬───────────────┬────────────────┘
            │               │
            ▼               ▼
┌──────────────────┐  ┌──────────────────┐
│  Child Workflow   │  │  Child Workflow   │
│ (FraudDetection)  │  │  (CreditCheck)    │
└──────────────────┘  └──────────────────┘
            │               │
            ▼               ▼
┌──────────────────┐  ┌──────────────────┐
│    Activities     │  │    Activities     │
│  (Atomic Tasks)   │  │  (Atomic Tasks)   │
└──────────────────┘  └──────────────────┘
```

## Core Workflow Concepts

### Workflows
Long-running, stateful processes that orchestrate multiple activities:

```php
namespace App\Domain\AI\Workflows;

use Workflow\Workflow;
use Workflow\WorkflowStub;
use Workflow\ActivityStub;

class CustomerServiceWorkflow extends Workflow
{
    private array $state = [];
    private array $compensations = [];
    
    public function execute(array $params): array
    {
        // Initialize workflow state
        $this->state = [
            'conversation_id' => $params['conversation_id'],
            'user_id' => $params['user_id'],
            'started_at' => now(),
        ];
        
        try {
            // Step 1: Analyze customer request
            $analysis = yield $this->analyzeRequest($params['message']);
            
            // Step 2: Check if human intervention needed
            if ($analysis['requires_human']) {
                return yield $this->escalateToHuman($analysis);
            }
            
            // Step 3: Execute appropriate child workflow
            $result = yield $this->executeChildWorkflow($analysis);
            
            // Step 4: Generate and validate response
            $response = yield $this->generateResponse($result);
            
            // Step 5: Record interaction
            yield $this->recordInteraction($response);
            
            return $response;
            
        } catch (\Exception $e) {
            // Execute compensations
            yield $this->compensate($e);
            throw $e;
        }
    }
    
    private function executeChildWorkflow(array $analysis): \Generator
    {
        $childWorkflow = match($analysis['intent']) {
            'fraud_check' => FraudDetectionWorkflow::class,
            'credit_assessment' => CreditRiskWorkflow::class,
            'market_analysis' => MarketAnalysisWorkflow::class,
            default => throw new \InvalidArgumentException('Unknown intent')
        };
        
        $stub = WorkflowStub::make($childWorkflow);
        return yield $stub->execute($analysis);
    }
    
    private function compensate(\Exception $error): \Generator
    {
        // Reverse executed operations
        foreach (array_reverse($this->compensations) as $compensation) {
            yield $compensation();
        }
        
        // Log compensation
        yield ActivityStub::make(LogCompensationActivity::class)
            ->execute($this->state, $error);
    }
}
```

### Child Workflows

Specialized workflows that handle specific domains:

```php
namespace App\Domain\AI\Workflows\Children;

use Workflow\Workflow;
use Workflow\ActivityStub;

class FraudDetectionWorkflow extends Workflow
{
    public function execute(array $params): array
    {
        // Step 1: Behavioral analysis
        $behaviorScore = yield ActivityStub::make(BehavioralAnalysisActivity::class)
            ->analyze($params['user_id'], $params['transaction']);
        
        // Step 2: Pattern matching
        $patternScore = yield ActivityStub::make(PatternMatchingActivity::class)
            ->match($params['transaction']);
        
        // Step 3: ML model prediction
        $mlScore = yield ActivityStub::make(MLPredictionActivity::class)
            ->predict($params['transaction']);
        
        // Step 4: Calculate composite risk score
        $riskScore = $this->calculateRiskScore(
            $behaviorScore,
            $patternScore,
            $mlScore
        );
        
        // Step 5: Make decision
        $decision = yield $this->makeDecision($riskScore, $params);
        
        // Step 6: Record decision
        yield $this->recordDecision($decision);
        
        return $decision;
    }
    
    private function calculateRiskScore(
        float $behaviorScore,
        float $patternScore,
        float $mlScore
    ): float {
        // Weighted average
        return ($behaviorScore * 0.3) + 
               ($patternScore * 0.3) + 
               ($mlScore * 0.4);
    }
    
    private function makeDecision(float $riskScore, array $params): \Generator
    {
        $threshold = config('ai.fraud.threshold', 0.7);
        
        if ($riskScore > $threshold) {
            // High risk - require human review
            return yield $this->requestHumanReview($riskScore, $params);
        }
        
        return [
            'approved' => true,
            'risk_score' => $riskScore,
            'requires_review' => false,
        ];
    }
}
```

### Activities

Atomic, stateless operations that perform specific tasks:

```php
namespace App\Domain\AI\Activities;

use App\Domain\AI\Services\MLService;
use App\Domain\AI\Events\PredictionMadeEvent;

class MLPredictionActivity
{
    public function __construct(
        private MLService $mlService
    ) {}
    
    public function predict(array $transaction): float
    {
        // Prepare features
        $features = $this->extractFeatures($transaction);
        
        // Get prediction from ML model
        $prediction = $this->mlService->predict('fraud_detection', $features);
        
        // Record event
        event(new PredictionMadeEvent(
            model: 'fraud_detection',
            features: $features,
            prediction: $prediction,
            confidence: $prediction['confidence']
        ));
        
        return $prediction['score'];
    }
    
    private function extractFeatures(array $transaction): array
    {
        return [
            'amount' => $transaction['amount'],
            'merchant_category' => $transaction['merchant']['category'],
            'time_of_day' => now()->hour,
            'day_of_week' => now()->dayOfWeek,
            'location_risk' => $this->calculateLocationRisk($transaction),
            'velocity' => $this->calculateVelocity($transaction),
        ];
    }
}
```

## Saga Pattern Implementation

Sagas handle distributed transactions with compensation:

```php
namespace App\Domain\AI\Sagas;

use Workflow\Saga;
use Workflow\SagaBuilder;

class RiskAssessmentSaga extends Saga
{
    public function definition(): SagaBuilder
    {
        return $this->saga()
            ->step('check_credit')
                ->activity(CreditCheckActivity::class)
                ->compensation(ReverseCreditCheckActivity::class)
            ->step('analyze_behavior')
                ->activity(BehaviorAnalysisActivity::class)
                ->compensation(ClearBehaviorCacheActivity::class)
            ->step('calculate_risk')
                ->activity(RiskCalculationActivity::class)
                ->compensation(LogRiskReversalActivity::class)
            ->step('make_decision')
                ->activity(DecisionActivity::class)
                ->compensation(ReverseDecisionActivity::class);
    }
    
    public function execute(array $params): array
    {
        try {
            return $this->run($params);
        } catch (\Exception $e) {
            // Compensations are automatically executed
            $this->compensate();
            throw $e;
        }
    }
}
```

## Human-in-the-Loop Integration

Workflows can pause for human input:

```php
namespace App\Domain\AI\Workflows;

use Workflow\Workflow;
use Workflow\Timer;
use App\Domain\AI\Signals\HumanApprovalSignal;

class HumanApprovalWorkflow extends Workflow
{
    public function execute(array $params): array
    {
        // Create approval request
        $approvalId = yield $this->createApprovalRequest($params);
        
        // Wait for human response (with timeout)
        $signal = new HumanApprovalSignal($approvalId);
        
        $approved = yield $this->waitForSignal(
            signal: $signal,
            timeout: Timer::days(1)  // 1 day timeout
        );
        
        if ($approved === null) {
            // Timeout - auto-reject
            return $this->handleTimeout($approvalId);
        }
        
        if ($approved) {
            return $this->processApproval($params);
        } else {
            return $this->processRejection($params);
        }
    }
    
    private function waitForSignal(
        HumanApprovalSignal $signal,
        Timer $timeout
    ): \Generator {
        $race = yield [
            'approval' => $signal->wait(),
            'timeout' => $timeout->wait(),
        ];
        
        return $race['approval'] ?? null;
    }
}
```

## Testing Workflows

### Unit Testing Activities

```php
namespace Tests\Unit\AI\Activities;

use Tests\TestCase;
use App\Domain\AI\Activities\MLPredictionActivity;
use App\Domain\AI\Services\MLService;
use Mockery;

class MLPredictionActivityTest extends TestCase
{
    /** @test */
    public function it_makes_fraud_prediction(): void
    {
        // Mock ML service
        $mlService = Mockery::mock(MLService::class);
        $mlService->shouldReceive('predict')
            ->with('fraud_detection', Mockery::type('array'))
            ->andReturn([
                'score' => 0.85,
                'confidence' => 0.92
            ]);
        
        // Create activity
        $activity = new MLPredictionActivity($mlService);
        
        // Execute
        $score = $activity->predict([
            'amount' => 1000,
            'merchant' => ['category' => 'electronics']
        ]);
        
        // Assert
        $this->assertEquals(0.85, $score);
        
        // Verify event was dispatched
        Event::assertDispatched(PredictionMadeEvent::class);
    }
}
```

### Integration Testing Workflows

```php
namespace Tests\Feature\AI\Workflows;

use Tests\TestCase;
use Workflow\WorkflowStub;
use App\Domain\AI\Workflows\CustomerServiceWorkflow;

class CustomerServiceWorkflowTest extends TestCase
{
    /** @test */
    public function it_handles_customer_request(): void
    {
        // Create workflow stub
        $workflow = WorkflowStub::make(CustomerServiceWorkflow::class);
        
        // Execute workflow
        $result = $workflow->execute([
            'conversation_id' => 'conv_123',
            'user_id' => 1,
            'message' => 'Check my account balance'
        ]);
        
        // Assert result
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('tools_used', $result);
        $this->assertEquals('balance_inquiry', $result['intent']);
    }
    
    /** @test */
    public function it_compensates_on_failure(): void
    {
        // Create workflow that will fail
        $workflow = WorkflowStub::make(CustomerServiceWorkflow::class);
        
        // Force failure
        $this->mockActivity(CreditCheckActivity::class)
            ->shouldThrow(new \Exception('Service unavailable'));
        
        try {
            $workflow->execute([
                'conversation_id' => 'conv_123',
                'user_id' => 1,
                'message' => 'Apply for loan'
            ]);
        } catch (\Exception $e) {
            // Verify compensations were executed
            Event::assertDispatched(CompensationExecutedEvent::class);
        }
    }
}
```

## Workflow Patterns

### Sequential Processing
Execute activities in order:

```php
public function execute(): \Generator
{
    $step1 = yield $activity1->execute();
    $step2 = yield $activity2->execute($step1);
    $step3 = yield $activity3->execute($step2);
    
    return $step3;
}
```

### Parallel Processing
Execute activities concurrently:

```php
public function execute(): \Generator
{
    $results = yield [
        'credit' => $this->checkCredit(),
        'behavior' => $this->analyzeBehavior(),
        'market' => $this->analyzeMarket(),
    ];
    
    return $this->aggregateResults($results);
}
```

### Conditional Execution
Branch based on conditions:

```php
public function execute(array $params): \Generator
{
    $analysis = yield $this->analyze($params);
    
    if ($analysis['risk'] === 'high') {
        return yield $this->highRiskWorkflow($params);
    } elseif ($analysis['risk'] === 'medium') {
        return yield $this->mediumRiskWorkflow($params);
    } else {
        return yield $this->lowRiskWorkflow($params);
    }
}
```

### Retry with Backoff
Handle transient failures:

```php
public function execute(): \Generator
{
    $maxRetries = 3;
    $delay = 1000; // milliseconds
    
    for ($i = 0; $i < $maxRetries; $i++) {
        try {
            return yield $this->activity->execute();
        } catch (\Exception $e) {
            if ($i === $maxRetries - 1) {
                throw $e;
            }
            
            yield Timer::milliseconds($delay * pow(2, $i))->wait();
        }
    }
}
```

## Performance Considerations

### State Management
- Keep workflow state minimal
- Use external storage for large data
- Clean up state after completion

### Activity Design
- Make activities idempotent
- Keep activities stateless
- Use caching for expensive operations

### Parallelization
- Identify independent operations
- Use parallel execution where possible
- Monitor resource usage

### Monitoring
- Track workflow execution time
- Monitor failure rates
- Alert on compensations

## Best Practices

1. **Single Responsibility**: Each workflow should handle one business process
2. **Idempotency**: Design activities to be safely retryable
3. **Compensation**: Always implement compensation for critical operations
4. **Testing**: Test both happy path and failure scenarios
5. **Monitoring**: Track metrics and set up alerts
6. **Documentation**: Document workflow purpose and flow
7. **Versioning**: Version workflows for backward compatibility

## Next Steps

- [Event Sourcing Patterns](04-Event-Sourcing.md)
- [API Reference](05-API-Reference.md)
- [Testing Guide](06-Testing.md)