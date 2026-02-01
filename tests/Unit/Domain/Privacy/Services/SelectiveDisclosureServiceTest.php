<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\PrivacyLevel;
use App\Domain\Privacy\Services\SelectiveDisclosureService;
use App\Domain\Privacy\ValueObjects\SelectiveDisclosure;

describe('SelectiveDisclosureService', function () {
    beforeEach(function () {
        $this->service = new SelectiveDisclosureService();
        $this->testCredential = [
            'full_name'     => 'John Doe',
            'date_of_birth' => '1990-05-15',
            'country'       => 'Germany',
            'kyc_tier'      => 3,
            'email'         => 'john@example.com',
            'phone'         => '+49123456789',
        ];
    });

    describe('selective disclosure', function () {
        it('creates disclosure with selected claims', function () {
            $disclosure = $this->service->createDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                requestedClaims: ['full_name', 'country', 'kyc_tier'],
                claimsToDisclose: ['country'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            expect($disclosure)->toBeInstanceOf(SelectiveDisclosure::class)
                ->and($disclosure->credentialId)->toBe('cred-123')
                ->and($disclosure->privacyLevel)->toBe(PrivacyLevel::SELECTIVE_DISCLOSURE)
                ->and($disclosure->disclosedClaims)->toHaveKey('country')
                ->and($disclosure->disclosedClaims['country'])->toBe('Germany')
                ->and($disclosure->isClaimDisclosed('full_name'))->toBeFalse();
        });

        it('determines full disclosure level', function () {
            $disclosure = $this->service->createDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                requestedClaims: ['country', 'kyc_tier'],
                claimsToDisclose: ['country', 'kyc_tier'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            expect($disclosure->privacyLevel)->toBe(PrivacyLevel::FULL_DISCLOSURE);
        });

        it('throws for non-existent claim', function () {
            expect(fn () => $this->service->createDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                requestedClaims: ['nonexistent'],
                claimsToDisclose: ['nonexistent'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            ))->toThrow(RuntimeException::class);
        });

        it('generates unique nonce', function () {
            $disclosure1 = $this->service->createDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                requestedClaims: ['country'],
                claimsToDisclose: ['country'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            $disclosure2 = $this->service->createDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                requestedClaims: ['country'],
                claimsToDisclose: ['country'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            expect($disclosure1->nonce)->not->toBe($disclosure2->nonce);
        });
    });

    describe('zero knowledge disclosure', function () {
        it('creates ZK disclosure without revealing values', function () {
            $disclosure = $this->service->createZkDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                claimsToProve: ['kyc_tier', 'country'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            expect($disclosure->privacyLevel)->toBe(PrivacyLevel::ZERO_KNOWLEDGE)
                ->and($disclosure->disclosedClaims)->toBeEmpty()
                ->and($disclosure->provenClaims)->toContain('kyc_tier')
                ->and($disclosure->provenClaims)->toContain('country');
        });

        it('throws for non-existent claim in ZK proof', function () {
            expect(fn () => $this->service->createZkDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                claimsToProve: ['nonexistent'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            ))->toThrow(RuntimeException::class);
        });
    });

    describe('range proof disclosure', function () {
        it('creates range proof for numeric claims', function () {
            $disclosure = $this->service->createRangeProofDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                rangeConstraints: [
                    'kyc_tier' => ['min' => 1, 'max' => 5],
                ],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            expect($disclosure->privacyLevel)->toBe(PrivacyLevel::RANGE_PROOF)
                ->and($disclosure->disclosedClaims)->toBeEmpty()
                ->and($disclosure->provenClaims)->toContain('kyc_tier');
        });

        it('throws for non-numeric claim in range proof', function () {
            expect(fn () => $this->service->createRangeProofDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                rangeConstraints: [
                    'full_name' => ['min' => 0, 'max' => 100], // String value
                ],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            ))->toThrow(RuntimeException::class, 'must be numeric');
        });

        it('throws when value is outside range', function () {
            expect(fn () => $this->service->createRangeProofDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                rangeConstraints: [
                    'kyc_tier' => ['min' => 5, 'max' => 10], // Actual value is 3
                ],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            ))->toThrow(RuntimeException::class, 'outside the specified range');
        });
    });

    describe('disclosure verification', function () {
        it('verifies disclosure satisfies requirements', function () {
            $disclosure = $this->service->createDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                requestedClaims: ['country', 'kyc_tier'],
                claimsToDisclose: ['country'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            $isValid = $this->service->verifyDisclosure($disclosure, ['country', 'kyc_tier']);

            expect($isValid)->toBeTrue();
        });

        it('fails verification when required claim is missing', function () {
            $disclosure = $this->service->createDisclosure(
                credentialId: 'cred-123',
                fullCredential: $this->testCredential,
                requestedClaims: ['country'],
                claimsToDisclose: ['country'],
                issuerDid: 'did:example:issuer',
                holderDid: 'did:example:holder',
            );

            $isValid = $this->service->verifyDisclosure($disclosure, ['country', 'email']);

            expect($isValid)->toBeFalse();
        });
    });

    describe('minimum disclosure calculation', function () {
        it('calculates minimum claims needed', function () {
            $minimum = $this->service->calculateMinimumDisclosure(
                availableClaims: ['full_name', 'country', 'kyc_tier', 'email'],
                requiredClaims: ['country', 'kyc_tier', 'phone'],
            );

            expect($minimum)->toContain('country')
                ->and($minimum)->toContain('kyc_tier')
                ->and($minimum)->not->toContain('phone')
                ->and($minimum)->not->toContain('full_name');
        });
    });
});
