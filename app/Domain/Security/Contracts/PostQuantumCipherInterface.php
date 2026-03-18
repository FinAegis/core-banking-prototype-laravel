<?php

declare(strict_types=1);

namespace App\Domain\Security\Contracts;

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\ValueObjects\EncapsulatedKey;
use App\Domain\Security\ValueObjects\PostQuantumKeyPair;

interface PostQuantumCipherInterface
{
    /**
     * Generate a post-quantum key encapsulation key pair.
     */
    public function generateKeyPair(): PostQuantumKeyPair;

    /**
     * Encapsulate a shared secret using the recipient's public key.
     * Returns ciphertext + shared secret (KEM encapsulation).
     */
    public function encapsulate(string $recipientPublicKey): EncapsulatedKey;

    /**
     * Decapsulate a shared secret from ciphertext using the secret key.
     */
    public function decapsulate(string $ciphertext, string $secretKey): string;

    /**
     * Get the algorithm this cipher implements.
     */
    public function getAlgorithm(): PostQuantumAlgorithm;

    /**
     * Get the NIST security level (1, 3, or 5).
     */
    public function getSecurityLevel(): int;
}
