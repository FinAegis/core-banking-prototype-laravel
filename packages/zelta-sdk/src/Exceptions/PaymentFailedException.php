<?php

declare(strict_types=1);

namespace Zelta\Exceptions;

use RuntimeException;

/**
 * Thrown when a payment attempt fails during the retry.
 */
class PaymentFailedException extends RuntimeException
{
    public function __construct(
        string $url,
        public readonly int $statusCode,
    ) {
        parent::__construct("Payment failed for {$url} — server returned HTTP {$statusCode} after payment.");
    }
}
