<?php

declare(strict_types=1);

namespace App\Domain\Security\Contracts;

use App\Domain\Security\Enums\PostQuantumAlgorithm;
use App\Domain\Security\ValueObjects\PostQuantumKeyPair;
use App\Domain\Security\ValueObjects\QuantumSignature;

interface PostQuantumSignatureInterface
{
    /**
     * Generate a post-quantum digital signature key pair.
     */
    public function generateSigningKeyPair(): PostQuantumKeyPair;

    /**
     * Sign a message using the signer's secret key.
     */
    public function sign(string $message, string $secretKey, string $signerKeyId): QuantumSignature;

    /**
     * Verify a signature against the message and signer's public key.
     */
    public function verify(string $message, QuantumSignature $signature, string $publicKey): bool;

    /**
     * Get the algorithm this signature scheme implements.
     */
    public function getAlgorithm(): PostQuantumAlgorithm;
}
