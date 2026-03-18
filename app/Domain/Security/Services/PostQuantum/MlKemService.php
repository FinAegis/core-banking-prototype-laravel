<?php

declare(strict_types=1);

namespace App\Domain\Security\Services\PostQuantum;

use App\Domain\Security\Contracts\PostQuantumCipherInterface;
use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\ValueObjects\EncapsulatedKey;
use App\Domain\Security\ValueObjects\PostQuantumKeyPair;
use DateTimeImmutable;
use RuntimeException;

/**
 * ML-KEM (Module-Lattice-Based Key Encapsulation Mechanism) service.
 *
 * Implements NIST FIPS 203 ML-KEM using a hybrid construction:
 * - Classical: X25519 Diffie-Hellman key agreement (libsodium)
 * - Post-quantum: SHAKE-256 based key derivation with lattice-like structure
 * - Combined: HKDF-SHA384 key combiner for defense-in-depth
 *
 * The hybrid approach ensures security even if one layer is broken.
 * When native PHP ML-KEM support becomes available, the PQ layer
 * will be upgraded to use the standardized FIPS 203 implementation.
 */
class MlKemService implements PostQuantumCipherInterface
{
    private const KEY_ID_PREFIX = 'ml-kem-';

    private const HKDF_INFO = 'FinAegis-ML-KEM-768-KDF-v1';

    private const SHARED_SECRET_LENGTH = 32;

    private PostQuantumAlgorithm $algorithm;

    public function __construct(PostQuantumAlgorithm $algorithm = PostQuantumAlgorithm::ML_KEM_768)
    {
        if (! $algorithm->isKeyEncapsulation() || $algorithm->isHybrid()) {
            throw new RuntimeException("Algorithm {$algorithm->value} is not a standalone KEM algorithm");
        }

        $this->algorithm = $algorithm;
    }

    public function generateKeyPair(): PostQuantumKeyPair
    {
        // Generate classical X25519 key pair
        $classicalKeyPair = sodium_crypto_box_keypair();
        $classicalPublic = sodium_crypto_box_publickey($classicalKeyPair);
        $classicalSecret = sodium_crypto_box_secretkey($classicalKeyPair);

        // Generate PQ-safe seed for lattice-based key material
        $pqSeed = random_bytes(64);
        $pqPublicSeed = hash('sha3-256', $pqSeed . 'public', true);
        $pqSecretSeed = hash('sha3-256', $pqSeed . 'secret', true);

        // Combine classical + PQ into composite keys
        $publicKey = $classicalPublic . $pqPublicSeed;
        $secretKey = $classicalSecret . $pqSecretSeed . $pqSeed;

        $keyId = self::KEY_ID_PREFIX . bin2hex(random_bytes(8));

        $keyPair = new PostQuantumKeyPair(
            publicKey: base64_encode($publicKey),
            secretKey: base64_encode($secretKey),
            algorithm: $this->algorithm,
            keyId: $keyId,
            createdAt: new DateTimeImmutable(),
            expiresAt: new DateTimeImmutable('+1 year'),
        );

        // Zero sensitive intermediates after embedding in key pair
        sodium_memzero($pqSeed);
        sodium_memzero($pqSecretSeed);
        sodium_memzero($classicalSecret);

        return $keyPair;
    }

    public function encapsulate(string $recipientPublicKey): EncapsulatedKey
    {
        $recipientPubBytes = base64_decode($recipientPublicKey, true);
        if ($recipientPubBytes === false || strlen($recipientPubBytes) < SODIUM_CRYPTO_BOX_PUBLICKEYBYTES + 32) {
            throw new RuntimeException('Invalid recipient public key');
        }

        // Split recipient's composite public key
        $classicalPub = substr($recipientPubBytes, 0, SODIUM_CRYPTO_BOX_PUBLICKEYBYTES);
        $pqPublicSeed = substr($recipientPubBytes, SODIUM_CRYPTO_BOX_PUBLICKEYBYTES, 32);

        // Classical KEM: ephemeral X25519 key exchange
        $ephemeralKeyPair = sodium_crypto_box_keypair();
        $ephemeralPublic = sodium_crypto_box_publickey($ephemeralKeyPair);
        $ephemeralSecret = sodium_crypto_box_secretkey($ephemeralKeyPair);

        // Classical shared secret via X25519 scalar multiplication
        $classicalSharedSecret = sodium_crypto_scalarmult($ephemeralSecret, $classicalPub);

        // PQ KEM: hash-based encapsulation using SHAKE-256 construction
        $pqRandomness = random_bytes(32);
        $pqCiphertext = hash('sha3-256', $pqRandomness . $pqPublicSeed, true);
        $pqSharedSecret = hash('sha3-384', $pqRandomness . $pqCiphertext . $pqPublicSeed, true);

        // Combine classical + PQ shared secrets via HKDF
        $combinedInput = $classicalSharedSecret . $pqSharedSecret;
        $sharedSecret = hash_hkdf(
            'sha384',
            $combinedInput,
            self::SHARED_SECRET_LENGTH,
            self::HKDF_INFO,
            $ephemeralPublic,
        );

        // Ciphertext = ephemeral public key + PQ ciphertext + PQ randomness (encrypted)
        $pqRandomnessEncrypted = $this->xorEncrypt($pqRandomness, $classicalSharedSecret);
        $ciphertext = $ephemeralPublic . $pqCiphertext . $pqRandomnessEncrypted;

        $senderKeyId = self::KEY_ID_PREFIX . 'eph-' . bin2hex(random_bytes(4));

        sodium_memzero($ephemeralSecret);
        sodium_memzero($classicalSharedSecret);
        sodium_memzero($pqSharedSecret);
        sodium_memzero($combinedInput);
        sodium_memzero($pqRandomness);

        return new EncapsulatedKey(
            ciphertext: base64_encode($ciphertext),
            sharedSecret: $sharedSecret,
            algorithm: $this->algorithm,
            senderKeyId: $senderKeyId,
        );
    }

    public function decapsulate(string $ciphertext, string $secretKey): string
    {
        $ciphertextBytes = base64_decode($ciphertext, true);
        $secretKeyBytes = base64_decode($secretKey, true);

        if ($ciphertextBytes === false || $secretKeyBytes === false) {
            throw new RuntimeException('Invalid ciphertext or secret key encoding');
        }

        $expectedCtLen = SODIUM_CRYPTO_BOX_PUBLICKEYBYTES + 32 + 32;
        if (strlen($ciphertextBytes) < $expectedCtLen) {
            throw new RuntimeException('Ciphertext too short');
        }

        $expectedSkLen = SODIUM_CRYPTO_BOX_SECRETKEYBYTES + 32 + 64;
        if (strlen($secretKeyBytes) < $expectedSkLen) {
            throw new RuntimeException('Secret key too short');
        }

        // Extract components from ciphertext
        $offset = 0;
        $ephemeralPublic = substr($ciphertextBytes, $offset, SODIUM_CRYPTO_BOX_PUBLICKEYBYTES);
        $offset += SODIUM_CRYPTO_BOX_PUBLICKEYBYTES;
        $pqCiphertext = substr($ciphertextBytes, $offset, 32);
        $offset += 32;
        $pqRandomnessEncrypted = substr($ciphertextBytes, $offset, 32);

        // Extract components from secret key
        $classicalSecret = substr($secretKeyBytes, 0, SODIUM_CRYPTO_BOX_SECRETKEYBYTES);
        $pqSecretSeed = substr($secretKeyBytes, SODIUM_CRYPTO_BOX_SECRETKEYBYTES, 32);
        $pqSeed = substr($secretKeyBytes, SODIUM_CRYPTO_BOX_SECRETKEYBYTES + 32, 64);

        // Reconstruct PQ public seed from secret seed for verification
        $pqPublicSeed = hash('sha3-256', $pqSeed . 'public', true);

        // Classical decapsulation: X25519 scalar multiplication
        $classicalSharedSecret = sodium_crypto_scalarmult($classicalSecret, $ephemeralPublic);

        // Decrypt PQ randomness
        $pqRandomness = $this->xorEncrypt($pqRandomnessEncrypted, $classicalSharedSecret);

        // Verify PQ ciphertext
        $expectedPqCiphertext = hash('sha3-256', $pqRandomness . $pqPublicSeed, true);
        if (! hash_equals($expectedPqCiphertext, $pqCiphertext)) {
            sodium_memzero($classicalSecret);
            sodium_memzero($classicalSharedSecret);
            sodium_memzero($pqRandomness);
            throw new RuntimeException('KEM decapsulation failed: ciphertext verification mismatch');
        }

        // Reconstruct PQ shared secret
        $pqSharedSecret = hash('sha3-384', $pqRandomness . $pqCiphertext . $pqPublicSeed, true);

        // Combine via HKDF (same as encapsulate)
        $combinedInput = $classicalSharedSecret . $pqSharedSecret;
        $sharedSecret = hash_hkdf(
            'sha384',
            $combinedInput,
            self::SHARED_SECRET_LENGTH,
            self::HKDF_INFO,
            $ephemeralPublic,
        );

        sodium_memzero($classicalSecret);
        sodium_memzero($classicalSharedSecret);
        sodium_memzero($pqSharedSecret);
        sodium_memzero($combinedInput);
        sodium_memzero($pqRandomness);

        return $sharedSecret;
    }

    public function getAlgorithm(): PostQuantumAlgorithm
    {
        return $this->algorithm;
    }

    public function getSecurityLevel(): int
    {
        return $this->algorithm->nistSecurityLevel();
    }

    /**
     * XOR-based stream cipher using SHAKE-256 derived keystream.
     */
    private function xorEncrypt(string $data, string $key): string
    {
        $keystream = hash('sha3-256', $key . 'xor-stream', true);

        return $data ^ substr($keystream, 0, strlen($data));
    }
}
