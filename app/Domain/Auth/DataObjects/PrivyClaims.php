<?php

declare(strict_types=1);

namespace App\Domain\Auth\DataObjects;

use App\Domain\Auth\Exceptions\PrivyJwtException;
use DateTimeImmutable;

/**
 * Verified claims extracted from a Privy session JWT.
 *
 * `linkedAccounts` mirrors the optional `linked_accounts` custom claim Privy
 * embeds when a Privy user has one or more linked wallets / OAuth identities.
 * It is left as `array` (not strongly typed) because the shape is provider-
 * specific and the backend only forwards it through to consumers that need it.
 */
final class PrivyClaims
{
    /**
     * @param array<int, array<string, mixed>> $linkedAccounts
     */
    public function __construct(
        public readonly string $privyUserId,
        public readonly string $issuer,
        public readonly string $audience,
        public readonly DateTimeImmutable $issuedAt,
        public readonly DateTimeImmutable $expiresAt,
        public readonly array $linkedAccounts = [],
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @throws PrivyJwtException
     */
    public static function fromPayload(array $payload): self
    {
        foreach (['sub', 'iss', 'aud', 'iat', 'exp'] as $claim) {
            if (! array_key_exists($claim, $payload)) {
                throw PrivyJwtException::malformed(sprintf('missing "%s" claim', $claim));
            }
        }

        $sub = $payload['sub'];
        $iss = $payload['iss'];
        $aud = $payload['aud'];
        $iat = $payload['iat'];
        $exp = $payload['exp'];

        if (! is_string($sub) || $sub === '') {
            throw PrivyJwtException::malformed('"sub" must be a non-empty string');
        }

        if (! is_string($iss)) {
            throw PrivyJwtException::malformed('"iss" must be a string');
        }

        if (! is_string($aud)) {
            throw PrivyJwtException::malformed('"aud" must be a string');
        }

        if (! is_int($iat) && ! (is_numeric($iat) && (float) $iat == (int) $iat)) {
            throw PrivyJwtException::malformed('"iat" must be an integer timestamp');
        }

        if (! is_int($exp) && ! (is_numeric($exp) && (float) $exp == (int) $exp)) {
            throw PrivyJwtException::malformed('"exp" must be an integer timestamp');
        }

        $linked = $payload['linked_accounts'] ?? [];
        if (! is_array($linked)) {
            $linked = [];
        }

        /** @var array<int, array<string, mixed>> $linked */
        return new self(
            privyUserId: $sub,
            issuer: $iss,
            audience: $aud,
            issuedAt: (new DateTimeImmutable())->setTimestamp((int) $iat),
            expiresAt: (new DateTimeImmutable())->setTimestamp((int) $exp),
            linkedAccounts: $linked,
        );
    }
}
