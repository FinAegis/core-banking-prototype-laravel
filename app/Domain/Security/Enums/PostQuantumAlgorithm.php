<?php

declare(strict_types=1);

namespace App\Domain\Security\Enums;

enum PostQuantumAlgorithm: string
{
    case ML_KEM_768 = 'ML-KEM-768';
    case ML_KEM_1024 = 'ML-KEM-1024';
    case ML_DSA_65 = 'ML-DSA-65';
    case ML_DSA_87 = 'ML-DSA-87';
    case HYBRID_X25519_ML_KEM = 'X25519-ML-KEM-768';
    case HYBRID_ED25519_ML_DSA = 'Ed25519-ML-DSA-65';

    public function isKeyEncapsulation(): bool
    {
        return match ($this) {
            self::ML_KEM_768, self::ML_KEM_1024, self::HYBRID_X25519_ML_KEM => true,
            default                                                         => false,
        };
    }

    public function isDigitalSignature(): bool
    {
        return match ($this) {
            self::ML_DSA_65, self::ML_DSA_87, self::HYBRID_ED25519_ML_DSA => true,
            default                                                       => false,
        };
    }

    public function isHybrid(): bool
    {
        return match ($this) {
            self::HYBRID_X25519_ML_KEM, self::HYBRID_ED25519_ML_DSA => true,
            default                                                 => false,
        };
    }

    public function nistSecurityLevel(): int
    {
        return match ($this) {
            self::ML_KEM_768, self::ML_DSA_65, self::HYBRID_X25519_ML_KEM, self::HYBRID_ED25519_ML_DSA => 3,
            self::ML_KEM_1024, self::ML_DSA_87                                                         => 5,
        };
    }

    public function displayName(): string
    {
        return match ($this) {
            self::ML_KEM_768            => 'ML-KEM-768 (Kyber)',
            self::ML_KEM_1024           => 'ML-KEM-1024 (Kyber)',
            self::ML_DSA_65             => 'ML-DSA-65 (Dilithium)',
            self::ML_DSA_87             => 'ML-DSA-87 (Dilithium)',
            self::HYBRID_X25519_ML_KEM  => 'Hybrid X25519 + ML-KEM-768',
            self::HYBRID_ED25519_ML_DSA => 'Hybrid Ed25519 + ML-DSA-65',
        };
    }
}
