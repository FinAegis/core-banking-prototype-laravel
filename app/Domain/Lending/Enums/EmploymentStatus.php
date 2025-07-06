<?php

namespace App\Domain\Lending\Enums;

enum EmploymentStatus: string
{
    case EMPLOYED = 'employed';
    case SELF_EMPLOYED = 'self_employed';
    case UNEMPLOYED = 'unemployed';
    case RETIRED = 'retired';
    case STUDENT = 'student';
    case OTHER = 'other';

    public function label(): string
    {
        return match($this) {
            self::EMPLOYED => 'Employed',
            self::SELF_EMPLOYED => 'Self-Employed',
            self::UNEMPLOYED => 'Unemployed',
            self::RETIRED => 'Retired',
            self::STUDENT => 'Student',
            self::OTHER => 'Other',
        };
    }

    public function getRiskScore(): int
    {
        return match($this) {
            self::EMPLOYED => 10,
            self::SELF_EMPLOYED => 20,
            self::RETIRED => 15,
            self::STUDENT => 30,
            self::UNEMPLOYED => 50,
            self::OTHER => 40,
        };
    }
}