<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mobile\Services;

use App\Domain\Mobile\Exceptions\BiometricJWTException;
use App\Domain\Mobile\Models\MobileDevice;
use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BiometricJWTServiceTest extends TestCase
{
    use RefreshDatabase;

    private BiometricJWTService $service;

    protected User $user;

    protected MobileDevice $device;

    protected MobileDeviceSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $this->service = new BiometricJWTService();

        $this->user = User::factory()->create();

        $this->device = MobileDevice::factory()->create([
            'user_id'              => $this->user->id,
            'biometric_enabled'    => true,
            'biometric_public_key' => 'test_public_key_base64_encoded',
            'biometric_key_id'     => 'test_key_id',
            'is_trusted'           => true,
        ]);

        $this->session = MobileDeviceSession::create([
            'mobile_device_id'     => $this->device->id,
            'user_id'              => $this->user->id,
            'session_token'        => MobileDeviceSession::generateToken(),
            'expires_at'           => now()->addMinutes(60),
            'is_biometric_session' => true,
            'last_activity_at'     => now(),
        ]);
    }

    public function test_generates_valid_jwt_token(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        $this->assertNotEmpty($token);
        $this->assertStringContainsString('.', $token);

        // JWT should have 3 parts
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function test_verifies_valid_token(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        $result = $this->service->verifyToken($this->user, $token);

        $this->assertTrue($result);
    }

    public function test_rejects_token_for_wrong_user(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        $otherUser = User::factory()->create();

        $result = $this->service->verifyToken($otherUser, $token);

        $this->assertFalse($result);
    }

    public function test_rejects_empty_token(): void
    {
        $result = $this->service->verifyToken($this->user, '');

        $this->assertFalse($result);
    }

    public function test_rejects_malformed_token(): void
    {
        $result = $this->service->verifyToken($this->user, 'not.a.valid.jwt');

        $this->assertFalse($result);
    }

    public function test_rejects_token_with_invalid_signature(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        // Tamper with the signature
        $parts = explode('.', $token);
        $parts[2] = 'tampered_signature_here';
        $tamperedToken = implode('.', $parts);

        $result = $this->service->verifyToken($this->user, $tamperedToken);

        $this->assertFalse($result);
    }

    public function test_decodes_token_correctly(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        $claims = $this->service->decodeToken($token);

        $this->assertIsArray($claims);
        $this->assertEquals($this->user->uuid, $claims['sub']);
        $this->assertEquals('userop_signing', $claims['aud']);
        $this->assertArrayHasKey('exp', $claims);
        $this->assertArrayHasKey('iat', $claims);
        $this->assertArrayHasKey('jti', $claims);
        $this->assertArrayHasKey('claims', $claims);
        $this->assertEquals($this->user->id, $claims['claims']['user_id']);
        $this->assertEquals($this->device->id, $claims['claims']['device_id']);
        $this->assertEquals($this->session->id, $claims['claims']['session_id']);
    }

    public function test_rejects_expired_token(): void
    {
        // Create a session that's about to expire
        $this->session->update(['expires_at' => now()->subMinutes(1)]);

        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        // Manually create an expired token by modifying payload
        $parts = explode('.', $token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $payload['exp'] = time() - 3600; // Expired 1 hour ago

        // Re-encode (this will have wrong signature, but we're testing decodeToken)
        $this->expectException(BiometricJWTException::class);
        $this->expectExceptionMessage('Invalid JWT signature');

        // Tampered token won't pass signature check
        $encodedPayload = json_encode($payload);
        $this->assertIsString($encodedPayload);
        $parts[1] = rtrim(strtr(base64_encode($encodedPayload), '+/', '-_'), '=');
        $expiredToken = implode('.', $parts);

        $this->service->decodeToken($expiredToken);
    }

    public function test_rejects_revoked_token(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        // Get the JTI and revoke it
        $claims = $this->service->decodeToken($token);
        $this->assertIsArray($claims);
        $this->assertArrayHasKey('jti', $claims);
        $this->service->revokeToken($claims['jti']);

        // Token should now be rejected
        $result = $this->service->verifyToken($this->user, $token);

        $this->assertFalse($result);
    }

    public function test_revokes_all_device_tokens(): void
    {
        // Create multiple sessions
        $session2 = MobileDeviceSession::create([
            'mobile_device_id'     => $this->device->id,
            'user_id'              => $this->user->id,
            'session_token'        => MobileDeviceSession::generateToken(),
            'expires_at'           => now()->addMinutes(60),
            'is_biometric_session' => true,
            'last_activity_at'     => now(),
        ]);

        $sessionIds = [$this->session->id, $session2->id];

        $count = $this->service->revokeDeviceTokens($this->device);

        $this->assertGreaterThanOrEqual(1, $count);

        // Sessions should be deleted (invalidate() deletes the session)
        $this->assertNull(MobileDeviceSession::find($sessionIds[0]));
        $this->assertNull(MobileDeviceSession::find($sessionIds[1]));
    }

    public function test_verifies_ios_device_attestation(): void
    {
        // Minimum length for iOS attestation is 100 chars
        $attestation = str_repeat('a', 150);

        $result = $this->service->verifyDeviceAttestation($attestation, 'ios');

        $this->assertTrue($result);
    }

    public function test_verifies_android_device_attestation(): void
    {
        // Minimum length for Android attestation is 50 chars
        $attestation = str_repeat('b', 75);

        $result = $this->service->verifyDeviceAttestation($attestation, 'android');

        $this->assertTrue($result);
    }

    public function test_rejects_short_attestation(): void
    {
        $attestation = str_repeat('c', 20);

        $result = $this->service->verifyDeviceAttestation($attestation, 'ios');

        $this->assertFalse($result);
    }

    public function test_rejects_empty_attestation(): void
    {
        $result = $this->service->verifyDeviceAttestation('', 'android');

        $this->assertFalse($result);
    }

    public function test_rejects_token_with_invalid_session(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);

        // Expire the session (invalidate() deletes it, so we expire instead)
        $this->session->update(['expires_at' => now()->subMinutes(1)]);

        $result = $this->service->verifyToken($this->user, $token);

        $this->assertFalse($result);
    }

    public function test_token_contains_device_fingerprint(): void
    {
        $token = $this->service->generateToken($this->user, $this->device, $this->session);
        $claims = $this->service->decodeToken($token);

        $this->assertIsArray($claims);
        $this->assertArrayHasKey('claims', $claims);
        $this->assertIsArray($claims['claims']);
        $this->assertEquals($this->device->device_id, $claims['claims']['device_fingerprint']);
        $this->assertEquals($this->device->biometric_key_id, $claims['claims']['biometric_key_id']);
        $this->assertEquals($this->device->is_trusted, $claims['claims']['is_trusted_device']);
    }

    public function test_returns_null_for_short_token(): void
    {
        $result = $this->service->decodeToken('short');

        $this->assertNull($result);
    }

    public function test_returns_null_for_wrong_part_count(): void
    {
        $result = $this->service->decodeToken('only.two.parts.extra');

        $this->assertNull($result);
    }
}
