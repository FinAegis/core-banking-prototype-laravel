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
}
