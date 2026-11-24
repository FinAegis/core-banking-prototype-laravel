# AI Agent Workflows Documentation

## Overview

The AI agent framework has three main workflows in `app/Domain/AI/Workflows/`. They let agents handle multi-step operations. Each workflow records its steps as events, can undo completed steps when a later one fails and can ask a person to approve a decision.

## Implemented Workflows

### 1. CustomerServiceWorkflow

**Purpose**: Handles customer inquiries and service requests with natural language understanding.

**Location**: `app/Domain/AI/Workflows/CustomerServiceWorkflow.php`

**Key Features**:
- Natural language query processing
- Intent classification with confidence scoring
- Tool mapping for banking operations
- Human approval for low-confidence decisions
- Full conversation tracking via event sourcing

**Workflow Steps**:
1. Initialize conversation in event store
2. Validate context and user permissions
3. Process and understand the query
4. Classify intent with confidence scoring
5. Execute appropriate tool based on intent
6. Generate natural language response
7. Record interaction in event store

**Intent Mapping**:
- `check_balance` → account.balance tool
- `transfer_funds` → payment.transfer tool
- `exchange_quote` → exchange.quote tool
- `check_kyc_status` → `compliance.kyc_status` tool (the registry has no tool by this name; the registered KYC tool is `compliance.kyc`)

The workflow does not call these tools yet. `executeToolForIntent()` returns a simulated result for every mapped intent.

### 2. ComplianceWorkflow

**Purpose**: Comprehensive compliance checking including KYC, AML, and transaction monitoring.

**Location**: `app/Domain/AI/Workflows/ComplianceWorkflow.php`

**Key Features**:
- Multi-step compliance verification
- Saga pattern with compensation support
- Simplified demo checks that stand in for the compliance services
- Regulatory report drafts for an operator to review
- Alert generation for suspicious activities

**Workflow Steps**:
1. Initialize compliance check in event store
2. Validate user and permissions
3. Execute compliance check based on type:
   - KYC verification
   - AML screening
   - Transaction monitoring
   - Regulatory reporting
4. Record compliance decision
5. Generate compliance report if needed
6. Trigger alerts if issues found

**Compensation Support**:
- Rollback KYC verification status
- Clear AML flags for blocked transactions
- Cancel regulatory reports not yet submitted

### 3. RiskAssessmentSaga

**Purpose**: Multi-dimensional risk analysis including credit, fraud, and portfolio assessment.

**Location**: `app/Domain/AI/Workflows/RiskAssessmentSaga.php`

**Key Features**:
- Comprehensive risk scoring
- Behavioral pattern analysis
- Composite risk calculation
- Alert generation with thresholds
- Mitigation recommendations
- Full saga pattern implementation

**Assessment Types**:
- **Credit Risk**: Credit scoring, DTI ratio, affordability analysis
- **Fraud Risk**: Transaction patterns, velocity checks, anomaly detection
- **Portfolio Risk**: Diversification, concentration, Value at Risk (VaR)
- **Comprehensive**: All assessments combined

**Workflow Steps**:
1. Initialize risk assessment in event store
2. Load user and financial data
3. Execute risk assessment based on type
4. Analyze behavioral patterns
5. Calculate composite risk score
6. Generate risk alerts if thresholds exceeded
7. Record risk decision with confidence
8. Generate mitigation recommendations

**Risk Scoring**:
- Weighted composite scoring
- Confidence-based decision making
- Automatic alert triggering
- Human review requirements

## Event Sourcing Integration

Each workflow records its steps through `AIInteractionAggregate`, which keeps the audit trail.

```php
$aggregate = AIInteractionAggregate::retrieve($conversationId);
$aggregate->startConversation($conversationId, $agentType, $userId, $context);
$aggregate->makeDecision($decision, $metadata, $confidence, $requiresHumanReview);
$aggregate->endConversation($result);
$aggregate->persist();
```

## Saga Pattern Implementation

Workflows use the saga pattern for multi-step operations. When a step fails, the completed steps are undone in reverse order. The code below is simplified.

```php
class RiskAssessmentSaga extends Workflow
{
    private array $compensationActions = [];
    
    public function execute(): \Generator {
        try {
            // Multi-step workflow execution
            yield $step1;
            yield $step2;
            // ...
        } catch (\Exception $e) {
            // Automatic compensation
            yield $this->compensate();
        }
    }
    
    public function compensate(): bool {
        // Rollback actions in reverse order
        foreach (array_reverse($this->compensationActions) as $action) {
            // Compensate each action
        }
    }
}
```

## Confidence Scoring

Every AI decision carries a confidence score.

```php
$confidence = match ($type) {
    'low_risk' => 0.9,   // High confidence
    'high_risk' => 0.9,  // High confidence
    'medium_risk' => 0.6 // Lower confidence, may need human review
};

$aggregate->makeDecision($decision, $metadata, $confidence, $confidence < 0.7);
```

## Human-in-the-Loop

A decision with low confidence goes to a person for review.

```php
if ($intent['confidence'] < 0.7) {
    yield $this->requestHumanApproval($intent);
}
```

## Service Integration

The workflows contain simplified demo logic. They do not call the domain services yet, although `ComplianceWorkflow` imports `KycService` and `AmlScreeningService`. These services hold the real logic that a production version would call:

| Service | Purpose |
|---|---|
| `App\Domain\Compliance\Services\KycService` | KYC verification |
| `App\Domain\Compliance\Services\AmlScreeningService` | AML checks |
| `App\Domain\Compliance\Services\TransactionMonitoringService` | Pattern detection |
| `App\Domain\AgentProtocol\Services\RegulatoryReportingService` | Report drafts |
| `App\Domain\Lending\Services\DefaultRiskAssessmentService` | Risk scoring |

## Testing

The workflows can be run directly in tests:

```php
// Test workflow execution
$workflow = new ComplianceWorkflow();
$result = iterator_to_array($workflow->execute($conversationId, $userId, 'kyc', $parameters));

// Verify event sourcing (events go to the default stored_events table)
$this->assertDatabaseHas('stored_events', [
    'aggregate_uuid' => $conversationId,
]);
```

## Configuration

`AI_CONFIDENCE_THRESHOLD` (default 0.7) sets the confidence below which `AIInteractionAggregate::makeDecision()` asks for human review. The other AI settings are in `config/ai.php`.

```env
# AI Agent Configuration
AI_CONFIDENCE_THRESHOLD=0.7
AI_HIGH_CONFIDENCE_THRESHOLD=0.9
AI_LOW_CONFIDENCE_THRESHOLD=0.5
AI_CONVERSATION_TTL=86400
```

## Usage Examples

### Customer Service
```php
$workflow = app(CustomerServiceWorkflow::class);
$result = iterator_to_array($workflow->execute(
    conversationId: 'conv_123',
    query: 'What is my account balance?',
    userId: 'user_456'
));
```

### Compliance Check
```php
$workflow = app(ComplianceWorkflow::class);
$result = iterator_to_array($workflow->execute(
    conversationId: 'conv_789',
    userId: 'user_456',
    complianceType: 'kyc',
    parameters: ['documents' => [...]]
));
```

### Risk Assessment
```php
$saga = app(RiskAssessmentSaga::class);
$result = iterator_to_array($saga->execute(
    conversationId: 'conv_abc',
    userId: 'user_456',
    assessmentType: 'credit',
    parameters: ['loan_amount' => 50000]
));
```

## Future Enhancements

Phase 4 added the trading agent workflow and multi-agent coordination. [ADVANCED_AI_FEATURES.md](ADVANCED_AI_FEATURES.md) describes them. Better language processing, learning from past decisions and protocols for agents to talk to each other are not built yet.