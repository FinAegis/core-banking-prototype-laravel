<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Enums;

/**
 * Types of tokens that can be issued in the commerce domain.
 */
enum TokenType: string
{
    case SOULBOUND = 'soulbound';
    case TRANSFERABLE = 'transferable';
    case SEMI_FUNGIBLE = 'semi_fungible';
    case FUNGIBLE = 'fungible';

    public function label(): string
    {
        return match ($this) {
            self::SOULBOUND     => 'Soulbound Token',
            self::TRANSFERABLE  => 'Transferable Token',
            self::SEMI_FUNGIBLE => 'Semi-Fungible Token',
            self::FUNGIBLE      => 'Fungible Token',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SOULBOUND     => 'Non-transferable token permanently bound to an identity',
            self::TRANSFERABLE  => 'Standard token that can be freely transferred',
            self::SEMI_FUNGIBLE => 'Token with limited transferability or quantity restrictions',
            self::FUNGIBLE      => 'Interchangeable token with unlimited supply',
        };
    }

    public function isTransferable(): bool
    {
        return match ($this) {
            self::SOULBOUND     => false,
            self::TRANSFERABLE  => true,
            self::SEMI_FUNGIBLE => true,
            self::FUNGIBLE      => true,
        };
    }
}
