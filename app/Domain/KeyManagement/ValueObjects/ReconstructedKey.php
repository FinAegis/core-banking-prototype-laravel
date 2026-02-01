<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\ValueObjects;

use DateTimeImmutable;

final readonly class ReconstructedKey
{
    public function __construct(
        public string $privateKey,
        public string $userId,
        public DateTimeImmutable $reconstructedAt,
        public int $ttlSeconds = 300
    ) {
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->reconstructedAt->modify("+{$this->ttlSeconds} seconds");

        return new DateTimeImmutable() > $expiresAt;
    }

    public function getPublicKey(): string
    {
        // Derive public key from private key (simplified for demo)
        // In production, use proper elliptic curve derivation
        return hash('sha256', $this->privateKey);
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->reconstructedAt->modify("+{$this->ttlSeconds} seconds");
    }

    /**
     * Securely wipe the key from memory.
     */
    public function wipe(): void
    {
        // In PHP, we can't truly wipe memory, but we can overwrite
        // This is a placeholder for the concept
        // In production, use sodium_memzero if available
    }
}
