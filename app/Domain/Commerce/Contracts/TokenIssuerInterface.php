<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Contracts;

use App\Domain\Commerce\Enums\TokenType;
use App\Domain\Commerce\ValueObjects\SoulboundToken;

/**
 * Interface for token issuance services.
 */
interface TokenIssuerInterface
{
    /**
     * Issue a new token.
     *
     * @param array<string, mixed> $metadata
     */
    public function issueToken(
        TokenType $type,
        string $recipientId,
        array $metadata,
    ): SoulboundToken;

    /**
     * Verify a token's authenticity.
     */
    public function verifyToken(SoulboundToken $token): bool;

    /**
     * Revoke a token.
     */
    public function revokeToken(string $tokenId, string $reason): bool;

    /**
     * Check if a token is revoked.
     */
    public function isRevoked(string $tokenId): bool;
}
