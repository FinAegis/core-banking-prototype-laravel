<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enable rate limiting for tests
        config(['rate_limiting.enabled' => true]);
        config(['rate_limiting.force_in_tests' => true]);

        // Clear rate limit cache
        Cache::flush();
    }

    /** @test */
    public function auth_endpoints_have_strict_rate_limiting()
    {
        // Auth endpoints allow 5 requests per minute
        $maxAttempts = 5;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => 'test@example.com',
                'password' => 'password',
            ]);

            // Should not be rate limited yet
            $this->assertNotEquals(429, $response->status());
        }

        // Next request should be rate limited
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(429)
            ->assertJsonStructure([
                'error',
                'message',
                'retry_after',
                'limit',
                'window',
            ]);

        // Check rate limit headers
        $response->assertHeader('X-RateLimit-Limit', $maxAttempts);
        $response->assertHeader('X-RateLimit-Remaining', 0);
        $response->assertHeader('Retry-After');
    }

    /** @test */
    public function password_reset_has_rate_limiting()
    {
        // Password reset allows 5 attempts per hour
        $maxAttempts = 5;

        for ($i = 0; $i < $maxAttempts; $i++) {
            $response = $this->postJson('/api/auth/forgot-password', [
                'email' => 'test@example.com',
            ]);

            // Should succeed with generic message
            $response->assertStatus(200)
                ->assertJsonFragment([
                    'message' => 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.',
                ]);
        }

        // Next request should be rate limited
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);

        $response->assertStatus(429)
            ->assertJsonFragment([
                'message' => 'Too many password reset attempts.',
            ]);
    }

    /** @test */
    public function different_endpoints_have_different_rate_limits()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        // Query endpoints have higher limits (100 per minute)
        for ($i = 0; $i < 50; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/auth/user');

            $response->assertStatus(200);
        }

        // Should still have remaining quota
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/user');

        $response->assertStatus(200);
        $remainingHeader = $response->headers->get('X-RateLimit-Remaining');
        $this->assertGreaterThan(0, $remainingHeader);
    }

    /** @test */
    public function rate_limit_is_per_user_not_per_ip()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $token1 = $user1->createToken('test')->plainTextToken;
        $token2 = $user2->createToken('test')->plainTextToken;

        // Make requests as user 1
        for ($i = 0; $i < 5; $i++) {
            $this->withHeader('Authorization', 'Bearer ' . $token1)
                ->postJson('/api/auth/logout');
        }

        // User 2 should not be affected by user 1's rate limit
        $response = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->postJson('/api/auth/logout');

        $this->assertNotEquals(429, $response->status());
    }

    /** @test */
    public function rate_limit_blocking_duration_varies_by_endpoint_type()
    {
        // Auth endpoints have 5 minute block duration
        // Make enough requests to trigger blocking
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => 'test@example.com',
                'password' => 'password',
            ]);
        }

        // Should be blocked
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(429);

        // Check for blocking indicator
        $this->assertNotNull($response->headers->get('X-RateLimit-Blocked-Until'));
    }

    /** @test */
    public function rate_limit_headers_are_present_on_successful_requests()
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200);

        // Check rate limit headers
        $response->assertHeader('X-RateLimit-Limit');
        $response->assertHeader('X-RateLimit-Remaining');
        $response->assertHeader('X-RateLimit-Reset');
        $response->assertHeader('X-RateLimit-Window');
    }

    /** @test */
    public function admin_endpoints_have_higher_rate_limits()
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $token = $admin->createToken('admin')->plainTextToken;

        // Admin endpoints allow 200 requests per minute
        for ($i = 0; $i < 100; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->getJson('/api/auth/user');

            $response->assertStatus(200);
        }

        // Should still have quota remaining
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/user');

        $response->assertStatus(200);
        $remaining = $response->headers->get('X-RateLimit-Remaining');
        $this->assertGreaterThan(50, $remaining);
    }

    /** @test */
    public function rate_limit_resets_after_window_expires()
    {
        // Make requests to nearly exhaust limit
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => 'test@example.com',
                'password' => 'password',
            ]);
        }

        // Travel forward in time past the window
        $this->travel(61)->seconds();

        // Rate limit should be reset
        $response = $this->postJson('/api/auth/login', [
            'email'    => 'test@example.com',
            'password' => 'password',
        ]);

        $this->assertNotEquals(429, $response->status());
        $response->assertHeader('X-RateLimit-Remaining');
    }
}
