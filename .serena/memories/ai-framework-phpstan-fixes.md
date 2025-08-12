# AI Framework PHPStan Test Fixes

## Context
The AI Framework has comprehensive test specifications in tests/Unit/AI/ that define the expected behavior. Some test methods reference classes and methods that are not yet implemented - this is intentional as these tests serve as specifications for future implementation.

## Missing Classes/Events to Create

### Events (app/Domain/AI/Events/)
1. **HumanInterventionRequestedEvent** - Triggered when confidence is low
2. **IntentRecognizedEvent** - When intent is classified  
3. **CompensationExecutedEvent** - After compensation runs
4. **HumanApprovalReceivedEvent** - Human approves action

### Activities (app/Domain/AI/Activities/)
1. **IntentRecognitionActivity** - Recognizes user intent
2. **ToolSelectionActivity** - Selects appropriate tools

### Services (app/Domain/AI/Services/)
1. **MultiAgentCoordinator** - Already exists as MultiAgentCoordinationService

### Workflows (app/Domain/AI/Workflows/)
1. **HumanApprovalWorkflow** - Human-in-the-loop workflow
2. **Children/FraudDetectionWorkflow** - Child workflow for fraud detection

### Signals (app/Domain/AI/Signals/)
1. **HumanApprovalSignal** - Signal for human approval

## Test Fixes Required

### AIInteractionAggregateTest.php
- Lines 117-120: Create HumanInterventionRequestedEvent class

### AgentWorkflowTest.php
- Lines 39, 47: Create IntentRecognitionActivity and ToolSelectionActivity
- Line 62: Create IntentRecognizedEvent
- Line 115: Fix RiskAssessmentSaga::execute() call - needs 4 params: conversationId, userId, assessmentType, parameters
- Lines 118-128: The execute() method returns a Generator, need to convert to array
- Line 136: Create Children/FraudDetectionWorkflow
- Lines 199, 228: Create missing events
- Line 215: Fix Mockery syntax - use ->andThrow() not ->andThrow()
- Lines 243-244: MultiAgentCoordinator should be MultiAgentCoordinationService
- Lines 263, 271, 285: Create HumanApprovalWorkflow and related classes

### MCPServerTest.php
- Line 144: Use Cache facade spy instead of shouldHaveReceived
- Line 181: Fix Mockery syntax

## PHPStan Baseline
These errors are expected and documented in phpstan-baseline-ai-tests.neon. The test files serve as specifications for Phase 4 implementation.