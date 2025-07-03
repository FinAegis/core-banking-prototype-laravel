# CGO Refund Processing System

## Overview

The CGO Refund Processing System implements a comprehensive event-sourced workflow for handling refund requests on CGO investments. It follows domain-driven design principles with event sourcing, sagas, and temporal workflows.

## Architecture

### Event Sourcing Components

#### 1. Events
- `RefundRequested`: Initial refund request
- `RefundApproved`: Admin approval of refund
- `RefundRejected`: Admin rejection of refund
- `RefundProcessed`: Payment processor initiated refund
- `RefundCompleted`: Refund successfully completed
- `RefundFailed`: Refund processing failed
- `RefundCancelled`: Refund cancelled by user/admin

#### 2. Aggregate
- `RefundAggregate`: Manages refund state and business rules
- Enforces state transitions
- Validates business invariants

#### 3. Projector
- `RefundProjector`: Updates read model (`cgo_refunds` table)
- Handles all refund events
- Maintains denormalized view for queries

#### 4. Repository
- `CgoEventRepository`: Custom event repository for CGO domain
- Stores events in `cgo_events` table
- Extends `EloquentStoredEventRepository`

### Workflow Components

#### 1. Workflow
- `ProcessRefundWorkflow`: Orchestrates refund process
- Handles auto-approval logic
- Manages error handling and compensation

#### 2. Activities
- `InitiateRefundActivity`: Creates refund request
- `ApproveRefundActivity`: Approves refund
- `ProcessRefundActivity`: Processes with payment provider
- `CompleteRefundActivity`: Marks refund as complete
- `FailRefundActivity`: Handles failures

#### 3. Actions
- `RequestRefundAction`: Entry point for refund requests
- Validates eligibility
- Determines auto-approval
- Starts temporal workflow

### Data Model

#### CgoRefund Model
```php
- id (UUID)
- investment_id
- user_id
- amount
- currency
- reason
- reason_details
- status
- initiated_by
- approved_by
- approval_notes
- approved_at
- rejected_by
- rejection_reason
- rejected_at
- payment_processor
- processor_refund_id
- processor_status
- processor_response
- processed_at
- amount_refunded
- completed_at
- failure_reason
- failed_at
- cancellation_reason
- cancelled_by
- cancelled_at
- requested_at
- metadata
```

## Business Rules

### Refund Eligibility
1. Investment must be in 'confirmed' status
2. Payment must be 'completed'
3. Not already refunded
4. Within 90-day time limit
5. No active refund in progress

### Auto-Approval Rules
Refunds are automatically approved if:
1. Amount ≤ $100
2. Within 7-day grace period
3. Specific reasons: duplicate_payment, payment_error, system_error

### Status Flow
```
pending → approved → processing → completed
  ↓         ↓           ↓           
rejected  cancelled   failed
```

## Admin Interface

### Filament Resources

#### CgoRefundResource
- View all refund requests
- Approve/Reject/Cancel actions
- Detailed refund information
- Status filtering and search

#### CgoInvestmentResource Enhancement
- Added "Request Refund" action
- Shows refund eligibility
- Links to related refunds

### Dashboard Features
- Pending refunds count badge
- Status-based color coding
- Bulk action support
- Timeline view of refund history

## Payment Processing

### Stripe Refunds
- Automatic refund via Stripe API
- Uses original payment_intent_id
- Returns processor reference

### Crypto Refunds
- Manual process flagged
- Requires wallet address
- Admin verification needed

### Bank Transfer Refunds
- Manual process flagged
- Uses stored bank details
- Requires manual wire transfer

## Security Considerations

1. **Authorization**
   - Only investment owner can request refund
   - Admin approval required for large amounts
   - Audit trail for all actions

2. **Validation**
   - Amount cannot exceed original investment
   - Partial refunds tracked cumulatively
   - Business rule enforcement in aggregate

3. **Event Integrity**
   - Events are immutable
   - Full audit trail maintained
   - Event replay capability

## Testing

### Unit Tests
- `RefundAggregateTest`: Tests all state transitions
- Validates business rules
- Tests error conditions

### Feature Tests
- `RefundProcessingTest`: End-to-end workflows
- Tests auto-approval logic
- Validates relationships

## Configuration

### Event Class Map
Add to `config/event-sourcing.php`:
```php
'cgo_refund_requested' => App\Domain\Cgo\Events\RefundRequested::class,
'cgo_refund_approved' => App\Domain\Cgo\Events\RefundApproved::class,
'cgo_refund_rejected' => App\Domain\Cgo\Events\RefundRejected::class,
'cgo_refund_processed' => App\Domain\Cgo\Events\RefundProcessed::class,
'cgo_refund_completed' => App\Domain\Cgo\Events\RefundCompleted::class,
'cgo_refund_failed' => App\Domain\Cgo\Events\RefundFailed::class,
'cgo_refund_cancelled' => App\Domain\Cgo\Events\RefundCancelled::class,
```

### Queue Configuration
Refund events use `EventQueues::EVENTS` queue.

### Temporal Configuration
Refund workflows use `cgo-refunds` task queue.

## Usage Examples

### Request Refund
```php
$action = app(RequestRefundAction::class);
$result = $action->execute(
    investment: $investment,
    initiator: $user,
    reason: 'customer_request',
    reasonDetails: 'Changed my mind'
);
```

### Approve Refund (Admin)
```php
RefundAggregate::retrieve($refundId)
    ->approve(
        approvedBy: auth()->id(),
        approvalNotes: 'Approved per policy'
    )
    ->persist();
```

### Check Refund Status
```php
$refund = CgoRefund::find($refundId);
if ($refund->isCompleted()) {
    // Refund completed
}
```

## Monitoring

### Key Metrics
- Refund request rate
- Auto-approval percentage
- Average processing time
- Failure rate by payment method

### Alerts
- Failed refunds
- Stuck refunds (processing > 24h)
- High refund rate anomalies

## Future Enhancements

1. **Automated Crypto Refunds**
   - Integration with crypto payment providers
   - Automatic wallet validation
   - Real-time exchange rate handling

2. **Partial Refunds**
   - Support for partial investment refunds
   - Tier adjustment calculations
   - Certificate updates

3. **Refund Policies**
   - Configurable approval rules
   - Time-based refund fees
   - Tier-specific policies

4. **Customer Portal**
   - Self-service refund requests
   - Refund status tracking
   - Document downloads