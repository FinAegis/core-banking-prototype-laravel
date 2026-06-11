<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Exceptions;

use RuntimeException;

/**
 * Thrown when a ramp session with type=off is requested on a provider that
 * only ships onramp in v1 (Bridge: bank-rail onramp only per the brief;
 * offramp lands in v1.1 alongside SWIFT + additional networks).
 *
 * Extends RuntimeException so callers that only know the generic contract
 * still degrade to a 422 SESSION_ERROR; RampController maps this subclass to
 * the explicit OFFRAMP_NOT_AVAILABLE error code so mobile can render a real
 * "coming soon" state instead of a generic failure toast.
 */
class OfframpNotAvailableException extends RuntimeException
{
    public function __construct(string $provider)
    {
        parent::__construct(
            "Offramp (type=off) is not available yet on the '{$provider}' provider — deferred to v1.1. v1 is bank-rail onramp only."
        );
    }
}
