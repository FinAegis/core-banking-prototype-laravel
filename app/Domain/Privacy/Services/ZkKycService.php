<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\ZkProverInterface;
use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Events\ZkKycVerificationFailed;
use App\Domain\Privacy\Events\ZkKycVerified;
use App\Domain\Privacy\ValueObjects\ZkProof;
use DateTimeInterface;
use Illuminate\Support\Facades\Event;
use RuntimeException;

/**
 * Zero-Knowledge KYC Service.
 *
 * Provides privacy-preserving KYC verification by generating ZK proofs
 * that verify KYC claims without revealing the underlying personal data.
 *
 * Example: Prove "user is over 18" without revealing actual date of birth.
 */
class ZkKycService
{
    public function __construct(
        private readonly ZkProverInterface $prover,
    ) {
    }

    /**
     * Generate a ZK proof for age verification.
     *
     * @param int $minimumAge The minimum age to prove
     */
    public function generateAgeProof(
        string $userId,
        DateTimeInterface $dateOfBirth,
        int $minimumAge = 18,
    ): ZkProof {
        $privateInputs = [
            'date_of_birth' => $dateOfBirth->format('Y-m-d'),
            'minimum_age'   => $minimumAge,
        ];

        $publicInputs = [
            'user_id_hash'           => hash('sha256', $userId),
            'minimum_age'            => $minimumAge,
            'verification_timestamp' => time(),
        ];

        return $this->prover->generateProof(
            ProofType::AGE_VERIFICATION,
            $privateInputs,
            $publicInputs,
        );
    }

    /**
     * Generate a ZK proof for residency verification.
     *
     * @param array<string> $allowedCountries
     */
    public function generateResidencyProof(
        string $userId,
        string $country,
        ?string $region = null,
        array $allowedCountries = [],
    ): ZkProof {
        $privateInputs = [
            'country' => $country,
            'region'  => $region,
        ];

        $publicInputs = [
            'user_id_hash'           => hash('sha256', $userId),
            'allowed_countries'      => $allowedCountries,
            'verification_timestamp' => time(),
        ];

        return $this->prover->generateProof(
            ProofType::RESIDENCY,
            $privateInputs,
            $publicInputs,
        );
    }

    /**
     * Generate a ZK proof for KYC tier verification.
     *
     * @param int $minimumTier The minimum KYC tier to prove
     */
    public function generateKycTierProof(
        string $userId,
        int $actualTier,
        string $kycProvider,
        int $minimumTier = 1,
    ): ZkProof {
        $privateInputs = [
            'kyc_tier'     => $actualTier,
            'kyc_provider' => $kycProvider,
        ];

        $publicInputs = [
            'user_id_hash'           => hash('sha256', $userId),
            'minimum_tier'           => $minimumTier,
            'verification_timestamp' => time(),
        ];

        return $this->prover->generateProof(
            ProofType::KYC_TIER,
            $privateInputs,
            $publicInputs,
        );
    }

    /**
     * Generate a ZK proof for sanctions clearance.
     */
    public function generateSanctionsClearanceProof(
        string $userId,
        string $fullName,
        DateTimeInterface $dateOfBirth,
        string $sanctionsListHash,
    ): ZkProof {
        // Generate identity hash for comparison
        $identityHash = hash('sha256', $fullName . $dateOfBirth->format('Y-m-d'));

        $privateInputs = [
            'identity_hash'       => $identityHash,
            'sanctions_list_hash' => $sanctionsListHash,
        ];

        $publicInputs = [
            'user_id_hash'           => hash('sha256', $userId),
            'sanctions_list_root'    => $sanctionsListHash,
            'verification_timestamp' => time(),
        ];

        return $this->prover->generateProof(
            ProofType::SANCTIONS_CLEAR,
            $privateInputs,
            $publicInputs,
        );
    }

    /**
     * Generate a ZK proof for accredited investor status.
     */
    public function generateAccreditedInvestorProof(
        string $userId,
        bool $isAccredited,
        string $jurisdiction,
        ?string $accreditationProvider = null,
    ): ZkProof {
        if (! $isAccredited) {
            throw new RuntimeException('Cannot generate proof for non-accredited investor');
        }

        $privateInputs = [
            'accreditation_status' => $isAccredited,
            'jurisdiction'         => $jurisdiction,
            'provider'             => $accreditationProvider,
        ];

        $publicInputs = [
            'user_id_hash'           => hash('sha256', $userId),
            'jurisdiction'           => $jurisdiction,
            'verification_timestamp' => time(),
        ];

        return $this->prover->generateProof(
            ProofType::ACCREDITED_INVESTOR,
            $privateInputs,
            $publicInputs,
        );
    }

    /**
     * Verify a ZK KYC proof and log the result.
     */
    public function verifyKycProof(
        ZkProof $proof,
        string $userId,
        ?string $requestId = null,
    ): bool {
        $isValid = $this->prover->verifyProof($proof);

        $context = [
            'user_id'    => $userId,
            'request_id' => $requestId,
            'proof_type' => $proof->type->value,
            'proof_hash' => $proof->getProofHash(),
        ];

        if ($isValid) {
            Event::dispatch(new ZkKycVerified(
                userId: $userId,
                proofType: $proof->type,
                proofHash: $proof->getProofHash(),
                verifiedAt: now(),
            ));
        } else {
            Event::dispatch(new ZkKycVerificationFailed(
                userId: $userId,
                proofType: $proof->type,
                reason: $proof->isExpired() ? 'Proof expired' : 'Invalid proof',
                failedAt: now(),
            ));
        }

        return $isValid;
    }

    /**
     * Get the ZK prover being used.
     */
    public function getProver(): ZkProverInterface
    {
        return $this->prover;
    }
}
