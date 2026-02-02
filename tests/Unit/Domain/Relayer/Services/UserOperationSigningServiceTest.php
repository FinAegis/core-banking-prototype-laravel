<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Relayer\Services;

use App\Domain\KeyManagement\HSM\HsmIntegrationService;
use App\Domain\Relayer\Exceptions\UserOpSigningException;
use App\Domain\Relayer\Services\UserOperationSigningService;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class UserOperationSigningServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserOperationSigningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        RateLimiter::clear('userop_signing:' . ($this->user->id ?? ''));

        // Create service with HSM integration
        $hsm = new HsmIntegrationService();
        $this->service = new UserOperationSigningService($hsm);
        $this->user ??= User::factory()->create();
    }

    public function test_signs_user_operation_successfully(): void
    {
        $userOpHash = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $deviceShardProof = '0xabcdef1234567890';
        $biometricToken = str_repeat('a', 32);

        $result = $this->service->signUserOperation(
            user: $this->user,
            userOpHash: $userOpHash,
            deviceShardProof: $deviceShardProof,
            biometricToken: $biometricToken
        );

        $this->assertArrayHasKey('auth_shard_signature', $result);
        $this->assertArrayHasKey('expires_at', $result);
        $this->assertArrayHasKey('signed_at', $result);
        $this->assertStringStartsWith('0x', $result['auth_shard_signature']);
        $this->assertInstanceOf(DateTimeInterface::class, $result['expires_at']);
        $this->assertInstanceOf(DateTimeInterface::class, $result['signed_at']);
    }

    public function test_signature_is_deterministic_for_same_inputs(): void
    {
        $userOpHash = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $deviceShardProof = '0xabcdef1234567890';
        $biometricToken = str_repeat('a', 32);

        $result1 = $this->service->signUserOperation(
            user: $this->user,
            userOpHash: $userOpHash,
            deviceShardProof: $deviceShardProof,
            biometricToken: $biometricToken
        );

        $result2 = $this->service->signUserOperation(
            user: $this->user,
            userOpHash: $userOpHash,
            deviceShardProof: $deviceShardProof,
            biometricToken: $biometricToken
        );

        $this->assertEquals($result1['auth_shard_signature'], $result2['auth_shard_signature']);
    }

    public function test_throws_exception_for_invalid_user_op_hash_format(): void
    {
        $this->expectException(UserOpSigningException::class);
        $this->expectExceptionMessage('Invalid UserOperation hash format');

        $this->service->signUserOperation(
            user: $this->user,
            userOpHash: 'invalid_hash',
            deviceShardProof: '0xabcdef1234567890',
            biometricToken: str_repeat('a', 32)
        );
    }

    public function test_throws_exception_for_user_op_hash_without_prefix(): void
    {
        $this->expectException(UserOpSigningException::class);
        $this->expectExceptionMessage('Invalid UserOperation hash format');

        $this->service->signUserOperation(
            user: $this->user,
            userOpHash: '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            deviceShardProof: '0xabcdef1234567890',
            biometricToken: str_repeat('a', 32)
        );
    }

    public function test_throws_exception_for_invalid_device_shard_proof(): void
    {
        $this->expectException(UserOpSigningException::class);
        $this->expectExceptionMessage('Invalid device shard proof');

        $this->service->signUserOperation(
            user: $this->user,
            userOpHash: '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            deviceShardProof: 'invalid_proof',
            biometricToken: str_repeat('a', 32)
        );
    }

    public function test_throws_exception_for_empty_biometric_token(): void
    {
        $this->expectException(UserOpSigningException::class);
        $this->expectExceptionMessage('Biometric verification failed');

        $this->service->signUserOperation(
            user: $this->user,
            userOpHash: '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            deviceShardProof: '0xabcdef1234567890',
            biometricToken: ''
        );
    }

    public function test_throws_exception_for_short_biometric_token(): void
    {
        $this->expectException(UserOpSigningException::class);
        $this->expectExceptionMessage('Biometric verification failed');

        $this->service->signUserOperation(
            user: $this->user,
            userOpHash: '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            deviceShardProof: '0xabcdef1234567890',
            biometricToken: 'short'
        );
    }

    public function test_verifies_biometric_token_format(): void
    {
        // Valid token (32+ characters)
        $this->assertTrue($this->service->verifyBiometricToken($this->user, str_repeat('a', 32)));
        $this->assertTrue($this->service->verifyBiometricToken($this->user, str_repeat('b', 100)));

        // Invalid tokens
        $this->assertFalse($this->service->verifyBiometricToken($this->user, ''));
        $this->assertFalse($this->service->verifyBiometricToken($this->user, str_repeat('c', 31)));
    }

    public function test_validates_device_shard_proof_format(): void
    {
        // Valid proofs
        $this->assertTrue($this->service->validateDeviceShardProof('0x1234'));
        $this->assertTrue($this->service->validateDeviceShardProof('0xabcdef'));
        $this->assertTrue($this->service->validateDeviceShardProof('0xABCDEF'));

        // Invalid proofs
        $this->assertFalse($this->service->validateDeviceShardProof('1234'));
        $this->assertFalse($this->service->validateDeviceShardProof('0x'));
        $this->assertFalse($this->service->validateDeviceShardProof('0xghij'));
        $this->assertFalse($this->service->validateDeviceShardProof(''));
    }

    public function test_enforces_rate_limiting(): void
    {
        $userOpHash = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $deviceShardProof = '0xabcdef1234567890';
        $biometricToken = str_repeat('a', 32);

        // First 10 requests should succeed
        for ($i = 0; $i < 10; $i++) {
            $result = $this->service->signUserOperation(
                user: $this->user,
                userOpHash: $userOpHash,
                deviceShardProof: $deviceShardProof,
                biometricToken: $biometricToken
            );
            $this->assertArrayHasKey('auth_shard_signature', $result);
        }

        // 11th request should be rate limited
        $this->expectException(UserOpSigningException::class);
        $this->expectExceptionMessage('Rate limit exceeded');

        $this->service->signUserOperation(
            user: $this->user,
            userOpHash: $userOpHash,
            deviceShardProof: $deviceShardProof,
            biometricToken: $biometricToken
        );
    }

    public function test_signature_expires_in_5_minutes(): void
    {
        $userOpHash = '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef';
        $deviceShardProof = '0xabcdef1234567890';
        $biometricToken = str_repeat('a', 32);

        $result = $this->service->signUserOperation(
            user: $this->user,
            userOpHash: $userOpHash,
            deviceShardProof: $deviceShardProof,
            biometricToken: $biometricToken
        );

        $signedAt = $result['signed_at'];
        $expiresAt = $result['expires_at'];

        $diff = $expiresAt->getTimestamp() - $signedAt->getTimestamp();
        $this->assertEquals(300, $diff, 'Signature should expire in 300 seconds (5 minutes)');
    }
}
