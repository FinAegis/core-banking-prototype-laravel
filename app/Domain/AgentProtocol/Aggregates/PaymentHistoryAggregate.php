<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\PaymentRecorded;
use App\Domain\AgentProtocol\Events\PaymentStatusChanged;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

/**
 * Aggregate for tracking payment history.
 */
class PaymentHistoryAggregate extends AggregateRoot
{
    private string $transactionId = '';

    private string $paymentId = '';

    private string $fromAgent = '';

    private string $toAgent = '';

    private float $amount = 0.0;

    private string $currency = '';

    private string $status = '';

    private float $fees = 0.0;

    private ?string $escrowId = null;

    private array $metadata = [];

    private array $history = [];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    /**
     * Record a payment in the history.
     */
    public function recordPayment(
        string $paymentId,
        string $fromAgent,
        string $toAgent,
        float $amount,
        string $currency,
        string $status,
        float $fees = 0.0,
        ?string $escrowId = null,
        array $metadata = []
    ): self {
        $this->recordThat(new PaymentRecorded(
            transactionId: $this->uuid(),
            paymentId: $paymentId,
            fromAgent: $fromAgent,
            toAgent: $toAgent,
            amount: $amount,
            currency: $currency,
            status: $status,
            fees: $fees,
            escrowId: $escrowId,
            metadata: $metadata,
            recordedAt: now()->toIso8601String()
        ));

        return $this;
    }

    /**
     * Update payment status.
     */
    public function updateStatus(string $newStatus, string $reason = '', array $details = []): self
    {
        $this->recordThat(new PaymentStatusChanged(
            transactionId: $this->uuid(),
            paymentId: $this->paymentId,
            oldStatus: $this->status,
            newStatus: $newStatus,
            reason: $reason,
            details: $details,
            changedAt: now()->toIso8601String()
        ));

        return $this;
    }

    // Event handlers
    protected function applyPaymentRecorded(PaymentRecorded $event): void
    {
        $this->transactionId = $event->transactionId;
        $this->paymentId = $event->paymentId;
        $this->fromAgent = $event->fromAgent;
        $this->toAgent = $event->toAgent;
        $this->amount = $event->amount;
        $this->currency = $event->currency;
        $this->status = $event->status;
        $this->fees = $event->fees;
        $this->escrowId = $event->escrowId;
        $this->metadata = $event->metadata;

        $this->history[] = [
            'event'     => 'payment_recorded',
            'timestamp' => $event->recordedAt,
            'data'      => $event->toArray(),
        ];
    }

    protected function applyPaymentStatusChanged(PaymentStatusChanged $event): void
    {
        $this->status = $event->newStatus;

        $this->history[] = [
            'event'      => 'status_changed',
            'timestamp'  => $event->changedAt,
            'old_status' => $event->oldStatus,
            'new_status' => $event->newStatus,
            'reason'     => $event->reason,
        ];
    }

    // Getters
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getPaymentId(): string
    {
        return $this->paymentId;
    }

    public function getFromAgent(): string
    {
        return $this->fromAgent;
    }

    public function getToAgent(): string
    {
        return $this->toAgent;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getFees(): float
    {
        return $this->fees;
    }

    public function getEscrowId(): ?string
    {
        return $this->escrowId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getHistory(): array
    {
        return $this->history;
    }
}
