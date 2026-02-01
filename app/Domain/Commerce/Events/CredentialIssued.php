<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Enums\CredentialType;
use DateTimeInterface;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a verifiable credential is issued.
 */
class CredentialIssued
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly string $credentialId,
        public readonly CredentialType $credentialType,
        public readonly string $issuerId,
        public readonly string $subjectId,
        public readonly DateTimeInterface $issuedAt,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'credential_id'   => $this->credentialId,
            'credential_type' => $this->credentialType->value,
            'issuer_id'       => $this->issuerId,
            'subject_id'      => $this->subjectId,
            'issued_at'       => $this->issuedAt->format('c'),
        ];
    }
}
