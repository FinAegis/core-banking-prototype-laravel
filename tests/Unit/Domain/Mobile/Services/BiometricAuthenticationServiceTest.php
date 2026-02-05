<?php

declare(strict_types=1);

use App\Domain\Mobile\Exceptions\BiometricBlockedException;
use App\Domain\Mobile\Models\BiometricChallenge;
use App\Domain\Mobile\Models\BiometricFailure;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Domain\Mobile\Services\BiometricAuthenticationService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Cache::flush();

    $this->service = new BiometricAuthenticationService();
    $this->user = User::factory()->create();
    $this->device = MobileDevice::factory()->create([
        'user_id'              => $this->user->id,
        'biometric_enabled'    => true,
        'biometric_public_key' => generateTestEcPublicKey(),
        'biometric_key_id'     => 'test-key-id',
        'biometric_enabled_at' => now(),
    ]);
});

/**
 * Generate a real ECDSA P-256 public key for testing.
 */
function generateTestEcPublicKey(): string
{
    $config = [
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name'       => 'prime256v1',
    ];

    $key = openssl_pkey_new($config);
    if ($key === false) {
        return '-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEtest1234567890abcdefghijklmn
opqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890test==
-----END PUBLIC KEY-----';
    }

    $details = openssl_pkey_get_details($key);
    if ($details === false) {
        return '';
    }

    return $details['key'] ?? '';
}

/**
 * Generate an ECDSA P-256 key pair (private + public) for sign/verify tests.
 *
 * @return array{private_key: OpenSSLAsymmetricKey, public_key_pem: string}
 */
function generateTestEcKeyPair(): array
{
    $config = [
        'private_key_type' => OPENSSL_KEYTYPE_EC,
        'curve_name'       => 'prime256v1',
    ];

    $key = openssl_pkey_new($config);
    assert($key !== false, 'OpenSSL EC key generation failed');

    $details = openssl_pkey_get_details($key);
    assert($details !== false, 'OpenSSL key details retrieval failed');

    return [
        'private_key'    => $key,
        'public_key_pem' => $details['key'],
    ];
}

describe('BiometricAuthenticationService', function (): void {
    describe('createChallenge', function (): void {
        it('creates a biometric challenge for a device', function (): void {
            $challenge = $this->service->createChallenge($this->device);

            expect($challenge)->toBeInstanceOf(BiometricChallenge::class);
            expect($challenge->mobile_device_id)->toBe($this->device->id);
            expect($challenge->user_id)->toBe($this->user->id);
            expect($challenge->status)->toBe(BiometricChallenge::STATUS_PENDING);
            expect($challenge->challenge)->not->toBeEmpty();
            expect($challenge->expires_at->isFuture())->toBeTrue();
        });

        it('stores ip address when provided', function (): void {
            $challenge = $this->service->createChallenge($this->device, '192.168.1.100');

            expect($challenge->ip_address)->toBe('192.168.1.100');
        });

        it('invalidates existing pending challenges on new challenge creation', function (): void {
            $firstChallenge = $this->service->createChallenge($this->device);
            $secondChallenge = $this->service->createChallenge($this->device);

            $firstChallenge->refresh();

            expect($firstChallenge->status)->toBe(BiometricChallenge::STATUS_EXPIRED);
            expect($secondChallenge->status)->toBe(BiometricChallenge::STATUS_PENDING);
        });

        it('creates challenge with 5-minute TTL', function (): void {
            Carbon::setTestNow(now());

            $challenge = $this->service->createChallenge($this->device);

            $expectedExpiry = now()->addSeconds(BiometricChallenge::CHALLENGE_TTL_SECONDS);
            expect($challenge->expires_at->diffInSeconds($expectedExpiry))->toBeLessThanOrEqual(1);

            Carbon::setTestNow();
        });
    });

    describe('challenge expiration', function (): void {
        it('marks challenge as expired after TTL passes', function (): void {
            Carbon::setTestNow(now());
            $challenge = $this->service->createChallenge($this->device);

            expect($challenge->isValid())->toBeTrue();
            expect($challenge->isExpired())->toBeFalse();

            // Travel past the TTL
            Carbon::setTestNow(now()->addSeconds(BiometricChallenge::CHALLENGE_TTL_SECONDS + 1));

            expect($challenge->isExpired())->toBeTrue();
            expect($challenge->isValid())->toBeFalse();

            Carbon::setTestNow();
        });

        it('cleans up expired challenges', function (): void {
            Carbon::setTestNow(now());

            // Create a challenge, then expire it
            $challenge = $this->service->createChallenge($this->device);
            expect($challenge->status)->toBe(BiometricChallenge::STATUS_PENDING);

            // Move past expiration
            Carbon::setTestNow(now()->addSeconds(BiometricChallenge::CHALLENGE_TTL_SECONDS + 1));

            $cleaned = $this->service->cleanupExpiredChallenges();

            expect($cleaned)->toBe(1);

            $challenge->refresh();
            expect($challenge->status)->toBe(BiometricChallenge::STATUS_EXPIRED);

            Carbon::setTestNow();
        });
    });

    describe('verifyAndCreateSession', function (): void {
        it('returns null when device is blocked', function (): void {
            $this->device->update(['is_blocked' => true, 'blocked_reason' => 'Security issue']);

            $challenge = $this->service->createChallenge($this->device);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                'fake-signature',
                '192.168.1.100'
            );

            expect($result)->toBeNull();

            // Should record a failure with REASON_DEVICE_BLOCKED
            $this->assertDatabaseHas('biometric_failures', [
                'mobile_device_id' => $this->device->id,
                'failure_reason'   => BiometricFailure::REASON_DEVICE_BLOCKED,
            ]);
        });

        it('throws BiometricBlockedException when biometric is temporarily blocked', function (): void {
            $this->device->blockBiometric(30);
            $this->device->refresh();

            $this->service->verifyAndCreateSession(
                $this->device,
                'any-challenge',
                'any-signature',
                '192.168.1.100'
            );
        })->throws(BiometricBlockedException::class);

        it('blocks device after exceeding max failures and throws exception', function (): void {
            config(['mobile.security.max_biometric_failures' => 3]);

            // Record 3 recent failures to exceed the threshold
            for ($i = 0; $i < 3; $i++) {
                BiometricFailure::record(
                    $this->device->id,
                    BiometricFailure::REASON_SIGNATURE_INVALID,
                    '192.168.1.100'
                );
            }

            expect(fn () => $this->service->verifyAndCreateSession(
                $this->device,
                'any-challenge',
                'any-signature',
                '192.168.1.100'
            ))->toThrow(BiometricBlockedException::class);
        });

        it('returns null when biometric is not enabled', function (): void {
            $this->device->update([
                'biometric_enabled'    => false,
                'biometric_public_key' => null,
            ]);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                'some-challenge',
                'some-signature',
                '192.168.1.100'
            );

            expect($result)->toBeNull();
        });

        it('returns null when challenge is not found', function (): void {
            $result = $this->service->verifyAndCreateSession(
                $this->device,
                'nonexistent-challenge',
                'some-signature',
                '192.168.1.100'
            );

            expect($result)->toBeNull();

            $this->assertDatabaseHas('biometric_failures', [
                'mobile_device_id' => $this->device->id,
                'failure_reason'   => BiometricFailure::REASON_CHALLENGE_NOT_FOUND,
            ]);
        });

        it('returns null when IP network differs', function (): void {
            $challenge = $this->service->createChallenge($this->device, '10.0.0.1');

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                'some-signature',
                '192.168.1.100'
            );

            expect($result)->toBeNull();

            $this->assertDatabaseHas('biometric_failures', [
                'mobile_device_id' => $this->device->id,
                'failure_reason'   => BiometricFailure::REASON_IP_MISMATCH,
            ]);
        });

        it('allows verification when both IPs are in same /24 network', function (): void {
            // Create a real key pair for proper signature verification
            $keyPair = generateTestEcKeyPair();
            $this->device->update(['biometric_public_key' => $keyPair['public_key_pem']]);

            $challenge = $this->service->createChallenge($this->device, '192.168.1.1');

            // Sign the challenge with the private key
            $signature = '';
            openssl_sign($challenge->challenge, $signature, $keyPair['private_key'], OPENSSL_ALGO_SHA256);
            $signatureBase64 = base64_encode($signature);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                $signatureBase64,
                '192.168.1.200' // Same /24 network
            );

            expect($result)->not->toBeNull();
            expect($result)->toHaveKeys(['token', 'expires_at', 'session_id']);
        });

        it('returns null when signature verification fails', function (): void {
            $challenge = $this->service->createChallenge($this->device, '192.168.1.1');

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                base64_encode('invalid-signature-bytes'),
                '192.168.1.1'
            );

            expect($result)->toBeNull();

            // Challenge should be marked as failed
            $challenge->refresh();
            expect($challenge->status)->toBe(BiometricChallenge::STATUS_FAILED);

            $this->assertDatabaseHas('biometric_failures', [
                'mobile_device_id' => $this->device->id,
                'failure_reason'   => BiometricFailure::REASON_SIGNATURE_INVALID,
            ]);
        });

        it('creates session on successful verification', function (): void {
            $keyPair = generateTestEcKeyPair();
            $this->device->update(['biometric_public_key' => $keyPair['public_key_pem']]);

            $challenge = $this->service->createChallenge($this->device, '10.0.0.5');

            $signature = '';
            openssl_sign($challenge->challenge, $signature, $keyPair['private_key'], OPENSSL_ALGO_SHA256);
            $signatureBase64 = base64_encode($signature);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                $signatureBase64,
                '10.0.0.5'
            );

            expect($result)->not->toBeNull();
            expect($result['token'])->toBeString();
            expect($result['token'])->not->toBeEmpty();
            expect($result['session_id'])->not->toBeEmpty();

            // Session should be in the database
            $this->assertDatabaseHas('mobile_device_sessions', [
                'id'                   => $result['session_id'],
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'is_biometric_session' => true,
            ]);

            // Challenge should be verified
            $challenge->refresh();
            expect($challenge->status)->toBe(BiometricChallenge::STATUS_VERIFIED);
            expect($challenge->verified_at)->not->toBeNull();
        });

        it('resets failure count on successful verification', function (): void {
            $keyPair = generateTestEcKeyPair();
            $this->device->update([
                'biometric_public_key'    => $keyPair['public_key_pem'],
                'biometric_failure_count' => 2,
            ]);

            $challenge = $this->service->createChallenge($this->device);

            $signature = '';
            openssl_sign($challenge->challenge, $signature, $keyPair['private_key'], OPENSSL_ALGO_SHA256);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                base64_encode($signature),
            );

            expect($result)->not->toBeNull();

            $this->device->refresh();
            expect($this->device->biometric_failure_count)->toBe(0);
        });

        it('uses extended session duration for trusted devices', function (): void {
            Carbon::setTestNow(now());

            $keyPair = generateTestEcKeyPair();
            $this->device->update([
                'biometric_public_key' => $keyPair['public_key_pem'],
                'is_trusted'           => true,
                'trusted_at'           => now(),
            ]);

            $challenge = $this->service->createChallenge($this->device);

            $signature = '';
            openssl_sign($challenge->challenge, $signature, $keyPair['private_key'], OPENSSL_ALGO_SHA256);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                base64_encode($signature),
            );

            expect($result)->not->toBeNull();

            // Trusted devices get 480 minute sessions
            /** @var MobileDeviceSession $session */
            $session = MobileDeviceSession::find($result['session_id']);
            expect($session)->not->toBeNull();
            $expectedExpiry = now()->addMinutes(480);
            expect($session->expires_at->diffInMinutes($expectedExpiry))->toBeLessThanOrEqual(1);

            Carbon::setTestNow();
        });

        it('uses standard session duration for untrusted devices', function (): void {
            Carbon::setTestNow(now());

            $keyPair = generateTestEcKeyPair();
            $this->device->update([
                'biometric_public_key' => $keyPair['public_key_pem'],
                'is_trusted'           => false,
            ]);

            $challenge = $this->service->createChallenge($this->device);

            $signature = '';
            openssl_sign($challenge->challenge, $signature, $keyPair['private_key'], OPENSSL_ALGO_SHA256);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                base64_encode($signature),
            );

            expect($result)->not->toBeNull();

            // Untrusted devices get 60 minute sessions
            /** @var MobileDeviceSession $session */
            $session = MobileDeviceSession::find($result['session_id']);
            expect($session)->not->toBeNull();
            $expectedExpiry = now()->addMinutes(60);
            expect($session->expires_at->diffInMinutes($expectedExpiry))->toBeLessThanOrEqual(1);

            Carbon::setTestNow();
        });

        it('allows verification when IP addresses are null', function (): void {
            $keyPair = generateTestEcKeyPair();
            $this->device->update(['biometric_public_key' => $keyPair['public_key_pem']]);

            $challenge = $this->service->createChallenge($this->device, null);

            $signature = '';
            openssl_sign($challenge->challenge, $signature, $keyPair['private_key'], OPENSSL_ALGO_SHA256);

            $result = $this->service->verifyAndCreateSession(
                $this->device,
                $challenge->challenge,
                base64_encode($signature),
                null
            );

            expect($result)->not->toBeNull();
        });
    });

    describe('isDeviceBlocked', function (): void {
        it('returns false for a device that is not blocked', function (): void {
            expect($this->service->isDeviceBlocked($this->device))->toBeFalse();
        });

        it('returns true for a biometrically blocked device', function (): void {
            $this->device->blockBiometric(30);
            $this->device->refresh();

            expect($this->service->isDeviceBlocked($this->device))->toBeTrue();
        });

        it('returns false after biometric block has expired', function (): void {
            Carbon::setTestNow(now());

            $this->device->blockBiometric(30);
            $this->device->refresh();

            expect($this->service->isDeviceBlocked($this->device))->toBeTrue();

            // Travel past the block duration
            Carbon::setTestNow(now()->addMinutes(31));

            expect($this->service->isDeviceBlocked($this->device))->toBeFalse();

            Carbon::setTestNow();
        });
    });

    describe('enableBiometric', function (): void {
        it('enables biometric with a valid public key', function (): void {
            $device = MobileDevice::factory()->create(['user_id' => $this->user->id]);
            $publicKey = generateTestEcPublicKey();

            $result = $this->service->enableBiometric($device, $publicKey, 'my-key-id');

            expect($result)->toBeTrue();

            $device->refresh();
            expect($device->biometric_enabled)->toBeTrue();
            expect($device->biometric_public_key)->toBe($publicKey);
            expect($device->biometric_key_id)->toBe('my-key-id');
        });

        it('returns false for invalid public key format', function (): void {
            $device = MobileDevice::factory()->create(['user_id' => $this->user->id]);

            $result = $this->service->enableBiometric($device, 'not-a-valid-key');

            expect($result)->toBeFalse();
            $device->refresh();
            expect($device->biometric_enabled)->toBeFalse();
        });

        it('generates a key ID when none is provided', function (): void {
            $device = MobileDevice::factory()->create(['user_id' => $this->user->id]);
            $publicKey = generateTestEcPublicKey();

            $result = $this->service->enableBiometric($device, $publicKey);

            expect($result)->toBeTrue();
            $device->refresh();
            expect($device->biometric_key_id)->toStartWith('key_');
        });
    });

    describe('disableBiometric', function (): void {
        it('disables biometric and removes biometric sessions', function (): void {
            // Create a biometric session
            MobileDeviceSession::create([
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'session_token'        => MobileDeviceSession::generateToken(),
                'expires_at'           => now()->addMinutes(60),
                'is_biometric_session' => true,
                'last_activity_at'     => now(),
            ]);

            // Create a non-biometric session
            $regularSession = MobileDeviceSession::create([
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'session_token'        => MobileDeviceSession::generateToken(),
                'expires_at'           => now()->addMinutes(60),
                'is_biometric_session' => false,
                'last_activity_at'     => now(),
            ]);

            $this->service->disableBiometric($this->device);

            $this->device->refresh();
            expect($this->device->biometric_enabled)->toBeFalse();
            expect($this->device->biometric_public_key)->toBeNull();

            // Biometric sessions should be deleted
            expect(MobileDeviceSession::where('mobile_device_id', $this->device->id)
                ->where('is_biometric_session', true)->count())->toBe(0);

            // Non-biometric session should remain
            expect(MobileDeviceSession::find($regularSession->id))->not->toBeNull();
        });
    });

    describe('unblockDeviceBiometric', function (): void {
        it('unblocks a biometrically blocked device', function (): void {
            $this->device->blockBiometric(30);
            $this->device->refresh();
            expect($this->service->isDeviceBlocked($this->device))->toBeTrue();

            $this->service->unblockDeviceBiometric($this->device);
            $this->device->refresh();

            expect($this->service->isDeviceBlocked($this->device))->toBeFalse();
            expect($this->device->biometric_blocked_until)->toBeNull();
            expect($this->device->biometric_failure_count)->toBe(0);
        });
    });

    describe('session management', function (): void {
        it('cleans up expired sessions', function (): void {
            // Create an expired session
            MobileDeviceSession::create([
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'session_token'        => MobileDeviceSession::generateToken(),
                'expires_at'           => now()->subMinutes(5),
                'is_biometric_session' => true,
                'last_activity_at'     => now()->subMinutes(65),
            ]);

            // Create an active session
            $activeSession = MobileDeviceSession::create([
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'session_token'        => MobileDeviceSession::generateToken(),
                'expires_at'           => now()->addMinutes(30),
                'is_biometric_session' => true,
                'last_activity_at'     => now(),
            ]);

            $cleaned = $this->service->cleanupExpiredSessions();

            expect($cleaned)->toBe(1);
            expect(MobileDeviceSession::find($activeSession->id))->not->toBeNull();
        });

        it('invalidates all sessions for a user', function (): void {
            MobileDeviceSession::create([
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'session_token'        => MobileDeviceSession::generateToken(),
                'expires_at'           => now()->addMinutes(60),
                'is_biometric_session' => true,
                'last_activity_at'     => now(),
            ]);

            MobileDeviceSession::create([
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'session_token'        => MobileDeviceSession::generateToken(),
                'expires_at'           => now()->addMinutes(60),
                'is_biometric_session' => false,
                'last_activity_at'     => now(),
            ]);

            $deleted = $this->service->invalidateUserSessions($this->user);

            expect($deleted)->toBe(2);
            expect(MobileDeviceSession::where('user_id', $this->user->id)->count())->toBe(0);
        });

        it('invalidates all sessions for a device', function (): void {
            MobileDeviceSession::create([
                'mobile_device_id'     => $this->device->id,
                'user_id'              => $this->user->id,
                'session_token'        => MobileDeviceSession::generateToken(),
                'expires_at'           => now()->addMinutes(60),
                'is_biometric_session' => true,
                'last_activity_at'     => now(),
            ]);

            $deleted = $this->service->invalidateDeviceSessions($this->device);

            expect($deleted)->toBe(1);
            expect(MobileDeviceSession::where('mobile_device_id', $this->device->id)->count())->toBe(0);
        });
    });
});
