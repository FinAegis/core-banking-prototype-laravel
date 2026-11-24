# Advanced AI Features Documentation

## Phase 4: Advanced AI Agent Features

This document describes the advanced features that Phase 4 added to the AI agent framework. They are a trading workflow, multi-agent coordination and human approval of AI decisions. The code is reference code. The trading features are an example template and not investment advice, and trading is off in production (`TRADING_ENABLED=false`).

## Table of Contents
1. [Trading Agent Workflow](#trading-agent-workflow)
2. [Multi-Agent Coordination](#multi-agent-coordination)
3. [Human-in-the-Loop Workflow](#human-in-the-loop-workflow)
4. [Integration Patterns](#integration-patterns)
5. [Usage Examples](#usage-examples)

## Trading Agent Workflow

`TradingAgentWorkflow` analyzes a market, proposes trading strategies and can place an order. It is a demonstration. Its market analysis runs on simulated prices and volumes generated inside the workflow, unless the caller passes its own `prices` and `volumes`. It does not read a live market feed.

### Features
- Market analysis finds trends in the price series.
- Technical analysis calculates RSI, MACD and moving averages and looks for patterns.
- Strategy generation proposes momentum, mean reversion and risk management strategies.
- Portfolio optimization suggests how to rebalance holdings.
- Risk assessment calculates Value at Risk (VaR), the Sharpe ratio and drawdown.

### Workflow Steps
1. The `MarketAnalysisWorkflow` child workflow analyzes the market and calculates the technical indicators.
2. The `StrategyGenerationWorkflow` child workflow proposes strategies and rates the risk of each one.
3. If the recommended strategy has a confidence of at least 0.7 and its action is not hold or wait, `TradingExecutionSaga` places an order through `OrderService`.
4. The workflow returns the analysis, the strategies and the execution result.

### Example Usage

```php
use App\Domain\AI\Workflows\TradingAgentWorkflow;

$workflow = new TradingAgentWorkflow();

$result = iterator_to_array($workflow->execute(
    conversationId: 'conv_123',
    userId: 'user_456',
    operation: 'trading_execution',
    parameters: [
        'auto_execute' => false,
        'max_order_value' => 10000,
        'timeframe' => '4h',
        'include_risk' => true,
    ]
));

// Result includes:
// - Market analysis with recommendations
// - Technical indicators and signals
// - Trading strategies with confidence scores
// - Risk assessment metrics
// - Portfolio optimization suggestions
// - Execution plan with stop-loss/take-profit levels
```

### Configuration

The 0.7 confidence threshold is hard-coded in `TradingAgentWorkflow::shouldExecuteTrade()`. The workflow does not read the `auto_execute` or `max_order_value` parameters shown in the example above.

## Multi-Agent Coordination

`MultiAgentCoordinationService` lets several agents work on one task.

### Features
- Agents are registered with their capabilities, and the service finds the agents that can handle a task.
- A task is split into subtasks, and each subtask goes to the best-suited agent.
- Agents can pass messages and share information.
- When the answers differ, the service builds a consensus by weighted voting.
- The service tracks each agent's load and spreads work across them.

### Registered agents

The service keeps its own list of four agent types. They are separate from the five agents that `AgentRouterService` uses for chat.

| Agent type | Capabilities |
|---|---|
| `customer_service` | Query handling, account operations, basic support |
| `compliance` | KYC verification, AML screening, regulatory reporting |
| `risk_assessment` | Credit scoring, fraud detection, portfolio risk |
| `trading` | Market analysis, trading strategy, portfolio optimization |

### Coordination Patterns

#### Single Agent Task
```php
use App\Domain\AI\Services\MultiAgentCoordinationService;

$coordinator = new MultiAgentCoordinationService();

$result = $coordinator->coordinateTask(
    taskId: 'task_001',
    taskType: 'customer_query',
    parameters: ['query' => 'Check my balance'],
    user: $user
);
```

#### Multi-Agent Task with Consensus
```php
$result = $coordinator->coordinateTask(
    taskId: 'task_002',
    taskType: 'comprehensive_risk_assessment',
    parameters: [
        'require_multi_agent' => true,
        'loan_amount' => 50000,
    ],
    user: $user
);

// Result includes:
// - Results from multiple agents (credit, fraud, market risk)
// - Consensus decision with confidence score
// - Inter-agent communications
// - Conflict resolutions if any
```

### Conflict Resolution

When agents disagree, the service resolves the conflict in one of two ways. Weighted voting weighs each answer by the agent's confidence score. The highest-confidence rule takes the answer of the most confident agent. Conflicts that need a person go through the human-in-the-loop workflow below.

## Human-in-the-Loop Workflow

`HumanInTheLoopWorkflow` sends AI decisions to a person for approval when the decision is uncertain or the amount is large.

### Features
- Each operation type has its own confidence threshold.
- Operations above a value threshold always go to a person.
- A reviewer approves or rejects the decision in a structured request.
- Every decision and override is recorded.
- The reviewer's answer is kept as feedback for the AI.

The thresholds are class constants in `HumanInTheLoopWorkflow`.

### Confidence Thresholds

```php
const CONFIDENCE_THRESHOLDS = [
    'high_value_transaction' => 0.95,
    'account_closure'        => 0.90,
    'large_withdrawal'       => 0.85,
    'trading_execution'      => 0.80,
    'loan_approval'         => 0.75,
    'kyc_verification'      => 0.70,
    'general_operation'     => 0.65,
];
```

### Value Thresholds

```php
const VALUE_THRESHOLDS = [
    'transaction' => 10000,  // USD
    'withdrawal'  => 5000,
    'trading'     => 25000,
    'loan'        => 50000,
];
```

### Workflow Example

```php
use App\Domain\AI\Workflows\HumanInTheLoopWorkflow;

$workflow = new HumanInTheLoopWorkflow();

$result = iterator_to_array($workflow->execute(
    conversationId: 'conv_789',
    userId: 'user_123',
    operationType: 'high_value_transaction',
    aiDecision: [
        'recommendation' => 'approve',
        'confidence' => 0.82,
        'reasoning' => 'Transaction patterns match user history',
    ],
    parameters: [
        'value' => 15000,
        'urgency' => 'high',
    ]
));

// Result includes:
// - Final decision (approved/rejected)
// - Decision maker (ai_automatic/human)
// - Audit trail of all steps
// - Feedback for AI improvement
```

### Approval Request Structure

```php
[
    'id' => 'approval_xyz',
    'operation_type' => 'high_value_transaction',
    'ai_decision' => [...],
    'ai_confidence' => 0.82,
    'operation_value' => 15000,
    'risk_factors' => ['high_value', 'unusual_pattern'],
    'priority' => 'high',
    'status' => 'pending_review',
    'expires_at' => '2024-09-10T15:30:00Z',
]
```

## Integration Patterns

### Event Sourcing Integration

AI decisions are recorded in the event store:

```php
use App\Domain\AI\Aggregates\AIInteractionAggregate;

$aggregate = AIInteractionAggregate::retrieve($conversationId);

$aggregate->makeDecision(
    decision: 'trading_strategy_momentum',
    reasoning: ['strategies' => [...], 'analysis' => [...]],
    confidence: 0.75,
    requiresApproval: false
);

$aggregate->persist();
```

A decision below `AI_CONFIDENCE_THRESHOLD` (default 0.7) also records a `HumanInterventionRequestedEvent`.

### Saga Pattern Integration

Multi-step workflows use the saga pattern, so a failed step undoes the earlier ones. The code below is simplified.

```php
class TradingExecutionSaga extends Saga
{
    protected array $compensationMap = [
        'market_analysis' => 'rollback_analysis',
        'place_orders' => 'cancel_orders',
        'update_portfolio' => 'restore_portfolio',
    ];
}
```

### Service Integration

AI agents call the existing domain services. The code below is simplified.

```php
// Trading Agent uses Exchange services
$marketData = $this->marketDataService->getLatestPrices();
$orderResult = $this->orderMatchingService->placeOrder($order);

// Compliance Agent uses KYC/AML services
$kycResult = $this->kycService->verify($user);
$amlResult = $this->amlScreeningService->screen($transaction);
```

## Usage Examples

### Complete Trading Workflow

```php
// 1. Initialize trading agent
$tradingAgent = new TradingAgentWorkflow();

// 2. Perform market analysis
$analysis = iterator_to_array($tradingAgent->execute(
    'conv_001',
    'user_001',
    'market_analysis',
    ['assets' => ['BTC', 'ETH'], 'timeframe' => '1d']
));

// 3. If confidence is high, execute trades
if ($analysis['confidence'] > 0.7) {
    $execution = iterator_to_array($tradingAgent->execute(
        'conv_001',
        'user_001',
        'trading_execution',
        [
            'auto_execute' => true,
            'max_order_value' => 5000,
            'strategies' => $analysis['decision']['recommended_action'],
        ]
    ));
}
```

### Multi-Agent Risk Assessment

```php
// Complex risk assessment requiring multiple agents
$coordinator = new MultiAgentCoordinationService();

$riskAssessment = $coordinator->coordinateTask(
    'task_risk_001',
    'comprehensive_risk_assessment',
    [
        'loan_amount' => 100000,
        'user_history' => $userHistory,
        'require_multi_agent' => true,
    ],
    $user
);

// Check if consensus was reached
if ($riskAssessment['consensus_reached']) {
    $approvalDecision = $riskAssessment['aggregated_result'];
} else {
    // Escalate to human review
    $humanReview = new HumanInTheLoopWorkflow();
    $finalDecision = iterator_to_array($humanReview->execute(
        'conv_002',
        $user->id,
        'loan_approval',
        $riskAssessment,
        ['value' => 100000]
    ));
}
```

### Human Override Example

```php
$humanWorkflow = new HumanInTheLoopWorkflow();

// Override an AI decision manually
$override = $humanWorkflow->overrideDecision(
    approvalId: 'approval_123',
    decision: 'rejected',
    reviewer: 'senior_analyst_001',
    reason: 'Suspicious activity patterns detected during manual review'
);

// Get complete audit trail
$auditTrail = $humanWorkflow->getAuditTrail();
```

## Performance Considerations

### Optimization tips
1. Batch several analyses into one call where you can.
2. Cache the results of repeated queries.
3. Put circuit breakers around external services.
4. Run decisions that are not urgent asynchronously.

### Monitoring

Track how fast agents respond, how often they agree, how often people override them and how well the confidence scores match the outcomes.

## Security Considerations

### Authorization

Workflows run for an authenticated user, and tools check that user's permissions. High-value operations go to a person for approval.

### Audit trail

Every decision is recorded with a timestamp and says whether the AI or a person made it. The context is kept with it, and stored events are never changed.

### Data protection

Operators should encrypt sensitive data at rest and keep personal data out of the logs. The `/api/ai/*` endpoints are rate limited.

## Future Enhancements

These ideas are not scheduled. They are possible next steps.

- Train models on past decisions.
- Use stronger consensus algorithms between agents.
- Feed the trading workflow with live market data instead of simulated data.
- Forecast market movements.
- Draft regulatory reports automatically for review.
- Decentralized agent networks