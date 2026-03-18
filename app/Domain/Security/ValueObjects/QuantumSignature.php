<?php

declare(strict_types=1);

namespace App\Domain\Security\ValueObjects;

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use DateTimeImmutable;

class QuantumSignature
{
    /**
     * @param  string  $signature  Base64-encoded signature bytes
     * @param  PostQuantumAlgorithm  $algorithm  Algorithm used for signing
     * @param  string  $signerKeyId  Key ID of the signer
     * @param  DateTimeImmutable  $timestamp  When the signature was created
     * @param  string|null  $classicalSignature  Optional classical signature component (hybrid mode)
     */
    public function __construct(
        public readonly string $signature,
        public readonly PostQuantumAlgorithm $algorithm,
        public readonly string $signerKeyId,
        public readonly DateTimeImmutable $timestamp,
        public readonly ?string $classicalSignature = null,
    ) {
    }

    public function isHybrid(): bool
    {
        return $this->classicalSignature !== null;
    }

    public function getSignatureBytes(): string
    {
        return base64_decode($this->signature, true) ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'signature'     => $this->signature,
            'algorithm'     => $this->algorithm->value,
            'signer_key_id' => $this->signerKeyId,
            'timestamp'     => $this->timestamp->format('c'),
            'is_hybrid'     => $this->isHybrid(),
        ];
    }
}
