<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;
use App\Domain\Privacy\ValueObjects\ZkProof;

describe('ZkProof Value Object', function () {
    it('creates a ZK proof with all properties', function () {
        $createdAt = new DateTimeImmutable('2026-01-01 00:00:00');
        $expiresAt = new DateTimeImmutable('2026-04-01 00:00:00');

        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof-data',
            publicInputs: ['minimum_age' => 18],
            verifierAddress: '0x123',
            createdAt: $createdAt,
            expiresAt: $expiresAt,
            metadata: ['provider' => 'demo'],
        );

        expect($proof->type)->toBe(ProofType::AGE_VERIFICATION)
            ->and($proof->proof)->toBe('proof-data')
            ->and($proof->publicInputs)->toBe(['minimum_age' => 18])
            ->and($proof->verifierAddress)->toBe('0x123')
            ->and($proof->createdAt)->toBe($createdAt)
            ->and($proof->expiresAt)->toBe($expiresAt)
            ->and($proof->metadata)->toBe(['provider' => 'demo']);
    });

    it('detects expired proofs', function () {
        $expiredProof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof',
            publicInputs: [],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable('-100 days'),
            expiresAt: new DateTimeImmutable('-10 days'),
        );

        $validProof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof',
            publicInputs: [],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable('-10 days'),
            expiresAt: new DateTimeImmutable('+80 days'),
        );

        expect($expiredProof->isExpired())->toBeTrue()
            ->and($validProof->isExpired())->toBeFalse();
    });

    it('calculates remaining validity seconds', function () {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof',
            publicInputs: [],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+1 hour'),
        );

        $remaining = $proof->getRemainingValiditySeconds();

        expect($remaining)->toBeGreaterThan(3500)
            ->and($remaining)->toBeLessThanOrEqual(3600);
    });

    it('returns zero for expired proof validity', function () {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof',
            publicInputs: [],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable('-100 days'),
            expiresAt: new DateTimeImmutable('-10 days'),
        );

        expect($proof->getRemainingValiditySeconds())->toBe(0);
    });

    it('generates proof hash', function () {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'my-proof-data',
            publicInputs: [],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+90 days'),
        );

        expect($proof->getProofHash())->toBe(hash('sha256', 'my-proof-data'));
    });

    it('checks public input existence', function () {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof',
            publicInputs: ['minimum_age' => 18, 'jurisdiction' => 'US'],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+90 days'),
        );

        expect($proof->hasPublicInput('minimum_age'))->toBeTrue()
            ->and($proof->hasPublicInput('jurisdiction'))->toBeTrue()
            ->and($proof->hasPublicInput('nonexistent'))->toBeFalse();
    });

    it('gets public input with default', function () {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof',
            publicInputs: ['minimum_age' => 18],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+90 days'),
        );

        expect($proof->getPublicInput('minimum_age'))->toBe(18)
            ->and($proof->getPublicInput('nonexistent', 'default'))->toBe('default');
    });

    it('converts to array without exposing raw proof', function () {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'secret-proof-data',
            publicInputs: ['minimum_age' => 18],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable('2026-01-01'),
            expiresAt: new DateTimeImmutable('2026-04-01'),
        );

        $array = $proof->toArray();

        expect($array)->toHaveKeys(['type', 'proof_hash', 'public_inputs', 'verifier_address', 'created_at', 'expires_at', 'is_expired'])
            ->and($array['type'])->toBe('age_verification')
            ->and($array['proof_hash'])->toBe(hash('sha256', 'secret-proof-data'))
            ->and($array)->not->toHaveKey('proof');
    });

    it('can be serialized to JSON', function () {
        $proof = new ZkProof(
            type: ProofType::AGE_VERIFICATION,
            proof: 'proof',
            publicInputs: ['age' => 21],
            verifierAddress: '0x123',
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+90 days'),
        );

        $json = json_encode($proof);

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toHaveKey('type');
    });

    it('can be created from array', function () {
        $data = [
            'type'             => 'kyc_tier',
            'proof'            => 'proof-data',
            'public_inputs'    => ['minimum_tier' => 2],
            'verifier_address' => '0x456',
            'created_at'       => '2026-01-01T00:00:00+00:00',
            'expires_at'       => '2026-04-01T00:00:00+00:00',
            'metadata'         => ['version' => '1.0'],
        ];

        $proof = ZkProof::fromArray($data);

        expect($proof->type)->toBe(ProofType::KYC_TIER)
            ->and($proof->proof)->toBe('proof-data')
            ->and($proof->publicInputs)->toBe(['minimum_tier' => 2])
            ->and($proof->verifierAddress)->toBe('0x456');
    });
});
