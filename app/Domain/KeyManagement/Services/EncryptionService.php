<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Services;

use Illuminate\Support\Facades\Config;
use RuntimeException;

class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    private const TAG_LENGTH = 16;

    /**
     * Encrypt data for a specific user.
     */
    public function encryptForUser(string $data, string $userId): string
    {
        $key = $this->deriveUserKey($userId);

        return $this->encrypt($data, $key);
    }

    /**
     * Decrypt data for a specific user.
     */
    public function decryptForUser(string $encryptedData, string $userId): string
    {
        $key = $this->deriveUserKey($userId);

        return $this->decrypt($encryptedData, $key);
    }

    /**
     * Encrypt data with a password.
     */
    public function encryptWithPassword(string $data, string $password): string
    {
        $key = $this->deriveKeyFromPassword($password);

        return $this->encrypt($data, $key);
    }

    /**
     * Decrypt data with a password.
     */
    public function decryptWithPassword(string $encryptedData, string $password): string
    {
        $key = $this->deriveKeyFromPassword($password);

        return $this->decrypt($encryptedData, $key);
    }

    /**
     * Encrypt data with multi-party encryption (for audit vault).
     *
     * @param array<string> $keyHolders
     */
    public function encryptWithMultiParty(string $data, array $keyHolders): string
    {
        // For now, use a shared key derived from all key holders
        // In production, implement proper threshold encryption
        $combinedKey = hash('sha256', implode(':', $keyHolders) . Config::get('app.key'), true);

        return $this->encrypt($data, $combinedKey);
    }

    /**
     * Core encryption function using AES-256-GCM.
     */
    private function encrypt(string $data, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';

        $encrypted = openssl_encrypt(
            $data,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH
        );

        if ($encrypted === false) {
            throw new RuntimeException('Encryption failed');
        }

        // Format: base64(iv + tag + ciphertext)
        return base64_encode($iv . $tag . $encrypted);
    }

    /**
     * Core decryption function using AES-256-GCM.
     */
    private function decrypt(string $encryptedData, string $key): string
    {
        $decoded = base64_decode($encryptedData);

        if ($decoded === false || strlen($decoded) < 12 + self::TAG_LENGTH) {
            throw new RuntimeException('Invalid encrypted data format');
        }

        $iv = substr($decoded, 0, 12);
        $tag = substr($decoded, 12, self::TAG_LENGTH);
        $ciphertext = substr($decoded, 12 + self::TAG_LENGTH);

        $decrypted = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed - data may be corrupted or tampered');
        }

        return $decrypted;
    }

    /**
     * Derive a user-specific encryption key.
     */
    private function deriveUserKey(string $userId): string
    {
        $masterKey = Config::get('app.key');

        return hash('sha256', $masterKey . ':user:' . $userId, true);
    }

    /**
     * Derive an encryption key from a password using PBKDF2.
     */
    private function deriveKeyFromPassword(string $password): string
    {
        $salt = Config::get('keymanagement.password_salt', 'finaegis-recovery');

        return hash_pbkdf2(
            'sha256',
            $password,
            $salt,
            100000, // iterations
            32, // key length
            true
        );
    }
}
