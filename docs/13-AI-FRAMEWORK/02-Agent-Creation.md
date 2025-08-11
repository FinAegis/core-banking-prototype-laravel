# Creating Custom AI Agents

## Overview

AI Agents in FinAegis are specialized workflows that handle specific banking domains. They combine natural language processing, tool execution, and decision-making capabilities while maintaining complete auditability through event sourcing.

## Agent Architecture

```
┌──────────────────────────────────────┐
│           AI Agent                    │
├──────────────────────────────────────┤
│  • Intent Recognition                 │
│  • Context Management                 │
│  • Tool Selection                     │
│  • Decision Making                    │
│  • Response Generation                │
└──────────────────────────────────────┘
              │
              ▼
┌──────────────────────────────────────┐
│      Laravel Workflow                 │
├──────────────────────────────────────┤
│  • Multi-step Orchestration          │
│  • Compensation Handling              │
│  • Human-in-the-Loop                 │
│  • Event Sourcing                    │
└──────────────────────────────────────┘
```

## Creating a Custom Agent

### Step 1: Define the Agent Workflow

Create a new workflow class extending `Workflow`:

```php
namespace App\Domain\AI\Workflows;

use Workflow\Workflow;
use App\Domain\AI\Activities\IntentRecognitionActivity;
use App\Domain\AI\Activities\ToolSelectionActivity;
use App\Domain\AI\Activities\ExecutionActivity;
use App\Domain\AI\Activities\ResponseGenerationActivity;

class LoanAdvisorAgentWorkflow extends Workflow
{
    private array $context = [];
    private float $confidenceThreshold = 0.8;
    
    public function execute(
        string $message,
        string $conversationId,
        int $userId,
        array $context = []
    ): array {
        $this->context = $context;
        
        // Step 1: Recognize user intent
        $intent = yield $this->recognizeIntent($message);
        
        // Step 2: Select appropriate tools
        $tools = yield $this->selectTools($intent);
        
        // Step 3: Execute tools with compensation
        $results = yield $this->executeToolsWithCompensation($tools, $intent);
        
        // Step 4: Generate response
        $response = yield $this->generateResponse($results, $intent);
        
        // Step 5: Record interaction
        yield $this->recordInteraction($conversationId, $message, $response);
        
        return $response;
    }
    
    private function recognizeIntent(string $message): \Generator
    {
        $activity = new IntentRecognitionActivity();
        
        $result = yield $activity->recognize($message, $this->context);
        
        // Check confidence threshold
        if ($result['confidence'] < $this->confidenceThreshold) {
            // Request human intervention
            yield $this->requestHumanClarification($message, $result);
        }
        
        return $result;
    }
    
    private function selectTools(array $intent): \Generator
    {
        $activity = new ToolSelectionActivity();
        
        return yield $activity->select([
            'intent' => $intent,
            'context' => $this->context,
            'user_permissions' => $this->getUserPermissions(),
        ]);
    }
    
    private function executeToolsWithCompensation(
        array $tools,
        array $intent
    ): \Generator {
        $results = [];
        $executedTools = [];
        
        try {
            foreach ($tools as $tool) {
                // Execute tool
                $result = yield $this->executeTool($tool, $intent);
                $results[] = $result;
                $executedTools[] = $tool;
                
                // Check if continuation is needed
                if ($this->shouldStop($result)) {
                    break;
                }
            }
        } catch (\Exception $e) {
            // Compensate for failed tools
            yield $this->compensate($executedTools, $e);
            throw $e;
        }
        
        return $results;
    }
    
    private function compensate(array $executedTools, \Exception $error): \Generator
    {
        // Reverse executed operations
        foreach (array_reverse($executedTools) as $tool) {
            yield $this->reverseToolExecution($tool);
        }
        
        // Log compensation
        yield $this->logCompensation($executedTools, $error);
    }
}
```

### Step 2: Create Agent Activities

Activities are the building blocks of agent workflows:

```php
namespace App\Domain\AI\Activities;

use App\Domain\AI\Services\NLPService;
use App\Domain\AI\Events\IntentRecognizedEvent;

class IntentRecognitionActivity
{
    public function __construct(
        private NLPService $nlpService
    ) {}
    
    public function recognize(string $message, array $context): array
    {
        // Analyze message
        $analysis = $this->nlpService->analyze($message);
        
        // Extract intent
        $intent = $this->extractIntent($analysis, $context);
        
        // Record event
        event(new IntentRecognizedEvent(
            message: $message,
            intent: $intent['type'],
            confidence: $intent['confidence'],
            entities: $intent['entities']
        ));
        
        return $intent;
    }
    
    private function extractIntent(array $analysis, array $context): array
    {
        // Pattern matching for banking intents
        $patterns = [
            '/loan|borrow|credit/i' => 'loan_inquiry',
            '/balance|account/i' => 'balance_check',
            '/transfer|send|pay/i' => 'payment_intent',
            '/invest|portfolio/i' => 'investment_advice',
        ];
        
        foreach ($patterns as $pattern => $intentType) {
            if (preg_match($pattern, $analysis['text'])) {
                return [
                    'type' => $intentType,
                    'confidence' => $this->calculateConfidence($analysis),
                    'entities' => $this->extractEntities($analysis),
                ];
            }
        }
        
        return [
            'type' => 'unknown',
            'confidence' => 0.0,
            'entities' => []
        ];
    }
}
```

### Step 3: Implement Agent Services

Create specialized services for agent functionality:

```php
namespace App\Domain\AI\Services;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\AIDecisionMadeEvent;

class LoanAdvisorService
{
    public function assessLoanEligibility(array $params): array
    {
        // Create aggregate
        $aggregate = AIInteractionAggregate::retrieve($params['interaction_id']);
        
        // Perform assessment
        $assessment = $this->performAssessment($params);
        
        // Record decision
        $aggregate->recordDecision(
            decision: 'loan_eligibility_assessed',
            confidence: $assessment['confidence'],
            factors: $assessment['factors'],
            recommendation: $assessment['recommendation']
        );
        
        // Persist aggregate
        $aggregate->persist();
        
        return $assessment;
    }
    
    private function performAssessment(array $params): array
    {
        // Credit score check
        $creditScore = $this->checkCreditScore($params['user_id']);
        
        // Income verification
        $incomeVerified = $this->verifyIncome($params['user_id']);
        
        // Debt-to-income ratio
        $dtiRatio = $this->calculateDTI($params['user_id']);
        
        // Calculate eligibility
        $eligible = $creditScore > 650 && $incomeVerified && $dtiRatio < 0.4;
        
        return [
            'eligible' => $eligible,
            'confidence' => $this->calculateConfidence($creditScore, $dtiRatio),
            'factors' => [
                'credit_score' => $creditScore,
                'income_verified' => $incomeVerified,
                'dti_ratio' => $dtiRatio,
            ],
            'recommendation' => $eligible 
                ? 'Proceed with loan application'
                : 'Improve credit score or reduce debt',
        ];
    }
}
```

### Step 4: Configure Human-in-the-Loop

Define when human intervention is required:

```php
namespace App\Domain\AI\Policies;

class HumanInterventionPolicy
{
    private array $thresholds = [
        'loan_approval' => [
            'amount' => 50000,  // Require human approval for loans > $50k
            'confidence' => 0.7,  // Require human review if confidence < 70%
        ],
        'investment_advice' => [
            'amount' => 100000,  // Require human approval for investments > $100k
            'risk_level' => 'high',  // Require human review for high-risk investments
        ],
    ];
    
    public function requiresHumanApproval(
        string $operation,
        array $params
    ): bool {
        if (!isset($this->thresholds[$operation])) {
            return false;
        }
        
        $threshold = $this->thresholds[$operation];
        
        // Check amount threshold
        if (isset($threshold['amount']) && 
            isset($params['amount']) &&
            $params['amount'] > $threshold['amount']) {
            return true;
        }
        
        // Check confidence threshold
        if (isset($threshold['confidence']) &&
            isset($params['confidence']) &&
            $params['confidence'] < $threshold['confidence']) {
            return true;
        }
        
        // Check risk level
        if (isset($threshold['risk_level']) &&
            isset($params['risk_level']) &&
            $params['risk_level'] === $threshold['risk_level']) {
            return true;
        }
        
        return false;
    }
}
```

### Step 5: Register the Agent

Register your agent in the service provider:

```php
namespace App\Providers;

use App\Domain\AI\Registry\AgentRegistry;
use App\Domain\AI\Workflows\LoanAdvisorAgentWorkflow;

class AIAgentServiceProvider extends ServiceProvider
{
    public function boot(AgentRegistry $registry): void
    {
        $registry->register(
            name: 'loan_advisor',
            workflow: LoanAdvisorAgentWorkflow::class,
            config: [
                'description' => 'Provides loan advice and eligibility assessment',
                'capabilities' => [
                    'loan_eligibility_check',
                    'interest_rate_calculation',
                    'repayment_planning',
                    'document_requirements',
                ],
                'confidence_threshold' => 0.8,
                'requires_auth' => true,
                'max_interactions_per_day' => 100,
            ]
        );
    }
}
```

## Testing Agents

### Unit Testing

Test individual agent components:

```php
namespace Tests\Unit\AI\Agents;

use Tests\TestCase;
use App\Domain\AI\Activities\IntentRecognitionActivity;

class IntentRecognitionActivityTest extends TestCase
{
    /** @test */
    public function it_recognizes_loan_intent(): void
    {
        $activity = app(IntentRecognitionActivity::class);
        
        $result = $activity->recognize(
            'I want to apply for a home loan',
            []
        );
        
        $this->assertEquals('loan_inquiry', $result['type']);
        $this->assertGreaterThan(0.7, $result['confidence']);
        $this->assertArrayHasKey('entities', $result);
    }
}
```

### Integration Testing

Test complete agent workflows:

```php
namespace Tests\Feature\AI\Agents;

use Tests\TestCase;
use App\Domain\AI\Workflows\LoanAdvisorAgentWorkflow;
use Workflow\WorkflowStub;

class LoanAdvisorAgentTest extends TestCase
{
    /** @test */
    public function it_processes_loan_inquiry_workflow(): void
    {
        // Create workflow stub
        $workflow = WorkflowStub::make(LoanAdvisorAgentWorkflow::class);
        
        // Execute workflow
        $result = $workflow->execute(
            message: 'I need a $10,000 personal loan',
            conversationId: 'conv_123',
            userId: 1,
            context: []
        );
        
        // Assert results
        $this->assertArrayHasKey('response', $result);
        $this->assertArrayHasKey('tools_used', $result);
        $this->assertArrayHasKey('confidence', $result);
        
        // Verify events were recorded
        Event::assertDispatched(IntentRecognizedEvent::class);
        Event::assertDispatched(ToolExecutedEvent::class);
        Event::assertDispatched(AIDecisionMadeEvent::class);
    }
}
```

## Agent Types

### Customer Service Agent
Handles general inquiries and support:
- Account information
- Transaction queries
- Basic troubleshooting
- FAQ responses

### Compliance Agent
Ensures regulatory compliance:
- KYC verification
- AML screening
- Transaction monitoring
- Suspicious activity reporting

### Risk Assessment Agent
Evaluates various risks:
- Credit risk assessment
- Market risk analysis
- Operational risk evaluation
- Fraud detection

### Trading Agent
Manages trading operations:
- Market analysis
- Trade execution
- Portfolio optimization
- Strategy recommendations

### Investment Advisor Agent
Provides investment guidance:
- Portfolio analysis
- Asset allocation
- Risk profiling
- Performance reporting

## Multi-Agent Coordination

Agents can collaborate on complex tasks:

```php
class MultiAgentCoordinator
{
    public function coordinate(string $task, array $agents): array
    {
        // Determine lead agent
        $leadAgent = $this->selectLeadAgent($task, $agents);
        
        // Delegate subtasks
        $subtasks = $this->delegateSubtasks($task, $agents);
        
        // Execute in parallel
        $results = $this->executeParallel($subtasks);
        
        // Aggregate results
        return $this->aggregateResults($results, $leadAgent);
    }
    
    private function selectLeadAgent(string $task, array $agents): string
    {
        // Score agents based on capability match
        $scores = [];
        foreach ($agents as $agent) {
            $scores[$agent] = $this->scoreAgentCapability($agent, $task);
        }
        
        // Return highest scoring agent
        return array_key_first(array_reverse($scores, true));
    }
}
```

## Performance Optimization

### Caching Strategies
- Cache intent recognition results
- Cache tool selection decisions
- Cache frequently accessed context

### Async Processing
- Use queues for long-running operations
- Process multiple tools in parallel
- Stream responses for better UX

### Resource Management
- Pool external API connections
- Implement circuit breakers
- Monitor token usage

## Security Best Practices

1. **Authentication**: Verify user identity before agent interaction
2. **Authorization**: Check permissions for each tool execution
3. **Audit Trail**: Log all decisions and tool executions
4. **Data Privacy**: Isolate conversation data per user
5. **Rate Limiting**: Prevent abuse with interaction limits
6. **Encryption**: Encrypt sensitive conversation data

## Next Steps

- [Workflow Development](03-Workflows.md)
- [Event Sourcing Patterns](04-Event-Sourcing.md)
- [API Reference](05-API-Reference.md)