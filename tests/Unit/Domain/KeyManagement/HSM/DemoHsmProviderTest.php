<?php

declare(strict_types=1);

use App\Domain\KeyManagement\HSM\DemoHsmProvider;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

describe('DemoHsmProvider', function () {
    beforeEach(function () {
        Cache::flush();
        $this->provider = new DemoHsmProvider();
    });

    describe('encryption', function () {
        it('encrypts and decrypts data', function () {
            $originalData = 'secret shard data';
            $keyId = 'test-key';

            $encrypted = $this->provider->encrypt($originalData, $keyId);
            $decrypted = $this->provider->decrypt($encrypted, $keyId);

            expect($decrypted)->toBe($originalData)
                ->and($encrypted)->not->toBe($originalData);
        });

        it('produces different ciphertext for same data', function () {
            $data = 'test data';

            $encrypted1 = $this->provider->encrypt($data, 'key1');
            $encrypted2 = $this->provider->encrypt($data, 'key1');

            // Random IV should make them different
            expect($encrypted1)->not->toBe($encrypted2);
        });

        it('handles binary data', function () {
            $binaryData = random_bytes(64);

            $encrypted = $this->provider->encrypt($binaryData, 'key');
            $decrypted = $this->provider->decrypt($encrypted, 'key');

            expect($decrypted)->toBe($binaryData);
        });
    });

    describe('storage', function () {
        it('stores and retrieves secrets', function () {
            $secretId = 'test-secret-123';
            $secretData = 'very secret data';

            $stored = $this->provider->store($secretId, $secretData);
            $retrieved = $this->provider->retrieve($secretId);

            expect($stored)->toBeTrue()
                ->and($retrieved)->toBe($secretData);
        });

        it('returns null for non-existent secret', function () {
            $result = $this->provider->retrieve('non-existent-secret');

            expect($result)->toBeNull();
        });

        it('deletes secrets', function () {
            $secretId = 'delete-me';
            $this->provider->store($secretId, 'data');

            $deleted = $this->provider->delete($secretId);
            $result = $this->provider->retrieve($secretId);

            expect($deleted)->toBeTrue()
                ->and($result)->toBeNull();
        });

        it('overwrites existing secrets', function () {
            $secretId = 'overwrite-test';

            $this->provider->store($secretId, 'original');
            $this->provider->store($secretId, 'updated');

            $result = $this->provider->retrieve($secretId);

            expect($result)->toBe('updated');
        });
    });

    describe('availability', function () {
        it('is always available', function () {
            expect($this->provider->isAvailable())->toBeTrue();
        });

        it('returns demo as provider name', function () {
            expect($this->provider->getProviderName())->toBe('demo');
        });
    });
});
