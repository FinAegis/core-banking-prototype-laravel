<?php

declare(strict_types=1);

namespace App\Domain\Pricing\Exceptions;

use RuntimeException;

/**
 * Thrown when FeeResolverService cannot determine a user's fee tier.
 *
 * Caught by PricingController to emit ERR_FEE_001 (HTTP 500).
 */
final class FeeResolverException extends RuntimeException
{
    public static function unresolvable(int $userId, string $reason = ''): self
    {
        $message = sprintf('Fee tier could not be resolved for user %d.', $userId);

        if ($reason !== '') {
            $message .= ' Reason: ' . $reason;
        }

        return new self($message);
    }
}
