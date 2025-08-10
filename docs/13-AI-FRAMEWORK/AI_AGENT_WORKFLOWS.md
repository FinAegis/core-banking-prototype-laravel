# AI Agent Workflows Documentation

## Overview

The FinAegis AI Agent Framework includes sophisticated workflow implementations that enable AI agents to handle complex banking operations with full event sourcing, compensation support, and human-in-the-loop capabilities.

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
- `check_kyc_status` → compliance.kyc_status tool

### 2. ComplianceWorkflow

**Purpose**: Comprehensive compliance checking including KYC, AML, and transaction monitoring.

**Location**: `app/Domain/AI/Workflows/ComplianceWorkflow.php`

**Key Features**:
- Multi-step compliance verification
- Saga pattern with compensation support
- Integration with compliance services
- Regulatory reporting automation
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

All workflows integrate with the `AIInteractionAggregate` for complete audit trail:

```php
$aggregate = AIInteractionAggregate::retrieve($conversationId);
$aggregate->startConversation($conversationId, $agentType, $userId, $context);
$aggregate->makeDecision($decision, $metadata, $confidence, $requiresHumanReview);
$aggregate->endConversation($result);
$aggregate->persist();
```

## Saga Pattern Implementation

Workflows implement the saga pattern for multi-step operations:

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

All AI decisions include confidence scores:

```php
$confidence = match ($type) {
    'low_risk' => 0.9,   // High confidence
    'high_risk' => 0.9,  // High confidence
    'medium_risk' => 0.6 // Lower confidence, may need human review
};

$aggregate->makeDecision($decision, $metadata, $confidence, $confidence < 0.7);
```

## Human-in-the-Loop

Low confidence decisions trigger human review:

```php
if ($intent['confidence'] < 0.7) {
    yield $this->requestHumanApproval($intent);
}
```

## Service Integration

Workflows integrate with existing domain services:

- `KycService` - KYC verification
- `AmlScreeningService` - AML checks
- `TransactionMonitoringService` - Pattern detection
- `RegulatoryReportingService` - Report generation
- `DefaultRiskAssessmentService` - Risk scoring

## Testing

All workflows are designed for testability:

```php
// Test workflow execution
$workflow = new ComplianceWorkflow($kycService, $amlService, $reportingService);
$result = iterator_to_array($workflow->execute($conversationId, $userId, 'kyc', $parameters));

// Verify event sourcing
$this->assertDatabaseHas('ai_interaction_events', [
    'aggregate_uuid' => $conversationId,
    'event_type' => 'ConversationStarted'
]);
```

## Configuration

Workflows can be configured via environment variables:

```env
# AI Agent Configuration
AI_CONFIDENCE_THRESHOLD=0.7
AI_HUMAN_REVIEW_REQUIRED=true
AI_MAX_CONVERSATION_DURATION=3600
AI_ENABLE_CACHING=true
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

### Phase 4 Plans:
- Trading Agent with market analysis
- Multi-Agent coordination
- Advanced NLP processing
- Real-time learning and adaptation
- Cross-agent communication protocols