<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

use Carbon\Carbon;

/**
 * Represents the result of an escrow operation.
 */
class EscrowResult
{
    public ?Carbon $fundedAt = null;

    public ?Carbon $releasedAt = null;

    public ?Carbon $returnedAt = null;

    public ?Carbon $expiredAt = null;

    public ?Carbon $cancelledAt = null;

    public ?Carbon $failedAt = null;

    public ?string $releasedTo = null;

    public ?string $errorMessage = null;

    public bool $fundsReturned = false;

    public function __construct(
        public string $escrowId,
        public string $status,
        public Carbon $createdAt
    ) {
    }

    /**
     * Check if the escrow is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, ['pending', 'funded']);
    }

    /**
     * Check if the escrow is completed.
     */
    public function isCompleted(): bool
    {
        return in_array($this->status, ['released', 'returned']);
    }

    /**
     * Check if the escrow failed.
     */
    public function isFailed(): bool
    {
        return in_array($this->status, ['expired', 'cancelled', 'error', 'rejected']);
    }

    /**
     * Get the duration of the escrow.
     */
    public function getDurationInSeconds(): ?int
    {
        if ($this->releasedAt) {
            return (int) $this->createdAt->diffInSeconds($this->releasedAt);
        }

        if ($this->returnedAt) {
            return (int) $this->createdAt->diffInSeconds($this->returnedAt);
        }

        if ($this->expiredAt) {
            return (int) $this->createdAt->diffInSeconds($this->expiredAt);
        }

        if ($this->cancelledAt) {
            return (int) $this->createdAt->diffInSeconds($this->cancelledAt);
        }

        return null;
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'escrow_id'        => $this->escrowId,
            'status'           => $this->status,
            'created_at'       => $this->createdAt->toIso8601String(),
            'funded_at'        => $this->fundedAt?->toIso8601String(),
            'released_at'      => $this->releasedAt?->toIso8601String(),
            'returned_at'      => $this->returnedAt?->toIso8601String(),
            'expired_at'       => $this->expiredAt?->toIso8601String(),
            'cancelled_at'     => $this->cancelledAt?->toIso8601String(),
            'failed_at'        => $this->failedAt?->toIso8601String(),
            'released_to'      => $this->releasedTo,
            'funds_returned'   => $this->fundsReturned,
            'error_message'    => $this->errorMessage,
            'duration_seconds' => $this->getDurationInSeconds(),
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        $result = new self(
            escrowId: $data['escrow_id'],
            status: $data['status'],
            createdAt: Carbon::parse($data['created_at'])
        );

        $result->fundedAt = isset($data['funded_at']) ? Carbon::parse($data['funded_at']) : null;
        $result->releasedAt = isset($data['released_at']) ? Carbon::parse($data['released_at']) : null;
        $result->returnedAt = isset($data['returned_at']) ? Carbon::parse($data['returned_at']) : null;
        $result->expiredAt = isset($data['expired_at']) ? Carbon::parse($data['expired_at']) : null;
        $result->cancelledAt = isset($data['cancelled_at']) ? Carbon::parse($data['cancelled_at']) : null;
        $result->failedAt = isset($data['failed_at']) ? Carbon::parse($data['failed_at']) : null;
        $result->releasedTo = $data['released_to'] ?? null;
        $result->fundsReturned = $data['funds_returned'] ?? false;
        $result->errorMessage = $data['error_message'] ?? null;

        return $result;
    }
}
