<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Exceptions;

use RuntimeException;

/**
 * Too many attempts to register new wallet addresses in the current window.
 * Re-posting addresses the caller already owns is never limited.
 * HTTP 429 ADDRESS_REGISTRATION_RATE_LIMITED.
 */
class AddressRegistrationRateLimitedException extends RuntimeException
{
    public function __construct(
        public readonly int $retryAfterSeconds,
    ) {
        parent::__construct('Too many address registration attempts. Please try again later.', 429);
    }
}
