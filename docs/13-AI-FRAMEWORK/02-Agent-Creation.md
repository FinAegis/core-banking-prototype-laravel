# Creating Custom AI Agents

## Overview

An AI agent in FinAegis handles one area of questions, such as balances, transfers or compliance lookups. It reads the question, calls tools from `ToolRegistry` and writes an answer. Its decisions can be stored as events, so they can be reviewed later.

There are two ways to build an agent.

- A simple agent extends `App\Domain\AI\Agents\BaseAgent` and is registered with `AgentRouterService`. The five built-in agents (`FinancialAgent`, `TransferAgent`, `TradingAgent`, `ComplianceAgent`, `GeneralAgent`) work this way. Step 5 shows the registration.
- A workflow agent is a Laravel Workflow class with activities, compensation and human approval. `CustomerServiceWorkflow` in `app/Domain/AI/Workflows/` is a real example.

The steps below build a loan advisor as a workflow agent. `LoanAdvisorAgentWorkflow`, `ExecutionActivity`, `ResponseGenerationActivity`, `NLPService`, `LoanAdvisorService` and `HumanInterventionPolicy` are example classes that you would write. They are not in the codebase. `IntentRecognitionActivity`, `ToolSelectionActivity` and `AIInteractionAggregate` do exist.

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

A simple agent extends `BaseAgent` and declares its name, description, keywords and tool names. Register it in `App\Providers\AgentServiceProvider`, next to the built-in agents:

```php
namespace App\Providers;

use App\Domain\AI\Agents\LoanAdvisorAgent; // your class, extends BaseAgent
use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\Services\AgentRouterService;
use App\Domain\AI\Services\LLMOrchestrationService;

$this->app->singleton(AgentRouterService::class, function ($app) {
    $router = new AgentRouterService();
    $toolRegistry = $app->make(ToolRegistry::class);
    $llmService = $app->make(LLMOrchestrationService::class);

    // ... built-in agents ...
    $router->registerAgent(new LoanAdvisorAgent($toolRegistry, $llmService));

    return $router;
});
```

The router scores each agent by its keywords and sends the question to the best matches. A workflow agent is started with `WorkflowStub::make(...)` instead and needs no registration.

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

These agents are registered by default:

| Agent | Handles |
|---|---|
| `FinancialAgent` | Balances, transactions and spending questions |
| `TransferAgent` | Payment and transfer requests |
| `TradingAgent` | Exchange quotes and market questions (example template; not investment advice) |
| `ComplianceAgent` | KYC and AML lookups that support an operator's compliance process |
| `GeneralAgent` | Questions that no other agent matches |

Risk assessment is not an agent. It runs as the `RiskAssessmentSaga` workflow.

## Multi-Agent Coordination

Agents can work on one task together. The code below is a simplified example. The real implementation is `MultiAgentCoordinationService::coordinateTask()`.

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

### Caching

Cache intent recognition results, tool selection decisions and context that is read often. Tools that return `isCacheable() === true` are cached by the internal MCP server.

### Async processing

Run long operations on queues or as workflows. Independent tool calls can run in parallel.

### Resource management

Reuse connections to external APIs and add circuit breakers around them. Watch language model token usage, which is recorded in the `ai_llm_usage` table.

## Security Best Practices

1. Verify the user's identity before the agent acts.
2. Check permissions for every tool call. Each tool has an `authorize()` method for this.
3. Record every decision and tool call.
4. Keep each user's conversations separate.
5. Limit how often a user can call the agent.
6. Encrypt sensitive conversation data.

## Next Steps

- [Workflow Development](03-Workflows.md)
- [Event Sourcing Patterns](04-Event-Sourcing.md)
- [API Reference](05-API-Reference.md)