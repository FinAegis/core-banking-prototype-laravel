<?php

declare(strict_types=1);

use App\Domain\KeyManagement\Enums\ShardType;
use App\Domain\KeyManagement\HSM\DemoHsmProvider;
use App\Domain\KeyManagement\HSM\HsmIntegrationService;
use App\Domain\KeyManagement\Services\EncryptionService;
use App\Domain\KeyManagement\Services\ShamirService;
use App\Domain\KeyManagement\ValueObjects\KeyShard;

uses(Tests\TestCase::class);

describe('ShamirService', function () {
    beforeEach(function () {
        $this->hsm = new HsmIntegrationService(new DemoHsmProvider());
        $this->encryption = new EncryptionService();
        $this->service = new ShamirService($this->hsm, $this->encryption);
    });

    describe('key splitting', function () {
        it('splits a private key into three shards', function () {
            $privateKey = 'test-private-key-for-splitting';
            $userId = 'user-test-123';

            $shards = $this->service->splitKey($privateKey, $userId);

            expect($shards)->toHaveCount(3)
                ->and($shards)->toHaveKeys(['device', 'auth', 'recovery'])
                ->and($shards['device'])->toBeInstanceOf(KeyShard::class)
                ->and($shards['auth'])->toBeInstanceOf(KeyShard::class)
                ->and($shards['recovery'])->toBeInstanceOf(KeyShard::class);
        });

        it('assigns correct types to shards', function () {
            $shards = $this->service->splitKey('key', 'user');

            expect($shards['device']->type)->toBe(ShardType::DEVICE)
                ->and($shards['auth']->type)->toBe(ShardType::AUTH)
                ->and($shards['recovery']->type)->toBe(ShardType::RECOVERY);
        });

        it('assigns correct indices to shards', function () {
            $shards = $this->service->splitKey('key', 'user');

            expect($shards['device']->index)->toBe(1)
                ->and($shards['auth']->index)->toBe(2)
                ->and($shards['recovery']->index)->toBe(3);
        });

        it('associates shards with correct user', function () {
            $userId = 'unique-user-id';
            $shards = $this->service->splitKey('key', $userId);

            expect($shards['device']->userId)->toBe($userId)
                ->and($shards['auth']->userId)->toBe($userId)
                ->and($shards['recovery']->userId)->toBe($userId);
        });

        it('encrypts auth shard with HSM', function () {
            $shards = $this->service->splitKey('key', 'user');

            // Auth shard should be HSM-encrypted (different from raw)
            expect($shards['auth']->encryptedFor)->toBe('hsm');
        });
    });

    describe('key reconstruction', function () {
        it('reconstructs key from device and auth shards', function () {
            $originalKey = 'my-secret-private-key';
            $userId = 'user-456';

            $shards = $this->service->splitKey($originalKey, $userId);

            // Simulate device shard being decrypted by device
            // Auth shard will be decrypted by HSM in service

            $reconstructed = $this->service->reconstructKey(
                $shards['device'],
                $shards['auth']
            );

            expect($reconstructed->privateKey)->toBe($originalKey)
                ->and($reconstructed->userId)->toBe($userId);
        });

        it('reconstructs key from device and recovery shards', function () {
            $originalKey = 'another-private-key';
            $userId = 'user-789';

            $shards = $this->service->splitKey($originalKey, $userId);

            $reconstructed = $this->service->reconstructKey(
                $shards['device'],
                $shards['recovery']
            );

            expect($reconstructed->privateKey)->toBe($originalKey);
        });

        it('reconstructs key from auth and recovery shards', function () {
            $originalKey = 'third-private-key';
            $userId = 'user-abc';

            $shards = $this->service->splitKey($originalKey, $userId);

            $reconstructed = $this->service->reconstructKey(
                $shards['auth'],
                $shards['recovery']
            );

            expect($reconstructed->privateKey)->toBe($originalKey);
        });

        it('returns reconstructed key with TTL', function () {
            $shards = $this->service->splitKey('key', 'user');

            $reconstructed = $this->service->reconstructKey(
                $shards['device'],
                $shards['auth']
            );

            expect($reconstructed->ttlSeconds)->toBe(300)
                ->and($reconstructed->isExpired())->toBeFalse()
                ->and($reconstructed->getExpiresAt())->toBeInstanceOf(DateTimeImmutable::class);
        });
    });

    describe('shard verification', function () {
        it('verifies valid shards against expected public key', function () {
            $privateKey = 'verification-test-key';
            $expectedPublicKey = hash('sha256', $privateKey);

            $shards = $this->service->splitKey($privateKey, 'user');

            $isValid = $this->service->verifyShards(
                [$shards['device'], $shards['auth']],
                $expectedPublicKey
            );

            expect($isValid)->toBeTrue();
        });

        it('rejects invalid shards', function () {
            $shards = $this->service->splitKey('key1', 'user');
            $wrongPublicKey = hash('sha256', 'different-key');

            $isValid = $this->service->verifyShards(
                [$shards['device'], $shards['auth']],
                $wrongPublicKey
            );

            expect($isValid)->toBeFalse();
        });

        it('rejects insufficient shards', function () {
            $shards = $this->service->splitKey('key', 'user');
            $publicKey = hash('sha256', 'key');

            // Only 1 shard (threshold is 2)
            $isValid = $this->service->verifyShards(
                [$shards['device']],
                $publicKey
            );

            expect($isValid)->toBeFalse();
        });
    });

    describe('configuration', function () {
        it('returns correct threshold', function () {
            expect($this->service->getThreshold())->toBe(2);
        });

        it('returns correct total shards', function () {
            expect($this->service->getTotalShards())->toBe(3);
        });

        it('throws on invalid threshold configuration', function () {
            expect(fn () => new ShamirService($this->hsm, $this->encryption, 3, 1))
                ->toThrow(InvalidArgumentException::class, 'Threshold must be at least 2');
        });

        it('throws when total shards less than threshold', function () {
            expect(fn () => new ShamirService($this->hsm, $this->encryption, 2, 3))
                ->toThrow(InvalidArgumentException::class, 'Total shards must be >= threshold');
        });

        it('throws when total shards exceeds limit', function () {
            expect(fn () => new ShamirService($this->hsm, $this->encryption, 15, 2))
                ->toThrow(InvalidArgumentException::class, 'Total shards cannot exceed 10');
        });
    });

    describe('key integrity', function () {
        it('maintains key integrity through multiple split/reconstruct cycles', function () {
            $originalKey = 'integrity-test-key-12345';

            // First cycle
            $shards1 = $this->service->splitKey($originalKey, 'user');
            $reconstructed1 = $this->service->reconstructKey($shards1['device'], $shards1['auth']);

            expect($reconstructed1->privateKey)->toBe($originalKey);

            // Second cycle with same key
            $shards2 = $this->service->splitKey($originalKey, 'user');
            $reconstructed2 = $this->service->reconstructKey($shards2['auth'], $shards2['recovery']);

            expect($reconstructed2->privateKey)->toBe($originalKey);
        });

        it('handles binary data in keys', function () {
            // Create a key with binary data
            $binaryKey = random_bytes(32);

            $shards = $this->service->splitKey($binaryKey, 'user');
            $reconstructed = $this->service->reconstructKey($shards['device'], $shards['auth']);

            expect($reconstructed->privateKey)->toBe($binaryKey);
        });

        it('handles empty string key', function () {
            $emptyKey = '';

            $shards = $this->service->splitKey($emptyKey, 'user');
            $reconstructed = $this->service->reconstructKey($shards['device'], $shards['auth']);

            expect($reconstructed->privateKey)->toBe($emptyKey);
        });
    });
});
