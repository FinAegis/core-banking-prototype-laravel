<?php

namespace App\Domain\Lending\Enums;

enum CollateralType: string
{
    case REAL_ESTATE = 'real_estate';
    case VEHICLE = 'vehicle';
    case SECURITIES = 'securities';
    case CRYPTO = 'crypto';
    case EQUIPMENT = 'equipment';
    case INVENTORY = 'inventory';
    case ACCOUNTS_RECEIVABLE = 'accounts_receivable';
    case PERSONAL_GUARANTEE = 'personal_guarantee';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::REAL_ESTATE => 'Real Estate',
            self::VEHICLE => 'Vehicle',
            self::SECURITIES => 'Securities',
            self::CRYPTO => 'Cryptocurrency',
            self::EQUIPMENT => 'Equipment',
            self::INVENTORY => 'Inventory',
            self::ACCOUNTS_RECEIVABLE => 'Accounts Receivable',
            self::PERSONAL_GUARANTEE => 'Personal Guarantee',
            self::OTHER => 'Other',
        };
    }

    public function getRequiredLTV(): float
    {
        return match($this) {
            self::REAL_ESTATE => 0.80,          // 80% LTV
            self::SECURITIES => 0.70,           // 70% LTV
            self::VEHICLE => 0.60,              // 60% LTV
            self::EQUIPMENT => 0.50,            // 50% LTV
            self::INVENTORY => 0.40,            // 40% LTV
            self::ACCOUNTS_RECEIVABLE => 0.50,  // 50% LTV
            self::CRYPTO => 0.30,               // 30% LTV (high volatility)
            self::PERSONAL_GUARANTEE => 1.00,   // 100% (not asset-backed)
            self::OTHER => 0.50,                // 50% default
        };
    }

    public function getValuationFrequency(): int
    {
        // Days between required revaluations
        return match($this) {
            self::CRYPTO => 1,                  // Daily
            self::SECURITIES => 7,              // Weekly
            self::INVENTORY => 30,              // Monthly
            self::ACCOUNTS_RECEIVABLE => 30,    // Monthly
            self::VEHICLE => 90,                // Quarterly
            self::EQUIPMENT => 180,             // Semi-annually
            self::REAL_ESTATE => 365,           // Annually
            self::PERSONAL_GUARANTEE => 365,    // Annually
            self::OTHER => 90,                  // Quarterly default
        };
    }
}