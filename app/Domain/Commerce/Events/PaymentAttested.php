<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Enums\AttestationType;
use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a payment attestation is created.
 */
class PaymentAttested
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $attestationId,
        public readonly AttestationType $attestationType,
        public readonly string $subjectId,
        public readonly string $attestationHash,
        public readonly DateTimeInterface $attestedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attestation_id'   => $this->attestationId,
            'attestation_type' => $this->attestationType->value,
            'subject_id'       => $this->subjectId,
            'attestation_hash' => $this->attestationHash,
            'attested_at'      => $this->attestedAt->format('c'),
        ];
    }
}
