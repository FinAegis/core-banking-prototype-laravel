<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\ValueObjects;

enum CollateralType: string
{
    case CRYPTO = 'crypto';
    case FIAT = 'fiat';
    case COMMODITY = 'commodity';
    case MIXED = 'mixed';
    case ALGORITHMIC = 'algorithmic';

    public function isVolatile(): bool
    {
        return match ($this) {
            self::CRYPTO, self::COMMODITY => true,
            self::FIAT, self::MIXED, self::ALGORITHMIC => false,
        };
    }

    public function defaultLiquidationThreshold(): float
    {
        return match ($this) {
            self::CRYPTO      => 150.0,      // 150% for volatile crypto
            self::FIAT        => 110.0,        // 110% for stable fiat
            self::COMMODITY   => 130.0,   // 130% for commodities
            self::MIXED       => 125.0,       // 125% for mixed collateral
            self::ALGORITHMIC => 120.0, // 120% for algorithmic
        };
    }

    public function minimumRatio(): float
    {
        return match ($this) {
            self::CRYPTO      => 200.0,      // 200% minimum for crypto
            self::FIAT        => 120.0,        // 120% for fiat
            self::COMMODITY   => 150.0,   // 150% for commodities
            self::MIXED       => 140.0,       // 140% for mixed
            self::ALGORITHMIC => 130.0, // 130% for algorithmic
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::CRYPTO      => 'Cryptocurrency',
            self::FIAT        => 'Fiat Currency',
            self::COMMODITY   => 'Commodity',
            self::MIXED       => 'Mixed Collateral',
            self::ALGORITHMIC => 'Algorithmic',
        };
    }
}
