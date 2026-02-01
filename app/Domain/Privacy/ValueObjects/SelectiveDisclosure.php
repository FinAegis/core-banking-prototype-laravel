<?php

declare(strict_types=1);

namespace App\Domain\Privacy\ValueObjects;

use App\Domain\Privacy\Enums\PrivacyLevel;
use JsonSerializable;

/**
 * Immutable value object representing a selective disclosure request/response.
 */
final readonly class SelectiveDisclosure implements JsonSerializable
{
    /**
     * @param array<string> $requestedClaims
     * @param array<string, mixed> $disclosedClaims
     * @param array<string> $provenClaims
     */
    public function __construct(
        public string $credentialId,
        public PrivacyLevel $privacyLevel,
        public array $requestedClaims,
        public array $disclosedClaims,
        public array $provenClaims,
        public string $issuerDid,
        public string $holderDid,
        public string $nonce,
    ) {
    }

    /**
     * Check if a specific claim was disclosed.
     */
    public function isClaimDisclosed(string $claim): bool
    {
        return isset($this->disclosedClaims[$claim]);
    }

    /**
     * Check if a specific claim was proven (but not revealed).
     */
    public function isClaimProven(string $claim): bool
    {
        return in_array($claim, $this->provenClaims, true);
    }

    /**
     * Get the value of a disclosed claim.
     */
    public function getDisclosedValue(string $claim, mixed $default = null): mixed
    {
        return $this->disclosedClaims[$claim] ?? $default;
    }

    /**
     * Calculate the privacy score (percentage of claims not fully disclosed).
     */
    public function getPrivacyScore(): float
    {
        $totalClaims = count($this->requestedClaims);
        if ($totalClaims === 0) {
            return 100.0;
        }

        $provenOnly = count(array_diff($this->provenClaims, array_keys($this->disclosedClaims)));

        return ($provenOnly / $totalClaims) * 100;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'credential_id'    => $this->credentialId,
            'privacy_level'    => $this->privacyLevel->value,
            'requested_claims' => $this->requestedClaims,
            'disclosed_claims' => array_keys($this->disclosedClaims),
            'proven_claims'    => $this->provenClaims,
            'issuer_did'       => $this->issuerDid,
            'holder_did'       => $this->holderDid,
            'nonce'            => $this->nonce,
            'privacy_score'    => $this->getPrivacyScore(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            credentialId: $data['credential_id'],
            privacyLevel: PrivacyLevel::from($data['privacy_level']),
            requestedClaims: $data['requested_claims'],
            disclosedClaims: $data['disclosed_claims'] ?? [],
            provenClaims: $data['proven_claims'] ?? [],
            issuerDid: $data['issuer_did'],
            holderDid: $data['holder_did'],
            nonce: $data['nonce'],
        );
    }
}
