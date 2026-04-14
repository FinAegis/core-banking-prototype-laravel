<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\Services\DemoZkProver;
use App\Domain\Privacy\ValueObjects\ZkProof;

uses(Tests\TestCase::class);

describe('ZK Proof Roundtrip (DemoZkProver)', function (): void {
    beforeEach(function (): void {
        $this->prover = new DemoZkProver();
    });

    it('generates and verifies age verification proof roundtrip', function (): void {
        $proof = $this->prover->generateProof(
            ProofType::AGE_VERIFICATION,
            ['date_of_birth' => '1990-01-01', 'minimum_age' => 18],
            ['user_id_hash'  => hash('sha256', 'user-roundtrip-1'), 'minimum_age' => 18],
        );

        expect($proof)->toBeInstanceOf(ZkProof::class)
            ->and($proof->type)->toBe(ProofType::AGE_VERIFICATION)
            ->and($proof->isExpired())->toBeFalse()
            ->and($this->prover->verifyProof($proof))->toBeTrue();
    });

    it('generates and verifies residency proof roundtrip', function (): void {
        $proof = $this->prover->generateProof(
            ProofType::RESIDENCY,
            ['country'      => 'US', 'region' => 'CA'],
            ['user_id_hash' => hash('sha256', 'user-roundtrip-2')],
        );

        expect($proof)->toBeInstanceOf(ZkProof::class)
            ->and($proof->type)->toBe(ProofType::RESIDENCY)
            ->and($this->prover->verifyProof($proof))->toBeTrue();
    });

    it('generates and verifies KYC tier proof roundtrip', function (): void {
        $proof = $this->prover->generateProof(
            ProofType::KYC_TIER,
            ['kyc_tier'     => 3, 'kyc_provider' => 'jumio'],
            ['user_id_hash' => hash('sha256', 'user-roundtrip-3'), 'minimum_tier' => 2],
        );

        expect($proof)->toBeInstanceOf(ZkProof::class)
            ->and($proof->type)->toBe(ProofType::KYC_TIER)
            ->and($this->prover->verifyProof($proof))->toBeTrue();
    });

    it('generates and verifies sanctions clearance proof roundtrip', function (): void {
        $proof = $this->prover->generateProof(
            ProofType::SANCTIONS_CLEAR,
            ['identity_hash' => hash('sha256', 'identity-1'), 'sanctions_list_hash' => hash('sha256', 'sanctions-list')],
            ['user_id_hash'  => hash('sha256', 'user-roundtrip-4')],
        );

        expect($proof)->toBeInstanceOf(ZkProof::class)
            ->and($proof->type)->toBe(ProofType::SANCTIONS_CLEAR)
            ->and($this->prover->verifyProof($proof))->toBeTrue();
    });

    it('generates and verifies income range proof roundtrip', function (): void {
        $proof = $this->prover->generateProof(
            ProofType::INCOME_RANGE,
            ['income_range_min' => 50000, 'income_range_max' => 100000],
            ['user_id_hash'     => hash('sha256', 'user-roundtrip-5')],
        );

        expect($proof)->toBeInstanceOf(ZkProof::class)
            ->and($proof->type)->toBe(ProofType::INCOME_RANGE)
            ->and($this->prover->verifyProof($proof))->toBeTrue();
    });

    it('rejects expired proof', function (): void {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: base64_encode(json_encode([
                'statement'  => 'age >= 18',
                'commitment' => hash('sha256', 'age >= 18' . json_encode([])),
                'challenge'  => hash('sha256', 'challenge'),
                'response'   => hash('sha256', 'response'),
            ]) ?: '{}'),
            publicInputs: [],
            verifierAddress: '0x0000000000000000000000000000000000000000',
            createdAt: new DateTimeImmutable('-100 days'),
            expiresAt: new DateTimeImmutable('-1 day'),
        );

        expect($this->prover->verifyProof($proof))->toBeFalse();
    });

    it('generates proof with correct metadata', function (): void {
        $proof = $this->prover->generateProof(
            ProofType::AGE_VERIFICATION,
            ['date_of_birth' => '1990-01-01', 'minimum_age' => 18],
            ['minimum_age'   => 18],
        );

        expect($proof->metadata)->toHaveKey('provider')
            ->and($proof->metadata['provider'])->toBe('demo')
            ->and($proof->metadata)->toHaveKey('circuit_version');
    });

    it('serializes and deserializes proof via ZkProof::fromArray', function (): void {
        $proof = $this->prover->generateProof(
            ProofType::AGE_VERIFICATION,
            ['date_of_birth' => '1990-01-01', 'minimum_age' => 18],
            ['user_id_hash'  => hash('sha256', 'user-serialize'), 'minimum_age' => 18],
        );

        $array = $proof->toArray();
        $array['proof'] = $proof->proof;
        $array['verifier_address'] = $proof->verifierAddress;
        $array['created_at'] = $proof->createdAt->format('c');
        $array['expires_at'] = $proof->expiresAt->format('c');
        $array['public_inputs'] = $proof->publicInputs;

        $restored = ZkProof::fromArray($array);

        expect($restored->type)->toBe($proof->type)
            ->and($restored->proof)->toBe($proof->proof)
            ->and($restored->publicInputs)->toBe($proof->publicInputs)
            ->and($restored->verifierAddress)->toBe($proof->verifierAddress);
    });

    it('supports all proof types for roundtrip', function (): void {
        $testCases = [
            [ProofType::AGE_VERIFICATION, ['date_of_birth' => '1990-01-01', 'minimum_age' => 18]],
            [ProofType::RESIDENCY, ['country'                        => 'US', 'region' => 'CA']],
            [ProofType::KYC_TIER, ['kyc_tier'                        => 3, 'kyc_provider' => 'jumio']],
            [ProofType::ACCREDITED_INVESTOR, ['accreditation_status' => 'approved', 'jurisdiction' => 'US']],
            [ProofType::SANCTIONS_CLEAR, ['identity_hash'            => 'hash1', 'sanctions_list_hash' => 'hash2']],
            [ProofType::INCOME_RANGE, ['income_range_min'            => 50000, 'income_range_max' => 100000]],
        ];

        foreach ($testCases as [$proofType, $privateInputs]) {
            $proof = $this->prover->generateProof(
                $proofType,
                $privateInputs,
                ['user_id_hash' => hash('sha256', 'user-all-types')],
            );

            expect($proof->type)->toBe($proofType)
                ->and($this->prover->verifyProof($proof))->toBeTrue("Failed roundtrip for: {$proofType->value}");
        }
    });
});
