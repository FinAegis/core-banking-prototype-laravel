<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Enums;

/**
 * Virtual card lifecycle status.
 */
enum CardStatus: string
{
    case PENDING = 'pending';           // Card created, not yet provisioned
    case ACTIVE = 'active';             // Card is active and can be used
    case FROZEN = 'frozen';             // Temporarily frozen by user/system
    case CANCELLED = 'cancelled';       // Permanently cancelled
    case EXPIRED = 'expired';           // Card has expired

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }

    public function canTransitionTo(self $newStatus): bool
    {
        return match ($this) {
            self::PENDING                  => in_array($newStatus, [self::ACTIVE, self::CANCELLED]),
            self::ACTIVE                   => in_array($newStatus, [self::FROZEN, self::CANCELLED, self::EXPIRED]),
            self::FROZEN                   => in_array($newStatus, [self::ACTIVE, self::CANCELLED]),
            self::CANCELLED, self::EXPIRED => false,
        };
    }
}
