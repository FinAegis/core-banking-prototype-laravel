<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events;

use App\Domain\Privacy\Enums\ProofType;
use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a ZK KYC proof verification fails.
 */
class ZkKycVerificationFailed
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly ProofType $proofType,
        public readonly string $reason,
        public readonly DateTimeInterface $failedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'    => $this->userId,
            'proof_type' => $this->proofType->value,
            'reason'     => $this->reason,
            'failed_at'  => $this->failedAt->format('c'),
        ];
    }
}
