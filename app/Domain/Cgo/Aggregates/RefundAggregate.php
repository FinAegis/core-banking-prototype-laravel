<?php

namespace App\Domain\Cgo\Aggregates;

use App\Domain\Cgo\Events\RefundApproved;
use App\Domain\Cgo\Events\RefundCancelled;
use App\Domain\Cgo\Events\RefundCompleted;
use App\Domain\Cgo\Events\RefundFailed;
use App\Domain\Cgo\Events\RefundProcessed;
use App\Domain\Cgo\Events\RefundRejected;
use App\Domain\Cgo\Events\RefundRequested;
use App\Domain\Cgo\Repositories\CgoEventRepository;
use DomainException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class RefundAggregate extends AggregateRoot
{
    protected string $refundId;

    protected string $investmentId;

    protected string $userId;

    protected int $amount;

    protected string $currency;

    protected string $status = 'pending';

    protected ?string $paymentProcessor = null;

    protected ?string $processorRefundId = null;

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(CgoEventRepository::class);
    }

    public function requestRefund(
        string $refundId,
        string $investmentId,
        string $userId,
        int $amount,
        string $currency,
        string $reason,
        ?string $reasonDetails,
        string $initiatedBy,
        array $metadata = []
    ): self {
        $this->recordThat(
            new RefundRequested(
                $refundId,
                $investmentId,
                $userId,
                $amount,
                $currency,
                $reason,
                $reasonDetails,
                $initiatedBy,
                $metadata
            )
        );

        return $this;
    }

    public function approve(string $approvedBy, string $approvalNotes, array $metadata = []): self
    {
        if ($this->status !== 'pending') {
            throw new DomainException("Can only approve pending refunds. Current status: {$this->status}");
        }

        $this->recordThat(
            new RefundApproved(
                $this->refundId,
                $approvedBy,
                $approvalNotes,
                $metadata
            )
        );

        return $this;
    }

    public function reject(string $rejectedBy, string $rejectionReason, array $metadata = []): self
    {
        if ($this->status !== 'pending') {
            throw new DomainException("Can only reject pending refunds. Current status: {$this->status}");
        }

        $this->recordThat(
            new RefundRejected(
                $this->refundId,
                $rejectedBy,
                $rejectionReason,
                $metadata
            )
        );

        return $this;
    }

    public function process(
        string $paymentProcessor,
        string $processorRefundId,
        string $status,
        array $processorResponse,
        array $metadata = []
    ): self {
        if ($this->status !== 'approved') {
            throw new DomainException("Can only process approved refunds. Current status: {$this->status}");
        }

        $this->recordThat(
            new RefundProcessed(
                $this->refundId,
                $paymentProcessor,
                $processorRefundId,
                $status,
                $processorResponse,
                $metadata
            )
        );

        return $this;
    }

    public function complete(string $completedAt, array $metadata = []): self
    {
        if ($this->status !== 'processing') {
            throw new DomainException("Can only complete processing refunds. Current status: {$this->status}");
        }

        $this->recordThat(
            new RefundCompleted(
                $this->refundId,
                $this->investmentId,
                $this->amount,
                $completedAt,
                $metadata
            )
        );

        return $this;
    }

    public function fail(string $failureReason, string $failedAt, array $metadata = []): self
    {
        if (! in_array($this->status, ['approved', 'processing'])) {
            throw new DomainException("Can only fail approved or processing refunds. Current status: {$this->status}");
        }

        $this->recordThat(
            new RefundFailed(
                $this->refundId,
                $failureReason,
                $failedAt,
                $metadata
            )
        );

        return $this;
    }

    public function cancel(string $cancellationReason, string $cancelledBy, string $cancelledAt, array $metadata = []): self
    {
        if (in_array($this->status, ['completed', 'cancelled'])) {
            throw new DomainException("Cannot cancel refunds in status: {$this->status}");
        }

        $this->recordThat(
            new RefundCancelled(
                $this->refundId,
                $cancellationReason,
                $cancelledBy,
                $cancelledAt,
                $metadata
            )
        );

        return $this;
    }

    protected function applyRefundRequested(RefundRequested $event): void
    {
        $this->refundId = $event->refundId;
        $this->investmentId = $event->investmentId;
        $this->userId = $event->userId;
        $this->amount = $event->amount;
        $this->currency = $event->currency;
        $this->status = 'pending';
    }

    protected function applyRefundApproved(RefundApproved $event): void
    {
        $this->status = 'approved';
    }

    protected function applyRefundRejected(RefundRejected $event): void
    {
        $this->status = 'rejected';
    }

    protected function applyRefundProcessed(RefundProcessed $event): void
    {
        $this->status = 'processing';
        $this->paymentProcessor = $event->paymentProcessor;
        $this->processorRefundId = $event->processorRefundId;
    }

    protected function applyRefundCompleted(RefundCompleted $event): void
    {
        $this->status = 'completed';
    }

    protected function applyRefundFailed(RefundFailed $event): void
    {
        $this->status = 'failed';
    }

    protected function applyRefundCancelled(RefundCancelled $event): void
    {
        $this->status = 'cancelled';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function getInvestmentId(): string
    {
        return $this->investmentId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}
