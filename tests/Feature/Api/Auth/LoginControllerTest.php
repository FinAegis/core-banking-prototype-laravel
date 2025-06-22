<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                ],
                'access_token',
                'token_type',
                'expires_in',
            ]);

        $this->assertNotEmpty($response->json('access_token'));
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email' => 'test@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_validates_required_fields(): void
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_user_can_logout(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson(['message' => 'Successfully logged out']);
    }

    public function test_user_can_logout_from_all_devices(): void
    {
        // Create multiple tokens
        $this->user->createToken('device-1');
        $this->user->createToken('device-2');
        $this->user->createToken('device-3');

        $this->assertEquals(3, $this->user->tokens()->count());

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/auth/logout-all');

        $response->assertOk()
            ->assertJson(['message' => 'Successfully logged out from all devices']);

        $this->assertEquals(0, $this->user->tokens()->count());
    }

    public function test_user_can_refresh_token(): void
    {
        // Create a token first
        $token = $this->user->createToken('test-token')->plainTextToken;
        
        // Use the token to make the request
        $response = $this->withToken($token)->postJson('/api/auth/refresh');

        $response->assertOk()
            ->assertJsonStructure([
                'access_token',
                'token_type',
                'expires_in',
            ]);

        // Verify new token works
        $newToken = $response->json('access_token');
        $this->assertNotEquals($token, $newToken);
        
        // Test new token works
        $authResponse = $this->withToken($newToken)->getJson('/api/auth/user');
        $authResponse->assertOk();
    }

    public function test_user_can_get_profile(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/auth/user');

        $response->assertOk()
            ->assertJsonPath('user.email', 'test@example.com');
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401);
    }
}