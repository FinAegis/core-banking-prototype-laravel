<?php

declare(strict_types=1);

namespace App\Domain\Security\ValueObjects;

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use DateTimeImmutable;

class PostQuantumKeyPair
{
    /**
     * @param  string  $publicKey  Base64-encoded public key
     * @param  string  $secretKey  Base64-encoded secret key
     * @param  PostQuantumAlgorithm  $algorithm  Algorithm used to generate keys
     * @param  string  $keyId  Unique identifier for this key pair
     * @param  DateTimeImmutable  $createdAt  When the key pair was generated
     * @param  DateTimeImmutable|null  $expiresAt  Optional expiration
     */
    public function __construct(
        public readonly string $publicKey,
        public readonly string $secretKey,
        public readonly PostQuantumAlgorithm $algorithm,
        public readonly string $keyId,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $expiresAt = null,
    ) {
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTimeImmutable();
    }

    public function getPublicKeyBytes(): string
    {
        return base64_decode($this->publicKey, true) ?: '';
    }

    public function getSecretKeyBytes(): string
    {
        return base64_decode($this->secretKey, true) ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key_id'     => $this->keyId,
            'algorithm'  => $this->algorithm->value,
            'public_key' => $this->publicKey,
            'created_at' => $this->createdAt->format('c'),
            'expires_at' => $this->expiresAt?->format('c'),
        ];
    }
}
