<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Contracts;

interface HsmProviderInterface
{
    /**
     * Encrypt data using HSM.
     */
    public function encrypt(string $data, string $keyId): string;

    /**
     * Decrypt data using HSM.
     */
    public function decrypt(string $encryptedData, string $keyId): string;

    /**
     * Store a secret in HSM.
     */
    public function store(string $secretId, string $data): bool;

    /**
     * Retrieve a secret from HSM.
     */
    public function retrieve(string $secretId): ?string;

    /**
     * Delete a secret from HSM.
     */
    public function delete(string $secretId): bool;

    /**
     * Check if HSM is available.
     */
    public function isAvailable(): bool;

    /**
     * Get provider name.
     */
    public function getProviderName(): string;

    /**
     * Sign data using ECDSA with secp256k1 curve.
     *
     * @param  string  $messageHash  32-byte hash to sign (hex with 0x prefix)
     * @param  string  $keyId  Key identifier in HSM
     * @return string ECDSA signature in compact format (r || s || v, 65 bytes, hex with 0x prefix)
     */
    public function sign(string $messageHash, string $keyId): string;

    /**
     * Verify an ECDSA signature.
     *
     * @param  string  $messageHash  32-byte hash that was signed (hex with 0x prefix)
     * @param  string  $signature  ECDSA signature (hex with 0x prefix)
     * @param  string  $publicKey  Public key to verify against (hex with 0x prefix)
     * @return bool True if signature is valid
     */
    public function verify(string $messageHash, string $signature, string $publicKey): bool;

    /**
     * Get the public key for a signing key.
     *
     * @param  string  $keyId  Key identifier in HSM
     * @return string Public key (hex with 0x prefix, 64 bytes uncompressed or 33 bytes compressed)
     */
    public function getPublicKey(string $keyId): string;
}
