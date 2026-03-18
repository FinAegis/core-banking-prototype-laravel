<?php

declare(strict_types=1);

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\Services\PostQuantum\HybridEncryptionService;
use App\Domain\Security\ValueObjects\HybridEncryptionResult;

it('generates hybrid key pairs', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    expect($keyPair->algorithm)->toBe(PostQuantumAlgorithm::HYBRID_X25519_ML_KEM);
    expect($keyPair->keyId)->toStartWith('hybrid-');
    expect($keyPair->isExpired())->toBeFalse();
});

it('encrypts and decrypts plaintext', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    $plaintext = 'Sensitive financial transaction data: Transfer $10,000 from Account A to B';

    $encrypted = $service->encrypt($plaintext, $keyPair->publicKey, $keyPair->keyId);

    expect($encrypted)->toBeInstanceOf(HybridEncryptionResult::class);
    expect($encrypted->algorithm)->toBe(PostQuantumAlgorithm::HYBRID_X25519_ML_KEM);
    expect($encrypted->recipientKeyId)->toBe($keyPair->keyId);
    expect($encrypted->version)->toBe(1);

    $decrypted = $service->decrypt($encrypted, $keyPair->secretKey);

    expect($decrypted)->toBe($plaintext);
});

it('encrypts large payloads', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    $plaintext = str_repeat('A', 1024 * 100); // 100KB

    $encrypted = $service->encrypt($plaintext, $keyPair->publicKey);
    $decrypted = $service->decrypt($encrypted, $keyPair->secretKey);

    expect($decrypted)->toBe($plaintext);
});

it('produces different ciphertexts for same plaintext (non-deterministic)', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    $enc1 = $service->encrypt('same data', $keyPair->publicKey);
    $enc2 = $service->encrypt('same data', $keyPair->publicKey);

    expect($enc1->ciphertext)->not->toBe($enc2->ciphertext);
    expect($enc1->nonce)->not->toBe($enc2->nonce);
});

it('fails decryption with wrong key', function (): void {
    $service = new HybridEncryptionService();
    $keyPair1 = $service->generateKeyPair();
    $keyPair2 = $service->generateKeyPair();

    $encrypted = $service->encrypt('secret', $keyPair1->publicKey);

    $service->decrypt($encrypted, $keyPair2->secretKey);
})->throws(RuntimeException::class);

it('detects tampering with ciphertext', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    $encrypted = $service->encrypt('original data', $keyPair->publicKey);

    // Tamper with ciphertext
    $tampered = new HybridEncryptionResult(
        ciphertext: base64_encode(random_bytes(64)),
        nonce: $encrypted->nonce,
        kemCiphertext: $encrypted->kemCiphertext,
        algorithm: $encrypted->algorithm,
        senderKeyId: $encrypted->senderKeyId,
        recipientKeyId: $encrypted->recipientKeyId,
    );

    $service->decrypt($tampered, $keyPair->secretKey);
})->throws(RuntimeException::class);

it('rejects empty plaintext', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    $service->encrypt('', $keyPair->publicKey);
})->throws(RuntimeException::class, 'Cannot encrypt empty plaintext');

it('re-encrypts data for key rotation', function (): void {
    $service = new HybridEncryptionService();
    $oldKeyPair = $service->generateKeyPair();
    $newKeyPair = $service->generateKeyPair();

    $plaintext = 'Data to be re-encrypted during key rotation';

    $encrypted = $service->encrypt($plaintext, $oldKeyPair->publicKey, $oldKeyPair->keyId);

    $reEncrypted = $service->reEncrypt(
        $encrypted,
        $oldKeyPair->secretKey,
        $newKeyPair->publicKey,
        $newKeyPair->keyId,
    );

    // Re-encrypted data should decrypt with new key
    $decrypted = $service->decrypt($reEncrypted, $newKeyPair->secretKey);
    expect($decrypted)->toBe($plaintext);

    // Re-encrypted should use new key ID
    expect($reEncrypted->recipientKeyId)->toBe($newKeyPair->keyId);
});

it('serializes and deserializes encryption result', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    $encrypted = $service->encrypt('serialize me', $keyPair->publicKey, $keyPair->keyId);
    $array = $encrypted->toArray();
    $restored = HybridEncryptionResult::fromArray($array);

    $decrypted = $service->decrypt($restored, $keyPair->secretKey);
    expect($decrypted)->toBe('serialize me');
});

it('handles unicode plaintext correctly', function (): void {
    $service = new HybridEncryptionService();
    $keyPair = $service->generateKeyPair();

    $plaintext = '金融取引データ: ¥10,000 送金 — émojis: 🏦💰🔐';

    $encrypted = $service->encrypt($plaintext, $keyPair->publicKey);
    $decrypted = $service->decrypt($encrypted, $keyPair->secretKey);

    expect($decrypted)->toBe($plaintext);
});
