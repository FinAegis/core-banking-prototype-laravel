<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Services;

use App\Domain\Commerce\Contracts\AttestationServiceInterface;
use App\Domain\Commerce\Enums\AttestationType;
use App\Domain\Commerce\Events\Broadcast\CommercePaymentConfirmed;
use App\Domain\Commerce\Events\PaymentAttested;
use App\Domain\Commerce\ValueObjects\PaymentAttestation;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for creating and verifying payment attestations.
 *
 * Payment attestations provide cryptographic proof of transactions
 * that can be:
 * - Verified offline
 * - Anchored on-chain
 * - Used for dispute resolution
 */
class PaymentAttestationService implements AttestationServiceInterface
{
    private readonly string $signingKey;

    public function __construct(
        private readonly string $issuerId = 'finaegis-attestor',
        ?string $signingKey = null,
    ) {
        $this->signingKey = $signingKey ?? hash('sha256', 'demo-signing-key');
    }

    /**
     * Create a new attestation.
     *
     * @param array<string, mixed> $claims
     */
    public function createAttestation(
        AttestationType $type,
        string $subjectId,
        array $claims,
    ): PaymentAttestation {
        // Validate required claims
        $missingClaims = $this->getMissingClaims($type, $claims);
        if (! empty($missingClaims)) {
            throw new InvalidArgumentException(
                'Missing required claims: ' . implode(', ', $missingClaims)
            );
        }

        $attestationId = Str::uuid()->toString();
        $issuedAt = new DateTimeImmutable();

        // Calculate expiry
        $validityDays = $type->defaultValidityDays();
        $expiresAt = $validityDays > 0
            ? $issuedAt->modify("+{$validityDays} days")
            : null;

        // Generate signature
        $signature = $this->generateSignature($attestationId, $type, $subjectId, $claims, $issuedAt);

        $attestation = new PaymentAttestation(
            attestationId: $attestationId,
            type: $type,
            issuerId: $this->issuerId,
            subjectId: $subjectId,
            claims: $claims,
            signature: $signature,
            issuedAt: $issuedAt,
            expiresAt: $expiresAt,
        );

        Event::dispatch(new PaymentAttested(
            attestationId: $attestationId,
            attestationType: $type,
            subjectId: $subjectId,
            attestationHash: $attestation->getAttestationHash(),
            attestedAt: $issuedAt,
        ));

        // Broadcast to merchant channel for real-time mobile updates
        $merchantId = $claims['merchant_id'] ?? $subjectId;
        CommercePaymentConfirmed::dispatch(
            merchantId: (string) $merchantId,
            attestationId: $attestationId,
            attestationType: $type->value,
            attestationHash: $attestation->getAttestationHash(),
            attestedAt: $issuedAt->format('c'),
        );

        return $attestation;
    }

    /**
     * Create a payment attestation specifically.
     */
    public function createPaymentAttestation(
        string $payerId,
        string $recipientId,
        string $amount,
        string $currency,
        ?string $transactionId = null,
    ): PaymentAttestation {
        return $this->createAttestation(
            type: AttestationType::PAYMENT,
            subjectId: $transactionId ?? Str::uuid()->toString(),
            claims: [
                'amount'       => $amount,
                'currency'     => $currency,
                'payer_id'     => $payerId,
                'recipient_id' => $recipientId,
                'timestamp'    => time(),
            ],
        );
    }

    /**
     * Create a delivery attestation.
     */
    public function createDeliveryAttestation(
        string $itemId,
        string $recipientId,
        string $location,
        ?int $deliveryTimestamp = null,
    ): PaymentAttestation {
        return $this->createAttestation(
            type: AttestationType::DELIVERY,
            subjectId: $itemId,
            claims: [
                'item_id'            => $itemId,
                'recipient_id'       => $recipientId,
                'delivery_timestamp' => $deliveryTimestamp ?? time(),
                'location'           => $location,
            ],
        );
    }

    /**
     * Create a receipt attestation.
     */
    public function createReceiptAttestation(
        string $transactionId,
        string $merchantId,
        string $amount,
        string $currency,
    ): PaymentAttestation {
        return $this->createAttestation(
            type: AttestationType::RECEIPT,
            subjectId: $transactionId,
            claims: [
                'transaction_id' => $transactionId,
                'amount'         => $amount,
                'merchant_id'    => $merchantId,
                'timestamp'      => time(),
                'currency'       => $currency,
            ],
        );
    }

    /**
     * Verify an attestation's authenticity and validity.
     */
    public function verifyAttestation(PaymentAttestation $attestation): bool
    {
        // Check if attestation is expired
        if (! $attestation->isValid()) {
            return false;
        }

        // Verify issuer
        if ($attestation->issuerId !== $this->issuerId) {
            return false;
        }

        // Verify signature
        $expectedSignature = $this->generateSignature(
            $attestation->attestationId,
            $attestation->type,
            $attestation->subjectId,
            $attestation->claims,
            $attestation->issuedAt,
        );

        return hash_equals($expectedSignature, $attestation->signature);
    }

    /**
     * Get the attestation hash for on-chain anchoring.
     */
    public function getAttestationHash(PaymentAttestation $attestation): string
    {
        return $attestation->getAttestationHash();
    }

    /**
     * Generate a Merkle root for multiple attestations.
     *
     * @param array<PaymentAttestation> $attestations
     */
    public function generateMerkleRoot(array $attestations): string
    {
        if (empty($attestations)) {
            return hash('sha256', '');
        }

        $hashes = array_map(
            fn (PaymentAttestation $att) => $att->getAttestationHash(),
            $attestations
        );

        return $this->computeMerkleRoot($hashes);
    }

    /**
     * Get missing required claims for an attestation type.
     *
     * @param array<string, mixed> $claims
     *
     * @return array<string>
     */
    private function getMissingClaims(AttestationType $type, array $claims): array
    {
        $required = $type->requiredClaims();
        $missing = [];

        foreach ($required as $claim) {
            if (! isset($claims[$claim])) {
                $missing[] = $claim;
            }
        }

        return $missing;
    }

    /**
     * Generate signature for attestation.
     *
     * @param array<string, mixed> $claims
     */
    private function generateSignature(
        string $attestationId,
        AttestationType $type,
        string $subjectId,
        array $claims,
        DateTimeInterface $issuedAt,
    ): string {
        $data = [
            'attestation_id' => $attestationId,
            'type'           => $type->value,
            'issuer_id'      => $this->issuerId,
            'subject_id'     => $subjectId,
            'claims_hash'    => hash('sha256', json_encode($claims, JSON_THROW_ON_ERROR)),
            'issued_at'      => $issuedAt->format('c'),
        ];

        return hash_hmac(
            'sha256',
            json_encode($data, JSON_THROW_ON_ERROR),
            $this->signingKey
        );
    }

    /**
     * Compute Merkle root from hashes.
     *
     * @param array<string> $hashes
     */
    private function computeMerkleRoot(array $hashes): string
    {
        if (count($hashes) === 1) {
            return $hashes[0];
        }

        // Pad to even number
        if (count($hashes) % 2 !== 0) {
            $hashes[] = end($hashes);
        }

        $newLevel = [];
        for ($i = 0; $i < count($hashes); $i += 2) {
            $newLevel[] = hash('sha256', $hashes[$i] . $hashes[$i + 1]);
        }

        return $this->computeMerkleRoot($newLevel);
    }
}
