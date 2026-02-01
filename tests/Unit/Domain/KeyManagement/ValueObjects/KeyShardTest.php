<?php

declare(strict_types=1);

use App\Domain\KeyManagement\Enums\ShardType;
use App\Domain\KeyManagement\ValueObjects\KeyShard;

describe('KeyShard Value Object', function () {
    it('creates a key shard with all properties', function () {
        $shard = new KeyShard(
            type: ShardType::AUTH,
            data: 'encrypted-data',
            encryptedFor: 'hsm',
            userId: 'user-123',
            index: 2
        );

        expect($shard->type)->toBe(ShardType::AUTH)
            ->and($shard->data)->toBe('encrypted-data')
            ->and($shard->encryptedFor)->toBe('hsm')
            ->and($shard->userId)->toBe('user-123')
            ->and($shard->index)->toBe(2);
    });

    it('identifies HSM encrypted shards', function () {
        $authShard = new KeyShard(ShardType::AUTH, 'data', 'hsm', 'user-1');
        $deviceShard = new KeyShard(ShardType::DEVICE, 'data', 'device', 'user-1');

        expect($authShard->isHsmEncrypted())->toBeTrue()
            ->and($deviceShard->isHsmEncrypted())->toBeFalse();
    });

    it('identifies shards requiring password', function () {
        $recoveryShard = new KeyShard(ShardType::RECOVERY, 'data', 'cloud', 'user-1');
        $authShard = new KeyShard(ShardType::AUTH, 'data', 'hsm', 'user-1');

        expect($recoveryShard->requiresPassword())->toBeTrue()
            ->and($authShard->requiresPassword())->toBeFalse();
    });

    it('converts to array without exposing raw data', function () {
        $shard = new KeyShard(
            type: ShardType::DEVICE,
            data: 'secret-data',
            encryptedFor: 'device',
            userId: 'user-456',
            index: 1
        );

        $array = $shard->toArray();

        expect($array)->toHaveKeys(['type', 'encrypted_for', 'user_id', 'index', 'data_hash'])
            ->and($array['type'])->toBe('device')
            ->and($array['data_hash'])->toBe(hash('sha256', 'secret-data'))
            ->and($array)->not->toHaveKey('data');
    });

    it('can be serialized to JSON', function () {
        $shard = new KeyShard(ShardType::AUTH, 'data', 'hsm', 'user-1', 2);

        $json = json_encode($shard);

        expect($json)->toBeString()
            ->and(json_decode($json, true))->toHaveKey('type');
    });

    it('can be created from array', function () {
        $data = [
            'type'          => 'recovery',
            'data'          => 'encrypted-recovery',
            'encrypted_for' => 'user-cloud',
            'user_id'       => 'user-789',
            'index'         => 3,
        ];

        $shard = KeyShard::fromArray($data);

        expect($shard->type)->toBe(ShardType::RECOVERY)
            ->and($shard->data)->toBe('encrypted-recovery')
            ->and($shard->userId)->toBe('user-789')
            ->and($shard->index)->toBe(3);
    });
});
