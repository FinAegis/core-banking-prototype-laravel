<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Enums;

enum KycVerificationLevel: string
{
    case BASIC = 'basic';
    case ENHANCED = 'enhanced';
    case FULL = 'full';

    public function getRequiredDocuments(): array
    {
        return match ($this) {
            self::BASIC    => ['government_id'],
            self::ENHANCED => ['government_id', 'proof_of_address', 'bank_statement'],
            self::FULL     => ['government_id', 'proof_of_address', 'bank_statement', 'business_registration', 'financial_statements'],
        };
    }

    public function getMaxTransactionLimit(): float
    {
        return match ($this) {
            self::BASIC    => 10000.0,
            self::ENHANCED => 50000.0,
            self::FULL     => 1000000.0,
        };
    }

    public function getDescription(): string
    {
        return match ($this) {
            self::BASIC    => 'Basic verification for low-value transactions',
            self::ENHANCED => 'Enhanced verification for medium-value transactions',
            self::FULL     => 'Full verification for high-value and business transactions',
        };
    }

    public function getVerificationPeriodDays(): int
    {
        return match ($this) {
            self::BASIC    => 180,    // 6 months
            self::ENHANCED => 365,  // 1 year
            self::FULL     => 730,     // 2 years
        };
    }
}
