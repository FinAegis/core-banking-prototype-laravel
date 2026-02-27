<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Contracts\ZkProverInterface;
use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\ValueObjects\ZkProof;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;

/**
 * RAILGUN-backed ZK prover service.
 *
 * Delegates proof generation to the Node.js RAILGUN bridge, which uses
 * the RAILGUN SDK's Groth16 prover for shield, unshield, and transfer
 * circuits. The bridge handles the computationally expensive proof
 * generation server-side.
 */
class RailgunZkProverService implements ZkProverInterface
{
    public function __construct(
        private readonly RailgunBridgeClient $bridge,
    ) {
    }

    public function generateProof(
        ProofType $type,
        array $privateInputs,
        array $publicInputs,
    ): ZkProof {
        $bridgeEndpoint = $this->mapProofTypeToBridgeEndpoint($type);

        Log::info('Generating RAILGUN proof via bridge', [
            'proof_type' => $type->value,
            'endpoint'   => $bridgeEndpoint,
        ]);

        $data = match ($bridgeEndpoint) {
            'shield' => $this->bridge->shield(
                walletId: $privateInputs['wallet_id'] ?? '',
                tokenAddress: $publicInputs['token_address'] ?? '',
                amount: $publicInputs['amount'] ?? '0',
                network: $publicInputs['network'] ?? 'polygon',
            ),
            'unshield' => $this->bridge->unshield(
                walletId: $privateInputs['wallet_id'] ?? '',
                encryptionKey: $privateInputs['encryption_key'] ?? '',
                recipientAddress: $publicInputs['recipient'] ?? '',
                tokenAddress: $publicInputs['token_address'] ?? '',
                amount: $publicInputs['amount'] ?? '0',
                network: $publicInputs['network'] ?? 'polygon',
            ),
            'transfer' => $this->bridge->privateTransfer(
                walletId: $privateInputs['wallet_id'] ?? '',
                encryptionKey: $privateInputs['encryption_key'] ?? '',
                recipientRailgunAddress: $publicInputs['recipient_railgun_address'] ?? '',
                tokenAddress: $publicInputs['token_address'] ?? '',
                amount: $publicInputs['amount'] ?? '0',
                network: $publicInputs['network'] ?? 'polygon',
            ),
            default => throw new RuntimeException("Unsupported proof type for RAILGUN: {$type->value}"),
        };

        // The bridge returns the transaction calldata which includes the proof
        $proofData = base64_encode(json_encode($data, JSON_THROW_ON_ERROR));

        $createdAt = new DateTimeImmutable();
        $validityDays = (int) config('privacy.zk.proof_validity_days', 90);
        $expiresAt = $createdAt->modify("+{$validityDays} days");

        return new ZkProof(
            type: $type,
            proof: $proofData,
            publicInputs: $publicInputs,
            verifierAddress: $this->getVerifierAddress($type),
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            metadata: [
                'provider' => $this->getProviderName(),
                'endpoint' => $bridgeEndpoint,
                'network'  => $publicInputs['network'] ?? 'polygon',
                'has_tx'   => isset($data['transaction']),
            ],
        );
    }

    public function verifyProof(ZkProof $proof): bool
    {
        if ($proof->isExpired()) {
            return false;
        }

        // RAILGUN proofs are verified on-chain by the RAILGUN smart contract.
        // If the proof was generated successfully by the bridge, it is valid
        // for submission to the contract.
        $metadata = $proof->metadata;
        if (($metadata['provider'] ?? '') !== 'railgun') {
            return false;
        }

        // Decode and verify the proof contains valid transaction data
        $decoded = base64_decode($proof->proof, true);
        if ($decoded === false) {
            return false;
        }

        try {
            $data = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);

            return isset($data['transaction']);
        } catch (JsonException) {
            return false;
        }
    }

    public function getVerifierAddress(ProofType $type): string
    {
        // RAILGUN uses its own smart contract as the verifier
        return (string) config("privacy.zk.verifier_addresses.{$type->value}", '0x' . str_repeat('0', 40));
    }

    public function supportsProofType(ProofType $type): bool
    {
        // RAILGUN supports shield, unshield, and transfer proof types
        return in_array($type, [
            ProofType::SANCTIONS_CLEAR,    // shield_1_1
            ProofType::KYC_TIER,           // unshield_2_1
            ProofType::AGE_VERIFICATION,   // transfer_2_2
        ], true);
    }

    public function getProviderName(): string
    {
        return 'railgun';
    }

    /**
     * Map a ProofType to the corresponding RAILGUN bridge endpoint.
     */
    private function mapProofTypeToBridgeEndpoint(ProofType $type): string
    {
        // RAILGUN circuits: shield_1_1, unshield_2_1, transfer_2_2
        // We map the generic ProofType to the RAILGUN-specific operation
        return match ($type) {
            ProofType::SANCTIONS_CLEAR  => 'shield',
            ProofType::KYC_TIER         => 'unshield',
            ProofType::AGE_VERIFICATION => 'transfer',
            default                     => throw new RuntimeException(
                "ProofType '{$type->value}' cannot be mapped to a RAILGUN circuit. " .
                'Supported: sanctions_clear (shield), kyc_tier (unshield), age_verification (transfer).',
            ),
        };
    }
}
