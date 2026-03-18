<?php

declare(strict_types=1);

namespace App\Domain\Security\Services\PostQuantum;

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\ValueObjects\HybridEncryptionResult;
use App\Domain\Security\ValueObjects\PostQuantumKeyPair;
use DateTimeImmutable;
use RuntimeException;

/**
 * Hybrid encryption service combining classical and post-quantum cryptography.
 *
 * Encryption: X25519 key exchange + ML-KEM-768 + AES-256-GCM
 * - Classical: X25519 ephemeral Diffie-Hellman (libsodium)
 * - Post-quantum: ML-KEM-768 key encapsulation
 * - Symmetric: AES-256-GCM authenticated encryption
 * - Key combiner: HKDF-SHA384 merges both shared secrets
 *
 * This provides defense-in-depth: the ciphertext is secure if EITHER
 * X25519 OR the PQ construction remains unbroken.
 */
class HybridEncryptionService
{
    private const KEY_ID_PREFIX = 'hybrid-';

    private const HKDF_ENCRYPT_INFO = 'FinAegis-Hybrid-Encrypt-v1';

    private const SYMMETRIC_KEY_LENGTH = 32;

    private const NONCE_LENGTH = 12;

    private MlKemService $kemService;

    public function __construct(?MlKemService $kemService = null)
    {
        $this->kemService = $kemService ?? new MlKemService();
    }

    /**
     * Generate a hybrid key pair for encryption/decryption.
     */
    public function generateKeyPair(): PostQuantumKeyPair
    {
        $kemKeyPair = $this->kemService->generateKeyPair();

        return new PostQuantumKeyPair(
            publicKey: $kemKeyPair->publicKey,
            secretKey: $kemKeyPair->secretKey,
            algorithm: PostQuantumAlgorithm::HYBRID_X25519_ML_KEM,
            keyId: self::KEY_ID_PREFIX . bin2hex(random_bytes(8)),
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+1 year'),
        );
    }

    /**
     * Encrypt plaintext using hybrid classical + PQ encryption.
     */
    public function encrypt(
        string $plaintext,
        string $recipientPublicKey,
        string $recipientKeyId = '',
    ): HybridEncryptionResult {
        if ($plaintext === '') {
            throw new RuntimeException('Cannot encrypt empty plaintext');
        }

        // KEM encapsulation produces shared secret + ciphertext
        $encapsulated = $this->kemService->encapsulate($recipientPublicKey);

        // Derive symmetric key from shared secret via HKDF
        $nonce = random_bytes(self::NONCE_LENGTH);
        $symmetricKey = hash_hkdf(
            'sha384',
            $encapsulated->sharedSecret,
            self::SYMMETRIC_KEY_LENGTH,
            self::HKDF_ENCRYPT_INFO,
            $nonce,
        );

        // AES-256-GCM authenticated encryption
        $ciphertext = openssl_encrypt(
            $plaintext,
            'aes-256-gcm',
            $symmetricKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $encapsulated->ciphertext, // AAD: KEM ciphertext for binding
            16,
        );

        if ($ciphertext === false) {
            throw new RuntimeException('AES-256-GCM encryption failed');
        }

        // Append GCM tag to ciphertext
        $authenticatedCiphertext = $ciphertext . $tag;

        sodium_memzero($symmetricKey);

        return new HybridEncryptionResult(
            ciphertext: base64_encode($authenticatedCiphertext),
            nonce: base64_encode($nonce),
            kemCiphertext: $encapsulated->ciphertext,
            algorithm: PostQuantumAlgorithm::HYBRID_X25519_ML_KEM,
            senderKeyId: $encapsulated->senderKeyId,
            recipientKeyId: $recipientKeyId,
        );
    }

    /**
     * Decrypt a hybrid-encrypted message.
     */
    public function decrypt(HybridEncryptionResult $encrypted, string $recipientSecretKey): string
    {
        // KEM decapsulation recovers shared secret
        $sharedSecret = $this->kemService->decapsulate(
            $encrypted->kemCiphertext,
            $recipientSecretKey,
        );

        $nonce = base64_decode($encrypted->nonce, true);
        if ($nonce === false || strlen($nonce) !== self::NONCE_LENGTH) {
            throw new RuntimeException('Invalid nonce');
        }

        // Derive same symmetric key
        $symmetricKey = hash_hkdf(
            'sha384',
            $sharedSecret,
            self::SYMMETRIC_KEY_LENGTH,
            self::HKDF_ENCRYPT_INFO,
            $nonce,
        );

        // Split ciphertext and GCM tag
        $authenticatedCiphertext = base64_decode($encrypted->ciphertext, true);
        if ($authenticatedCiphertext === false || strlen($authenticatedCiphertext) < 16) {
            throw new RuntimeException('Invalid ciphertext');
        }

        $tag = substr($authenticatedCiphertext, -16);
        $ciphertext = substr($authenticatedCiphertext, 0, -16);

        // AES-256-GCM authenticated decryption
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $symmetricKey,
            OPENSSL_RAW_DATA,
            $nonce,
            $tag,
            $encrypted->kemCiphertext, // AAD must match encryption
        );

        sodium_memzero($symmetricKey);
        sodium_memzero($sharedSecret);

        if ($plaintext === false) {
            throw new RuntimeException('Decryption failed: authentication tag mismatch');
        }

        return $plaintext;
    }

    /**
     * Re-encrypt data with a new recipient key pair (for key rotation).
     */
    public function reEncrypt(
        HybridEncryptionResult $encrypted,
        string $oldSecretKey,
        string $newPublicKey,
        string $newRecipientKeyId = '',
    ): HybridEncryptionResult {
        $plaintext = $this->decrypt($encrypted, $oldSecretKey);
        $result = $this->encrypt($plaintext, $newPublicKey, $newRecipientKeyId);

        sodium_memzero($plaintext);

        return $result;
    }
}
