<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Contracts;

use App\Domain\Commerce\Enums\AttestationType;
use App\Domain\Commerce\ValueObjects\PaymentAttestation;

/**
 * Interface for attestation services.
 */
interface AttestationServiceInterface
{
    /**
     * Create a new attestation.
     *
     * @param array<string, mixed> $claims
     */
    public function createAttestation(
        AttestationType $type,
        string $subjectId,
        array $claims,
    ): PaymentAttestation;

    /**
     * Verify an attestation's authenticity and validity.
     */
    public function verifyAttestation(PaymentAttestation $attestation): bool;

    /**
     * Get the attestation hash for on-chain anchoring.
     */
    public function getAttestationHash(PaymentAttestation $attestation): string;
}
