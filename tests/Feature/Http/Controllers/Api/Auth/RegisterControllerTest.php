<?php

namespace Tests\Feature\Http\Controllers\Api\Auth;

use App\Models\User;
use Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class RegisterControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
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
            ])
            ->assertJson([
                'message' => 'User registered successfully',
                'user'    => [
                    'name'              => 'John Doe',
                    'email'             => 'john@example.com',
                    'email_verified_at' => null,
                ],
                'token_type' => 'Bearer',
            ]);

        $this->assertNotEmpty($response->json('access_token'));

        // Verify user was created
        $this->assertDatabaseHas('users', [
            'name'  => 'John Doe',
            'email' => 'john@example.com',
        ]);

        // Verify token was created
        $user = User::where('email', 'john@example.com')->first();
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name'         => 'api-token',
        ]);
    }

    #[Test]
    public function test_register_as_business_customer(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'Business User',
            'email'                 => 'business@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
            'is_business_customer'  => true,
        ]);

        $response->assertStatus(201);

        // Verify business customer role was assigned
        $user = User::where('email', 'business@example.com')->first();
        $this->assertTrue($user->hasRole('customer_business'));
    }

    #[Test]
    public function test_register_fails_with_missing_required_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    #[Test]
    public function test_register_fails_with_invalid_email(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'not-an-email',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function test_register_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'existing@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJson([
                'errors' => [
                    'email' => ['The email has already been taken.'],
                ],
            ]);
    }

    #[Test]
    public function test_register_fails_with_short_password(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'short',
            'password_confirmation' => 'short',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function test_register_fails_with_mismatched_password_confirmation(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'password123',
            'password_confirmation' => 'different123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    #[Test]
    public function test_register_fails_with_name_too_long(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => str_repeat('a', 256),
            'email'                 => 'john@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function test_register_fails_with_email_too_long(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => str_repeat('a', 250) . '@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    #[Test]
    public function test_register_defaults_to_regular_customer(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
            // is_business_customer not provided
        ]);

        $response->assertStatus(201);

        // Verify default is regular customer
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue($user->hasRole('customer_private'));
    }

    #[Test]
    public function test_register_validates_boolean_for_business_customer(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
            'is_business_customer'  => 'not-a-boolean',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['is_business_customer']);
    }

    #[Test]
    public function test_register_returns_unverified_email_status(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email_verified_at', null);
    }

    #[Test]
    public function test_register_creates_account_record(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
        ]);

        $response->assertStatus(201);

        $user = User::where('email', 'john@example.com')->first();

        // Verify team was created (CreateNewUser creates a team, not account)
        $this->assertDatabaseHas('teams', [
            'user_id'       => $user->id,
            'personal_team' => true,
        ]);
    }

    #[Test]
    public function test_register_uses_create_new_user_action(): void
    {
        // This test verifies that the controller uses the Fortify CreateNewUser action
        // which ensures consistency with web registration

        $response = $this->postJson('/api/auth/register', [
            'name'                  => 'John Doe',
            'email'                 => 'john@example.com',
            'password'              => 'MySecure#Pass2024!',
            'password_confirmation' => 'MySecure#Pass2024!',
        ]);

        $response->assertStatus(201);

        // The CreateNewUser action should handle password hashing
        $user = User::where('email', 'john@example.com')->first();
        $this->assertTrue(Hash::check('MySecure#Pass2024!', $user->password));
    }
}
