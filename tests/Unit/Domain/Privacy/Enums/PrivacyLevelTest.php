<?php

declare(strict_types=1);

use App\Domain\Privacy\Enums\PrivacyLevel;

describe('PrivacyLevel Enum', function () {
    it('has correct cases', function () {
        expect(PrivacyLevel::cases())->toHaveCount(4)
            ->and(PrivacyLevel::FULL_DISCLOSURE->value)->toBe('full_disclosure')
            ->and(PrivacyLevel::SELECTIVE_DISCLOSURE->value)->toBe('selective_disclosure')
            ->and(PrivacyLevel::ZERO_KNOWLEDGE->value)->toBe('zero_knowledge')
            ->and(PrivacyLevel::RANGE_PROOF->value)->toBe('range_proof');
    });

    it('returns correct labels', function () {
        expect(PrivacyLevel::FULL_DISCLOSURE->label())->toBe('Full Disclosure')
            ->and(PrivacyLevel::SELECTIVE_DISCLOSURE->label())->toBe('Selective Disclosure')
            ->and(PrivacyLevel::ZERO_KNOWLEDGE->label())->toBe('Zero Knowledge Proof')
            ->and(PrivacyLevel::RANGE_PROOF->label())->toBe('Range Proof');
    });

    it('returns descriptions', function () {
        expect(PrivacyLevel::FULL_DISCLOSURE->description())
            ->toContain('revealed');

        expect(PrivacyLevel::ZERO_KNOWLEDGE->description())
            ->toContain('No attributes revealed');
    });

    it('returns correct strength values', function () {
        expect(PrivacyLevel::FULL_DISCLOSURE->strength())->toBe(0)
            ->and(PrivacyLevel::SELECTIVE_DISCLOSURE->strength())->toBe(1)
            ->and(PrivacyLevel::RANGE_PROOF->strength())->toBe(2)
            ->and(PrivacyLevel::ZERO_KNOWLEDGE->strength())->toBe(3);
    });

    it('orders by privacy strength correctly', function () {
        $levels = PrivacyLevel::cases();
        usort($levels, fn ($a, $b) => $a->strength() <=> $b->strength());

        expect($levels[0])->toBe(PrivacyLevel::FULL_DISCLOSURE)
            ->and($levels[3])->toBe(PrivacyLevel::ZERO_KNOWLEDGE);
    });

    it('returns all values as array', function () {
        $values = PrivacyLevel::values();

        expect($values)->toBeArray()
            ->toHaveCount(4)
            ->toContain('full_disclosure')
            ->toContain('zero_knowledge');
    });
});
