<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\HSM;

use App\Domain\KeyManagement\Contracts\HsmProviderInterface;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Demo HSM Provider for development and testing.
 *
 * WARNING: This is NOT secure for production use.
 * Uses cache storage with encryption simulation.
 */
class DemoHsmProvider implements HsmProviderInterface
{
    private const CACHE_PREFIX = 'demo_hsm:';

    private const DEMO_KEY = 'demo-hsm-encryption-key-32bytes!';

    public function encrypt(string $data, string $keyId): string
    {
        // Simple encryption for demo purposes
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $data,
            'aes-256-cbc',
            self::DEMO_KEY,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        return base64_encode($iv . $encrypted);
    }

    public function decrypt(string $encryptedData, string $keyId): string
    {
        $decoded = base64_decode($encryptedData);
        $iv = substr($decoded, 0, 16);
        $encrypted = substr($decoded, 16);

        $decrypted = openssl_decrypt(
            $encrypted,
            'aes-256-cbc',
            self::DEMO_KEY,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    public function store(string $secretId, string $data): bool
    {
        $encrypted = $this->encrypt($data, 'storage');
        Cache::put(self::CACHE_PREFIX . $secretId, $encrypted, now()->addDays(365));

        return true;
    }

    public function retrieve(string $secretId): ?string
    {
        $encrypted = Cache::get(self::CACHE_PREFIX . $secretId);

        if ($encrypted === null) {
            return null;
        }

        return $this->decrypt($encrypted, 'storage');
    }

    public function delete(string $secretId): bool
    {
        return Cache::forget(self::CACHE_PREFIX . $secretId);
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return 'demo';
    }

    /**
     * Sign data using demo ECDSA simulation.
     *
     * WARNING: This produces a DETERMINISTIC signature that will NOT verify on-chain.
     * Use only for testing/development. Production must use actual HSM.
     *
     * @todo PRODUCTION: Replace with actual HSM secp256k1 signing
     */
    public function sign(string $messageHash, string $keyId): string
    {
        // Validate input is 32-byte hex with 0x prefix
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $messageHash)) {
            throw new RuntimeException('Invalid message hash format. Expected 32-byte hex with 0x prefix.');
        }

        // Demo: Generate deterministic "signature" from message hash and key ID
        // This is NOT a real ECDSA signature and will NOT verify on-chain
        $sigInput = $messageHash . $keyId . self::DEMO_KEY;

        // Create fake r, s values (each 32 bytes)
        $r = hash('sha256', 'r_component_' . $sigInput);
        $s = hash('sha256', 's_component_' . $sigInput);

        // v is recovery id (27 or 28 for Ethereum, here we use 0x1b = 27)
        $v = '1b';

        return '0x' . $r . $s . $v;
    }

    /**
     * Verify an ECDSA signature.
     *
     * Demo implementation always returns true for non-empty signatures.
     *
     * @todo PRODUCTION: Implement actual ECDSA verification with secp256k1
     */
    public function verify(string $messageHash, string $signature, string $publicKey): bool
    {
        // Validate inputs
        if (! preg_match('/^0x[a-fA-F0-9]{64}$/', $messageHash)) {
            return false;
        }

        if (! preg_match('/^0x[a-fA-F0-9]+$/', $signature)) {
            return false;
        }

        // Demo: Accept any well-formed signature
        // Production would actually verify the ECDSA signature
        return strlen($signature) >= 130; // 0x + 64 (r) + 64 (s) + 2 (v) = 132 chars minimum
    }

    /**
     * Get the public key for a signing key.
     *
     * Demo implementation returns a deterministic fake public key.
     *
     * @todo PRODUCTION: Return actual public key from HSM
     */
    public function getPublicKey(string $keyId): string
    {
        // Demo: Generate deterministic public key from key ID
        $pubKeyHash = hash('sha256', 'public_key_' . $keyId . self::DEMO_KEY);

        // Return as compressed public key format (33 bytes = 66 hex chars)
        // First byte 02 or 03 indicates even/odd y coordinate
        return '0x02' . substr($pubKeyHash, 0, 64);
    }
}
