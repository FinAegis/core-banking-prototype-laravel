<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Events;

use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a Proof of Innocence is generated.
 */
class ProofOfInnocenceGenerated
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $proofType,
        public readonly string $proofHash,
        public readonly DateTimeInterface $generatedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id'      => $this->userId,
            'proof_type'   => $this->proofType,
            'proof_hash'   => $this->proofHash,
            'generated_at' => $this->generatedAt->format('c'),
        ];
    }
}
