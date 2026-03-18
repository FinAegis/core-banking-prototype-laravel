<?php

declare(strict_types=1);

namespace App\Domain\Security\ValueObjects;

use App\Domain\Security\Enums\PostQuantumAlgorithm;

class HybridEncryptionResult
{
    /**
     * @param  string  $ciphertext  Base64-encoded encrypted data
     * @param  string  $nonce  Base64-encoded nonce/IV
     * @param  string  $kemCiphertext  Base64-encoded KEM ciphertext for key exchange
     * @param  PostQuantumAlgorithm  $algorithm  Algorithm used
     * @param  string  $senderKeyId  Ephemeral sender key ID
     * @param  string  $recipientKeyId  Recipient's key ID
     * @param  int  $version  Encryption format version
     */
    public function __construct(
        public readonly string $ciphertext,
        public readonly string $nonce,
        public readonly string $kemCiphertext,
        public readonly PostQuantumAlgorithm $algorithm,
        public readonly string $senderKeyId,
        public readonly string $recipientKeyId,
        public readonly int $version = 1,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'ciphertext'       => $this->ciphertext,
            'nonce'            => $this->nonce,
            'kem_ciphertext'   => $this->kemCiphertext,
            'algorithm'        => $this->algorithm->value,
            'sender_key_id'    => $this->senderKeyId,
            'recipient_key_id' => $this->recipientKeyId,
            'version'          => $this->version,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            ciphertext: (string) $data['ciphertext'],
            nonce: (string) $data['nonce'],
            kemCiphertext: (string) $data['kem_ciphertext'],
            algorithm: PostQuantumAlgorithm::from((string) $data['algorithm']),
            senderKeyId: (string) $data['sender_key_id'],
            recipientKeyId: (string) $data['recipient_key_id'],
            version: (int) ($data['version'] ?? 1),
        );
    }
}
