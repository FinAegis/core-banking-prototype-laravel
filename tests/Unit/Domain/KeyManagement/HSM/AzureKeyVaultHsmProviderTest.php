<?php

declare(strict_types=1);

use App\Domain\KeyManagement\HSM\AzureKeyVaultHsmProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Cache::flush();
});

function createAzureProvider(): AzureKeyVaultHsmProvider
{
    return new AzureKeyVaultHsmProvider(
        vaultUrl: 'https://test-vault.vault.azure.net',
        keyName: 'test-key',
        tenantId: 'test-tenant-id',
        clientId: 'test-client-id',
        clientSecret: 'test-client-secret',
        signingKeyName: 'signing-key',
    );
}

function fakeAzureAuth(): void
{
    // Pre-cache the token to avoid auth HTTP calls in tests
    Cache::put('azure_hsm:access_token', 'fake-access-token', 3600);
}

describe('AzureKeyVaultHsmProvider', function (): void {
    describe('getProviderName', function (): void {
        it('returns azure', function (): void {
            $provider = createAzureProvider();
            expect($provider->getProviderName())->toBe('azure');
        });
    });

    describe('encrypt', function (): void {
        it('encrypts data via Key Vault REST API', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/keys/test-key/encrypt*' => Http::response([
                    'value' => 'encrypted-base64-value',
                ]),
            ]);

            $provider = createAzureProvider();
            $result = $provider->encrypt('secret data', 'default');

            expect($result)->toBe('encrypted-base64-value');
        });

        it('throws on API failure', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/keys/test-key/encrypt*' => Http::response('Error', 500),
            ]);

            $provider = createAzureProvider();
            $provider->encrypt('data', 'default');
        })->throws(RuntimeException::class, 'Azure Key Vault encryption failed');
    });

    describe('decrypt', function (): void {
        it('decrypts data via Key Vault REST API', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/keys/test-key/decrypt*' => Http::response([
                    'value' => base64_encode('decrypted data'),
                ]),
            ]);

            $provider = createAzureProvider();
            $result = $provider->decrypt('encrypted-value', 'default');

            expect($result)->toBe('decrypted data');
        });

        it('throws on API failure', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/keys/test-key/decrypt*' => Http::response('Error', 500),
            ]);

            $provider = createAzureProvider();
            $provider->decrypt('encrypted', 'default');
        })->throws(RuntimeException::class, 'Azure Key Vault decryption failed');
    });

    describe('store and retrieve', function (): void {
        it('stores secrets via Key Vault secrets API', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/secrets/my-secret*' => Http::response([
                    'id'    => 'https://test-vault.vault.azure.net/secrets/my-secret',
                    'value' => base64_encode('stored-value'),
                ]),
            ]);

            $provider = createAzureProvider();
            $stored = $provider->store('my-secret', 'stored-value');
            expect($stored)->toBeTrue();
        });

        it('retrieves secrets from Key Vault', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/secrets/my-secret*' => Http::response([
                    'value' => base64_encode('my-secret-data'),
                ]),
            ]);

            $provider = createAzureProvider();
            $result = $provider->retrieve('my-secret');
            expect($result)->toBe('my-secret-data');
        });

        it('returns null when secret not found', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/secrets/missing*' => Http::response('Not found', 404),
            ]);

            $provider = createAzureProvider();
            expect($provider->retrieve('missing'))->toBeNull();
        });
    });

    describe('delete', function (): void {
        it('deletes secret via API', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/secrets/delete-me*' => Http::response([
                    'recoveryId' => 'https://test-vault.vault.azure.net/deletedsecrets/delete-me',
                ]),
            ]);

            $provider = createAzureProvider();
            expect($provider->delete('delete-me'))->toBeTrue();
        });

        it('returns false on failure', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/secrets/fail-delete*' => Http::response('Error', 500),
            ]);

            $provider = createAzureProvider();
            expect($provider->delete('fail-delete'))->toBeFalse();
        });
    });

    describe('isAvailable', function (): void {
        it('returns true when vault is accessible', function (): void {
            fakeAzureAuth();

            Http::preventStrayRequests();
            Http::fake([
                'https://test-vault.vault.azure.net/keys*' => Http::response([
                    'value' => [],
                ]),
            ]);

            $provider = createAzureProvider();
            expect($provider->isAvailable())->toBeTrue();
        });

        it('returns false when vault is not accessible', function (): void {
            fakeAzureAuth();

            Http::preventStrayRequests();
            Http::fake([
                'https://test-vault.vault.azure.net/keys*' => Http::response('Forbidden', 403),
            ]);

            $provider = createAzureProvider();
            expect($provider->isAvailable())->toBeFalse();
        });
    });

    describe('sign', function (): void {
        it('signs message hash via Key Vault', function (): void {
            fakeAzureAuth();

            // Create a fake r||s signature (each 32 bytes)
            $r = str_repeat('ab', 32);
            $s = str_repeat('cd', 32);
            $sigBytes = hex2bin($r . $s);

            Http::fake([
                'test-vault.vault.azure.net/keys/signing-key/sign*' => Http::response([
                    'value' => base64_encode($sigBytes),
                ]),
            ]);

            $provider = createAzureProvider();
            $messageHash = '0x' . str_repeat('aa', 32);
            $result = $provider->sign($messageHash, 'default');

            expect($result)->toStartWith('0x');
            expect(strlen($result))->toBe(132); // 0x + 64r + 64s + 2v
        });

        it('throws for invalid message hash format', function (): void {
            $provider = createAzureProvider();
            $provider->sign('invalid-hash', 'default');
        })->throws(RuntimeException::class, 'Invalid message hash format');

        it('throws on API failure', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/keys/signing-key/sign*' => Http::response('Error', 500),
            ]);

            $provider = createAzureProvider();
            $provider->sign('0x' . str_repeat('aa', 32), 'default');
        })->throws(RuntimeException::class, 'Azure Key Vault signing failed');
    });

    describe('verify', function (): void {
        it('returns true for valid signature format', function (): void {
            $provider = createAzureProvider();
            $messageHash = '0x' . str_repeat('aa', 32);
            $signature = '0x' . str_repeat('bb', 32) . str_repeat('cc', 32) . '1b';

            expect($provider->verify($messageHash, $signature, '0x' . str_repeat('dd', 32)))->toBeTrue();
        });

        it('returns false for invalid message hash', function (): void {
            $provider = createAzureProvider();
            expect($provider->verify('invalid', '0x' . str_repeat('aa', 66), '0xpub'))->toBeFalse();
        });

        it('returns false for too-short signature', function (): void {
            $provider = createAzureProvider();
            $messageHash = '0x' . str_repeat('aa', 32);
            expect($provider->verify($messageHash, '0xshort', '0xpub'))->toBeFalse();
        });
    });

    describe('getPublicKey', function (): void {
        it('fetches EC public key from Key Vault', function (): void {
            fakeAzureAuth();

            $xBytes = str_repeat("\xab", 32);
            $yBytes = str_repeat("\xcd", 32);

            Http::fake([
                'test-vault.vault.azure.net/keys/signing-key?*' => Http::response([
                    'key' => [
                        'kty' => 'EC',
                        'x'   => base64_encode($xBytes),
                        'y'   => base64_encode($yBytes),
                    ],
                ]),
            ]);

            $provider = createAzureProvider();
            $result = $provider->getPublicKey('default');

            expect($result)->toStartWith('0x');
            expect(strlen($result))->toBe(130); // 0x + 128 hex chars
        });

        it('throws when EC coordinates are missing', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/keys/signing-key?*' => Http::response([
                    'key' => ['kty' => 'EC'],
                ]),
            ]);

            $provider = createAzureProvider();
            $provider->getPublicKey('default');
        })->throws(RuntimeException::class, 'Azure Key Vault getPublicKey failed');

        it('throws on API failure', function (): void {
            fakeAzureAuth();

            Http::fake([
                'test-vault.vault.azure.net/keys/signing-key?*' => Http::response('Error', 500),
            ]);

            $provider = createAzureProvider();
            $provider->getPublicKey('default');
        })->throws(RuntimeException::class, 'Azure Key Vault getPublicKey failed');
    });

    describe('OAuth token management', function (): void {
        it('authenticates via Azure AD and caches token', function (): void {
            Http::fake([
                'login.microsoftonline.com/*' => Http::response([
                    'access_token' => 'new-access-token',
                    'token_type'   => 'Bearer',
                    'expires_in'   => 3600,
                ]),
                'test-vault.vault.azure.net/keys*' => Http::response(['value' => []]),
            ]);

            $provider = createAzureProvider();
            $provider->isAvailable();

            // Token should be cached now
            expect(Cache::get('azure_hsm:access_token'))->toBe('new-access-token');
        });

        it('returns false when Azure AD auth fails', function (): void {
            Http::fake([
                'login.microsoftonline.com/*' => Http::response('Unauthorized', 401),
            ]);

            $provider = createAzureProvider();
            // isAvailable catches auth exceptions and returns false
            expect($provider->isAvailable())->toBeFalse();
        });
    });
});
