<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Enums;

/**
 * Settlement status for MPP payments.
 */
enum MppSettlementStatus: string
{
    case PENDING = 'pending';
    case VERIFIED = 'verified';
    case SETTLED = 'settled';
    case FAILED = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING  => 'Pending',
            self::VERIFIED => 'Verified',
            self::SETTLED  => 'Settled',
            self::FAILED   => 'Failed',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::SETTLED, self::FAILED => true,
            default                     => false,
        };
    }
}
