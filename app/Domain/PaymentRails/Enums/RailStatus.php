<?php

declare(strict_types=1);

namespace App\Domain\PaymentRails\Enums;

enum RailStatus: string
{
    case INITIATED = 'initiated';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case RETURNED = 'returned';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::INITIATED  => 'Initiated',
            self::PENDING    => 'Pending',
            self::PROCESSING => 'Processing',
            self::COMPLETED  => 'Completed',
            self::FAILED     => 'Failed',
            self::RETURNED   => 'Returned',
            self::CANCELLED  => 'Cancelled',
        };
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::RETURNED, self::CANCELLED => true,
            default                                                        => false,
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::COMPLETED;
    }
}
