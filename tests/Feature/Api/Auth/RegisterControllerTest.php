<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_business_customer' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'email_verified_at',
                ],
                'access_token',
                'token_type',
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Verify user has the correct role
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->hasRole('private'));
    }

    public function test_business_user_can_register_via_api(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Business User',
            'email' => 'business@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'is_business_customer' => true,
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'business@example.com')->first();
        $this->assertTrue($user->hasRole('business'));
    }

    public function test_registration_validates_input(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => '',
            'email' => 'invalid-email',
            'password' => 'short',
            'password_confirmation' => 'different',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_registration_prevents_duplicate_emails(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'existing@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registered_user_receives_access_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertStatus(201);
        $token = $response->json('access_token');
        $this->assertNotEmpty($token);

        // Test that the token works
        $authResponse = $this->withToken($token)->getJson('/api/auth/user');
        $authResponse->assertOk()
            ->assertJsonPath('user.email', 'john@example.com');
    }
}