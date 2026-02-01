<?php

declare(strict_types=1);

namespace App\Domain\Commerce\ValueObjects;

use App\Domain\Commerce\Enums\AttestationType;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Represents a cryptographic attestation of a payment or transaction.
 *
 * Payment attestations provide:
 * - Verifiable proof of payment
 * - On-chain anchorable receipts
 * - Privacy-preserving payment records
 */
final readonly class PaymentAttestation
{
    public function __construct(
        public string $attestationId,
        public AttestationType $type,
        public string $issuerId,
        public string $subjectId,
        /** @var array<string, mixed> */
        public array $claims,
        public string $signature,
        public DateTimeInterface $issuedAt,
        public ?DateTimeInterface $expiresAt = null,
        /** @var array<string, mixed> */
        public array $metadata = [],
    ) {
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    public function isValid(): bool
    {
        return ! $this->isExpired();
    }

    /**
     * Get the attestation hash for on-chain anchoring.
     */
    public function getAttestationHash(): string
    {
        $data = [
            'attestation_id' => $this->attestationId,
            'type'           => $this->type->value,
            'issuer_id'      => $this->issuerId,
            'subject_id'     => $this->subjectId,
            'claims_hash'    => hash('sha256', json_encode($this->claims, JSON_THROW_ON_ERROR)),
            'issued_at'      => $this->issuedAt->format('c'),
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    /**
     * Get a specific claim value.
     */
    public function getClaim(string $key, mixed $default = null): mixed
    {
        return $this->claims[$key] ?? $default;
    }

    /**
     * Check if all required claims are present.
     */
    public function hasRequiredClaims(): bool
    {
        $requiredClaims = $this->type->requiredClaims();

        foreach ($requiredClaims as $claim) {
            if (! isset($this->claims[$claim])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string>
     */
    public function getMissingClaims(): array
    {
        $requiredClaims = $this->type->requiredClaims();
        $missing = [];

        foreach ($requiredClaims as $claim) {
            if (! isset($this->claims[$claim])) {
                $missing[] = $claim;
            }
        }

        return $missing;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'attestation_id' => $this->attestationId,
            'type'           => $this->type->value,
            'issuer_id'      => $this->issuerId,
            'subject_id'     => $this->subjectId,
            'claims'         => $this->claims,
            'signature'      => $this->signature,
            'issued_at'      => $this->issuedAt->format('c'),
            'expires_at'     => $this->expiresAt?->format('c'),
            'metadata'       => $this->metadata,
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            attestationId: $data['attestation_id'],
            type: AttestationType::from($data['type']),
            issuerId: $data['issuer_id'],
            subjectId: $data['subject_id'],
            claims: $data['claims'],
            signature: $data['signature'],
            issuedAt: new DateTimeImmutable($data['issued_at']),
            expiresAt: isset($data['expires_at']) ? new DateTimeImmutable($data['expires_at']) : null,
            metadata: $data['metadata'] ?? [],
        );
    }
}
