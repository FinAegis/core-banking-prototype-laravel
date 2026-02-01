<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Enums;

/**
 * Types of zero-knowledge proofs supported by the system.
 */
enum ProofType: string
{
    case AGE_VERIFICATION = 'age_verification';
    case RESIDENCY = 'residency';
    case KYC_TIER = 'kyc_tier';
    case ACCREDITED_INVESTOR = 'accredited_investor';
    case SANCTIONS_CLEAR = 'sanctions_clear';
    case INCOME_RANGE = 'income_range';
    case CUSTOM = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::AGE_VERIFICATION    => 'Age Verification',
            self::RESIDENCY           => 'Residency Proof',
            self::KYC_TIER            => 'KYC Tier Verification',
            self::ACCREDITED_INVESTOR => 'Accredited Investor Status',
            self::SANCTIONS_CLEAR     => 'Sanctions Clearance',
            self::INCOME_RANGE        => 'Income Range Verification',
            self::CUSTOM              => 'Custom Proof',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AGE_VERIFICATION    => 'Proves user is above a certain age without revealing exact date of birth',
            self::RESIDENCY           => 'Proves residency in a region without revealing exact address',
            self::KYC_TIER            => 'Proves KYC tier level without revealing identity documents',
            self::ACCREDITED_INVESTOR => 'Proves accredited investor status without revealing financial details',
            self::SANCTIONS_CLEAR     => 'Proves non-inclusion in sanctions lists without revealing identity',
            self::INCOME_RANGE        => 'Proves income falls within a range without revealing exact amount',
            self::CUSTOM              => 'Custom proof with user-defined claims',
        };
    }

    public function requiredClaims(): array
    {
        return match ($this) {
            self::AGE_VERIFICATION    => ['date_of_birth', 'minimum_age'],
            self::RESIDENCY           => ['country', 'region'],
            self::KYC_TIER            => ['kyc_tier', 'kyc_provider'],
            self::ACCREDITED_INVESTOR => ['accreditation_status', 'jurisdiction'],
            self::SANCTIONS_CLEAR     => ['identity_hash', 'sanctions_list_hash'],
            self::INCOME_RANGE        => ['income_range_min', 'income_range_max'],
            self::CUSTOM              => [],
        };
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
