<?php

namespace App\Domain\Cgo\Projectors;

use App\Domain\Cgo\Events\RefundApproved;
use App\Domain\Cgo\Events\RefundCancelled;
use App\Domain\Cgo\Events\RefundCompleted;
use App\Domain\Cgo\Events\RefundFailed;
use App\Domain\Cgo\Events\RefundProcessed;
use App\Domain\Cgo\Events\RefundRejected;
use App\Domain\Cgo\Events\RefundRequested;
use App\Models\CgoRefund;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class RefundProjector extends Projector
{
    public function onRefundRequested(RefundRequested $event): void
    {
        CgoRefund::create([
            'id' => $event->refundId,
            'investment_id' => $event->investmentId,
            'user_id' => $event->userId,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'reason' => $event->reason,
            'reason_details' => $event->reasonDetails,
            'status' => 'pending',
            'initiated_by' => $event->initiatedBy,
            'metadata' => $event->metadata,
            'requested_at' => now(),
        ]);
    }

    public function onRefundApproved(RefundApproved $event): void
    {
        CgoRefund::where('id', $event->refundId)->update([
            'status' => 'approved',
            'approved_by' => $event->approvedBy,
            'approval_notes' => $event->approvalNotes,
            'approved_at' => now(),
            'metadata' => array_merge(
                CgoRefund::find($event->refundId)->metadata ?? [],
                $event->metadata
            ),
        ]);
    }

    public function onRefundRejected(RefundRejected $event): void
    {
        CgoRefund::where('id', $event->refundId)->update([
            'status' => 'rejected',
            'rejected_by' => $event->rejectedBy,
            'rejection_reason' => $event->rejectionReason,
            'rejected_at' => now(),
            'metadata' => array_merge(
                CgoRefund::find($event->refundId)->metadata ?? [],
                $event->metadata
            ),
        ]);
    }

    public function onRefundProcessed(RefundProcessed $event): void
    {
        CgoRefund::where('id', $event->refundId)->update([
            'status' => 'processing',
            'payment_processor' => $event->paymentProcessor,
            'processor_refund_id' => $event->processorRefundId,
            'processor_status' => $event->status,
            'processor_response' => $event->processorResponse,
            'processed_at' => now(),
            'metadata' => array_merge(
                CgoRefund::find($event->refundId)->metadata ?? [],
                $event->metadata
            ),
        ]);
    }

    public function onRefundCompleted(RefundCompleted $event): void
    {
        CgoRefund::where('id', $event->refundId)->update([
            'status' => 'completed',
            'amount_refunded' => $event->amountRefunded,
            'completed_at' => $event->completedAt,
            'metadata' => array_merge(
                CgoRefund::find($event->refundId)->metadata ?? [],
                $event->metadata
            ),
        ]);
    }

    public function onRefundFailed(RefundFailed $event): void
    {
        CgoRefund::where('id', $event->refundId)->update([
            'status' => 'failed',
            'failure_reason' => $event->failureReason,
            'failed_at' => $event->failedAt,
            'metadata' => array_merge(
                CgoRefund::find($event->refundId)->metadata ?? [],
                $event->metadata
            ),
        ]);
    }

    public function onRefundCancelled(RefundCancelled $event): void
    {
        CgoRefund::where('id', $event->refundId)->update([
            'status' => 'cancelled',
            'cancellation_reason' => $event->cancellationReason,
            'cancelled_by' => $event->cancelledBy,
            'cancelled_at' => $event->cancelledAt,
            'metadata' => array_merge(
                CgoRefund::find($event->refundId)->metadata ?? [],
                $event->metadata
            ),
        ]);
    }
}