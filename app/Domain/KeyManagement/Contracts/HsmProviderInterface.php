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
}
