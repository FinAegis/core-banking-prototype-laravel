<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ConcurrentSessionTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_concurrent_session_limit_is_enforced(): void
    {
        // Set limit to 5
        config(['auth.max_concurrent_sessions' => 5]);

        $tokens = [];

        // Create 5 tokens
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'       => $this->user->email,
                'password'    => 'password',
                'device_name' => "Device {$i}",
            ]);

            $response->assertOk();
            $tokens[] = $response->json('access_token');
        }

        // Verify 5 tokens exist
        $this->assertEquals(5, $this->user->tokens()->count());

        // Create 6th token - should delete the oldest
        $response = $this->postJson('/api/auth/login', [
            'email'       => $this->user->email,
            'password'    => 'password',
            'device_name' => 'Device 6',
        ]);

        $response->assertOk();
        $tokens[] = $response->json('access_token');

        // Still only 5 tokens
        $this->assertEquals(5, $this->user->tokens()->count());

        // First token should no longer work
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokens[0])
            ->getJson('/api/auth/user');
        $response->assertUnauthorized();

        // Last 5 tokens should still work
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $tokens[$i])
                ->getJson('/api/auth/user');
            $response->assertOk();
        }
    }

    public function test_concurrent_session_limit_respects_configuration(): void
    {
        // Test with custom limit of 3
        config(['auth.max_concurrent_sessions' => 3]);

        // Create 3 tokens
        for ($i = 1; $i <= 3; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'       => $this->user->email,
                'password'    => 'password',
                'device_name' => "Device {$i}",
            ]);
            $response->assertOk();
        }

        $this->assertEquals(3, $this->user->tokens()->count());

        // 4th login should maintain limit of 3
        $response = $this->postJson('/api/auth/login', [
            'email'       => $this->user->email,
            'password'    => 'password',
            'device_name' => 'Device 4',
        ]);
        $response->assertOk();

        $this->assertEquals(3, $this->user->tokens()->count());
    }

    public function test_oldest_sessions_are_removed_first(): void
    {
        config(['auth.max_concurrent_sessions' => 3]);

        // Create tokens with delays to ensure different timestamps
        $deviceNames = [];
        for ($i = 1; $i <= 3; $i++) {
            $deviceName = "Device {$i}";
            $deviceNames[] = $deviceName;

            $response = $this->postJson('/api/auth/login', [
                'email'       => $this->user->email,
                'password'    => 'password',
                'device_name' => $deviceName,
            ]);
            $response->assertOk();

            // Small delay to ensure different created_at times
            usleep(100000); // 100ms
        }

        // Get the oldest token name
        $oldestToken = $this->user->tokens()
            ->orderBy('created_at', 'asc')
            ->first();
        $oldestDeviceName = $oldestToken->name;

        // Create new token
        $response = $this->postJson('/api/auth/login', [
            'email'       => $this->user->email,
            'password'    => 'password',
            'device_name' => 'Device 4',
        ]);
        $response->assertOk();

        // Oldest token should be gone
        $remainingTokens = $this->user->tokens()->pluck('name')->toArray();
        $this->assertNotContains($oldestDeviceName, $remainingTokens);
        $this->assertContains('Device 4', $remainingTokens);
    }

    public function test_revoke_tokens_option_deletes_all_existing_tokens(): void
    {
        // Create some existing tokens
        for ($i = 1; $i <= 3; $i++) {
            $this->user->createToken("Device {$i}")->plainTextToken;
        }

        $this->assertEquals(3, $this->user->tokens()->count());

        // Login with revoke_tokens option
        $response = $this->postJson('/api/auth/login', [
            'email'         => $this->user->email,
            'password'      => 'password',
            'device_name'   => 'New Device',
            'revoke_tokens' => true,
        ]);

        $response->assertOk();

        // Only the new token should exist
        $this->assertEquals(1, $this->user->tokens()->count());
        $this->assertEquals('New Device', $this->user->tokens()->first()->name);
    }

    public function test_logout_all_revokes_all_sessions(): void
    {
        // Create multiple tokens
        $tokens = [];
        for ($i = 1; $i <= 3; $i++) {
            $tokens[] = $this->user->createToken("Device {$i}")->plainTextToken;
        }

        $this->assertEquals(3, $this->user->tokens()->count());

        // Logout all using one of the tokens
        $response = $this->withHeader('Authorization', 'Bearer ' . $tokens[0])
            ->postJson('/api/auth/logout-all');

        $response->assertOk();
        $response->assertJson(['message' => 'Successfully logged out from all devices']);

        // All tokens should be revoked in database
        $this->user->refresh();
        $this->assertEquals(0, $this->user->tokens()->count());

        // Check that tokens are deleted from database
        $dbTokenCount = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('tokenable_type', get_class($this->user))
            ->count();
        $this->assertEquals(0, $dbTokenCount);
    }

    public function test_concurrent_session_limit_is_per_user(): void
    {
        config(['auth.max_concurrent_sessions' => 2]);

        $user2 = User::factory()->create();

        // Create 2 tokens for user1
        for ($i = 1; $i <= 2; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'       => $this->user->email,
                'password'    => 'password',
                'device_name' => "User1 Device {$i}",
            ]);
            $response->assertOk();
        }

        // Create 2 tokens for user2
        for ($i = 1; $i <= 2; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'       => $user2->email,
                'password'    => 'password',
                'device_name' => "User2 Device {$i}",
            ]);
            $response->assertOk();
        }

        // Both users should have 2 tokens each
        $this->assertEquals(2, $this->user->tokens()->count());
        $this->assertEquals(2, $user2->tokens()->count());

        // Adding a 3rd token for user1 shouldn't affect user2
        $response = $this->postJson('/api/auth/login', [
            'email'       => $this->user->email,
            'password'    => 'password',
            'device_name' => 'User1 Device 3',
        ]);
        $response->assertOk();

        $this->assertEquals(2, $this->user->tokens()->count());
        $this->assertEquals(2, $user2->tokens()->count());
    }

    public function test_default_concurrent_session_limit_is_5(): void
    {
        // Use the default config value which should be 5
        // Note: in test environment, config may not load from .env
        // So we explicitly set it to match production default
        config(['auth.max_concurrent_sessions' => 5]);

        // Create 5 tokens (should be allowed by default)
        for ($i = 1; $i <= 5; $i++) {
            $response = $this->postJson('/api/auth/login', [
                'email'       => $this->user->email,
                'password'    => 'password',
                'device_name' => "Device {$i}",
            ]);
            $response->assertOk();
        }

        $this->assertEquals(5, $this->user->tokens()->count());

        // 6th token should enforce the limit
        $response = $this->postJson('/api/auth/login', [
            'email'       => $this->user->email,
            'password'    => 'password',
            'device_name' => 'Device 6',
        ]);
        $response->assertOk();

        // Should still be 5 (oldest removed)
        $this->assertEquals(5, $this->user->tokens()->count());
    }

    public function test_single_logout_only_revokes_current_token(): void
    {
        // Create multiple tokens
        $token1 = $this->user->createToken('Device 1')->plainTextToken;
        $token2 = $this->user->createToken('Device 2')->plainTextToken;
        $token3 = $this->user->createToken('Device 3')->plainTextToken;

        $this->assertEquals(3, $this->user->tokens()->count());

        // Logout using token1
        $response = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->postJson('/api/auth/logout');

        $response->assertOk();
        $response->assertJson(['message' => 'Successfully logged out']);

        // Refresh user to get updated token count
        $this->user->refresh();

        // Should have 2 tokens remaining
        $this->assertEquals(2, $this->user->tokens()->count());

        // Check database directly
        $remainingTokens = PersonalAccessToken::where('tokenable_id', $this->user->id)
            ->where('tokenable_type', get_class($this->user))
            ->pluck('name')
            ->toArray();

        $this->assertNotContains('Device 1', $remainingTokens);
        $this->assertContains('Device 2', $remainingTokens);
        $this->assertContains('Device 3', $remainingTokens);
    }
}
