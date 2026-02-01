<?php

declare(strict_types=1);

use App\Domain\KeyManagement\Enums\ShardType;

describe('ShardType Enum', function () {
    it('has correct cases', function () {
        expect(ShardType::cases())->toHaveCount(3)
            ->and(ShardType::DEVICE->value)->toBe('device')
            ->and(ShardType::AUTH->value)->toBe('auth')
            ->and(ShardType::RECOVERY->value)->toBe('recovery');
    });

    it('returns correct labels', function () {
        expect(ShardType::DEVICE->label())->toBe('Device Enclave')
            ->and(ShardType::AUTH->label())->toBe('Authentication (HSM)')
            ->and(ShardType::RECOVERY->label())->toBe('Recovery Backup');
    });

    it('identifies HSM stored shards correctly', function () {
        expect(ShardType::AUTH->isHsmStored())->toBeTrue()
            ->and(ShardType::DEVICE->isHsmStored())->toBeFalse()
            ->and(ShardType::RECOVERY->isHsmStored())->toBeFalse();
    });

    it('identifies password required shards correctly', function () {
        expect(ShardType::RECOVERY->requiresPassword())->toBeTrue()
            ->and(ShardType::DEVICE->requiresPassword())->toBeFalse()
            ->and(ShardType::AUTH->requiresPassword())->toBeFalse();
    });

    it('returns all values as array', function () {
        $values = ShardType::values();
        expect($values)->toBe(['device', 'auth', 'recovery']);
    });
});
