<?php

namespace App\Domain\Lending\Enums;

enum CollateralStatus: string
{
    case PENDING_VERIFICATION = 'pending_verification';
    case VERIFIED = 'verified';
    case REJECTED = 'rejected';
    case RELEASED = 'released';
    case LIQUIDATED = 'liquidated';

    public function label(): string
    {
        return match($this) {
            self::PENDING_VERIFICATION => 'Pending Verification',
            self::VERIFIED => 'Verified',
            self::REJECTED => 'Rejected',
            self::RELEASED => 'Released',
            self::LIQUIDATED => 'Liquidated',
        };
    }

    public function getColor(): string
    {
        return match($this) {
            self::PENDING_VERIFICATION => 'yellow',
            self::VERIFIED => 'green',
            self::REJECTED => 'red',
            self::RELEASED => 'blue',
            self::LIQUIDATED => 'gray',
        };
    }

    public function canBeModified(): bool
    {
        return match($this) {
            self::PENDING_VERIFICATION => true,
            self::VERIFIED => true,
            self::REJECTED => false,
            self::RELEASED => false,
            self::LIQUIDATED => false,
        };
    }
}