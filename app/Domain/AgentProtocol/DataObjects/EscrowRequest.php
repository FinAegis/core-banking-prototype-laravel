<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Represents an escrow request for secure agent-to-agent payments.
 */
class EscrowRequest
{
    public readonly string $escrowId;

    public readonly Carbon $createdAt;

    public function __construct(
        public readonly string $buyerDid,
        public readonly string $sellerDid,
        public readonly float $amount,
        public readonly string $currency,
        public readonly array $conditions,
        public readonly array $releaseConditions,
        public readonly ?string $disputeResolutionDid = null,
        public readonly ?int $timeoutSeconds = 86400, // 24 hours default
        public readonly ?array $metadata = null,
        ?string $escrowId = null,
        ?Carbon $createdAt = null
    ) {
        $this->escrowId = $escrowId ?? 'escrow-' . Str::uuid()->toString();
        $this->createdAt = $createdAt ?? now();
    }

    /**
     * Check if all release conditions are met.
     */
    public function areReleaseConditionsMet(): bool
    {
        foreach ($this->releaseConditions as $condition) {
            if (! isset($this->conditions[$condition]) || $this->conditions[$condition] !== true) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if the escrow has a dispute resolver.
     */
    public function hasDisputeResolver(): bool
    {
        return $this->disputeResolutionDid !== null;
    }

    /**
     * Get the timeout timestamp.
     */
    public function getTimeoutAt(): Carbon
    {
        return $this->createdAt->copy()->addSeconds($this->timeoutSeconds ?? 86400);
    }

    /**
     * Check if the escrow has timed out.
     */
    public function isTimedOut(): bool
    {
        return now()->isAfter($this->getTimeoutAt());
    }

    /**
     * Convert to array for storage or serialization.
     */
    public function toArray(): array
    {
        return [
            'escrow_id'              => $this->escrowId,
            'buyer_did'              => $this->buyerDid,
            'seller_did'             => $this->sellerDid,
            'amount'                 => $this->amount,
            'currency'               => $this->currency,
            'conditions'             => $this->conditions,
            'release_conditions'     => $this->releaseConditions,
            'dispute_resolution_did' => $this->disputeResolutionDid,
            'timeout_seconds'        => $this->timeoutSeconds,
            'metadata'               => $this->metadata,
            'created_at'             => $this->createdAt->toIso8601String(),
            'timeout_at'             => $this->getTimeoutAt()->toIso8601String(),
        ];
    }
}
