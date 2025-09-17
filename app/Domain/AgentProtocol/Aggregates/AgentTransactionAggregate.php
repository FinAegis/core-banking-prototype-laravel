<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\EscrowHeld;
use App\Domain\AgentProtocol\Events\EscrowReleased;
use App\Domain\AgentProtocol\Events\FeeCalculated;
use App\Domain\AgentProtocol\Events\TransactionCompleted;
use App\Domain\AgentProtocol\Events\TransactionFailed;
use App\Domain\AgentProtocol\Events\TransactionInitiated;
use App\Domain\AgentProtocol\Events\TransactionValidated;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class AgentTransactionAggregate extends AggregateRoot
{
    // Transaction states
    private const STATUS_INITIATED = 'initiated';

    private const STATUS_VALIDATED = 'validated';

    private const STATUS_PROCESSING = 'processing';

    private const STATUS_COMPLETED = 'completed';

    private const STATUS_FAILED = 'failed';

    // Transaction types
    private const TYPE_DIRECT = 'direct';

    private const TYPE_ESCROW = 'escrow';

    private const TYPE_SPLIT = 'split';

    // State properties
    private string $transactionId = '';

    private string $fromAgentId = '';

    private string $toAgentId = '';

    private float $amount = 0.0;

    private string $currency = 'USD';

    private string $type = self::TYPE_DIRECT;

    private string $status = '';

    private ?string $escrowId = null;

    private array $fees = [];

    private array $splitDetails = [];

    private array $metadata = [];

    private bool $isEscrowHeld = false;

    private ?string $failureReason = null;

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function initiate(
        string $transactionId,
        string $fromAgentId,
        string $toAgentId,
        float $amount,
        string $currency = 'USD',
        string $type = self::TYPE_DIRECT,
        ?string $escrowId = null,
        array $metadata = []
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Transaction amount must be greater than zero');
        }

        if (! in_array($type, [self::TYPE_DIRECT, self::TYPE_ESCROW, self::TYPE_SPLIT], true)) {
            throw new InvalidArgumentException("Invalid transaction type: {$type}");
        }

        if ($type === self::TYPE_ESCROW && empty($escrowId)) {
            $escrowId = 'escrow_' . Str::uuid()->toString();
        }

        $aggregate = new self();
        $aggregate->recordThat(new TransactionInitiated(
            transactionId: $transactionId,
            fromAgentId: $fromAgentId,
            toAgentId: $toAgentId,
            amount: $amount,
            currency: $currency,
            type: $type,
            escrowId: $escrowId,
            metadata: $metadata
        ));

        return $aggregate;
    }

    public function validate(array $validationData = []): self
    {
        if ($this->status !== self::STATUS_INITIATED) {
            throw new InvalidArgumentException("Cannot validate transaction in status: {$this->status}");
        }

        $this->recordThat(new TransactionValidated(
            transactionId: $this->transactionId,
            validatedAt: now()->toIso8601String(),
            validationData: $validationData
        ));

        return $this;
    }

    public function calculateFees(float $feeAmount, string $feeType = 'processing', array $feeDetails = []): self
    {
        if ($feeAmount < 0) {
            throw new InvalidArgumentException('Fee amount cannot be negative');
        }

        $this->recordThat(new FeeCalculated(
            transactionId: $this->transactionId,
            feeAmount: $feeAmount,
            feeType: $feeType,
            feeDetails: $feeDetails
        ));

        return $this;
    }

    public function holdInEscrow(float $amount, array $escrowDetails = []): self
    {
        if ($this->type !== self::TYPE_ESCROW) {
            throw new InvalidArgumentException('Can only hold escrow for escrow-type transactions');
        }

        if ($this->status !== self::STATUS_VALIDATED) {
            throw new InvalidArgumentException("Cannot hold escrow in status: {$this->status}");
        }

        if ($amount <= 0 || $amount > $this->amount) {
            throw new InvalidArgumentException('Invalid escrow amount');
        }

        $this->recordThat(new EscrowHeld(
            transactionId: $this->transactionId,
            escrowId: $this->escrowId ?? '',
            amount: $amount,
            heldAt: now()->toIso8601String(),
            escrowDetails: $escrowDetails
        ));

        return $this;
    }

    public function releaseFromEscrow(string $releasedBy, string $reason, array $releaseDetails = []): self
    {
        if (! $this->isEscrowHeld) {
            throw new InvalidArgumentException('No escrow funds to release');
        }

        $this->recordThat(new EscrowReleased(
            transactionId: $this->transactionId,
            escrowId: $this->escrowId ?? '',
            releasedBy: $releasedBy,
            releasedAt: now()->toIso8601String(),
            reason: $reason,
            releaseDetails: $releaseDetails
        ));

        return $this;
    }

    public function complete(string $completionStatus = 'success', array $completionDetails = []): self
    {
        if (! in_array($this->status, [self::STATUS_VALIDATED, self::STATUS_PROCESSING], true)) {
            throw new InvalidArgumentException("Cannot complete transaction in status: {$this->status}");
        }

        if ($this->type === self::TYPE_ESCROW && $this->isEscrowHeld) {
            throw new InvalidArgumentException('Must release escrow before completing transaction');
        }

        $finalAmount = $this->amount;
        foreach ($this->fees as $fee) {
            $finalAmount -= $fee['amount'] ?? 0;
        }

        $this->recordThat(new TransactionCompleted(
            transactionId: $this->transactionId,
            status: $completionStatus,
            finalAmount: $finalAmount,
            currency: $this->currency,
            fees: $this->fees,
            metadata: array_merge($this->metadata, $completionDetails)
        ));

        return $this;
    }

    public function fail(string $reason, array $errorDetails = []): self
    {
        if (in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true)) {
            throw new InvalidArgumentException("Cannot fail transaction in status: {$this->status}");
        }

        $this->recordThat(new TransactionFailed(
            transactionId: $this->transactionId,
            reason: $reason,
            failedAt: now()->toIso8601String(),
            errorDetails: $errorDetails,
            reversible: $this->status === self::STATUS_PROCESSING
        ));

        return $this;
    }

    public function addSplitRecipient(string $recipientAgentId, float $amount, string $splitType = 'fixed'): self
    {
        if ($this->type !== self::TYPE_SPLIT) {
            throw new InvalidArgumentException('Can only add split recipients to split-type transactions');
        }

        if ($this->status !== self::STATUS_INITIATED) {
            throw new InvalidArgumentException('Can only add split recipients before validation');
        }

        $totalSplitAmount = array_sum(array_column($this->splitDetails, 'amount')) + $amount;
        if ($totalSplitAmount > $this->amount) {
            throw new InvalidArgumentException('Total split amount exceeds transaction amount');
        }

        $this->splitDetails[] = [
            'recipientAgentId' => $recipientAgentId,
            'amount'           => $amount,
            'splitType'        => $splitType,
            'addedAt'          => now()->toIso8601String(),
        ];

        return $this;
    }

    // Event handlers
    protected function applyTransactionInitiated(TransactionInitiated $event): void
    {
        $this->transactionId = $event->transactionId;
        $this->fromAgentId = $event->fromAgentId;
        $this->toAgentId = $event->toAgentId;
        $this->amount = $event->amount;
        $this->currency = $event->currency;
        $this->type = $event->type;
        $this->status = self::STATUS_INITIATED;
        $this->escrowId = $event->escrowId;
        $this->metadata = $event->metadata;
    }

    protected function applyTransactionValidated(TransactionValidated $event): void
    {
        $this->status = self::STATUS_VALIDATED;
        $this->metadata['validation'] = $event->validationData;
        $this->metadata['validatedAt'] = $event->validatedAt;
    }

    protected function applyFeeCalculated(FeeCalculated $event): void
    {
        $this->fees[] = [
            'amount'       => $event->feeAmount,
            'type'         => $event->feeType,
            'details'      => $event->feeDetails,
            'calculatedAt' => now()->toIso8601String(),
        ];
    }

    protected function applyEscrowHeld(EscrowHeld $event): void
    {
        $this->isEscrowHeld = true;
        $this->status = self::STATUS_PROCESSING;
        $this->metadata['escrow'] = [
            'heldAt'  => $event->heldAt,
            'amount'  => $event->amount,
            'details' => $event->escrowDetails,
        ];
    }

    protected function applyEscrowReleased(EscrowReleased $event): void
    {
        $this->isEscrowHeld = false;
        $this->metadata['escrowRelease'] = [
            'releasedBy' => $event->releasedBy,
            'releasedAt' => $event->releasedAt,
            'reason'     => $event->reason,
            'details'    => $event->releaseDetails,
        ];
    }

    protected function applyTransactionCompleted(TransactionCompleted $event): void
    {
        $this->status = self::STATUS_COMPLETED;
        $this->metadata['completion'] = [
            'status'      => $event->status,
            'finalAmount' => $event->finalAmount,
            'completedAt' => now()->toIso8601String(),
        ];
    }

    protected function applyTransactionFailed(TransactionFailed $event): void
    {
        $this->status = self::STATUS_FAILED;
        $this->failureReason = $event->reason;
        $this->metadata['failure'] = [
            'reason'     => $event->reason,
            'failedAt'   => $event->failedAt,
            'details'    => $event->errorDetails,
            'reversible' => $event->reversible,
        ];
    }

    // Getters for state
    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getFromAgentId(): string
    {
        return $this->fromAgentId;
    }

    public function getToAgentId(): string
    {
        return $this->toAgentId;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getFees(): array
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

    public function isEscrowTransaction(): bool
    {
        return $this->type === self::TYPE_ESCROW;
    }

    public function hasEscrowHeld(): bool
    {
        return $this->isEscrowHeld;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function getSplitDetails(): array
    {
        return $this->splitDetails;
    }
}
