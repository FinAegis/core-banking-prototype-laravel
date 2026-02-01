<?php

declare(strict_types=1);

use App\Domain\KeyManagement\Enums\ShardStatus;

describe('ShardStatus Enum', function () {
    it('has correct cases', function () {
        expect(ShardStatus::cases())->toHaveCount(4)
            ->and(ShardStatus::ACTIVE->value)->toBe('active')
            ->and(ShardStatus::REVOKED->value)->toBe('revoked')
            ->and(ShardStatus::ROTATED->value)->toBe('rotated')
            ->and(ShardStatus::PENDING->value)->toBe('pending');
    });

    it('returns correct labels', function () {
        expect(ShardStatus::ACTIVE->label())->toBe('Active')
            ->and(ShardStatus::REVOKED->label())->toBe('Revoked')
            ->and(ShardStatus::ROTATED->label())->toBe('Rotated')
            ->and(ShardStatus::PENDING->label())->toBe('Pending Activation');
    });

    it('identifies usable status correctly', function () {
        expect(ShardStatus::ACTIVE->isUsable())->toBeTrue()
            ->and(ShardStatus::REVOKED->isUsable())->toBeFalse()
            ->and(ShardStatus::ROTATED->isUsable())->toBeFalse()
            ->and(ShardStatus::PENDING->isUsable())->toBeFalse();
    });

    it('returns all values as array', function () {
        $values = ShardStatus::values();
        expect($values)->toBe(['active', 'revoked', 'rotated', 'pending']);
    });
});
