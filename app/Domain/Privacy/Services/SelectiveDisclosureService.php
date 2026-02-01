<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Services;

use App\Domain\Privacy\Enums\PrivacyLevel;
use App\Domain\Privacy\ValueObjects\SelectiveDisclosure;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Selective Disclosure Service.
 *
 * Enables credential holders to selectively reveal only the claims
 * needed for a specific verification request, while proving the
 * validity of other claims without revealing their values.
 */
class SelectiveDisclosureService
{
    /**
     * Create a selective disclosure response.
     *
     * @param array<string, mixed> $fullCredential All credential claims
     * @param array<string> $requestedClaims Claims requested by verifier
     * @param array<string> $claimsToDisclose Claims to actually reveal
     */
    public function createDisclosure(
        string $credentialId,
        array $fullCredential,
        array $requestedClaims,
        array $claimsToDisclose,
        string $issuerDid,
        string $holderDid,
    ): SelectiveDisclosure {
        // Validate claims to disclose exist in credential
        foreach ($claimsToDisclose as $claim) {
            if (! isset($fullCredential[$claim])) {
                throw new RuntimeException("Claim '{$claim}' not found in credential");
            }
        }

        // Build disclosed claims
        $disclosedClaims = [];
        foreach ($claimsToDisclose as $claim) {
            $disclosedClaims[$claim] = $fullCredential[$claim];
        }

        // Claims that are proven but not disclosed
        $provenClaims = array_values(array_intersect($requestedClaims, array_keys($fullCredential)));

        // Determine privacy level
        $privacyLevel = $this->determinePrivacyLevel($requestedClaims, $claimsToDisclose);

        return new SelectiveDisclosure(
            credentialId: $credentialId,
            privacyLevel: $privacyLevel,
            requestedClaims: $requestedClaims,
            disclosedClaims: $disclosedClaims,
            provenClaims: $provenClaims,
            issuerDid: $issuerDid,
            holderDid: $holderDid,
            nonce: Str::random(32),
        );
    }

    /**
     * Create a zero-knowledge disclosure (prove claims without revealing).
     *
     * @param array<string, mixed> $fullCredential
     * @param array<string> $claimsToProve
     */
    public function createZkDisclosure(
        string $credentialId,
        array $fullCredential,
        array $claimsToProve,
        string $issuerDid,
        string $holderDid,
    ): SelectiveDisclosure {
        // Validate claims exist
        foreach ($claimsToProve as $claim) {
            if (! isset($fullCredential[$claim])) {
                throw new RuntimeException("Claim '{$claim}' not found in credential");
            }
        }

        return new SelectiveDisclosure(
            credentialId: $credentialId,
            privacyLevel: PrivacyLevel::ZERO_KNOWLEDGE,
            requestedClaims: $claimsToProve,
            disclosedClaims: [], // No claims disclosed
            provenClaims: $claimsToProve,
            issuerDid: $issuerDid,
            holderDid: $holderDid,
            nonce: Str::random(32),
        );
    }

    /**
     * Create a range proof disclosure.
     *
     * @param array<string, mixed> $fullCredential
     * @param array<string, array{min: int|float, max: int|float}> $rangeConstraints
     */
    public function createRangeProofDisclosure(
        string $credentialId,
        array $fullCredential,
        array $rangeConstraints,
        string $issuerDid,
        string $holderDid,
    ): SelectiveDisclosure {
        $provenClaims = [];

        foreach ($rangeConstraints as $claim => $range) {
            if (! isset($fullCredential[$claim])) {
                throw new RuntimeException("Claim '{$claim}' not found in credential");
            }

            $value = $fullCredential[$claim];
            if (! is_numeric($value)) {
                throw new RuntimeException("Claim '{$claim}' must be numeric for range proof");
            }

            if ($value < $range['min'] || $value > $range['max']) {
                throw new RuntimeException("Claim '{$claim}' value is outside the specified range");
            }

            $provenClaims[] = $claim;
        }

        return new SelectiveDisclosure(
            credentialId: $credentialId,
            privacyLevel: PrivacyLevel::RANGE_PROOF,
            requestedClaims: array_keys($rangeConstraints),
            disclosedClaims: [], // Only prove, don't reveal
            provenClaims: $provenClaims,
            issuerDid: $issuerDid,
            holderDid: $holderDid,
            nonce: Str::random(32),
        );
    }

    /**
     * Verify that a disclosure satisfies a request.
     *
     * @param array<string> $requiredClaims
     */
    public function verifyDisclosure(
        SelectiveDisclosure $disclosure,
        array $requiredClaims,
    ): bool {
        // Check that all required claims are either disclosed or proven
        foreach ($requiredClaims as $claim) {
            if (! $disclosure->isClaimDisclosed($claim) && ! $disclosure->isClaimProven($claim)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Calculate the minimum claims needed to satisfy a request.
     *
     * @param array<string> $availableClaims
     * @param array<string> $requiredClaims
     * @return array<string>
     */
    public function calculateMinimumDisclosure(
        array $availableClaims,
        array $requiredClaims,
    ): array {
        return array_values(array_intersect($availableClaims, $requiredClaims));
    }

    /**
     * @param array<string> $requestedClaims
     * @param array<string> $claimsToDisclose
     */
    private function determinePrivacyLevel(array $requestedClaims, array $claimsToDisclose): PrivacyLevel
    {
        if (empty($claimsToDisclose)) {
            return PrivacyLevel::ZERO_KNOWLEDGE;
        }

        $disclosureRatio = count($claimsToDisclose) / max(1, count($requestedClaims));

        if ($disclosureRatio >= 1.0) {
            return PrivacyLevel::FULL_DISCLOSURE;
        }

        return PrivacyLevel::SELECTIVE_DISCLOSURE;
    }
}
