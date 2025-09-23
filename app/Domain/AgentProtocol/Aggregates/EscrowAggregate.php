<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Aggregates;

use App\Domain\AgentProtocol\Events\EscrowCancelled;
use App\Domain\AgentProtocol\Events\EscrowCreated;
use App\Domain\AgentProtocol\Events\EscrowDisputed;
use App\Domain\AgentProtocol\Events\EscrowDisputeResolved;
use App\Domain\AgentProtocol\Events\EscrowExpired;
use App\Domain\AgentProtocol\Events\EscrowFundsDeposited;
use App\Domain\AgentProtocol\Events\EscrowFundsReleased;
use App\Domain\AgentProtocol\Repositories\AgentProtocolEventRepository;
use App\Domain\AgentProtocol\Repositories\AgentProtocolSnapshotRepository;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;
use Spatie\EventSourcing\Snapshots\SnapshotRepository;
use Spatie\EventSourcing\StoredEvents\Repositories\StoredEventRepository;

class EscrowAggregate extends AggregateRoot
{
    // Escrow states
    private const STATUS_CREATED = 'created';

    private const STATUS_FUNDED = 'funded';

    private const STATUS_RELEASED = 'released';

    private const STATUS_DISPUTED = 'disputed';

    private const STATUS_RESOLVED = 'resolved';

    private const STATUS_EXPIRED = 'expired';

    private const STATUS_CANCELLED = 'cancelled';

    // Dispute resolution types
    private const RESOLUTION_RELEASE_TO_RECEIVER = 'release_to_receiver';

    private const RESOLUTION_RETURN_TO_SENDER = 'return_to_sender';

    private const RESOLUTION_SPLIT = 'split';

    private const RESOLUTION_ARBITRATED = 'arbitrated';

    // State properties
    private string $escrowId = '';

    private string $transactionId = '';

    private string $senderAgentId = '';

    private string $receiverAgentId = '';

    private float $amount = 0.0;

    private string $currency = 'USD';

    private string $status = '';

    private array $conditions = [];

    private ?string $expiresAt = null;

    private float $fundedAmount = 0.0;

    private bool $isDisputed = false;

    private ?array $disputeDetails = null;

    private ?array $resolutionDetails = null;

    private array $metadata = [];

    private ?string $releasedAt = null;

    private ?string $releasedBy = null;

    private ?string $disputedBy = null;

    private ?string $disputeReason = null;

    private ?string $resolutionType = null;

    private array $resolutionAllocation = [];

    protected function getStoredEventRepository(): StoredEventRepository
    {
        return app(AgentProtocolEventRepository::class);
    }

    protected function getSnapshotRepository(): SnapshotRepository
    {
        return app(AgentProtocolSnapshotRepository::class);
    }

    public static function create(
        string $escrowId,
        string $transactionId,
        string $senderAgentId,
        string $receiverAgentId,
        float $amount,
        string $currency = 'USD',
        array $conditions = [],
        ?string $expiresAt = null,
        array $metadata = []
    ): self {
        if ($amount <= 0) {
            throw new InvalidArgumentException('Escrow amount must be greater than zero');
        }

        if ($expiresAt !== null && strtotime($expiresAt) <= time()) {
            throw new InvalidArgumentException('Expiration date must be in the future');
        }

        $aggregate = new self();
        $aggregate->recordThat(new EscrowCreated(
            escrowId: $escrowId,
            transactionId: $transactionId,
            senderAgentId: $senderAgentId,
            receiverAgentId: $receiverAgentId,
            amount: $amount,
            currency: $currency,
            conditions: $conditions,
            expiresAt: $expiresAt,
            metadata: $metadata
        ));

        return $aggregate;
    }

    public function deposit(float $amount, string $depositedBy, array $depositDetails = []): self
    {
        if (! in_array($this->status, [self::STATUS_CREATED, 'partially_funded'], true)) {
            throw new InvalidArgumentException("Cannot deposit to escrow in status: {$this->status}");
        }

        if ($amount <= 0) {
            throw new InvalidArgumentException('Deposit amount must be greater than zero');
        }

        if ($this->fundedAmount + $amount > $this->amount) {
            throw new InvalidArgumentException('Deposit would exceed escrow amount');
        }

        $this->recordThat(new EscrowFundsDeposited(
            escrowId: $this->escrowId,
            amount: $amount,
            depositedBy: $depositedBy,
            depositedAt: now()->toIso8601String(),
            totalFunded: $this->fundedAmount + $amount,
            depositDetails: $depositDetails
        ));

        return $this;
    }

    public function release(string $releasedBy, string $reason = 'conditions_met', array $releaseDetails = []): self
    {
        if (! in_array($this->status, [self::STATUS_FUNDED, self::STATUS_RESOLVED], true)) {
            throw new InvalidArgumentException("Cannot release escrow in status: {$this->status}");
        }

        if ($this->fundedAmount < $this->amount) {
            throw new InvalidArgumentException('Escrow not fully funded');
        }

        $this->recordThat(new EscrowFundsReleased(
            escrowId: $this->escrowId,
            releasedTo: $this->receiverAgentId,
            amount: $this->fundedAmount,
            releasedBy: $releasedBy,
            releasedAt: now()->toIso8601String(),
            reason: $reason,
            releaseDetails: $releaseDetails
        ));

        return $this;
    }

    public function dispute(string $disputedBy, string $reason, array $disputeEvidence = []): self
    {
        if (! in_array($this->status, [self::STATUS_FUNDED], true)) {
            throw new InvalidArgumentException("Cannot dispute escrow in status: {$this->status}");
        }

        if ($disputedBy !== $this->senderAgentId && $disputedBy !== $this->receiverAgentId) {
            throw new InvalidArgumentException('Only sender or receiver can dispute escrow');
        }

        $this->recordThat(new EscrowDisputed(
            escrowId: $this->escrowId,
            disputedBy: $disputedBy,
            disputedAt: now()->toIso8601String(),
            reason: $reason,
            evidence: $disputeEvidence
        ));

        return $this;
    }

    public function resolveDispute(
        string $resolvedBy,
        string $resolutionType,
        array $resolutionAllocation = [],
        array $resolutionDetails = []
    ): self {
        if ($this->status !== self::STATUS_DISPUTED) {
            throw new InvalidArgumentException('No dispute to resolve');
        }

        if (
            ! in_array($resolutionType, [
            self::RESOLUTION_RELEASE_TO_RECEIVER,
            self::RESOLUTION_RETURN_TO_SENDER,
            self::RESOLUTION_SPLIT,
            self::RESOLUTION_ARBITRATED,
            ], true)
        ) {
            throw new InvalidArgumentException("Invalid resolution type: {$resolutionType}");
        }

        if ($resolutionType === self::RESOLUTION_SPLIT && empty($resolutionAllocation)) {
            throw new InvalidArgumentException('Split resolution requires allocation details');
        }

        $this->recordThat(new EscrowDisputeResolved(
            escrowId: $this->escrowId,
            resolvedBy: $resolvedBy,
            resolvedAt: now()->toIso8601String(),
            resolutionType: $resolutionType,
            resolutionAllocation: $resolutionAllocation,
            resolutionDetails: $resolutionDetails
        ));

        return $this;
    }

    public function expire(): self
    {
        if (in_array($this->status, [self::STATUS_RELEASED, self::STATUS_CANCELLED, self::STATUS_EXPIRED], true)) {
            throw new InvalidArgumentException("Cannot expire escrow in status: {$this->status}");
        }

        // Allow expiration to be called manually or when time has passed
        $this->recordThat(new EscrowExpired(
            escrowId: $this->escrowId,
            expiredAt: now()->toIso8601String(),
            returnAmount: $this->fundedAmount,
            returnTo: $this->senderAgentId
        ));

        return $this;
    }

    public function cancel(string $cancelledBy, string $reason, array $cancellationDetails = []): self
    {
        if (in_array($this->status, [self::STATUS_RELEASED, self::STATUS_CANCELLED], true)) {
            throw new InvalidArgumentException("Cannot cancel escrow in status: {$this->status}");
        }

        // Only allow cancellation by sender before funding is complete
        if ($this->status === self::STATUS_FUNDED && $cancelledBy !== $this->senderAgentId) {
            throw new InvalidArgumentException('Only sender can cancel funded escrow');
        }

        $this->recordThat(new EscrowCancelled(
            escrowId: $this->escrowId,
            cancelledBy: $cancelledBy,
            cancelledAt: now()->toIso8601String(),
            reason: $reason,
            refundAmount: $this->fundedAmount,
            cancellationDetails: $cancellationDetails
        ));

        return $this;
    }

    // Event handlers
    protected function applyEscrowCreated(EscrowCreated $event): void
    {
        $this->escrowId = $event->escrowId;
        $this->transactionId = $event->transactionId;
        $this->senderAgentId = $event->senderAgentId;
        $this->receiverAgentId = $event->receiverAgentId;
        $this->amount = $event->amount;
        $this->currency = $event->currency;
        $this->conditions = $event->conditions;
        $this->expiresAt = $event->expiresAt;
        $this->metadata = $event->metadata;
        $this->status = self::STATUS_CREATED;
        $this->fundedAmount = 0.0;
    }

    protected function applyEscrowFundsDeposited(EscrowFundsDeposited $event): void
    {
        $this->fundedAmount = $event->totalFunded;
        if ($this->fundedAmount >= $this->amount) {
            $this->status = self::STATUS_FUNDED;
        } elseif ($this->fundedAmount > 0) {
            $this->status = 'partially_funded';
        }
        $this->metadata['lastDeposit'] = [
            'amount'      => $event->amount,
            'depositedBy' => $event->depositedBy,
            'depositedAt' => $event->depositedAt,
        ];
    }

    protected function applyEscrowFundsReleased(EscrowFundsReleased $event): void
    {
        $this->status = self::STATUS_RELEASED;
        $this->releasedBy = $event->releasedBy;
        $this->releasedAt = $event->releasedAt;
        $this->metadata['release'] = [
            'releasedTo' => $event->releasedTo,
            'amount'     => $event->amount,
            'releasedBy' => $event->releasedBy,
            'releasedAt' => $event->releasedAt,
            'reason'     => $event->reason,
        ];
    }

    protected function applyEscrowDisputed(EscrowDisputed $event): void
    {
        $this->status = self::STATUS_DISPUTED;
        $this->isDisputed = true;
        $this->disputedBy = $event->disputedBy;
        $this->disputeReason = $event->reason;
        $this->disputeDetails = [
            'disputedBy' => $event->disputedBy,
            'disputedAt' => $event->disputedAt,
            'reason'     => $event->reason,
            'evidence'   => $event->evidence,
        ];
    }

    protected function applyEscrowDisputeResolved(EscrowDisputeResolved $event): void
    {
        $this->status = self::STATUS_RESOLVED;
        $this->isDisputed = false;
        $this->resolutionType = $event->resolutionType;
        $this->resolutionAllocation = $event->resolutionAllocation;
        $this->resolutionDetails = [
            'resolvedBy'           => $event->resolvedBy,
            'resolvedAt'           => $event->resolvedAt,
            'resolutionType'       => $event->resolutionType,
            'resolutionAllocation' => $event->resolutionAllocation,
            'details'              => $event->resolutionDetails,
        ];
    }

    protected function applyEscrowExpired(EscrowExpired $event): void
    {
        $this->status = self::STATUS_EXPIRED;
        $this->metadata['expiration'] = [
            'expiredAt'    => $event->expiredAt,
            'returnAmount' => $event->returnAmount,
            'returnTo'     => $event->returnTo,
        ];
    }

    protected function applyEscrowCancelled(EscrowCancelled $event): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->metadata['cancellation'] = [
            'cancelledBy'  => $event->cancelledBy,
            'cancelledAt'  => $event->cancelledAt,
            'reason'       => $event->reason,
            'refundAmount' => $event->refundAmount,
        ];
    }

    // Getters for state
    public function getEscrowId(): string
    {
        return $this->escrowId;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }

    public function getSenderAgentId(): string
    {
        return $this->senderAgentId;
    }

    public function getReceiverAgentId(): string
    {
        return $this->receiverAgentId;
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

    public function getFundedAmount(): float
    {
        return $this->fundedAmount;
    }

    public function getConditions(): array
    {
        return $this->conditions;
    }

    public function getExpiresAt(): ?string
    {
        return $this->expiresAt;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getReleasedAt(): ?string
    {
        return $this->releasedAt;
    }

    public function getReleasedBy(): ?string
    {
        return $this->releasedBy;
    }

    public function getDisputedBy(): ?string
    {
        return $this->disputedBy;
    }

    public function getDisputeReason(): ?string
    {
        return $this->disputeReason;
    }

    // Status check methods
    public function isFunded(): bool
    {
        return $this->status === self::STATUS_FUNDED ||
               ($this->status === 'partially_funded' && $this->fundedAmount >= $this->amount);
    }

    public function isReadyForRelease(): bool
    {
        if ($this->status !== self::STATUS_FUNDED) {
            return false;
        }

        // Check if all conditions are met
        foreach ($this->conditions as $condition => $value) {
            if ($value !== true) {
                return false;
            }
        }

        return true;
    }

    public function isDisputeResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED && $this->resolutionDetails !== null;
    }

    public function isResolvedInFavorOfRecipient(): bool
    {
        if (! $this->isDisputeResolved()) {
            return false;
        }

        return $this->resolutionType === self::RESOLUTION_RELEASE_TO_RECEIVER ||
               ($this->resolutionType === self::RESOLUTION_ARBITRATED &&
                isset($this->resolutionAllocation[$this->receiverAgentId]) &&
                $this->resolutionAllocation[$this->receiverAgentId] > 0);
    }

    public function getResolutionType(): ?string
    {
        return $this->resolutionType;
    }

    public function getResolutionAllocation(): array
    {
        return $this->resolutionAllocation;
    }

    public function getDisputeDetails(): array
    {
        return $this->disputeDetails;
    }

    public function getResolutionDetails(): array
    {
        return $this->resolutionDetails;
    }

    public function isFullyFunded(): bool
    {
        return $this->fundedAmount >= $this->amount;
    }

    public function isDisputed(): bool
    {
        return $this->isDisputed;
    }

    public function hasExpired(): bool
    {
        return $this->expiresAt !== null && strtotime($this->expiresAt) <= time();
    }
}
