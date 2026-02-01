<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\ZkProverInterface;
use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Events\ProofOfInnocenceGenerated;
use App\Domain\Privacy\ValueObjects\ZkProof;
use Illuminate\Support\Facades\Event;

/**
 * Proof of Innocence Service.
 *
 * Generates proofs that demonstrate compliance with regulatory requirements
 * (e.g., not on sanctions lists, funds not from illicit sources) without
 * revealing the underlying transaction history or identity details.
 *
 * Inspired by RAILGUN's Proof of Innocence system.
 */
class ProofOfInnocenceService
{
    public function __construct(
        private readonly ZkProverInterface $prover,
    ) {
    }

    /**
     * Generate a proof that user's funds are not from sanctioned addresses.
     *
     * @param array<string> $transactionHistory Array of transaction hashes
     */
    public function generateSanctionsClearanceProof(
        string $userId,
        array $transactionHistory,
        string $sanctionsListMerkleRoot,
    ): ZkProof {
        $privateInputs = [
            'identity_hash'       => hash('sha256', $userId),
            'sanctions_list_hash' => $sanctionsListMerkleRoot,
            'transaction_hashes'  => $transactionHistory,
        ];

        $publicInputs = [
            'user_commitment'     => $this->generateUserCommitment($userId),
            'sanctions_list_root' => $sanctionsListMerkleRoot,
            'proof_timestamp'     => time(),
        ];

        $proof = $this->prover->generateProof(
            ProofType::SANCTIONS_CLEAR,
            $privateInputs,
            $publicInputs,
        );

        Event::dispatch(new ProofOfInnocenceGenerated(
            userId: $userId,
            proofType: 'sanctions_clearance',
            proofHash: $proof->getProofHash(),
            generatedAt: now(),
        ));

        return $proof;
    }

    /**
     * Generate a proof that transaction does not involve illicit sources.
     *
     * @param array<string> $sourceAddresses
     * @param array<string> $illicitAddressesMerkleProof
     */
    public function generateSourceClearanceProof(
        string $transactionId,
        array $sourceAddresses,
        string $illicitListMerkleRoot,
        array $illicitAddressesMerkleProof,
    ): ZkProof {
        $privateInputs = [
            'identity_hash'       => hash('sha256', $transactionId),
            'sanctions_list_hash' => $illicitListMerkleRoot,
            'source_addresses'    => $sourceAddresses,
            'merkle_proof'        => $illicitAddressesMerkleProof,
        ];

        $publicInputs = [
            'transaction_commitment' => $this->generateTransactionCommitment($transactionId),
            'illicit_list_root'      => $illicitListMerkleRoot,
            'proof_timestamp'        => time(),
        ];

        return $this->prover->generateProof(
            ProofType::SANCTIONS_CLEAR,
            $privateInputs,
            $publicInputs,
        );
    }

    /**
     * Verify a proof of innocence.
     */
    public function verifyProofOfInnocence(
        ZkProof $proof,
        string $currentSanctionsListRoot,
    ): ProofOfInnocenceResult {
        // Check that the proof was generated against the current sanctions list
        $proofSanctionsRoot = $proof->getPublicInput('sanctions_list_root');

        if ($proofSanctionsRoot !== $currentSanctionsListRoot) {
            return new ProofOfInnocenceResult(
                valid: false,
                reason: 'Proof generated against outdated sanctions list',
            );
        }

        // Verify the ZK proof itself
        if (! $this->prover->verifyProof($proof)) {
            return new ProofOfInnocenceResult(
                valid: false,
                reason: 'Invalid ZK proof',
            );
        }

        // Check proof expiration
        if ($proof->isExpired()) {
            return new ProofOfInnocenceResult(
                valid: false,
                reason: 'Proof has expired',
            );
        }

        return new ProofOfInnocenceResult(
            valid: true,
            reason: null,
            validUntil: $proof->expiresAt,
        );
    }

    /**
     * Check if a new proof is needed (e.g., sanctions list updated).
     */
    public function isProofRenewalNeeded(
        ZkProof $existingProof,
        string $currentSanctionsListRoot,
        int $renewalThresholdDays = 30,
    ): bool {
        // Check if sanctions list has been updated
        if ($existingProof->getPublicInput('sanctions_list_root') !== $currentSanctionsListRoot) {
            return true;
        }

        // Check if proof is expiring soon
        $remainingDays = $existingProof->getRemainingValiditySeconds() / 86400;

        return $remainingDays < $renewalThresholdDays;
    }

    private function generateUserCommitment(string $userId): string
    {
        return hash('sha256', 'user_commitment:' . $userId . ':' . time());
    }

    private function generateTransactionCommitment(string $transactionId): string
    {
        return hash('sha256', 'tx_commitment:' . $transactionId);
    }
}
