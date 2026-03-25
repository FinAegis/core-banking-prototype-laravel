<?php

declare(strict_types=1);

namespace Zelta\Exceptions;

use RuntimeException;

/**
 * Thrown when a 402 Payment Required response is received
 * and auto-pay is disabled.
 */
class PaymentRequiredException extends RuntimeException
{
    /**
     * @param array<string, mixed> $requirements
     */
    public function __construct(
        string $url,
        public readonly array $requirements = [],
    ) {
        parent::__construct("Payment required for {$url}. Enable auto-pay or handle the payment manually.");
    }
}
