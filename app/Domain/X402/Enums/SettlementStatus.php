<?php

declare(strict_types=1);

namespace App\Domain\X402\Enums;

/**
 * Settlement lifecycle status for x402 payments.
 *
 * Flow: PENDING -> VERIFIED -> SETTLED
 * Failure: PENDING -> FAILED
 * Expiry:  PENDING -> EXPIRED
 */
enum SettlementStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case SETTLED = 'settled';
    case FAILED = 'failed';
    case EXPIRED = 'expired';

    /**
     * Get a human-readable label for this status.
     */
    public function label(): string
    {
        return match ($this) {
            self::PENDING  => 'Pending',
            self::VERIFIED => 'Verified',
            self::SETTLED  => 'Settled',
            self::FAILED   => 'Failed',
            self::EXPIRED  => 'Expired',
        };
    }

    /**
     * Determine whether this status represents a terminal state.
     */
    public function isFinal(): bool
    {
        return match ($this) {
            self::SETTLED, self::FAILED, self::EXPIRED => true,
            self::PENDING, self::VERIFIED              => false,
        };
    }

    /**
     * Get the Filament/admin UI color for this status.
     */
    public function color(): string
    {
        return match ($this) {
            self::PENDING  => 'gray',
            self::VERIFIED => 'blue',
            self::SETTLED  => 'green',
            self::FAILED   => 'red',
            self::EXPIRED  => 'orange',
        };
    }
}
