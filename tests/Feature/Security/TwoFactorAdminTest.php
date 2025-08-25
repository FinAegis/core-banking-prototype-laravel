<?php

namespace Tests\Feature\Security;

use App\Domain\User\Values\UserRoles;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TwoFactorAdminTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_without_2fa_enabled_is_blocked()
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRoles::ADMIN->value);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'error'          => 'Two-Factor Authentication Required',
                'setup_required' => true,
            ]);
    }

    /** @test */
    public function admin_with_2fa_enabled_but_not_confirmed_is_blocked()
    {
        $admin = User::factory()->create([
            'two_factor_secret'       => encrypt('secret'),
            'two_factor_confirmed_at' => null,
        ]);
        $admin->assignRole(UserRoles::ADMIN->value);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'error'          => 'Two-Factor Authentication Required',
                'setup_required' => true,
            ]);
    }

    /** @test */
    public function admin_with_confirmed_2fa_but_not_verified_in_session_is_blocked()
    {
        $admin = User::factory()->create([
            'two_factor_secret'       => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole(UserRoles::ADMIN->value);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/admin/dashboard');

        $response->assertStatus(403)
            ->assertJson([
                'error'                 => 'Two-Factor Verification Required',
                'verification_required' => true,
            ]);
    }

    /** @test */
    public function admin_with_verified_2fa_can_access_protected_routes()
    {
        $admin = User::factory()->create([
            'two_factor_secret'       => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole(UserRoles::ADMIN->value);

        // Set session verification
        $this->withSession(['two_factor_verified_' . $admin->id => true]);

        Sanctum::actingAs($admin);

        // Create a mock admin route for testing
        $response = $this->getJson('/api/admin/dashboard');

        // Should not get 403 from 2FA middleware
        $this->assertNotEquals(403, $response->status());
    }

    /** @test */
    public function non_admin_users_are_not_required_to_have_2fa()
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/auth/user');

        // Should not be blocked by 2FA requirement
        $this->assertNotEquals(403, $response->status());
        $response->assertStatus(200);
    }

    /** @test */
    public function admin_can_enable_2fa()
    {
        $admin = User::factory()->create();
        $admin->assignRole(UserRoles::ADMIN->value);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/auth/2fa/enable');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'secret',
                'qr_code',
                'recovery_codes',
            ]);

        // Verify 2FA secret was stored
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNotNull($admin->two_factor_recovery_codes);
    }

    /** @test */
    public function admin_can_confirm_2fa_with_valid_code()
    {
        $admin = User::factory()->create([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);
        $admin->assignRole(UserRoles::ADMIN->value);

        Sanctum::actingAs($admin);

        // Mock the TwoFactorAuthenticationProvider
        $this->mock(\Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn(true);

        $response = $this->postJson('/api/auth/2fa/confirm', [
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Two-factor authentication confirmed successfully.',
            ]);

        // Verify confirmation was stored
        $admin->refresh();
        $this->assertNotNull($admin->two_factor_confirmed_at);
    }

    /** @test */
    public function admin_cannot_disable_2fa_without_password()
    {
        $admin = User::factory()->create([
            'two_factor_secret'       => encrypt('secret'),
            'two_factor_confirmed_at' => now(),
        ]);
        $admin->assignRole(UserRoles::ADMIN->value);

        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/auth/2fa/disable', [
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);
    }
}
