<?php

declare(strict_types=1);

namespace App\Domain\Interledger\Enums;

/**
 * Lifecycle states for an ILP payment.
 *
 * Flow: PENDING -> SENDING -> COMPLETED
 * Failure: PENDING/SENDING -> FAILED
 * Expiry:  PENDING/SENDING -> EXPIRED
 */
enum PaymentState: string
{
    case PENDING = 'pending';
    case SENDING = 'sending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case EXPIRED = 'expired';

    /**
     * Determine whether this state is a terminal (non-recoverable) state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::EXPIRED => true,
            self::PENDING, self::SENDING                 => false,
        };
    }

    /**
     * Determine whether this state represents a successful outcome.
     */
    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}
