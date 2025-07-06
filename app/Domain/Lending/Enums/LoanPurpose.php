<?php

namespace App\Domain\Lending\Enums;

enum LoanPurpose: string
{
    case PERSONAL = 'personal';
    case BUSINESS = 'business';
    case HOME_IMPROVEMENT = 'home_improvement';
    case DEBT_CONSOLIDATION = 'debt_consolidation';
    case EDUCATION = 'education';
    case MEDICAL = 'medical';
    case VEHICLE = 'vehicle';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::PERSONAL => 'Personal Use',
            self::BUSINESS => 'Business Investment',
            self::HOME_IMPROVEMENT => 'Home Improvement',
            self::DEBT_CONSOLIDATION => 'Debt Consolidation',
            self::EDUCATION => 'Education',
            self::MEDICAL => 'Medical Expenses',
            self::VEHICLE => 'Vehicle Purchase',
            self::OTHER => 'Other',
        };
    }

    public function getBaseInterestRate(): float
    {
        return match($this) {
            self::EDUCATION => 4.5,
            self::MEDICAL => 5.0,
            self::HOME_IMPROVEMENT => 6.0,
            self::VEHICLE => 6.5,
            self::BUSINESS => 7.0,
            self::DEBT_CONSOLIDATION => 8.0,
            self::PERSONAL => 9.0,
            self::OTHER => 10.0,
        };
    }

    public function getMaxTermMonths(): int
    {
        return match($this) {
            self::EDUCATION => 120, // 10 years
            self::HOME_IMPROVEMENT => 84, // 7 years
            self::BUSINESS => 60, // 5 years
            self::VEHICLE => 60, // 5 years
            self::DEBT_CONSOLIDATION => 48, // 4 years
            self::MEDICAL => 36, // 3 years
            self::PERSONAL => 36, // 3 years
            self::OTHER => 24, // 2 years
        };
    }
}