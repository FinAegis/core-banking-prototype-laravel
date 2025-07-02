<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Laravel\Fortify\RecoveryCode;
use Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AuthenticationSecurityTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_request_password_reset_link()
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function user_cannot_request_password_reset_with_invalid_email()
    {
        $response = $this->postJson('/api/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422);
    }

    #[Test]
    public function user_can_reset_password_with_valid_token()
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $response = $this->postJson('/api/auth/reset-password', [
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'token' => $token,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));
    }

    #[Test]
    public function user_can_enable_two_factor_authentication()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/auth/2fa/enable');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'secret',
                'qr_code',
                'recovery_codes',
            ]);

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertNotNull($user->fresh()->two_factor_recovery_codes);
    }

    #[Test]
    public function user_can_confirm_two_factor_authentication()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // Enable 2FA first
        $this->postJson('/api/auth/2fa/enable');
        
        $user = $user->fresh();
        $secret = decrypt($user->two_factor_secret);
        
        // Generate valid OTP code
        $google2fa = new \PragmaRX\Google2FA\Google2FA();
        $validCode = $google2fa->getCurrentOtp($secret);

        $response = $this->postJson('/api/auth/2fa/confirm', [
            'code' => $validCode,
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertNotNull($user->fresh()->two_factor_confirmed_at);
    }

    #[Test]
    public function user_can_disable_two_factor_authentication()
    {
        $user = User::factory()->create([
            'password' => Hash::make('password'),
        ]);
        $user->forceFill([
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(RecoveryCode::generate())),
        ])->save();

        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/auth/2fa/disable', [
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertNull($user->fresh()->two_factor_recovery_codes);
    }

    #[Test]
    public function user_can_verify_email_with_valid_link()
    {
        $user = User::factory()->unverified()->create();
        
        $verificationUrl = URL::temporarySignedRoute(
            'api.verification.verify',
            now()->addMinutes(60),
            [
                'id' => $user->id,
                'hash' => sha1($user->email),
            ]
        );
        
        $response = $this->get($verificationUrl);

        $response->assertOk()
            ->assertJsonStructure(['message']);

        $this->assertNotNull($user->fresh()->email_verified_at);
    }

    #[Test]
    public function user_can_resend_verification_email()
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/auth/resend-verification');

        $response->assertOk()
            ->assertJsonStructure(['message']);
    }

    #[Test]
    public function verified_user_cannot_resend_verification_email()
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/auth/resend-verification');

        $response->assertStatus(400)
            ->assertJson(['message' => 'Email already verified.']);
    }

    #[Test]
    public function user_can_get_oauth_redirect_url()
    {
        $response = $this->getJson('/api/auth/social/google');

        $response->assertOk()
            ->assertJsonStructure(['url']);
    }

    #[Test]
    public function user_cannot_get_oauth_redirect_for_invalid_provider()
    {
        $response = $this->getJson('/api/auth/social/invalid-provider');

        $response->assertStatus(400)
            ->assertJson(['message' => 'Invalid provider']);
    }
}