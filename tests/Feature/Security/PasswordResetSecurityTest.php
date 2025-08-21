<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class PasswordResetSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Clear rate limiter before each test
        RateLimiter::clear('password-reset:127.0.0.1');
        RateLimiter::clear('password-reset:192.168.1.1');
        RateLimiter::clear('password-reset:192.168.1.2');
        RateLimiter::clear('password-reset-attempt:127.0.0.1');
    }

    public function test_password_reset_returns_same_message_for_existing_and_non_existing_emails(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        // Test with existing email
        $response1 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'existing@example.com',
        ]);

        $response1->assertOk();
        $expectedMessage = 'If your email address exists in our database, you will receive a password recovery link at your email address in a few minutes.';
        $response1->assertJson(['message' => $expectedMessage]);

        // Test with non-existing email
        $response2 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexisting@example.com',
        ]);

        $response2->assertOk();
        $response2->assertJson(['message' => $expectedMessage]);

        // Both responses should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    public function test_password_reset_implements_rate_limiting(): void
    {
        $email = 'test@example.com';

        // Make 5 requests (the limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/forgot-password', [
                'email' => $email,
            ]);
            $response->assertOk();
        }

        // 6th request should be rate limited
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $email,
        ]);

        $response->assertStatus(429);
        $response->assertJsonStructure(['message']);
        $this->assertStringContainsString('Too many password reset attempts', $response->json('message'));
    }

    public function test_password_reset_rate_limiting_is_per_ip(): void
    {
        // Clear rate limiters for test IPs
        RateLimiter::clear('password-reset:192.168.1.1');
        RateLimiter::clear('password-reset:192.168.1.2');

        $email = 'test@example.com';

        // 5 requests from IP 1
        for ($i = 0; $i < 5; $i++) {
            // Simulate request from IP 192.168.1.1
            $this->serverVariables = ['REMOTE_ADDR' => '192.168.1.1'];
            $response = $this->postJson('/api/auth/forgot-password', [
                'email' => $email,
            ]);
            $response->assertOk();
        }

        // Request from different IP should work
        $this->serverVariables = ['REMOTE_ADDR' => '192.168.1.2'];
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $email,
        ]);
        $response->assertOk();

        // 6th request from IP 1 should be blocked
        $this->serverVariables = ['REMOTE_ADDR' => '192.168.1.1'];
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $email,
        ]);
        $response->assertStatus(429);
    }

    public function test_password_reset_attempt_implements_rate_limiting(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        // Make 5 attempts (the limit)
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/reset-password', [
                'email'                 => $user->email,
                'password'              => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'token'                 => 'invalid-token',
            ]);
            $response->assertStatus(422);
        }

        // 6th attempt should be rate limited
        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token'                 => 'invalid-token',
        ]);

        $response->assertStatus(422);
        $errors = $response->json('errors.email');
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Too many reset attempts', $errors[0]);
    }

    public function test_successful_password_reset_clears_rate_limiter(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        // Make 4 failed attempts
        for ($i = 0; $i < 4; $i++) {
            $response = $this->postJson('/api/auth/reset-password', [
                'email'                 => $user->email,
                'password'              => 'newpassword123',
                'password_confirmation' => 'newpassword123',
                'token'                 => 'invalid-token',
            ]);
            $response->assertStatus(422);
        }

        // Successful reset with valid token
        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token'                 => $token,
        ]);
        $response->assertOk();

        // Rate limiter should be cleared, so we can make requests again
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/auth/reset-password', [
                'email'                 => $user->email,
                'password'              => 'anotherpassword123',
                'password_confirmation' => 'anotherpassword123',
                'token'                 => 'invalid-token',
            ]);
            $response->assertStatus(422);
            $errors = $response->json('errors.email');
            // Should not contain rate limit message for first 5 attempts
            if ($errors) {
                $this->assertStringNotContainsString('Too many reset attempts', $errors[0]);
            }
        }
    }

    public function test_password_reset_revokes_all_user_tokens(): void
    {
        $user = User::factory()->create();

        // Create some tokens for the user
        $token1 = $user->createToken('token1')->plainTextToken;
        $token2 = $user->createToken('token2')->plainTextToken;
        $token3 = $user->createToken('token3')->plainTextToken;

        $this->assertEquals(3, $user->tokens()->count());

        // Reset password
        $resetToken = Password::createToken($user);
        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token'                 => $resetToken,
        ]);

        $response->assertOk();

        // All tokens should be revoked
        $this->assertEquals(0, $user->fresh()->tokens()->count());

        // Old tokens should not work
        $response = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson('/api/auth/user');
        $response->assertUnauthorized();
    }

    public function test_password_reset_requires_valid_email_format(): void
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }

    public function test_password_reset_requires_matching_confirmation(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'differentpassword123',
            'token'                 => $token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_reset_requires_minimum_length(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email'                 => $user->email,
            'password'              => 'short',
            'password_confirmation' => 'short',
            'token'                 => $token,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['password']);
    }

    public function test_password_reset_does_not_reveal_email_timing(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com']);

        // Measure response time for existing email
        $start1 = microtime(true);
        $response1 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'existing@example.com',
        ]);
        $time1 = microtime(true) - $start1;

        // Measure response time for non-existing email
        $start2 = microtime(true);
        $response2 = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexisting@example.com',
        ]);
        $time2 = microtime(true) - $start2;

        // Both should return same status and message
        $response1->assertOk();
        $response2->assertOk();
        $this->assertEquals($response1->json('message'), $response2->json('message'));

        // Response times should be similar (within 500ms difference)
        // This prevents timing attacks
        $timeDifference = abs($time1 - $time2);
        $this->assertLessThan(0.5, $timeDifference, 'Response time difference is too large, possible timing attack vulnerability');
    }
}
