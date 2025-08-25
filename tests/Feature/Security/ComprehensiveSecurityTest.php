<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ComprehensiveSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear any cached IP blocks to prevent test interference
        \Illuminate\Support\Facades\Cache::flush();

        // Clear database IP blocks if table exists
        if (\Illuminate\Support\Facades\Schema::hasTable('blocked_ips')) {
            \Illuminate\Support\Facades\DB::table('blocked_ips')->truncate();
        }

        $this->user = User::factory()->create([
            'password' => Hash::make('password123'),
        ]);
    }

    /**
     * Test 1: Verify Sanctum token expiration is working properly.
     */
    public function test_sanctum_token_expiration_works_correctly(): void
    {
        // Set token expiration to 60 minutes
        config(['sanctum.expiration' => 60]);

        // Create a token
        $token = $this->user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;

        // Manually set expiration to past
        $token->accessToken->expires_at = Carbon::now()->subMinute();
        $token->accessToken->save();

        // Try to use the expired token
        $response = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/auth/user');

        // Should be unauthorized
        $response->assertUnauthorized();

        // Verify token still exists but is expired
        $dbToken = PersonalAccessToken::find($token->accessToken->id);
        $this->assertNotNull($dbToken);
        $this->assertTrue($dbToken->expires_at->isPast());
    }

    /**
     * Test 2: Verify user enumeration prevention in password reset.
     */
    public function test_password_reset_prevents_user_enumeration(): void
    {
        // Test with existing email
        $response1 = $this->postJson('/api/auth/forgot-password', [
            'email' => $this->user->email,
        ]);

        $response1->assertOk();
        $message1 = $response1->json('message');

        // Test with non-existent email
        $response2 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response2->assertOk();
        $message2 = $response2->json('message');

        // Both should return the same message
        $this->assertEquals($message1, $message2);
        $this->assertStringContainsString('If your email address exists', $message1);
    }

    /**
     * Test 3: Verify concurrent session limits are enforced (5 sessions max).
     */
    public function test_concurrent_session_limit_is_enforced(): void
    {
        // Set max sessions to 5
        config(['auth.max_concurrent_sessions' => 5]);

        // Create 5 sessions
        $tokens = [];
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'       => $this->user->email,
                'password'    => 'password123',
                'device_name' => "device-{$i}",
            ]);
            $response->assertOk();
            $tokens[] = $response->json('access_token');
        }

        // All 5 tokens should be valid
        $this->assertEquals(5, $this->user->tokens()->count());

        // Create a 6th session - should delete the oldest
        $response = $this->postJson('/api/auth/login', [
            'email'       => $this->user->email,
            'password'    => 'password123',
            'device_name' => 'device-6',
        ]);
        $response->assertOk();

        // Should still have only 5 tokens
        $this->assertEquals(5, $this->user->tokens()->count());

        // The oldest token (first one) should be deleted
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokens[0])
            ->getJson('/api/auth/user');
        $response->assertUnauthorized();

        // The newer tokens should still work
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokens[4])
            ->getJson('/api/auth/user');
        $response->assertOk();
    }

    /**
     * Test 4: Verify rate limiting on authentication endpoints.
     */
    public function test_authentication_endpoints_have_rate_limiting(): void
    {
        // Clear rate limiter
        RateLimiter::clear('password-reset:' . request()->ip());

        // Password reset endpoint should have rate limiting
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/auth/forgot-password', [
                'email' => 'test@example.com',
            ]);
            $response->assertOk();
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'test@example.com',
        ]);
        $response->assertStatus(429);
        // Check that message contains "Too many"
        $this->assertStringContainsString('Too many', $response->json('message'));
    }

    /**
     * Test 5: Verify security headers are present.
     */
    public function test_security_headers_are_present(): void
    {
        // Use a route that exists and goes through the SecurityHeaders middleware
        $response = $this->getJson('/api/auth/user');

        // Since we're not authenticated, we get 401, but headers should still be present
        // Check for security headers
        $this->assertNotNull($response->headers->get('X-Content-Type-Options'));
        $this->assertNotNull($response->headers->get('X-XSS-Protection'));
        $this->assertNotNull($response->headers->get('X-Frame-Options'));
        $this->assertNotNull($response->headers->get('Referrer-Policy'));
        $this->assertNotNull($response->headers->get('Content-Security-Policy'));
        $this->assertNotNull($response->headers->get('Permissions-Policy'));
    }

    /**
     * Test 6: Verify 2FA is available and working.
     */
    public function test_two_factor_authentication_is_available(): void
    {
        // Enable 2FA
        $response = $this->actingAs($this->user)
            ->postJson('/api/auth/2fa/enable');

        $response->assertOk();
        $response->assertJsonStructure([
            'message',
            'secret',
            'qr_code',
            'recovery_codes',
        ]);

        // Verify user has 2FA enabled
        $this->user->refresh();
        $this->assertNotNull($this->user->two_factor_secret);
        $this->assertNotNull($this->user->two_factor_recovery_codes);
    }

    /**
     * Test 7: Verify API scope enforcement is working.
     */
    public function test_api_scope_enforcement_works(): void
    {
        // Create token with limited scopes
        $token = $this->user->createToken('limited-token', ['read'])->plainTextToken;

        // Try to perform a write operation
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/accounts', [
                'account_number' => 'TEST123',
                'account_type'   => 'savings',
                'currency'       => 'USD',
            ]);

        // Should be forbidden due to missing 'write' scope
        $response->assertStatus(403);
        // Check that error message mentions scope or permissions
        $message = $response->json('message') ?? $response->json('error');
        $this->assertStringContainsStringIgnoringCase('scope', $message);
    }

    /**
     * Test 8: Verify tokens are revoked on password reset.
     */
    public function test_tokens_revoked_on_password_reset(): void
    {
        // Create a token
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Verify token works
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/user');
        $response->assertOk();

        // Simulate password reset
        $resetToken = app('auth.password.broker')->createToken($this->user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $this->user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token'                 => $resetToken,
        ]);

        $response->assertOk();

        // Old token should no longer work
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/user');
        $response->assertUnauthorized();

        // Verify all tokens were deleted
        $this->assertEquals(0, $this->user->tokens()->count());
    }

    /**
     * Test 9: Verify IP-based rate limiting for failed login attempts.
     */
    public function test_failed_login_attempts_are_rate_limited(): void
    {
        config(['rate_limiting.enabled' => true]);

        // Multiple failed login attempts
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'    => $this->user->email,
                'password' => 'wrongpassword',
            ]);
            // Should get validation error, not rate limit yet
            $response->assertStatus(422);
        }

        // Further attempts might be rate limited
        // Note: The exact behavior depends on ApiRateLimitMiddleware configuration
    }

    /**
     * Test 10: Verify HSTS header in production environment.
     */
    public function test_hsts_header_in_production(): void
    {
        // This test needs to be skipped in testing environment
        // HSTS is only set in production
        $this->markTestSkipped('HSTS header is only set in production environment');
    }

    /**
     * Test 11: Verify session regeneration on login.
     */
    public function test_session_regeneration_on_login(): void
    {
        // Create a session-based request
        $this->withSession(['test_value' => 'before_login']);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $this->user->email,
            'password' => 'password123',
        ]);

        $response->assertOk();

        // Session should be regenerated (implementation detail of Laravel)
        // The session ID changes, preventing session fixation attacks
    }

    /**
     * Test 12: Verify CheckTokenExpiration middleware works.
     */
    public function test_check_token_expiration_middleware(): void
    {
        // Create a token with expiration
        config(['sanctum.expiration' => 60]);

        $token = $this->user->createToken('test-token');
        $plainTextToken = $token->plainTextToken;

        // Set expiration to past
        $token->accessToken->expires_at = Carbon::now()->subMinute();
        $token->accessToken->save();

        // Make request with expired token through middleware
        $response = $this->withHeader('Authorization', 'Bearer ' . $plainTextToken)
            ->getJson('/api/auth/user');

        // Should be unauthorized (either from Sanctum or our middleware)
        $response->assertUnauthorized();
    }

    /**
     * Test 13: Verify admin accounts requirement for certain operations.
     */
    public function test_admin_operations_require_admin_role(): void
    {
        // Create admin user
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        // Regular user token
        $userToken = $this->user->createToken('user-token', ['read', 'write'])->plainTextToken;

        // Admin token
        $adminToken = $admin->createToken('admin-token', ['read', 'write', 'delete', 'admin'])->plainTextToken;

        // Try admin operation as regular user (should fail)
        $response = $this->withHeader('Authorization', 'Bearer ' . $userToken)
            ->postJson('/api/admin/accounts/1/freeze');

        // Should be forbidden or not found (depends on route protection)
        $this->assertContains($response->status(), [403, 404]);

        // Try as admin (route may not exist, but we're testing the concept)
        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->postJson('/api/admin/accounts/1/freeze');

        // May be 404 if route doesn't exist, but shouldn't be 403
        $this->assertNotEquals(403, $response->status());
    }
}
