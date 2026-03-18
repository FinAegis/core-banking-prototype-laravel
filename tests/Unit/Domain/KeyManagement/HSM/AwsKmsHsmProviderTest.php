<?php

declare(strict_types=1);

use App\Domain\KeyManagement\HSM\AwsKmsHsmProvider;
use Aws\Kms\KmsClient;
use Aws\Result;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    // Use array driver to avoid cross-process interference when running --parallel
    config(['cache.default' => 'array']);
    Cache::flush();
});

function createMockKmsClient(): KmsClient
{
    return Mockery::mock(KmsClient::class);
}

function createProvider(?KmsClient $kmsClient = null): AwsKmsHsmProvider
{
    return new AwsKmsHsmProvider(
        kmsClient: $kmsClient ?? createMockKmsClient(),
        keyArn: 'arn:aws:kms:us-east-1:123456789:key/test-key-id',
        signingKeyArn: 'arn:aws:kms:us-east-1:123456789:key/signing-key-id',
    );
}

describe('AwsKmsHsmProvider', function (): void {
    describe('getProviderName', function (): void {
        it('returns aws', function (): void {
            $provider = createProvider();
            expect($provider->getProviderName())->toBe('aws');
        });
    });

    describe('encrypt', function (): void {
        it('encrypts data via KMS', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('encrypt')
                ->once()
                ->with(Mockery::on(function ($args) {
                    return $args['Plaintext'] === 'secret data'
                        && $args['EncryptionAlgorithm'] === 'SYMMETRIC_DEFAULT';
                }))
                ->andReturn(new Result(['CiphertextBlob' => 'encrypted-blob']));

            $provider = createProvider($mockClient);
            $result = $provider->encrypt('secret data', 'default');

            expect($result)->toBe(base64_encode('encrypted-blob'));
        });

        it('throws on encryption failure', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('encrypt')
                ->once()
                ->andThrow(new RuntimeException('KMS error'));

            $provider = createProvider($mockClient);
            $provider->encrypt('data', 'default');
        })->throws(RuntimeException::class, 'AWS KMS encryption failed');
    });

    describe('decrypt', function (): void {
        it('decrypts data via KMS', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('decrypt')
                ->once()
                ->with(Mockery::on(function ($args) {
                    return $args['CiphertextBlob'] === base64_decode(base64_encode('encrypted-blob'))
                        && $args['EncryptionAlgorithm'] === 'SYMMETRIC_DEFAULT';
                }))
                ->andReturn(new Result(['Plaintext' => 'secret data']));

            $provider = createProvider($mockClient);
            $result = $provider->decrypt(base64_encode('encrypted-blob'), 'default');

            expect($result)->toBe('secret data');
        });

        it('throws on decryption failure', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('decrypt')
                ->once()
                ->andThrow(new RuntimeException('KMS error'));

            $provider = createProvider($mockClient);
            $provider->decrypt(base64_encode('data'), 'default');
        })->throws(RuntimeException::class, 'AWS KMS decryption failed');
    });

    describe('store and retrieve', function (): void {
        it('stores and retrieves secrets via encrypt/cache', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('encrypt')
                ->once()
                ->andReturn(new Result(['CiphertextBlob' => 'encrypted-secret']));
            $mockClient->shouldReceive('decrypt')
                ->once()
                ->andReturn(new Result(['Plaintext' => 'my-secret-value']));

            $provider = createProvider($mockClient);

            $stored = $provider->store('test-secret', 'my-secret-value');
            expect($stored)->toBeTrue();

            $retrieved = $provider->retrieve('test-secret');
            expect($retrieved)->toBe('my-secret-value');
        });

        it('returns null for missing secrets', function (): void {
            $provider = createProvider();
            expect($provider->retrieve('nonexistent'))->toBeNull();
        });
    });

    describe('delete', function (): void {
        it('deletes from cache', function (): void {
            Cache::put('aws_hsm:test-key', 'value', 3600);
            $provider = createProvider();

            $result = $provider->delete('test-key');
            expect($result)->toBeTrue();
            expect(Cache::get('aws_hsm:test-key'))->toBeNull();
        });
    });

    describe('isAvailable', function (): void {
        it('returns true when KMS key is accessible', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('describeKey')
                ->once()
                ->andReturn(new Result(['KeyMetadata' => ['KeyId' => 'test']]));

            $provider = createProvider($mockClient);
            expect($provider->isAvailable())->toBeTrue();
        });

        it('returns false when KMS key is not accessible', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('describeKey')
                ->once()
                ->andThrow(new RuntimeException('Not found'));

            $provider = createProvider($mockClient);
            expect($provider->isAvailable())->toBeFalse();
        });
    });

    describe('sign', function (): void {
        it('signs message hash via KMS', function (): void {
            // Build a valid DER signature
            $r = str_repeat('ab', 32);
            $s = str_repeat('cd', 32);
            $derR = '02' . '20' . $r;
            $derS = '02' . '20' . $s;
            $derLen = dechex((int) (strlen($derR . $derS) / 2));
            $der = '30' . $derLen . $derR . $derS;

            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('sign')
                ->once()
                ->with(Mockery::on(function ($args) {
                    return $args['MessageType'] === 'DIGEST'
                        && $args['SigningAlgorithm'] === 'ECDSA_SHA_256';
                }))
                ->andReturn(new Result(['Signature' => hex2bin($der)]));

            $provider = createProvider($mockClient);
            $messageHash = '0x' . str_repeat('aa', 32);
            $result = $provider->sign($messageHash, 'default');

            expect($result)->toStartWith('0x');
            expect(strlen($result))->toBe(132); // 0x + 64r + 64s + 2v
        });

        it('throws for invalid message hash format', function (): void {
            $provider = createProvider();
            $provider->sign('invalid-hash', 'default');
        })->throws(RuntimeException::class, 'Invalid message hash format');

        it('throws on KMS sign failure', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('sign')
                ->once()
                ->andThrow(new RuntimeException('Sign failed'));

            $provider = createProvider($mockClient);
            $provider->sign('0x' . str_repeat('aa', 32), 'default');
        })->throws(RuntimeException::class, 'AWS KMS signing failed');
    });

    describe('verify', function (): void {
        it('returns true for valid signature format', function (): void {
            $provider = createProvider();
            $messageHash = '0x' . str_repeat('aa', 32);
            $signature = '0x' . str_repeat('bb', 32) . str_repeat('cc', 32) . '1b';

            expect($provider->verify($messageHash, $signature, '0x' . str_repeat('dd', 32)))->toBeTrue();
        });

        it('returns false for invalid message hash', function (): void {
            $provider = createProvider();
            expect($provider->verify('invalid', '0x' . str_repeat('aa', 66), '0xpub'))->toBeFalse();
        });

        it('returns false for too-short signature', function (): void {
            $provider = createProvider();
            $messageHash = '0x' . str_repeat('aa', 32);
            expect($provider->verify($messageHash, '0xshort', '0xpub'))->toBeFalse();
        });
    });

    describe('getPublicKey', function (): void {
        it('fetches public key from KMS', function (): void {
            // Create a DER-like structure with an uncompressed EC point
            $x = str_repeat('ab', 32);
            $y = str_repeat('cd', 32);
            $fakePrefix = str_repeat('00', 24); // 48 hex chars = 24 bytes prefix
            $derHex = $fakePrefix . '04' . $x . $y;

            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('getPublicKey')
                ->once()
                ->andReturn(new Result(['PublicKey' => hex2bin($derHex)]));

            $provider = createProvider($mockClient);
            $result = $provider->getPublicKey('default');

            expect($result)->toStartWith('0x');
            expect(strlen($result))->toBe(130); // 0x + 128 hex chars (64 bytes)
        });

        it('throws on failure', function (): void {
            $mockClient = createMockKmsClient();
            $mockClient->shouldReceive('getPublicKey')
                ->once()
                ->andThrow(new RuntimeException('Key not found'));

            $provider = createProvider($mockClient);
            $provider->getPublicKey('default');
        })->throws(RuntimeException::class, 'AWS KMS getPublicKey failed');
    });
});
