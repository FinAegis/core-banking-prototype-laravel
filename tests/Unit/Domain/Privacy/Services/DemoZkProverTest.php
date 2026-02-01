<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Services\DemoZkProver;
use App\Domain\Privacy\ValueObjects\ZkProof;

uses(Tests\TestCase::class);

describe('DemoZkProver', function () {
    beforeEach(function () {
        $this->prover = new DemoZkProver();
    });

    describe('proof generation', function () {
        it('generates age verification proof', function () {
            $proof = $this->prover->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01', 'minimum_age' => 18],
                ['user_id_hash'  => hash('sha256', 'user-123'), 'minimum_age' => 18],
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::AGE_VERIFICATION)
                ->and($proof->proof)->not->toBeEmpty()
                ->and($proof->isExpired())->toBeFalse();
        });

        it('generates residency proof', function () {
            $proof = $this->prover->generateProof(
                ProofType::RESIDENCY,
                ['country'      => 'US', 'region' => 'CA'],
                ['user_id_hash' => hash('sha256', 'user-123')],
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::RESIDENCY);
        });

        it('generates KYC tier proof', function () {
            $proof = $this->prover->generateProof(
                ProofType::KYC_TIER,
                ['kyc_tier'     => 3, 'kyc_provider' => 'jumio'],
                ['user_id_hash' => hash('sha256', 'user-123'), 'minimum_tier' => 2],
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::KYC_TIER);
        });

        it('generates sanctions clearance proof', function () {
            $proof = $this->prover->generateProof(
                ProofType::SANCTIONS_CLEAR,
                ['identity_hash' => 'hash', 'sanctions_list_hash' => 'list-hash'],
                ['user_id_hash'  => hash('sha256', 'user-123')],
            );

            expect($proof)->toBeInstanceOf(ZkProof::class)
                ->and($proof->type)->toBe(ProofType::SANCTIONS_CLEAR);
        });

        it('sets correct expiration', function () {
            $proof = $this->prover->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01', 'minimum_age' => 18],
                [],
            );

            $expectedExpiry = (new DateTimeImmutable())->modify('+90 days');
            $diff = abs($proof->expiresAt->getTimestamp() - $expectedExpiry->getTimestamp());

            expect($diff)->toBeLessThan(5); // Within 5 seconds
        });

        it('includes metadata', function () {
            $proof = $this->prover->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01', 'minimum_age' => 18],
                [],
            );

            expect($proof->metadata)->toHaveKey('provider')
                ->and($proof->metadata['provider'])->toBe('demo');
        });

        it('throws for missing required inputs', function () {
            expect(fn () => $this->prover->generateProof(
                ProofType::AGE_VERIFICATION,
                [], // Missing required inputs
                [],
            ))->toThrow(InvalidArgumentException::class);
        });
    });

    describe('proof verification', function () {
        it('verifies valid proof', function () {
            $proof = $this->prover->generateProof(
                ProofType::AGE_VERIFICATION,
                ['date_of_birth' => '1990-01-01', 'minimum_age' => 18],
                ['user_id_hash'  => hash('sha256', 'user-123'), 'minimum_age' => 18],
            );

            $isValid = $this->prover->verifyProof($proof);

            expect($isValid)->toBeTrue();
        });

        it('rejects expired proof', function () {
            $proof = new ZkProof(
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

            $isValid = $this->prover->verifyProof($proof);

            expect($isValid)->toBeFalse();
        });

        it('rejects proof with invalid format', function () {
            $proof = new ZkProof(
                type: ProofType::AGE_VERIFICATION,
                proof: 'invalid-proof',
                publicInputs: [],
                verifierAddress: '0x0',
                createdAt: new DateTimeImmutable(),
                expiresAt: new DateTimeImmutable('+90 days'),
            );

            $isValid = $this->prover->verifyProof($proof);

            expect($isValid)->toBeFalse();
        });

        it('rejects proof with missing fields', function () {
            $proof = new ZkProof(
                type: ProofType::AGE_VERIFICATION,
                proof: base64_encode(json_encode(['only_one_field' => 'value'])),
                publicInputs: [],
                verifierAddress: '0x0',
                createdAt: new DateTimeImmutable(),
                expiresAt: new DateTimeImmutable('+90 days'),
            );

            $isValid = $this->prover->verifyProof($proof);

            expect($isValid)->toBeFalse();
        });
    });

    describe('provider configuration', function () {
        it('returns demo provider name', function () {
            expect($this->prover->getProviderName())->toBe('demo');
        });

        it('supports all proof types', function () {
            foreach (ProofType::cases() as $type) {
                expect($this->prover->supportsProofType($type))->toBeTrue();
            }
        });

        it('returns verifier address', function () {
            $address = $this->prover->getVerifierAddress(ProofType::AGE_VERIFICATION);

            expect($address)->toBeString()
                ->and(strlen($address))->toBe(42);
        });
    });
});
