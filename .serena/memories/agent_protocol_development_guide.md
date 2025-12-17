# Agent Protocol Development Guide

## Overview
This guide documents best practices for developing and testing Agent Protocol domain components in the FinAegis platform.

## Key Aggregates and Their Usage

### AgentComplianceAggregate
Used for KYC/AML compliance tracking for agents.

**Creating a new aggregate:**
```php
// CORRECT - uses agentId as the aggregate UUID for later retrieval
$aggregate = AgentComplianceAggregate::initiateKyc(
    agentId: $agentId,        // This becomes the aggregate UUID
    agentDid: $agentDid,
    level: KycVerificationLevel::BASIC,
    requiredDocuments: ['government_id']
);
$aggregate->persist();

// Later, retrieve by the same agentId
$aggregate = AgentComplianceAggregate::retrieve($agentId);
```

### AgentWalletAggregate
Used for managing agent wallet balances and transactions.

**Payment flow (two-phase):**
```php
// 1. Initiate payment (holds funds, doesn't debit)
$wallet->initiatePayment(
    transactionId: $txnId,
    toAgentId: $recipientDid,
    amount: 100.00,
    type: 'transfer'
);

// 2. Complete payment (actually debits balance)
$wallet->completePayment(
    transactionId: $txnId,
    amount: 100.00,
    toAgentId: $recipientDid
);
$wallet->persist();
```

**For immediate settlement (like fees):**
```php
// Call both initiate and complete in sequence
$wallet->initiatePayment(...);
$wallet->completePayment(...);
$wallet->persist();
```

### AgentIdentityAggregate
Used for agent registration and status tracking.

**Note:** A fresh aggregate (no events) will have `isActive()` return `false`. Always register and activate agents before testing interactions.

## Testing Patterns

### Unit Tests for Activities
For unit tests that don't need full aggregate setup, use the `basicValidationOnly` option:

```php
// Unit test - skip aggregate checks
$result = $activity->execute($request, ['basicValidationOnly' => true]);
$this->assertTrue($result->isValid);

// Integration test - full validation with aggregates
$result = $activity->execute($request);
```

### Setting Up Test Data
```php
protected function setUp(): void
{
    parent::setUp();
    
    // Initialize wallet with balance
    $wallet = AgentWalletAggregate::retrieve($this->senderDid);
    $wallet->receivePayment(
        transactionId: 'init-' . Str::uuid()->toString(),
        fromAgentId: 'did:agent:test:system',
        amount: 1000.00,
        metadata: ['type' => 'initial_deposit']
    );
    $wallet->persist();
}
```

### Workflow Activities
Activities require a StoredWorkflow context:
```php
$workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
$storedWorkflow = StoredWorkflow::findOrFail($workflow->id());

$activity = new ValidatePaymentActivity(
    0,                              // index
    now()->toDateTimeString(),      // now
    $storedWorkflow,                // storedWorkflow
    $request                        // arguments
);
```

## Event Serialization
The `TestEventSerializer` handles PHP 8.1+ enums:
- Backed enums: serialized to backing value, deserialized with `from()`
- Unit enums: serialized to name, deserialized with `cases()` lookup

## DID Formats
The system accepts multiple DID formats:
```
did:finaegis:key:abc123...        # Production format
did:agent:test:sender             # Test format
did:example:xyz123                # General format
```

Regex pattern: `/^did:[a-z]+:[a-z0-9]+(?::[a-zA-Z0-9_-]+)*$/`

## Validation Result Structure
The `ValidatePaymentActivity` returns:
```php
stdClass {
    isValid: bool,
    errors: array,          // Array of error messages
    warnings: array,        // Non-blocking warnings
    validatedAt: Carbon,    // Timestamp of validation
    escrowRequirements: array  // For escrow payments
}
```

## Fee Application
The `ApplyFeesActivity` handles fee collection:
- Uses both `initiatePayment()` and `completePayment()` for immediate settlement
- Credits fee collector via `receivePayment()`
- Supports fee reversals for cancelled transactions

## Common Gotchas

1. **Aggregate UUID mismatch**: Always use the same ID for creating and retrieving aggregates
2. **Balance not debiting**: Ensure `completePayment()` is called after `initiatePayment()`
3. **Fresh aggregates inactive**: New aggregates have default status, not 'active'
4. **Unit test failures**: Use `basicValidationOnly` to skip aggregate checks in unit tests
5. **Enum serialization**: The TestEventSerializer handles enums automatically
