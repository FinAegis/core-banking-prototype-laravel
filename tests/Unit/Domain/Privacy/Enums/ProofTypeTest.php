<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\ProofType;

describe('ProofType Enum', function () {
    it('has correct cases', function () {
        expect(ProofType::cases())->toHaveCount(7)
            ->and(ProofType::AGE_VERIFICATION->value)->toBe('age_verification')
            ->and(ProofType::RESIDENCY->value)->toBe('residency')
            ->and(ProofType::KYC_TIER->value)->toBe('kyc_tier')
            ->and(ProofType::ACCREDITED_INVESTOR->value)->toBe('accredited_investor')
            ->and(ProofType::SANCTIONS_CLEAR->value)->toBe('sanctions_clear')
            ->and(ProofType::INCOME_RANGE->value)->toBe('income_range')
            ->and(ProofType::CUSTOM->value)->toBe('custom');
    });

    it('returns correct labels', function () {
        expect(ProofType::AGE_VERIFICATION->label())->toBe('Age Verification')
            ->and(ProofType::RESIDENCY->label())->toBe('Residency Proof')
            ->and(ProofType::KYC_TIER->label())->toBe('KYC Tier Verification')
            ->and(ProofType::ACCREDITED_INVESTOR->label())->toBe('Accredited Investor Status')
            ->and(ProofType::SANCTIONS_CLEAR->label())->toBe('Sanctions Clearance');
    });

    it('returns descriptions', function () {
        expect(ProofType::AGE_VERIFICATION->description())
            ->toContain('age')
            ->toContain('date of birth');
    });

    it('returns required claims for each type', function () {
        expect(ProofType::AGE_VERIFICATION->requiredClaims())
            ->toContain('date_of_birth')
            ->toContain('minimum_age');

        expect(ProofType::RESIDENCY->requiredClaims())
            ->toContain('country')
            ->toContain('region');

        expect(ProofType::CUSTOM->requiredClaims())
            ->toBeArray()
            ->toBeEmpty();
    });

    it('returns all values as array', function () {
        $values = ProofType::values();

        expect($values)->toBeArray()
            ->toContain('age_verification')
            ->toContain('residency')
            ->toContain('kyc_tier');
    });
});
