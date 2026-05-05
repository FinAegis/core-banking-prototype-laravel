<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

/**
 * Thrown when a Privy-issued session JWT cannot be trusted.
 *
 * Use the named constructors so call-sites and tests can assert on the
 * specific failure mode rather than parsing message strings.
 */
final class PrivyJwtException extends RuntimeException
{
    public static function signatureInvalid(): self
    {
        return new self('Privy JWT signature is invalid.');
    }

    public static function expired(): self
    {
        return new self('Privy JWT has expired.');
    }

    public static function wrongIssuer(string $iss): self
    {
        return new self(sprintf('Privy JWT has unexpected issuer "%s".', $iss));
    }

    public static function wrongAudience(string $aud): self
    {
        return new self(sprintf('Privy JWT has unexpected audience "%s".', $aud));
    }

    public static function jwksUnreachable(): self
    {
        return new self('Privy JWKS endpoint is unreachable.');
    }

    public static function malformed(string $reason): self
    {
        return new self(sprintf('Privy JWT is malformed: %s', $reason));
    }
}
