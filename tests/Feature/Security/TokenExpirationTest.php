<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TokenExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // $this->setUpSecurityTesting(); // Removed - trait deleted
        $this->user = User::factory()->create();
    }

    public function test_token_has_expiration_set_when_created(): void
    {
        // Set expiration to 60 minutes
        config(['sanctum.expiration' => 60]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'access_token',
            'expires_in',
        ]);

        // Check that expires_in is set correctly (60 minutes = 3600 seconds)
        $this->assertEquals(3600, $response->json('expires_in'));

        // Check database for token expiration
        $token = $this->user->tokens()->first();
        $this->assertNotNull($token);
        $this->assertNotNull($token->expires_at);

        // Token should expire in approximately 60 minutes
        $diffInMinutes = Carbon::now()->diffInMinutes($token->expires_at);
        $this->assertGreaterThanOrEqual(59, $diffInMinutes);
        $this->assertLessThanOrEqual(61, $diffInMinutes);
    }

    public function test_expired_token_is_rejected(): void
    {
        // Create a token
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Manually expire the token
        $this->user->tokens()->first()->update([
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        // Try to use the expired token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/user');

        // Sanctum returns Unauthenticated for expired tokens
        $response->assertUnauthorized();
        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_valid_token_passes_expiration_check(): void
    {
        // Create a token with future expiration
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Set expiration to future
        $this->user->tokens()->first()->update([
            'expires_at' => Carbon::now()->addHour(),
        ]);

        // Use the valid token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/user');

        $response->assertOk();
        $response->assertJsonFragment([
            'id'    => $this->user->id,
            'email' => $this->user->email,
        ]);
    }

    public function test_token_without_expiration_is_accepted(): void
    {
        // Create a token without expiration
        $token = $this->user->createToken('test-token')->plainTextToken;

        // Remove expiration
        $this->user->tokens()->first()->update([
            'expires_at' => null,
        ]);

        // Use the token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/auth/user');

        $response->assertOk();
    }

    public function test_token_refresh_maintains_scopes(): void
    {
        // Create initial token with specific scopes
        $token = $this->user->createToken('test-token', ['read', 'write'])->plainTextToken;

        // Refresh the token
        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/auth/refresh');

        $response->assertOk();
        $response->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
        ]);

        // Check new token has same scopes
        $newToken = $this->user->tokens()->latest()->first();
        $this->assertEquals(['read', 'write'], $newToken->abilities);
    }

    public function test_multiple_expired_tokens_are_handled_correctly(): void
    {
        // Create multiple tokens
        $token1 = $this->user->createToken('token1')->plainTextToken;
        $token2 = $this->user->createToken('token2')->plainTextToken;
        $token3 = $this->user->createToken('token3')->plainTextToken;

        // Expire first two tokens
        $this->user->tokens()->whereIn('name', ['token1', 'token2'])->update([
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        // Try to use expired token1
        $response = $this->withHeader('Authorization', 'Bearer ' . $token1)
            ->getJson('/api/auth/user');
        $response->assertUnauthorized();

        // Try to use expired token2
        $response = $this->withHeader('Authorization', 'Bearer ' . $token2)
            ->getJson('/api/auth/user');
        $response->assertUnauthorized();

        // Valid token3 should still work
        $response = $this->withHeader('Authorization', 'Bearer ' . $token3)
            ->getJson('/api/auth/user');
        $response->assertOk();

        // All 3 tokens still exist (expired tokens are not automatically deleted by Sanctum)
        $this->assertEquals(3, $this->user->tokens()->count());
    }

    public function test_token_expiration_respects_sanctum_config(): void
    {
        // Test with no expiration set
        config(['sanctum.expiration' => null]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertNull($response->json('expires_in'));

        // Token should not have expires_at set
        $token = $this->user->tokens()->first();
        $this->assertNull($token->expires_at);
    }

    public function test_token_expiration_is_set_correctly_with_config(): void
    {
        // Test with 120 minutes expiration
        config(['sanctum.expiration' => 120]);

        $response = $this->postJson('/api/auth/login', [
            'email'    => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertOk();
        $this->assertEquals(7200, $response->json('expires_in')); // 120 minutes = 7200 seconds

        // Check database
        $token = $this->user->tokens()->first();
        $this->assertNotNull($token->expires_at);

        // Should expire in approximately 120 minutes
        $diffInMinutes = Carbon::now()->diffInMinutes($token->expires_at);
        $this->assertGreaterThanOrEqual(119, $diffInMinutes);
        $this->assertLessThanOrEqual(121, $diffInMinutes);
    }
}
