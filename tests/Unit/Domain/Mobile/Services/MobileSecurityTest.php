<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mobile\Services;

use App\Domain\Mobile\Exceptions\BiometricBlockedException;
use App\Domain\Mobile\Exceptions\DeviceTakeoverAttemptException;
use App\Domain\Mobile\Models\BiometricFailure;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Services\BiometricAuthenticationService;
use App\Domain\Mobile\Services\MobileDeviceService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for mobile security features including:
 * - Device takeover prevention
 * - Per-device biometric rate limiting
 * - Biometric blocking after failures
 */
class MobileSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected MobileDeviceService $deviceService;

    protected BiometricAuthenticationService $biometricService;

    protected User $user;

    protected User $attacker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceService = new MobileDeviceService();
        $this->biometricService = new BiometricAuthenticationService();
        $this->user = User::factory()->create();
        $this->attacker = User::factory()->create();
    }

    // ==========================================================
    // Device Takeover Prevention Tests
    // ==========================================================

    public function test_device_takeover_attempt_throws_exception(): void
    {
        // User registers a device
        $device = $this->deviceService->registerDevice(
            $this->user,
            'shared-device-id',
            'android',
            '1.0.0'
        );

        // Attacker tries to register the same device
        $this->expectException(DeviceTakeoverAttemptException::class);
        $this->expectExceptionMessage('Device already registered to another user');

        $this->deviceService->registerDevice(
            $this->attacker,
            'shared-device-id',
            'android',
            '1.0.0'
        );
    }

    public function test_device_takeover_exception_contains_context(): void
    {
        $device = $this->deviceService->registerDevice(
            $this->user,
            'my-device',
            'android',
            '1.0.0'
        );

        try {
            $this->deviceService->registerDevice(
                $this->attacker,
                'my-device',
                'ios',
                '2.0.0'
            );
            $this->fail('Expected DeviceTakeoverAttemptException was not thrown');
        } catch (DeviceTakeoverAttemptException $e) {
            $this->assertEquals('my-device', $e->deviceId);
            $this->assertEquals($this->user->id, $e->existingUserId);
            $this->assertEquals($this->attacker->id, $e->attemptedUserId);
            $this->assertEquals(409, $e->getHttpStatusCode());

            $context = $e->context();
            $this->assertArrayHasKey('device_id', $context);
            $this->assertArrayHasKey('existing_user_id', $context);
            $this->assertArrayHasKey('attempted_user_id', $context);
        }
    }

    public function test_same_user_can_reregister_device(): void
    {
        // First registration
        $device1 = $this->deviceService->registerDevice(
            $this->user,
            'reregistered-device',
            'android',
            '1.0.0'
        );

        // Same user re-registers (should work)
        $device2 = $this->deviceService->registerDevice(
            $this->user,
            'reregistered-device',
            'android',
            '2.0.0'
        );

        $this->assertEquals($device1->id, $device2->id);
        $this->assertEquals('2.0.0', $device2->app_version);
    }

    public function test_device_takeover_does_not_modify_original_device(): void
    {
        $device = $this->deviceService->registerDevice(
            $this->user,
            'protected-device',
            'android',
            '1.0.0',
            'original-token'
        );

        try {
            $this->deviceService->registerDevice(
                $this->attacker,
                'protected-device',
                'ios',
                '2.0.0',
                'attacker-token'
            );
        } catch (DeviceTakeoverAttemptException $e) {
            // Expected
        }

        $device->refresh();

        // Verify device was not modified
        $this->assertEquals($this->user->id, $device->user_id);
        $this->assertEquals('android', $device->platform);
        $this->assertEquals('1.0.0', $device->app_version);
        $this->assertEquals('original-token', $device->push_token);
    }

    // ==========================================================
    // Biometric Rate Limiting Tests
    // ==========================================================

    public function test_biometric_failure_is_recorded(): void
    {
        $device = $this->createDeviceWithBiometric();

        BiometricFailure::record(
            $device->id,
            BiometricFailure::REASON_SIGNATURE_INVALID,
            '192.168.1.100'
        );

        $this->assertDatabaseHas('biometric_failures', [
            'mobile_device_id' => $device->id,
            'failure_reason'   => BiometricFailure::REASON_SIGNATURE_INVALID,
            'ip_address'       => '192.168.1.100',
        ]);
    }

    public function test_recent_failures_are_counted_correctly(): void
    {
        $device = $this->createDeviceWithBiometric();

        // Record 3 failures
        for ($i = 0; $i < 3; $i++) {
            BiometricFailure::record($device->id, BiometricFailure::REASON_SIGNATURE_INVALID);
        }

        $count = BiometricFailure::countRecentForDevice($device->id, 10);
        $this->assertEquals(3, $count);
    }

    public function test_old_failures_are_not_counted(): void
    {
        $device = $this->createDeviceWithBiometric();

        // Create an old failure
        $oldFailure = BiometricFailure::create([
            'mobile_device_id' => $device->id,
            'failure_reason'   => BiometricFailure::REASON_SIGNATURE_INVALID,
            'created_at'       => now()->subMinutes(15),
            'updated_at'       => now()->subMinutes(15),
        ]);

        // Create a recent failure
        BiometricFailure::record($device->id, BiometricFailure::REASON_SIGNATURE_INVALID);

        // Only recent failure should count (within 10 minute window)
        $count = BiometricFailure::countRecentForDevice($device->id, 10);
        $this->assertEquals(1, $count);
    }

    public function test_device_biometric_can_be_blocked(): void
    {
        $device = $this->createDeviceWithBiometric();

        $device->blockBiometric(30);
        $device->refresh();

        $this->assertTrue($device->isBiometricBlocked());
        $this->assertNotNull($device->biometric_blocked_until);
        $this->assertEquals(0, $device->biometric_failure_count);
    }

    public function test_biometric_blocked_exception_has_retry_after(): void
    {
        $blockedUntil = now()->addMinutes(15);

        $exception = new BiometricBlockedException($blockedUntil);

        $this->assertEquals(429, $exception->getHttpStatusCode());
        $this->assertGreaterThan(0, $exception->getRetryAfterSeconds());
        $this->assertLessThanOrEqual(15 * 60, $exception->getRetryAfterSeconds());

        $context = $exception->context();
        $this->assertArrayHasKey('blocked_until', $context);
        $this->assertArrayHasKey('retry_after_seconds', $context);
    }

    public function test_device_biometric_can_be_unblocked(): void
    {
        $device = $this->createDeviceWithBiometric();

        $device->blockBiometric(30);
        $device->refresh();
        $this->assertTrue($device->isBiometricBlocked());

        $device->unblockBiometric();
        $device->refresh();

        $this->assertFalse($device->isBiometricBlocked());
        $this->assertNull($device->biometric_blocked_until);
    }

    public function test_can_use_biometric_returns_false_when_blocked(): void
    {
        $device = $this->createDeviceWithBiometric();

        $this->assertTrue($device->canUseBiometric());

        $device->blockBiometric(30);
        $device->refresh();

        $this->assertFalse($device->canUseBiometric());
    }

    public function test_biometric_failure_count_increments(): void
    {
        $device = $this->createDeviceWithBiometric();
        $this->assertEquals(0, $device->biometric_failure_count);

        $device->incrementBiometricFailures();
        $device->refresh();
        $this->assertEquals(1, $device->biometric_failure_count);

        $device->incrementBiometricFailures();
        $device->refresh();
        $this->assertEquals(2, $device->biometric_failure_count);
    }

    public function test_biometric_failure_count_resets(): void
    {
        $device = $this->createDeviceWithBiometric();

        $device->incrementBiometricFailures();
        $device->incrementBiometricFailures();
        $device->refresh();
        $this->assertEquals(2, $device->biometric_failure_count);

        $device->resetBiometricFailures();
        $device->refresh();

        $this->assertEquals(0, $device->biometric_failure_count);
    }

    // ==========================================================
    // BiometricFailure Model Tests
    // ==========================================================

    public function test_biometric_failure_belongs_to_device(): void
    {
        $device = $this->createDeviceWithBiometric();

        $failure = BiometricFailure::record(
            $device->id,
            BiometricFailure::REASON_SIGNATURE_INVALID
        );

        $relatedDevice = $failure->device;
        $this->assertNotNull($relatedDevice);
        $this->assertEquals($device->id, $relatedDevice->id);
    }

    public function test_biometric_failure_cleanup(): void
    {
        $device = $this->createDeviceWithBiometric();

        // Create old failure
        BiometricFailure::create([
            'mobile_device_id' => $device->id,
            'failure_reason'   => BiometricFailure::REASON_SIGNATURE_INVALID,
            'created_at'       => now()->subDays(10),
            'updated_at'       => now()->subDays(10),
        ]);

        // Create recent failure
        BiometricFailure::record($device->id, BiometricFailure::REASON_SIGNATURE_INVALID);

        $deleted = BiometricFailure::cleanup(7);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, BiometricFailure::count());
    }

    public function test_failure_reason_constants_are_defined(): void
    {
        $this->assertEquals('signature_invalid', BiometricFailure::REASON_SIGNATURE_INVALID);
        $this->assertEquals('challenge_expired', BiometricFailure::REASON_CHALLENGE_EXPIRED);
        $this->assertEquals('challenge_not_found', BiometricFailure::REASON_CHALLENGE_NOT_FOUND);
        $this->assertEquals('ip_mismatch', BiometricFailure::REASON_IP_MISMATCH);
        $this->assertEquals('user_agent_invalid', BiometricFailure::REASON_USER_AGENT_INVALID);
        $this->assertEquals('device_blocked', BiometricFailure::REASON_DEVICE_BLOCKED);
    }

    // ==========================================================
    // Integration Tests
    // ==========================================================

    public function test_blocked_device_cannot_authenticate(): void
    {
        $device = $this->createDeviceWithBiometric();
        $device->block('Suspicious activity');
        $device->refresh();

        // Create challenge (this should work)
        $challenge = $this->biometricService->createChallenge($device);

        // Try to verify (should fail and record failure)
        $result = $this->biometricService->verifyAndCreateSession(
            $device,
            $challenge->challenge,
            'invalid-signature',
            '192.168.1.100'
        );

        $this->assertNull($result);
        $this->assertDatabaseHas('biometric_failures', [
            'mobile_device_id' => $device->id,
            'failure_reason'   => BiometricFailure::REASON_DEVICE_BLOCKED,
        ]);
    }

    public function test_biometric_blocked_device_throws_exception_on_verify(): void
    {
        $device = $this->createDeviceWithBiometric();
        $device->blockBiometric(30);
        $device->refresh();

        $this->expectException(BiometricBlockedException::class);

        $this->biometricService->verifyAndCreateSession(
            $device,
            'any-challenge',
            'any-signature',
            '192.168.1.100'
        );
    }

    // ==========================================================
    // Helper Methods
    // ==========================================================

    /**
     * Create a device with biometric enabled for testing.
     */
    protected function createDeviceWithBiometric(): MobileDevice
    {
        $device = MobileDevice::create([
            'user_id'              => $this->user->id,
            'device_id'            => 'test-device-' . uniqid(),
            'platform'             => 'ios',
            'app_version'          => '1.0.0',
            'biometric_enabled'    => true,
            'biometric_public_key' => $this->generateTestPublicKey(),
            'biometric_key_id'     => 'test-key-id',
            'biometric_enabled_at' => now(),
        ]);

        return $device;
    }

    /**
     * Generate a test ECDSA P-256 public key.
     */
    protected function generateTestPublicKey(): string
    {
        $config = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ];

        $key = openssl_pkey_new($config);
        if ($key === false) {
            // Fallback for testing environments without EC support
            return '-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEtest1234567890abcdefghijklmn
opqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890test==
-----END PUBLIC KEY-----';
        }

        $details = openssl_pkey_get_details($key);

        return $details['key'] ?? '';
    }
}
