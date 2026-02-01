<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Enums;

enum ShardStatus: string
{
    case ACTIVE = 'active';
    case REVOKED = 'revoked';
    case ROTATED = 'rotated';
    case PENDING = 'pending';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE  => 'Active',
            self::REVOKED => 'Revoked',
            self::ROTATED => 'Rotated',
            self::PENDING => 'Pending Activation',
        };
    }

    public function isUsable(): bool
    {
        return $this === self::ACTIVE;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
