<?php

namespace App\Domain\Exchange\ValueObjects;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case OPEN = 'open';
    case PARTIALLY_FILLED = 'partially_filled';
    case FILLED = 'filled';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case REJECTED = 'rejected';

    public function isFinal(): bool
    {
        return in_array($this, [
            self::FILLED,
            self::CANCELLED,
            self::EXPIRED,
            self::REJECTED,
        ]);
    }

    public function canBeFilled(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::OPEN,
            self::PARTIALLY_FILLED,
        ]);
    }

    public function getLabel(): string
    {
        return match($this) {
            self::PENDING => 'Pending',
            self::OPEN => 'Open',
            self::PARTIALLY_FILLED => 'Partially Filled',
            self::FILLED => 'Filled',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::REJECTED => 'Rejected',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::PENDING => 'gray',
            self::OPEN => 'blue',
            self::PARTIALLY_FILLED => 'yellow',
            self::FILLED => 'green',
            self::CANCELLED => 'red',
            self::EXPIRED => 'gray',
            self::REJECTED => 'red',
        };
    }
}