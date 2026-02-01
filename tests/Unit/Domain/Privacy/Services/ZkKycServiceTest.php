<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Events\ZkKycVerificationFailed;
use App\Domain\Privacy\Events\ZkKycVerified;
use App\Domain\Privacy\Services\DemoZkProver;
use App\Domain\Privacy\Services\ZkKycService;
use App\Domain\Privacy\ValueObjects\ZkProof;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class);

describe('ZkKycService', function () {
    beforeEach(function () {
        $this->prover = new DemoZkProver();
        $this->service = new ZkKycService($this->prover);
        Event::fake();
    });

    describe('age verification', function () {
        it('generates age verification proof', function () {
            $proof = $this->service->generateAgeProof(
                userId: 'user-123',
                dateOfBirth: new DateTimeImmutable('1990-05-15'),
                minimumAge: 18,
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::AGE_VERIFICATION)
                ->and($proof->publicInputs)->toHaveKey('minimum_age')
                ->and($proof->publicInputs['minimum_age'])->toBe(18);
        });

        it('includes user hash in public inputs', function () {
            $proof = $this->service->generateAgeProof(
                userId: 'user-123',
                dateOfBirth: new DateTimeImmutable('1990-05-15'),
            );

            expect($proof->publicInputs)->toHaveKey('user_id_hash')
                ->and($proof->publicInputs['user_id_hash'])->toBe(hash('sha256', 'user-123'));
        });

        it('generates valid proof for user over minimum age', function () {
            $proof = $this->service->generateAgeProof(
                userId: 'user-123',
                dateOfBirth: new DateTimeImmutable('1990-01-01'),
                minimumAge: 18,
            );

            $isValid = $this->prover->verifyProof($proof);

            expect($isValid)->toBeTrue();
        });
    });

    describe('residency verification', function () {
        it('generates residency proof', function () {
            $proof = $this->service->generateResidencyProof(
                userId: 'user-123',
                country: 'DE',
                region: 'Bavaria',
                allowedCountries: ['DE', 'FR', 'IT'],
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::RESIDENCY)
                ->and($proof->publicInputs)->toHaveKey('allowed_countries');
        });

        it('includes region in proof', function () {
            $proof = $this->service->generateResidencyProof(
                userId: 'user-123',
                country: 'US',
                region: 'California',
            );

            expect($proof)->toBeInstanceOf(ZkProof::class);
        });
    });

    describe('KYC tier verification', function () {
        it('generates KYC tier proof', function () {
            $proof = $this->service->generateKycTierProof(
                userId: 'user-123',
                actualTier: 3,
                kycProvider: 'jumio',
                minimumTier: 2,
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::KYC_TIER)
                ->and($proof->publicInputs)->toHaveKey('minimum_tier')
                ->and($proof->publicInputs['minimum_tier'])->toBe(2);
        });
    });

    describe('sanctions clearance', function () {
        it('generates sanctions clearance proof', function () {
            $proof = $this->service->generateSanctionsClearanceProof(
                userId: 'user-123',
                fullName: 'John Doe',
                dateOfBirth: new DateTimeImmutable('1990-01-01'),
                sanctionsListHash: hash('sha256', 'sanctions-list-2026'),
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::SANCTIONS_CLEAR)
                ->and($proof->publicInputs)->toHaveKey('sanctions_list_root');
        });
    });

    describe('accredited investor', function () {
        it('generates accredited investor proof', function () {
            $proof = $this->service->generateAccreditedInvestorProof(
                userId: 'user-123',
                isAccredited: true,
                jurisdiction: 'US',
                accreditationProvider: 'verifyinvestor',
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::ACCREDITED_INVESTOR)
                ->and($proof->publicInputs)->toHaveKey('jurisdiction')
                ->and($proof->publicInputs['jurisdiction'])->toBe('US');
        });

        it('throws for non-accredited user', function () {
            expect(fn () => $this->service->generateAccreditedInvestorProof(
                userId: 'user-123',
                isAccredited: false,
                jurisdiction: 'US',
            ))->toThrow(RuntimeException::class);
        });
    });

    describe('proof verification', function () {
        it('verifies valid proof and dispatches event', function () {
            $proof = $this->service->generateAgeProof(
                userId: 'user-123',
                dateOfBirth: new DateTimeImmutable('1990-01-01'),
            );

            $isValid = $this->service->verifyKycProof($proof, 'user-123');

            expect($isValid)->toBeTrue();
            Event::assertDispatched(ZkKycVerified::class);
        });

        it('dispatches failed event for invalid proof', function () {
            $invalidProof = new ZkProof(
                type: ProofType::AGE_VERIFICATION,
                proof: 'invalid',
                publicInputs: [],
                verifierAddress: '0x0',
                createdAt: new DateTimeImmutable(),
                expiresAt: new DateTimeImmutable('+90 days'),
            );

            $isValid = $this->service->verifyKycProof($invalidProof, 'user-123');

            expect($isValid)->toBeFalse();
            Event::assertDispatched(ZkKycVerificationFailed::class);
        });

        it('dispatches failed event for expired proof', function () {
            $expiredProof = new ZkProof(
                type: ProofType::AGE_VERIFICATION,
                proof: base64_encode(json_encode([
                    'statement'  => 'test',
                    'commitment' => 'test',
                    'challenge'  => 'test',
                    'response'   => 'test',
                ])),
                publicInputs: [],
                verifierAddress: '0x0',
                createdAt: new DateTimeImmutable('-100 days'),
                expiresAt: new DateTimeImmutable('-10 days'),
            );

            $isValid = $this->service->verifyKycProof($expiredProof, 'user-123');

            expect($isValid)->toBeFalse();
            Event::assertDispatched(ZkKycVerificationFailed::class, function ($event) {
                return str_contains($event->reason, 'expired');
            });
        });
    });

    describe('prover access', function () {
        it('returns the prover', function () {
            $prover = $this->service->getProver();

            expect($prover)->toBeInstanceOf(DemoZkProver::class);
        });
    });
});
