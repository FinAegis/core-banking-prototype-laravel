<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Contracts;

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\ValueObjects\ZkProof;

/**
 * Interface for zero-knowledge proof generation and verification.
 */
interface ZkProverInterface
{
    /**
     * Generate a zero-knowledge proof for a given statement.
     *
     * @param array<string, mixed> $privateInputs
     * @param array<string, mixed> $publicInputs
     */
    public function generateProof(
        ProofType $type,
        array $privateInputs,
        array $publicInputs,
    ): ZkProof;

    /**
     * Verify a zero-knowledge proof.
     */
    public function verifyProof(ZkProof $proof): bool;

    /**
     * Get the verifier contract address for a proof type.
     */
    public function getVerifierAddress(ProofType $type): string;

    /**
     * Check if a proof type is supported.
     */
    public function supportsProofType(ProofType $type): bool;

    /**
     * Get the provider name.
     */
    public function getProviderName(): string;
}
