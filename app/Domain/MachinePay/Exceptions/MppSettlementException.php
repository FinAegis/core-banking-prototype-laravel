<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Exceptions;

/**
 * Thrown when an MPP payment settlement fails.
 */
class MppSettlementException extends MppException
{
    public static function railUnavailable(string $rail): self
    {
        return new self("Payment rail '{$rail}' is not available for settlement.");
    }

    public static function verificationFailed(string $detail): self
    {
        return new self("Payment verification failed: {$detail}");
    }

    public static function settlementFailed(string $reference, string $detail): self
    {
        return new self("Settlement failed for reference '{$reference}': {$detail}");
    }

    public static function spendingLimitExceeded(string $agentId, int $requested, int $limit): self
    {
        return new self(
            "Agent '{$agentId}' spending limit exceeded: requested {$requested} cents, limit {$limit} cents."
        );
    }
}
