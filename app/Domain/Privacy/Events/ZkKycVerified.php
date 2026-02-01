<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events;

use App\Domain\Privacy\Enums\ProofType;
use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a ZK KYC proof is successfully verified.
 */
class ZkKycVerified
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly ProofType $proofType,
        public readonly string $proofHash,
        public readonly DateTimeInterface $verifiedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'     => $this->userId,
            'proof_type'  => $this->proofType->value,
            'proof_hash'  => $this->proofHash,
            'verified_at' => $this->verifiedAt->format('c'),
        ];
    }
}
