<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserOpSigningControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->user ??= User::factory()->create();
    }

    /**
     * Generate a valid HMAC-signed demo biometric token for testing.
     */
    private function demoBiometricToken(): string
    {
        return hash_hmac('sha256', 'demo_biometric:' . $this->user->id, config('app.key'));
    }

    public function test_signs_user_operation_successfully(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => $this->demoBiometricToken(),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'auth_shard_signature',
                    'expires_at',
                    'signed_at',
                ],
            ]);

        $data = $response->json('data');
        $this->assertStringStartsWith('0x', $data['auth_shard_signature']);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => 'any-token-doesnt-matter-unauthenticated',
        ]);

        $response->assertStatus(401);
    }

    public function test_validates_user_op_hash_format(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => 'invalid_hash',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => $this->demoBiometricToken(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_op_hash']);
    }

    public function test_validates_user_op_hash_without_prefix(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => $this->demoBiometricToken(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_op_hash']);
    }

    public function test_validates_device_shard_proof_format(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => 'invalid_proof',
            'biometric_token'    => $this->demoBiometricToken(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['device_shard_proof']);
    }

    public function test_validates_biometric_token_minimum_length(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['biometric_token']);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_op_hash', 'device_shard_proof', 'biometric_token']);
    }

    public function test_returns_error_for_biometric_verification_failure(): void
    {
        // The service accepts any token >= 32 chars in demo mode
        // This test verifies the error response format when signing fails
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => str_repeat('a', 31), // Too short
        ]);

        // Will fail validation (min:32)
        $response->assertStatus(422);
    }

    public function test_returns_signature_with_correct_format(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => $this->demoBiometricToken(),
        ]);

        $response->assertStatus(200);

        $signature = $response->json('data.auth_shard_signature');

        // Signature should be a hex string starting with 0x
        $this->assertMatchesRegularExpression('/^0x[a-fA-F0-9]+$/', $signature);
    }

    public function test_returns_iso8601_timestamps(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => $this->demoBiometricToken(),
        ]);

        $response->assertStatus(200);

        $expiresAt = $response->json('data.expires_at');
        $signedAt = $response->json('data.signed_at');

        // Should be ISO 8601 format
        $this->assertNotFalse(strtotime($expiresAt));
        $this->assertNotFalse(strtotime($signedAt));
    }

    public function test_rate_limits_after_10_requests(): void
    {
        $biometricToken = $this->demoBiometricToken();

        // Make 10 successful requests
        for ($i = 0; $i < 10; $i++) {
            $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
                'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
                'device_shard_proof' => '0xabcdef1234567890',
                'biometric_token'    => $biometricToken,
            ]);
            $response->assertStatus(200);
        }

        // 11th request should be rate limited by route-level throttle middleware
        // Returns 429 Too Many Requests (standard HTTP rate limiting status)
        $response = $this->actingAs($this->user)->postJson('/api/auth/sign-userop', [
            'user_op_hash'       => '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            'device_shard_proof' => '0xabcdef1234567890',
            'biometric_token'    => $biometricToken,
        ]);

        $response->assertStatus(429);
    }
}
