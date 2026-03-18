<?php

declare(strict_types=1);

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\Services\PostQuantum\MlKemService;

it('generates a valid key pair', function (): void {
    $service = new MlKemService();
    $keyPair = $service->generateKeyPair();

    expect($keyPair->algorithm)->toBe(PostQuantumAlgorithm::ML_KEM_768);
    expect($keyPair->keyId)->toStartWith('ml-kem-');
    expect($keyPair->publicKey)->toBeString()->not->toBeEmpty();
    expect($keyPair->secretKey)->toBeString()->not->toBeEmpty();
    expect($keyPair->isExpired())->toBeFalse();
    expect($keyPair->expiresAt)->not->toBeNull();
});

it('encapsulates and decapsulates shared secrets', function (): void {
    $service = new MlKemService();
    $keyPair = $service->generateKeyPair();

    // Encapsulate using recipient's public key
    $encapsulated = $service->encapsulate($keyPair->publicKey);

    expect($encapsulated->ciphertext)->toBeString()->not->toBeEmpty();
    expect(strlen($encapsulated->sharedSecret))->toBe(32);
    expect($encapsulated->algorithm)->toBe(PostQuantumAlgorithm::ML_KEM_768);
    expect($encapsulated->senderKeyId)->toStartWith('ml-kem-eph-');

    // Decapsulate using recipient's secret key
    $recoveredSecret = $service->decapsulate(
        $encapsulated->ciphertext,
        $keyPair->secretKey,
    );

    expect($recoveredSecret)->toBe($encapsulated->sharedSecret);
});

it('produces different shared secrets for different encapsulations', function (): void {
    $service = new MlKemService();
    $keyPair = $service->generateKeyPair();

    $enc1 = $service->encapsulate($keyPair->publicKey);
    $enc2 = $service->encapsulate($keyPair->publicKey);

    expect($enc1->sharedSecret)->not->toBe($enc2->sharedSecret);
    expect($enc1->ciphertext)->not->toBe($enc2->ciphertext);
});

it('fails decapsulation with wrong secret key', function (): void {
    $service = new MlKemService();
    $keyPair1 = $service->generateKeyPair();
    $keyPair2 = $service->generateKeyPair();

    $encapsulated = $service->encapsulate($keyPair1->publicKey);

    // Decapsulate with wrong key should throw
    $service->decapsulate($encapsulated->ciphertext, $keyPair2->secretKey);
})->throws(RuntimeException::class, 'ciphertext verification mismatch');

it('rejects invalid public key', function (): void {
    $service = new MlKemService();

    $service->encapsulate(base64_encode('too-short'));
})->throws(RuntimeException::class, 'Invalid recipient public key');

it('rejects non-KEM algorithm in constructor', function (): void {
    new MlKemService(PostQuantumAlgorithm::ML_DSA_65);
})->throws(RuntimeException::class);

it('reports correct security level', function (): void {
    $service768 = new MlKemService(PostQuantumAlgorithm::ML_KEM_768);
    expect($service768->getSecurityLevel())->toBe(3);

    $service1024 = new MlKemService(PostQuantumAlgorithm::ML_KEM_1024);
    expect($service1024->getSecurityLevel())->toBe(5);
});

it('generates unique key pairs each time', function (): void {
    $service = new MlKemService();
    $kp1 = $service->generateKeyPair();
    $kp2 = $service->generateKeyPair();

    expect($kp1->publicKey)->not->toBe($kp2->publicKey);
    expect($kp1->secretKey)->not->toBe($kp2->secretKey);
    expect($kp1->keyId)->not->toBe($kp2->keyId);
});

it('serializes key pair to array without secret key', function (): void {
    $service = new MlKemService();
    $keyPair = $service->generateKeyPair();
    $array = $keyPair->toArray();

    expect($array)->toHaveKeys(['key_id', 'algorithm', 'public_key', 'created_at', 'expires_at']);
    expect($array)->not->toHaveKey('secret_key');
    expect($array['algorithm'])->toBe('ML-KEM-768');
});
