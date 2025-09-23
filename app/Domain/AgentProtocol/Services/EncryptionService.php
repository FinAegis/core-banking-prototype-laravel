<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class EncryptionService
{
    private const CIPHER_METHODS = [
        'AES-256-GCM'       => ['key_size' => 32, 'tag_length' => 16],
        'AES-256-CBC'       => ['key_size' => 32, 'iv_length' => 16],
        'AES-128-GCM'       => ['key_size' => 16, 'tag_length' => 16],
        'ChaCha20-Poly1305' => ['key_size' => 32, 'nonce_length' => 12],
    ];

    private const KEY_ROTATION_DAYS = 30;

    /**
     * Encrypt transaction data.
     */
    public function encryptData(
        array $data,
        string $keyId,
        string $cipher = 'AES-256-GCM'
    ): array {
        try {
            if (! array_key_exists($cipher, self::CIPHER_METHODS)) {
                throw new InvalidArgumentException("Unsupported cipher method: {$cipher}");
            }

            $jsonData = json_encode($data);
            if ($jsonData === false) {
                throw new Exception('Failed to encode data to JSON');
            }

            $encryptionKey = $this->getOrGenerateKey($keyId, $cipher);

            if ($cipher === 'AES-256-GCM' || $cipher === 'AES-128-GCM') {
                return $this->encryptWithGCM($jsonData, $encryptionKey, $cipher);
            } elseif ($cipher === 'AES-256-CBC') {
                return $this->encryptWithCBC($jsonData, $encryptionKey);
            } elseif ($cipher === 'ChaCha20-Poly1305') {
                return $this->encryptWithChaCha20($jsonData, $encryptionKey);
            } else {
                throw new Exception("Cipher implementation not available: {$cipher}");
            }
        } catch (Exception $e) {
            Log::error('Data encryption failed', [
                'error'  => $e->getMessage(),
                'cipher' => $cipher,
                'key_id' => $keyId,
            ]);

            throw $e;
        }
    }

    /**
     * Decrypt transaction data.
     */
    public function decryptData(
        string $encryptedData,
        string $keyId,
        string $cipher,
        array $metadata
    ): array {
        try {
            if (! array_key_exists($cipher, self::CIPHER_METHODS)) {
                throw new InvalidArgumentException("Unsupported cipher method: {$cipher}");
            }

            $encryptionKey = $this->getKey($keyId);
            if (! $encryptionKey) {
                throw new Exception("Encryption key not found: {$keyId}");
            }

            $decryptedJson = '';

            if ($cipher === 'AES-256-GCM' || $cipher === 'AES-128-GCM') {
                $decryptedJson = $this->decryptWithGCM(
                    $encryptedData,
                    $encryptionKey,
                    $cipher,
                    $metadata
                );
            } elseif ($cipher === 'AES-256-CBC') {
                $decryptedJson = $this->decryptWithCBC(
                    $encryptedData,
                    $encryptionKey,
                    $metadata
                );
            } elseif ($cipher === 'ChaCha20-Poly1305') {
                $decryptedJson = $this->decryptWithChaCha20(
                    $encryptedData,
                    $encryptionKey,
                    $metadata
                );
            }

            return json_decode($decryptedJson, true);
        } catch (Exception $e) {
            Log::error('Data decryption failed', [
                'error'  => $e->getMessage(),
                'cipher' => $cipher,
                'key_id' => $keyId,
            ]);

            throw $e;
        }
    }

    /**
     * Generate a new encryption key.
     */
    public function generateKey(string $keyId, string $cipher = 'AES-256-GCM'): string
    {
        $keySize = self::CIPHER_METHODS[$cipher]['key_size'];
        $key = openssl_random_pseudo_bytes($keySize);

        // Store key securely (in production, use proper key management service)
        $this->storeKey($keyId, base64_encode($key));

        Log::info('Encryption key generated', [
            'key_id' => $keyId,
            'cipher' => $cipher,
            'size'   => $keySize * 8 . ' bits',
        ]);

        return base64_encode($key);
    }

    /**
     * Rotate encryption keys.
     */
    public function rotateKey(string $oldKeyId, string $newKeyId, string $cipher = 'AES-256-GCM'): array
    {
        $newKey = $this->generateKey($newKeyId, $cipher);

        // Mark old key for archival (still needed for decryption)
        $this->archiveKey($oldKeyId);

        return [
            'old_key_id'    => $oldKeyId,
            'new_key_id'    => $newKeyId,
            'rotated_at'    => now()->toIso8601String(),
            'next_rotation' => now()->addDays(self::KEY_ROTATION_DAYS)->toIso8601String(),
        ];
    }

    /**
     * Encrypt with AES-GCM.
     */
    private function encryptWithGCM(string $data, string $key, string $cipher): array
    {
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength === false) {
            throw new Exception('Failed to get IV length for cipher');
        }
        $iv = openssl_random_pseudo_bytes($ivLength);
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            $cipher,
            base64_decode($key),
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            throw new Exception('GCM encryption failed');
        }

        return [
            'encrypted_data' => base64_encode($encrypted),
            'cipher'         => $cipher,
            'iv'             => base64_encode($iv),
            'tag'            => base64_encode($tag),
            'timestamp'      => now()->toIso8601String(),
        ];
    }

    /**
     * Decrypt with AES-GCM.
     */
    private function decryptWithGCM(
        string $encryptedData,
        string $key,
        string $cipher,
        array $metadata
    ): string {
        $decrypted = openssl_decrypt(
            base64_decode($encryptedData),
            $cipher,
            base64_decode($key),
            OPENSSL_RAW_DATA,
            base64_decode($metadata['iv']),
            base64_decode($metadata['tag'])
        );

        if ($decrypted === false) {
            throw new Exception('GCM decryption failed');
        }

        return $decrypted;
    }

    /**
     * Encrypt with AES-CBC.
     */
    private function encryptWithCBC(string $data, string $key): array
    {
        $cipher = 'AES-256-CBC';
        $ivLength = openssl_cipher_iv_length($cipher);
        if ($ivLength === false) {
            throw new Exception('Failed to get IV length for cipher');
        }
        $iv = openssl_random_pseudo_bytes($ivLength);

        $encrypted = openssl_encrypt(
            $data,
            $cipher,
            base64_decode($key),
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new Exception('CBC encryption failed');
        }

        // Add HMAC for authentication
        $hmac = hash_hmac('sha256', $encrypted . $iv, $key);

        return [
            'encrypted_data' => base64_encode($encrypted),
            'cipher'         => $cipher,
            'iv'             => base64_encode($iv),
            'hmac'           => $hmac,
            'timestamp'      => now()->toIso8601String(),
        ];
    }

    /**
     * Decrypt with AES-CBC.
     */
    private function decryptWithCBC(
        string $encryptedData,
        string $key,
        array $metadata
    ): string {
        // Verify HMAC first
        $hmac = hash_hmac(
            'sha256',
            base64_decode($encryptedData) . base64_decode($metadata['iv']),
            $key
        );

        if (! hash_equals($hmac, $metadata['hmac'])) {
            throw new Exception('HMAC verification failed');
        }

        $decrypted = openssl_decrypt(
            base64_decode($encryptedData),
            'AES-256-CBC',
            base64_decode($key),
            OPENSSL_RAW_DATA,
            base64_decode($metadata['iv'])
        );

        if ($decrypted === false) {
            throw new Exception('CBC decryption failed');
        }

        return $decrypted;
    }

    /**
     * Encrypt with ChaCha20-Poly1305 (simplified).
     */
    private function encryptWithChaCha20(string $data, string $key): array
    {
        // Note: PHP doesn't have native ChaCha20-Poly1305 support
        // This is a fallback to AES-256-GCM
        // In production, use sodium_crypto_aead_chacha20poly1305_ietf_encrypt
        Log::warning('ChaCha20-Poly1305 not available, falling back to AES-256-GCM');

        return $this->encryptWithGCM($data, $key, 'AES-256-GCM');
    }

    /**
     * Decrypt with ChaCha20-Poly1305 (simplified).
     */
    private function decryptWithChaCha20(
        string $encryptedData,
        string $key,
        array $metadata
    ): string {
        // Note: PHP doesn't have native ChaCha20-Poly1305 support
        // This is a fallback to AES-256-GCM
        // In production, use sodium_crypto_aead_chacha20poly1305_ietf_decrypt
        Log::warning('ChaCha20-Poly1305 not available, falling back to AES-256-GCM');

        return $this->decryptWithGCM($encryptedData, $key, 'AES-256-GCM', $metadata);
    }

    /**
     * Get or generate an encryption key.
     */
    private function getOrGenerateKey(string $keyId, string $cipher): string
    {
        $key = $this->getKey($keyId);
        if (! $key) {
            $key = $this->generateKey($keyId, $cipher);
        }

        return $key;
    }

    /**
     * Get an encryption key.
     */
    private function getKey(string $keyId): ?string
    {
        return Cache::get("encryption_key:{$keyId}");
    }

    /**
     * Store an encryption key.
     */
    private function storeKey(string $keyId, string $key): void
    {
        // In production, use a proper key management service
        Cache::put("encryption_key:{$keyId}", $key, now()->addDays(self::KEY_ROTATION_DAYS * 2));
    }

    /**
     * Archive an old encryption key.
     */
    private function archiveKey(string $keyId): void
    {
        $key = $this->getKey($keyId);
        if ($key) {
            Cache::put("archived_key:{$keyId}", $key, now()->addYears(1));
            Log::info('Encryption key archived', ['key_id' => $keyId]);
        }
    }
}
