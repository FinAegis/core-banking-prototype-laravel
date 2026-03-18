<?php

declare(strict_types=1);

use App\Domain\Security\Enums\PostQuantumAlgorithm;

it('identifies key encapsulation algorithms', function (): void {
    expect(PostQuantumAlgorithm::ML_KEM_768->isKeyEncapsulation())->toBeTrue();
    expect(PostQuantumAlgorithm::ML_KEM_1024->isKeyEncapsulation())->toBeTrue();
    expect(PostQuantumAlgorithm::HYBRID_X25519_ML_KEM->isKeyEncapsulation())->toBeTrue();
    expect(PostQuantumAlgorithm::ML_DSA_65->isKeyEncapsulation())->toBeFalse();
    expect(PostQuantumAlgorithm::ML_DSA_87->isKeyEncapsulation())->toBeFalse();
});

it('identifies digital signature algorithms', function (): void {
    expect(PostQuantumAlgorithm::ML_DSA_65->isDigitalSignature())->toBeTrue();
    expect(PostQuantumAlgorithm::ML_DSA_87->isDigitalSignature())->toBeTrue();
    expect(PostQuantumAlgorithm::HYBRID_ED25519_ML_DSA->isDigitalSignature())->toBeTrue();
    expect(PostQuantumAlgorithm::ML_KEM_768->isDigitalSignature())->toBeFalse();
});

it('identifies hybrid algorithms', function (): void {
    expect(PostQuantumAlgorithm::HYBRID_X25519_ML_KEM->isHybrid())->toBeTrue();
    expect(PostQuantumAlgorithm::HYBRID_ED25519_ML_DSA->isHybrid())->toBeTrue();
    expect(PostQuantumAlgorithm::ML_KEM_768->isHybrid())->toBeFalse();
    expect(PostQuantumAlgorithm::ML_DSA_65->isHybrid())->toBeFalse();
});

it('returns correct NIST security levels', function (): void {
    expect(PostQuantumAlgorithm::ML_KEM_768->nistSecurityLevel())->toBe(3);
    expect(PostQuantumAlgorithm::ML_KEM_1024->nistSecurityLevel())->toBe(5);
    expect(PostQuantumAlgorithm::ML_DSA_65->nistSecurityLevel())->toBe(3);
    expect(PostQuantumAlgorithm::ML_DSA_87->nistSecurityLevel())->toBe(5);
});

it('provides display names for all algorithms', function (): void {
    foreach (PostQuantumAlgorithm::cases() as $algo) {
        expect($algo->displayName())->toBeString()->not->toBeEmpty();
    }
});
