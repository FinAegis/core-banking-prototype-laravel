<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Thrown when the server-side Privy email-OTP REST flow fails.
 *
 * Mirrors the named-constructor style of PrivyJwtException so tests can
 * assert on the specific failure mode rather than parsing message strings.
 */
final class PrivyEmailOtpException extends RuntimeException
{
    public static function misconfigured(): self
    {
        return new self('Privy app credentials are not configured.');
    }

    public static function transport(string $path, Throwable $previous): self
    {
        return new self(sprintf('Privy %s request failed (transport).', $path), 0, $previous);
    }

    public static function apiError(string $path, int $status, string $message): self
    {
        return new self(sprintf('Privy %s returned %d: %s', $path, $status, $message));
    }

    public static function malformedResponse(string $path, string $reason): self
    {
        return new self(sprintf('Privy %s response is malformed: %s', $path, $reason));
    }
}
