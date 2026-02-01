<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\ZkProverInterface;
use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\ValueObjects\ZkProof;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Demo implementation of ZK prover for development and testing.
 *
 * In production, this would integrate with actual ZK proof systems
 * like Circom/SnarkJS, Polygon ID, or Galactica Network.
 */
class DemoZkProver implements ZkProverInterface
{
    private const DEMO_VERIFIER_ADDRESS = '0x0000000000000000000000000000000000000000';

    private const DEFAULT_PROOF_VALIDITY_DAYS = 90;

    /**
     * @param array<string, mixed> $privateInputs
     * @param array<string, mixed> $publicInputs
     */
    public function generateProof(
        ProofType $type,
        array $privateInputs,
        array $publicInputs,
    ): ZkProof {
        // Validate required inputs
        $this->validateInputs($type, $privateInputs, $publicInputs);

        // Generate demo proof (in production, this would run actual ZK circuits)
        $proofData = $this->generateDemoProofData($type, $privateInputs, $publicInputs);

        $createdAt = new DateTimeImmutable();
        $expiresAt = $createdAt->modify('+' . self::DEFAULT_PROOF_VALIDITY_DAYS . ' days');

        return new ZkProof(
            type: $type,
            proof: $proofData,
            publicInputs: $this->sanitizePublicInputs($publicInputs),
            verifierAddress: $this->getVerifierAddress($type),
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            metadata: [
                'provider'        => $this->getProviderName(),
                'circuit_version' => '1.0.0',
            ],
        );
    }

    public function verifyProof(ZkProof $proof): bool
    {
        // Check expiration
        if ($proof->isExpired()) {
            return false;
        }

        // Verify proof structure
        if (empty($proof->proof)) {
            return false;
        }

        // In demo mode, verify the proof format
        $decoded = json_decode(base64_decode($proof->proof), true);
        if ($decoded === null) {
            return false;
        }

        // Verify the proof contains required fields
        if (! isset($decoded['statement'], $decoded['commitment'], $decoded['challenge'], $decoded['response'])) {
            return false;
        }

        // Demo verification: check signature
        $expectedSignature = $this->generateCommitment($decoded['statement'], $proof->publicInputs);

        return hash_equals($expectedSignature, $decoded['commitment']);
    }

    public function getVerifierAddress(ProofType $type): string
    {
        // In production, return actual deployed verifier contract addresses
        return self::DEMO_VERIFIER_ADDRESS;
    }

    public function supportsProofType(ProofType $type): bool
    {
        return true; // Demo supports all proof types
    }

    public function getProviderName(): string
    {
        return 'demo';
    }

    /**
     * @param array<string, mixed> $privateInputs
     * @param array<string, mixed> $publicInputs
     */
    private function validateInputs(ProofType $type, array $privateInputs, array $publicInputs): void
    {
        $requiredClaims = $type->requiredClaims();

        foreach ($requiredClaims as $claim) {
            if (! isset($privateInputs[$claim]) && ! isset($publicInputs[$claim])) {
                throw new InvalidArgumentException("Missing required input: {$claim}");
            }
        }
    }

    /**
     * @param array<string, mixed> $privateInputs
     * @param array<string, mixed> $publicInputs
     */
    private function generateDemoProofData(ProofType $type, array $privateInputs, array $publicInputs): string
    {
        // Generate a deterministic but secure-looking proof for demo purposes
        $statement = $this->generateStatement($type, $publicInputs);
        $commitment = $this->generateCommitment($statement, $publicInputs);
        $challenge = hash('sha256', $commitment . random_bytes(16));
        $response = hash('sha256', $challenge . json_encode($privateInputs));

        return base64_encode(json_encode([
            'statement'  => $statement,
            'commitment' => $commitment,
            'challenge'  => $challenge,
            'response'   => $response,
        ]) ?: '{}');
    }

    /**
     * @param array<string, mixed> $publicInputs
     */
    private function generateStatement(ProofType $type, array $publicInputs): string
    {
        return match ($type) {
            ProofType::AGE_VERIFICATION => sprintf(
                'age >= %d',
                $publicInputs['minimum_age'] ?? 18
            ),
            ProofType::RESIDENCY => sprintf(
                'country IN [%s]',
                implode(',', (array) ($publicInputs['allowed_countries'] ?? ['*']))
            ),
            ProofType::KYC_TIER => sprintf(
                'kyc_tier >= %d',
                $publicInputs['minimum_tier'] ?? 1
            ),
            ProofType::ACCREDITED_INVESTOR => 'accredited_investor = true',
            ProofType::SANCTIONS_CLEAR     => 'NOT IN sanctions_list',
            ProofType::INCOME_RANGE        => sprintf(
                'income IN [%d, %d]',
                $publicInputs['income_range_min'] ?? 0,
                $publicInputs['income_range_max'] ?? PHP_INT_MAX
            ),
            ProofType::CUSTOM => $publicInputs['custom_statement'] ?? 'custom_proof',
        };
    }

    /**
     * @param array<string, mixed> $publicInputs
     */
    private function generateCommitment(string $statement, array $publicInputs): string
    {
        return hash('sha256', $statement . json_encode($publicInputs));
    }

    /**
     * @param array<string, mixed> $publicInputs
     * @return array<string, mixed>
     */
    private function sanitizePublicInputs(array $publicInputs): array
    {
        // Remove any potentially sensitive data from public inputs
        $sanitized = $publicInputs;
        unset($sanitized['password'], $sanitized['secret'], $sanitized['private_key']);

        return $sanitized;
    }
}
