<?php

declare(strict_types=1);

use App\Domain\Security\Enums\KeyRotationStrategy;
use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\Services\PostQuantum\HybridEncryptionService;
use App\Domain\Security\Services\PostQuantum\QuantumSafeKeyRotationService;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Cache::flush();
});

it('rotates encryption key pairs', function (): void {
    $encService = new HybridEncryptionService();
    $rotationService = new QuantumSafeKeyRotationService($encService);

    $oldKeyPair = $encService->generateKeyPair();

    $result = $rotationService->rotateEncryptionKeyPair($oldKeyPair);

    expect($result)->toHaveKeys(['old', 'new']);
    expect($result['old']->keyId)->toBe($oldKeyPair->keyId);
    expect($result['new']->keyId)->not->toBe($oldKeyPair->keyId);
    expect($result['new']->algorithm)->toBe(PostQuantumAlgorithm::HYBRID_X25519_ML_KEM);
});

it('rotates signing key pairs', function (): void {
    $rotationService = new QuantumSafeKeyRotationService();

    $oldKeyPair = (new App\Domain\Security\Services\PostQuantum\MlDsaService())->generateSigningKeyPair();

    $result = $rotationService->rotateSigningKeyPair($oldKeyPair);

    expect($result)->toHaveKeys(['old', 'new']);
    expect($result['new']->algorithm)->toBe(PostQuantumAlgorithm::ML_DSA_65);
});

it('re-encrypts batch of items during rotation', function (): void {
    $encService = new HybridEncryptionService();
    $rotationService = new QuantumSafeKeyRotationService($encService);

    $oldKeyPair = $encService->generateKeyPair();
    $newKeyPair = $encService->generateKeyPair();

    // Encrypt 5 items with old key
    $items = [];
    $plaintexts = [];
    for ($i = 0; $i < 5; $i++) {
        $plaintexts[] = "Sensitive record #{$i}";
        $items[] = $encService->encrypt($plaintexts[$i], $oldKeyPair->publicKey);
    }

    $result = $rotationService->reEncryptBatch(
        $items,
        $oldKeyPair->secretKey,
        $newKeyPair->publicKey,
        $newKeyPair->keyId,
    );

    expect($result['total'])->toBe(5);
    expect($result['success_count'])->toBe(5);
    expect($result['failed'])->toBeEmpty();

    // Verify all re-encrypted items decrypt with new key
    foreach ($result['re_encrypted'] as $index => $reEncrypted) {
        $decrypted = $encService->decrypt($reEncrypted, $newKeyPair->secretKey);
        expect($decrypted)->toBe($plaintexts[$index]);
    }
});

it('handles failed re-encryption gracefully', function (): void {
    $encService = new HybridEncryptionService();
    $rotationService = new QuantumSafeKeyRotationService($encService);

    $oldKeyPair = $encService->generateKeyPair();
    $wrongKeyPair = $encService->generateKeyPair();
    $newKeyPair = $encService->generateKeyPair();

    // Encrypt with old key
    $item = $encService->encrypt('data', $oldKeyPair->publicKey);

    // Try re-encrypt with wrong old secret key
    $result = $rotationService->reEncryptBatch(
        [$item],
        $wrongKeyPair->secretKey, // wrong key
        $newKeyPair->publicKey,
    );

    expect($result['total'])->toBe(1);
    expect($result['success_count'])->toBe(0);
    expect($result['failed'])->toBe([0]);
});

it('upgrades classical key to quantum-safe', function (): void {
    $rotationService = new QuantumSafeKeyRotationService();

    // Generate classical X25519 key pair
    $classicalKeyPair = sodium_crypto_box_keypair();
    $classicalPublic = base64_encode(sodium_crypto_box_publickey($classicalKeyPair));
    $classicalSecret = base64_encode(sodium_crypto_box_secretkey($classicalKeyPair));

    $upgraded = $rotationService->upgradeToQuantumSafe($classicalPublic, $classicalSecret);

    expect($upgraded->algorithm)->toBe(PostQuantumAlgorithm::HYBRID_X25519_ML_KEM);
    expect($upgraded->keyId)->toStartWith('upgraded-');
    expect(strlen($upgraded->getPublicKeyBytes()))->toBeGreaterThan(SODIUM_CRYPTO_BOX_PUBLICKEYBYTES);
});

it('assesses key rotation need for expired keys', function (): void {
    $rotationService = new QuantumSafeKeyRotationService();

    $expiredKeyPair = new App\Domain\Security\ValueObjects\PostQuantumKeyPair(
        publicKey: base64_encode(random_bytes(64)),
        secretKey: base64_encode(random_bytes(128)),
        algorithm: PostQuantumAlgorithm::HYBRID_X25519_ML_KEM,
        keyId: 'test-expired',
        createdAt: new DateTimeImmutable('-2 years'),
        expiresAt: new DateTimeImmutable('-1 day'),
    );

    $assessment = $rotationService->assessRotationNeed($expiredKeyPair);

    expect($assessment['needs_rotation'])->toBeTrue();
    expect($assessment['reason'])->toContain('expired');
});

it('assesses key rotation need for old keys', function (): void {
    $rotationService = new QuantumSafeKeyRotationService();

    $oldKeyPair = new App\Domain\Security\ValueObjects\PostQuantumKeyPair(
        publicKey: base64_encode(random_bytes(64)),
        secretKey: base64_encode(random_bytes(128)),
        algorithm: PostQuantumAlgorithm::HYBRID_X25519_ML_KEM,
        keyId: 'test-old',
        createdAt: new DateTimeImmutable('-100 days'),
        expiresAt: new DateTimeImmutable('+265 days'),
    );

    $assessment = $rotationService->assessRotationNeed($oldKeyPair);

    expect($assessment['needs_rotation'])->toBeTrue();
    expect($assessment['reason'])->toContain('90 days');
});

it('reports no rotation needed for fresh keys', function (): void {
    $encService = new HybridEncryptionService();
    $rotationService = new QuantumSafeKeyRotationService($encService);

    $freshKeyPair = $encService->generateKeyPair();

    $assessment = $rotationService->assessRotationNeed($freshKeyPair);

    expect($assessment['needs_rotation'])->toBeFalse();
    expect($assessment['reason'])->toBeNull();
});

it('tracks rotation history', function (): void {
    $encService = new HybridEncryptionService();
    $rotationService = new QuantumSafeKeyRotationService($encService);

    $keyPair = $encService->generateKeyPair();
    $rotationService->rotateEncryptionKeyPair($keyPair, KeyRotationStrategy::GRACEFUL);

    $history = $rotationService->getRotationHistory();

    expect($history)->toHaveCount(1);
    expect($history[0])->toHaveKeys(['old_key_id', 'new_key_id', 'algorithm', 'strategy', 'key_type', 'rotated_at']);
    expect($history[0]['strategy'])->toBe('graceful');
    expect($history[0]['key_type'])->toBe('encryption');
});

it('supports different rotation strategies', function (): void {
    $encService = new HybridEncryptionService();
    $rotationService = new QuantumSafeKeyRotationService($encService);

    foreach (KeyRotationStrategy::cases() as $strategy) {
        $keyPair = $encService->generateKeyPair();
        $result = $rotationService->rotateEncryptionKeyPair($keyPair, $strategy);

        expect($result['new']->keyId)->not->toBe($keyPair->keyId);
    }
});
