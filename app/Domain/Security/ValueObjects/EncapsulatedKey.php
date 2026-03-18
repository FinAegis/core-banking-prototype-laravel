<?php

declare(strict_types=1);

namespace App\Domain\Security\ValueObjects;

use App\Domain\Security\Enums\PostQuantumAlgorithm;

class EncapsulatedKey
{
    /**
     * @param  string  $ciphertext  Base64-encoded encapsulated ciphertext
     * @param  string  $sharedSecret  Raw shared secret bytes (for immediate use, not storage)
     * @param  PostQuantumAlgorithm  $algorithm  Algorithm used for encapsulation
     * @param  string  $senderKeyId  Key ID of the sender's ephemeral key
     */
    public function __construct(
        public readonly string $ciphertext,
        public readonly string $sharedSecret,
        public readonly PostQuantumAlgorithm $algorithm,
        public readonly string $senderKeyId,
    ) {
    }

    public function getCiphertextBytes(): string
    {
        return base64_decode($this->ciphertext, true) ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ciphertext'    => $this->ciphertext,
            'algorithm'     => $this->algorithm->value,
            'sender_key_id' => $this->senderKeyId,
        ];
    }
}
