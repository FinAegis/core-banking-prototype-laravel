<?php

namespace Tests\Security\Authentication;

use App\Models\User;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('login');
    }

    /**
     * @test
     */
    public function test_login_is_protected_against_brute_force()
    {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('correct-password')
        ]);

        $attempts = 0;
        $blockedAt = null;

        // Attempt multiple failed logins
        for ($i = 0; $i < 20; $i++) {
            $response = $this->postJson('/api/v2/auth/login', [
                'email' => 'test@example.com',
                'password' => 'wrong-password-' . $i
            ]);

            $attempts++;

            if ($response->status() === 429) {
                $blockedAt = $attempts;
                break;
            }
        }

        // Should be rate limited before 20 attempts
        $this->assertNotNull($blockedAt, 'Login should be rate limited');
        $this->assertLessThan(20, $blockedAt, 'Should block before 20 attempts');

        // Verify rate limit message
        $this->assertEquals(429, $response->status());
        $this->assertArrayHasKey('message', $response->json());
    }

    /**
     * @test
     */
    public function test_password_requirements_are_enforced()
    {
        $weakPasswords = [
            'password',          // Common password
            '12345678',         // Numeric only
            'aaaaaaaa',         // Repeated characters
            'abcdefgh',         // Sequential
            'p@ssw0rd',         // Common substitution
            'admin123',         // Common pattern
            'qwertyui',         // Keyboard pattern
            'password1',        // Common suffix
            'Pa$$w0rd',         // Predictable substitution
            'iloveyou',         // Common phrase
        ];

        foreach ($weakPasswords as $password) {
            $response = $this->postJson('/api/v2/auth/register', [
                'name' => 'Test User',
                'email' => 'test' . uniqid() . '@example.com',
                'password' => $password,
                'password_confirmation' => $password
            ]);

            $this->assertEquals(422, $response->status(), "Weak password '{$password}' should be rejected");
            $this->assertArrayHasKey('password', $response->json('errors'));
        }
    }

    /**
     * @test
     */
    public function test_timing_attacks_are_mitigated_on_login()
    {
        $validUser = User::factory()->create([
            'email' => 'valid@example.com',
            'password' => Hash::make('password123')
        ]);

        $timings = [];

        // Test with valid username
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            
            $this->postJson('/api/v2/auth/login', [
                'email' => 'valid@example.com',
                'password' => 'wrong-password'
            ]);
            
            $timings['valid_user'][] = microtime(true) - $start;
        }

        // Test with invalid username
        for ($i = 0; $i < 5; $i++) {
            $start = microtime(true);
            
            $this->postJson('/api/v2/auth/login', [
                'email' => 'nonexistent@example.com',
                'password' => 'wrong-password'
            ]);
            
            $timings['invalid_user'][] = microtime(true) - $start;
        }

        // Calculate average timings
        $avgValidUser = array_sum($timings['valid_user']) / count($timings['valid_user']);
        $avgInvalidUser = array_sum($timings['invalid_user']) / count($timings['invalid_user']);

        // Timing difference should be minimal (less than 50ms)
        $difference = abs($avgValidUser - $avgInvalidUser);
        $this->assertLessThan(0.05, $difference, 'Login timing should be constant to prevent user enumeration');
    }

    /**
     * @test
     */
    public function test_session_fixation_is_prevented()
    {
        $user = User::factory()->create();
        
        // Get initial session ID
        $this->get('/');
        $initialSessionId = session()->getId();

        // Login
        $response = $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);

        if ($response->status() === 200) {
            // Session ID should change after login
            $newSessionId = session()->getId();
            $this->assertNotEquals($initialSessionId, $newSessionId, 'Session should be regenerated after login');
        }
    }

    /**
     * @test
     */
    public function test_concurrent_session_limit_is_enforced()
    {
        $user = User::factory()->create();

        // Create multiple tokens
        $tokens = [];
        for ($i = 0; $i < 10; $i++) {
            $response = $this->postJson('/api/v2/auth/login', [
                'email' => $user->email,
                'password' => 'password',
                'device_name' => 'device-' . $i
            ]);

            if ($response->status() === 200) {
                $tokens[] = $response->json('token');
            }
        }

        // Should limit concurrent sessions
        $this->assertLessThanOrEqual(5, count($tokens), 'Should limit concurrent sessions per user');

        // Verify old tokens are invalidated
        if (count($tokens) > 5) {
            $response = $this->withToken($tokens[0])->getJson('/api/v2/profile');
            $this->assertEquals(401, $response->status(), 'Oldest token should be invalidated');
        }
    }

    /**
     * @test
     */
    public function test_token_expiration_is_enforced()
    {
        $user = User::factory()->create();
        
        // Create token with short expiration
        $token = $user->createToken('test-token', ['*'], now()->addMinutes(1))->plainTextToken;

        // Token should work immediately
        $response = $this->withToken($token)->getJson('/api/v2/profile');
        $this->assertEquals(200, $response->status());

        // Simulate time passing
        $this->travel(2)->minutes();

        // Token should be expired
        $response = $this->withToken($token)->getJson('/api/v2/profile');
        $this->assertEquals(401, $response->status());
    }

    /**
     * @test
     */
    public function test_account_lockout_after_failed_attempts()
    {
        $user = User::factory()->create();

        // Make multiple failed attempts
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/v2/auth/login', [
                'email' => $user->email,
                'password' => 'wrong-password'
            ]);
        }

        // Try with correct password
        $response = $this->postJson('/api/v2/auth/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);

        // Should still be locked
        $this->assertEquals(429, $response->status());
        
        // Check lockout time is reasonable
        $retryAfter = $response->headers->get('Retry-After');
        $this->assertNotNull($retryAfter);
        $this->assertGreaterThan(60, $retryAfter, 'Lockout should be at least 1 minute');
    }

    /**
     * @test
     */
    public function test_password_reset_tokens_expire()
    {
        $user = User::factory()->create();

        // Request password reset
        $response = $this->postJson('/api/v2/auth/forgot-password', [
            'email' => $user->email
        ]);

        if ($response->status() === 200) {
            // Simulate expired token
            $expiredToken = 'expired-token-12345';

            $response = $this->postJson('/api/v2/auth/reset-password', [
                'email' => $user->email,
                'token' => $expiredToken,
                'password' => 'new-password-123',
                'password_confirmation' => 'new-password-123'
            ]);

            $this->assertEquals(422, $response->status());
            $this->assertArrayHasKey('email', $response->json('errors'));
        }
    }

    /**
     * @test
     */
    public function test_user_enumeration_is_prevented()
    {
        User::factory()->create(['email' => 'exists@example.com']);

        // Test password reset with existing user
        $response1 = $this->postJson('/api/v2/auth/forgot-password', [
            'email' => 'exists@example.com'
        ]);

        // Test password reset with non-existing user
        $response2 = $this->postJson('/api/v2/auth/forgot-password', [
            'email' => 'doesnotexist@example.com'
        ]);

        // Both should return same response
        $this->assertEquals($response1->status(), $response2->status());
        $this->assertEquals($response1->json('message'), $response2->json('message'));
    }

    /**
     * @test
     */
    public function test_secure_headers_are_present()
    {
        $response = $this->getJson('/api/v2/auth/login');

        // Security headers that should be present
        $securityHeaders = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => ['DENY', 'SAMEORIGIN'],
            'X-XSS-Protection' => '1; mode=block',
            'Strict-Transport-Security' => 'max-age=',
            'Referrer-Policy' => ['no-referrer', 'strict-origin-when-cross-origin']
        ];

        foreach ($securityHeaders as $header => $expectedValues) {
            $this->assertTrue(
                $response->headers->has($header),
                "Security header {$header} should be present"
            );

            if (is_array($expectedValues)) {
                $headerValue = $response->headers->get($header);
                $hasValidValue = false;
                foreach ($expectedValues as $expected) {
                    if (str_contains($headerValue, $expected)) {
                        $hasValidValue = true;
                        break;
                    }
                }
                $this->assertTrue($hasValidValue, "{$header} should contain valid value");
            }
        }
    }

    /**
     * @test
     */
    public function test_api_tokens_cannot_access_web_routes()
    {
        $user = User::factory()->create();
        $token = $user->createToken('api-token')->plainTextToken;

        // Try to access web routes with API token
        $webRoutes = ['/', '/dashboard', '/profile', '/settings'];

        foreach ($webRoutes as $route) {
            $response = $this->withHeader('Authorization', "Bearer {$token}")
                ->get($route);

            // Should redirect to login or return 401/403
            $this->assertContains($response->status(), [302, 401, 403]);
        }
    }

    /**
     * @test
     */
    public function test_logout_invalidates_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Verify token works
        $response = $this->withToken($token)->getJson('/api/v2/profile');
        $this->assertEquals(200, $response->status());

        // Logout
        $response = $this->withToken($token)->postJson('/api/v2/auth/logout');
        $this->assertEquals(200, $response->status());

        // Token should no longer work
        $response = $this->withToken($token)->getJson('/api/v2/profile');
        $this->assertEquals(401, $response->status());
    }
}