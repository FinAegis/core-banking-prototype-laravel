<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

/**
 * Thrown when an inbound request reuses an idempotency key but the body
 * differs from the previously-stored record (different recipient, amount,
 * asset, etc.). HTTP 409 Conflict is the canonical mapping.
 */
class IdempotencyConflictException extends RuntimeException
{
    protected $code = 409;

    public function errorCode(): string
    {
        return 'IDEMPOTENCY_CONFLICT';
    }
}
