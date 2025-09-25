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
use App\Domain\AgentProtocol\Repositories\AgentRepositoryInterface;
use App\Domain\AgentProtocol\ValueObjects\AgentIdentifier;
use App\Domain\AgentProtocol\ValueObjects\TransactionAmount;
use DateTimeImmutable;
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

    // State properties using Value Objects
    private string $transactionId = '';

    private ?AgentIdentifier $fromAgent = null;

    private ?AgentIdentifier $toAgent = null;

    private ?TransactionAmount $amount = null;

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
        AgentIdentifier $fromAgent,
        AgentIdentifier $toAgent,
        TransactionAmount $amount,
        string $type = self::TYPE_DIRECT,
        ?string $escrowId = null,
        array $metadata = []
    ): self {
        if (! in_array($type, [self::TYPE_DIRECT, self::TYPE_ESCROW, self::TYPE_SPLIT], true)) {
            throw new InvalidArgumentException("Invalid transaction type: {$type}");
        }

        if ($type === self::TYPE_ESCROW && empty($escrowId)) {
            $escrowId = 'escrow_' . Str::uuid()->toString();
        }

        $aggregate = static::retrieve($transactionId);
        $aggregate->recordThat(new TransactionInitiated(
            transactionId: $transactionId,
            fromAgentId: $fromAgent->getAgentId(),
            toAgentId: $toAgent->getAgentId(),
            amount: $amount->getAmount(),
            currency: $amount->getCurrency(),
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

    public function calculateFees(TransactionAmount $feeAmount, string $feeType = 'processing', array $feeDetails = []): self
    {
        if ($this->amount && ! $this->amount->isSameCurrency($feeAmount)) {
            throw new InvalidArgumentException('Fee currency must match transaction currency');
        }

        $this->recordThat(new FeeCalculated(
            transactionId: $this->transactionId,
            feeAmount: $feeAmount->getAmount(),
            feeType: $feeType,
            feeDetails: array_merge($feeDetails, ['currency' => $feeAmount->getCurrency()])
        ));

        return $this;
    }

    public function holdInEscrow(TransactionAmount $escrowAmount, array $escrowDetails = []): self
    {
        if ($this->type !== self::TYPE_ESCROW) {
            throw new InvalidArgumentException('Can only hold escrow for escrow-type transactions');
        }

        if ($this->status !== self::STATUS_VALIDATED) {
            throw new InvalidArgumentException("Cannot hold escrow in status: {$this->status}");
        }

        if (! $this->amount) {
            throw new InvalidArgumentException('Transaction amount not set');
        }

        if (! $this->amount->isSameCurrency($escrowAmount)) {
            throw new InvalidArgumentException('Escrow currency must match transaction currency');
        }

        if ($escrowAmount->isGreaterThan($this->amount)) {
            throw new InvalidArgumentException('Escrow amount exceeds transaction amount');
        }

        $this->recordThat(new EscrowHeld(
            transactionId: $this->transactionId,
            escrowId: $this->escrowId ?? '',
            amount: $escrowAmount->getAmount(),
            heldAt: now()->toIso8601String(),
            escrowDetails: array_merge($escrowDetails, ['currency' => $escrowAmount->getCurrency()])
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

        if (! $this->amount) {
            throw new InvalidArgumentException('Transaction amount not set');
        }

        $finalAmount = $this->amount;
        foreach ($this->fees as $fee) {
            $feeAmount = new TransactionAmount($fee['amount'] ?? 0, $fee['currency'] ?? $this->amount->getCurrency());
            $finalAmount = $finalAmount->subtract($feeAmount);
        }

        $this->recordThat(new TransactionCompleted(
            transactionId: $this->transactionId,
            status: $completionStatus,
            finalAmount: $finalAmount->getAmount(),
            currency: $finalAmount->getCurrency(),
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

    public function addSplitRecipient(AgentIdentifier $recipientAgent, TransactionAmount $splitAmount, string $splitType = 'fixed'): self
    {
        if ($this->type !== self::TYPE_SPLIT) {
            throw new InvalidArgumentException('Can only add split recipients to split-type transactions');
        }

        if ($this->status !== self::STATUS_INITIATED) {
            throw new InvalidArgumentException('Can only add split recipients before validation');
        }

        if (! $this->amount) {
            throw new InvalidArgumentException('Transaction amount not set');
        }

        if (! $this->amount->isSameCurrency($splitAmount)) {
            throw new InvalidArgumentException('Split currency must match transaction currency');
        }

        $totalSplitAmount = new TransactionAmount(0, $this->amount->getCurrency());
        foreach ($this->splitDetails as $split) {
            $splitValue = new TransactionAmount($split['amount'], $split['currency'] ?? $this->amount->getCurrency());
            $totalSplitAmount = $totalSplitAmount->add($splitValue);
        }

        $totalSplitAmount = $totalSplitAmount->add($splitAmount);

        if ($totalSplitAmount->isGreaterThan($this->amount)) {
            throw new InvalidArgumentException('Total split amount exceeds transaction amount');
        }

        $this->splitDetails[] = [
        'recipientAgentId' => $recipientAgent->getAgentId(),
        'recipientDid'     => $recipientAgent->getDid(),
        'amount'           => $splitAmount->getAmount(),
        'currency'         => $splitAmount->getCurrency(),
        'splitType'        => $splitType,
        'addedAt'          => now()->toIso8601String(),
        ];

        return $this;
    }

    // Event handlers
    protected function applyTransactionInitiated(TransactionInitiated $event): void
    {
        $this->transactionId = $event->transactionId;

        // Create AgentIdentifier value objects from the event data
        // We'll need to fetch the DID from the repository or store it in the event
        $fromAgentRepo = app(AgentRepositoryInterface::class);
        $toAgentRepo = app(AgentRepositoryInterface::class);

        $fromAgentModel = $fromAgentRepo->findByAgentId($event->fromAgentId);
        $toAgentModel = $toAgentRepo->findByAgentId($event->toAgentId);

        if ($fromAgentModel) {
            $this->fromAgent = new AgentIdentifier($event->fromAgentId, $fromAgentModel->did);
        }
        if ($toAgentModel) {
            $this->toAgent = new AgentIdentifier($event->toAgentId, $toAgentModel->did);
        }

        $this->amount = new TransactionAmount($event->amount, $event->currency);
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
        $currency = $event->feeDetails['currency'] ?? ($this->amount ? $this->amount->getCurrency() : 'USD');
        $this->fees[] = [
        'amount'       => $event->feeAmount,
        'currency'     => $currency,
        'type'         => $event->feeType,
        'details'      => $event->feeDetails,
        'calculatedAt' => now()->toIso8601String(),
        ];
    }

    protected function applyEscrowHeld(EscrowHeld $event): void
    {
        $this->isEscrowHeld = true;
        $this->status = self::STATUS_PROCESSING;
        $currency = $event->escrowDetails['currency'] ?? ($this->amount ? $this->amount->getCurrency() : 'USD');
        $this->metadata['escrow'] = [
        'heldAt'   => $event->heldAt,
        'amount'   => $event->amount,
        'currency' => $currency,
        'details'  => $event->escrowDetails,
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
        'currency'    => $event->currency,
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

    public function getFromAgent(): ?AgentIdentifier
    {
        return $this->fromAgent;
    }

    public function getToAgent(): ?AgentIdentifier
    {
        return $this->toAgent;
    }

    public function getFromAgentId(): string
    {
        return $this->fromAgent ? $this->fromAgent->getAgentId() : '';
    }

    public function getToAgentId(): string
    {
        return $this->toAgent ? $this->toAgent->getAgentId() : '';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getAmount(): ?TransactionAmount
    {
        return $this->amount;
    }

    public function getAmountValue(): float
    {
        return $this->amount ? $this->amount->getAmount() : 0.0;
    }

    public function getCurrency(): string
    {
        return $this->amount ? $this->amount->getCurrency() : 'USD';
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

    /**
     * Confirm a transaction (for receiver confirmation).
     */
    public function confirm(string $confirmedBy, ?string $confirmationCode = null): self
    {
        if ($this->status === self::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Transaction is already completed');
        }

        $this->complete('confirmed', [
        'confirmed_by'      => $confirmedBy,
        'confirmation_code' => $confirmationCode,
        'confirmed_at'      => now()->toIso8601String(),
        ]);

        return $this;
    }

    /**
     * Cancel a transaction (for sender cancellation).
     */
    public function cancel(string $reason, string $cancelledBy): self
    {
        if ($this->status === self::STATUS_COMPLETED) {
            throw new InvalidArgumentException('Cannot cancel completed transaction');
        }

        $this->fail($reason, [
        'cancelled_by'        => $cancelledBy,
        'cancellation_reason' => $reason,
        'cancelled_at'        => now()->toIso8601String(),
        ]);

        return $this;
    }

    /**
     * Get sender agent ID (alias for getFromAgentId).
     */
    public function getSenderAgentId(): string
    {
        return $this->getFromAgentId();
    }

    /**
     * Get receiver agent ID (alias for getToAgentId).
     */
    public function getReceiverAgentId(): string
    {
        return $this->getToAgentId();
    }

    /**
     * Get created at timestamp.
     */
    public function getCreatedAt(): ?DateTimeImmutable
    {
        return isset($this->metadata['created_at']) ? new DateTimeImmutable($this->metadata['created_at']) : null;
    }

    /**
     * Get updated at timestamp.
     */
    public function getUpdatedAt(): ?DateTimeImmutable
    {
        return isset($this->metadata['updated_at']) ? new DateTimeImmutable($this->metadata['updated_at']) : null;
    }
}
