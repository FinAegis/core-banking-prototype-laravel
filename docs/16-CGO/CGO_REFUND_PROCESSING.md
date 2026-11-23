# CGO Refund Processing System

> Concept and demo code for an investment-round workflow (tiers, payments, KYC, agreements, refunds). The CGO round is closed.

## Overview

The CGO refund system is an event-sourced workflow for refund requests on CGO investments. It uses an aggregate, a projector and a workflow with activities.

It is not reachable from the outside yet. No page, API route or admin action calls `RequestRefundAction`. The `Cgo` module is also switched off in production through `MODULES_DISABLED`.

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
`RefundAggregate` holds the refund state. It records the events above and refuses status changes that the rules do not allow.

#### 3. Projector
`RefundProjector` handles every refund event. It keeps the `cgo_refunds` table up to date for queries.

#### 4. Repository
`CgoEventRepository` is the event repository for the CGO domain. It extends `EloquentStoredEventRepository` and stores events in the `cgo_events` table.

### Workflow Components

#### 1. Workflow
`ProcessRefundWorkflow` runs the refund from request to completion. An auto-approved refund is approved, processed and completed in one run. Any other refund waits without end, because the workflow has no approval signal yet. When a step fails, the workflow runs the failure activity.

#### 2. Activities
- `InitiateRefundActivity`: Creates refund request
- `ApproveRefundActivity`: Approves refund
- `ProcessRefundActivity`: Processes with payment provider
- `CompleteRefundActivity`: Marks refund as complete
- `FailRefundActivity`: Handles failures

#### 3. Actions
- `RequestRefundAction` is the entry point for refund requests.
- It checks eligibility and decides whether the refund is approved automatically.
- It starts `ProcessRefundWorkflow` on the `cgo-refunds` task queue through the Temporal client (`Temporal\Client\WorkflowClient`).
- The Temporal PHP SDK is not installed (it is not in `composer.lock`), so the action cannot run as it stands. `ProcessRefundWorkflow` itself is a Laravel Workflow class.

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
- processor_reference
- processing_notes
- amount_refunded
- completed_at
- failure_reason
- failed_at
- cancellation_reason
- cancelled_by
- cancelled_at
- requested_at
- refund_address
- bank_details
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
A refund is approved automatically when any one of these is true:
1. The investment amount is 10000 or less. The code comment calls this $100, but `cgo_investments.amount` is stored in dollars, so the limit is in practice $10,000.
2. The payment completed within the last 7 days.
3. The reason is `duplicate_payment`, `payment_error` or `system_error`.

### Status Flow
```
pending → approved → processing → completed
  ↓         ↓           ↓           
rejected  cancelled   failed
```

## Admin Interface

There is no admin interface for refunds. The admin panel has no refund resource, and `CgoInvestmentResource` is an empty scaffold without a refund action. See [CGO_ADMIN_RESOURCES.md](../15-ADMINISTRATION/CGO_ADMIN_RESOURCES.md).

## Payment Processing

`RefundProcessingService` picks the path from the investment's payment method: `stripe`, `crypto` or `bank_transfer`. The web invest form stores card payments as `card`, which this service does not accept.

### Stripe Refunds
- The service creates a refund through the Stripe API.
- It uses the investment's `stripe_payment_intent_id`.
- It stores Stripe's refund id as the processor reference.

### Crypto Refunds
- The service marks the refund as `processing` and flags it for manual handling.
- A person has to send the refund to the refund address, which the customer still has to provide.

### Bank Transfer Refunds
- The service marks the refund as `processing` and flags it for manual handling.
- A person has to send the wire transfer.

## Security Considerations

1. **Authorization**
   - `RequestRefundAction` records who started the refund, but it does not check that this person owns the investment. A caller has to check that.
   - Refunds that are not approved automatically stay `pending`. The workflow has no way yet to receive a manual approval.
   - Every step is recorded as an event.

2. **Validation**
   - The refund amount is the investment amount.
   - `CgoInvestment::getTotalRefundedAmount()` adds up completed refunds.
   - The aggregate enforces the status transitions (only `pending` refunds can be approved or rejected, and only `approved` refunds can be processed).

3. **Event Integrity**
   - Events are immutable
   - Full audit trail maintained
   - Event replay capability

## Testing

### Unit Tests
- `tests/Unit/Domain/Cgo/RefundAggregateTest.php` covers the aggregate's state transitions and error cases.

There is no feature test for the full refund workflow.

## Configuration

### Event Class Map
The refund events are registered in `config/event-sourcing.php`:
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
Refund events go to the `events` queue.

### Workflow Configuration
`RequestRefundAction` starts refund workflows on the `cgo-refunds` task queue.

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

The module has no refund metrics or alerts yet. These are the ones worth adding.

### Suggested Metrics
- Refund request rate
- Auto-approval percentage
- Average processing time
- Failure rate by payment method

### Suggested Alerts
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