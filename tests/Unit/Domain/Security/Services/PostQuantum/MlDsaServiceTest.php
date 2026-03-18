<?php

declare(strict_types=1);

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\Services\PostQuantum\MlDsaService;

it('generates a valid signing key pair', function (): void {
    $service = new MlDsaService();
    $keyPair = $service->generateSigningKeyPair();

    expect($keyPair->algorithm)->toBe(PostQuantumAlgorithm::ML_DSA_65);
    expect($keyPair->keyId)->toStartWith('ml-dsa-');
    expect($keyPair->publicKey)->toBeString()->not->toBeEmpty();
    expect($keyPair->secretKey)->toBeString()->not->toBeEmpty();
    expect($keyPair->isExpired())->toBeFalse();
});

it('signs and verifies messages', function (): void {
    $service = new MlDsaService();
    $keyPair = $service->generateSigningKeyPair();

    $message = 'Transfer 1000 USDC from account A to account B';

    $signature = $service->sign($message, $keyPair->secretKey, $keyPair->keyId);

    expect($signature->algorithm)->toBe(PostQuantumAlgorithm::ML_DSA_65);
    expect($signature->signerKeyId)->toBe($keyPair->keyId);
    expect($signature->signature)->toBeString()->not->toBeEmpty();
    expect($signature->isHybrid())->toBeFalse();

    // Verify the signature
    $isValid = $service->verify($message, $signature, $keyPair->publicKey);
    expect($isValid)->toBeTrue();
});

it('rejects signature with wrong message', function (): void {
    $service = new MlDsaService();
    $keyPair = $service->generateSigningKeyPair();

    $signature = $service->sign('original message', $keyPair->secretKey, $keyPair->keyId);

    $isValid = $service->verify('tampered message', $signature, $keyPair->publicKey);
    expect($isValid)->toBeFalse();
});

it('rejects signature with wrong public key', function (): void {
    $service = new MlDsaService();
    $keyPair1 = $service->generateSigningKeyPair();
    $keyPair2 = $service->generateSigningKeyPair();

    $signature = $service->sign('message', $keyPair1->secretKey, $keyPair1->keyId);

    $isValid = $service->verify('message', $signature, $keyPair2->publicKey);
    expect($isValid)->toBeFalse();
});

it('produces deterministic signatures for same message and key', function (): void {
    $service = new MlDsaService();
    $keyPair = $service->generateSigningKeyPair();

    $sig1 = $service->sign('same message', $keyPair->secretKey, $keyPair->keyId);
    $sig2 = $service->sign('same message', $keyPair->secretKey, $keyPair->keyId);

    // Signatures should be deterministic (same message + same key = same sig)
    expect($sig1->signature)->toBe($sig2->signature);
});

it('produces different signatures for different messages', function (): void {
    $service = new MlDsaService();
    $keyPair = $service->generateSigningKeyPair();

    $sig1 = $service->sign('message 1', $keyPair->secretKey, $keyPair->keyId);
    $sig2 = $service->sign('message 2', $keyPair->secretKey, $keyPair->keyId);

    expect($sig1->signature)->not->toBe($sig2->signature);
});

it('rejects non-signature algorithm', function (): void {
    new MlDsaService(PostQuantumAlgorithm::ML_KEM_768);
})->throws(RuntimeException::class);

it('handles empty message signing', function (): void {
    $service = new MlDsaService();
    $keyPair = $service->generateSigningKeyPair();

    $signature = $service->sign('', $keyPair->secretKey, $keyPair->keyId);

    $isValid = $service->verify('', $signature, $keyPair->publicKey);
    expect($isValid)->toBeTrue();
});

it('serializes signature to array', function (): void {
    $service = new MlDsaService();
    $keyPair = $service->generateSigningKeyPair();

    $signature = $service->sign('test', $keyPair->secretKey, $keyPair->keyId);
    $array = $signature->toArray();

    expect($array)->toHaveKeys(['signature', 'algorithm', 'signer_key_id', 'timestamp', 'is_hybrid']);
    expect($array['algorithm'])->toBe('ML-DSA-65');
    expect($array['is_hybrid'])->toBeFalse();
});

it('supports ML-DSA-87 security level', function (): void {
    $service = new MlDsaService(PostQuantumAlgorithm::ML_DSA_87);
    $keyPair = $service->generateSigningKeyPair();

    expect($keyPair->algorithm)->toBe(PostQuantumAlgorithm::ML_DSA_87);
    expect($service->getAlgorithm()->nistSecurityLevel())->toBe(5);

    $sig = $service->sign('high security', $keyPair->secretKey, $keyPair->keyId);
    expect($service->verify('high security', $sig, $keyPair->publicKey))->toBeTrue();
});
