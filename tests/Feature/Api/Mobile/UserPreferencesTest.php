<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Mobile;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class UserPreferencesTest extends TestCase
{
    protected User $user;

    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token', ['read', 'write', 'delete'])->plainTextToken;
    }

    public function test_get_preferences_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/user/preferences');

        $response->assertUnauthorized();
    }

    public function test_get_preferences_returns_defaults_for_new_user(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/user/preferences');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'activeNetwork',
                    'isPrivacyModeEnabled',
                    'autoLockEnabled',
                    'transactionAuthRequired',
                    'hideBalances',
                    'poiEnabled',
                    'biometricLockEnabled',
                ],
            ])
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.activeNetwork', 'solana')
            ->assertJsonPath('data.isPrivacyModeEnabled', true)
            ->assertJsonPath('data.hideBalances', false);
    }

    public function test_patch_preferences_requires_authentication(): void
    {
        $response = $this->patchJson('/api/v1/user/preferences', [
            'hideBalances' => true,
        ]);

        $response->assertUnauthorized();
    }

    public function test_patch_preferences_updates_single_field(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson('/api/v1/user/preferences', [
                'hideBalances' => true,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.hideBalances', true)
            ->assertJsonPath('data.activeNetwork', 'solana'); // other defaults preserved
    }

    public function test_patch_preferences_updates_multiple_fields(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson('/api/v1/user/preferences', [
                'activeNetwork'        => 'polygon',
                'autoLockEnabled'      => false,
                'biometricLockEnabled' => false,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.activeNetwork', 'polygon')
            ->assertJsonPath('data.autoLockEnabled', false)
            ->assertJsonPath('data.biometricLockEnabled', false)
            ->assertJsonPath('data.isPrivacyModeEnabled', true); // untouched default
    }

    public function test_patch_preferences_persists_changes(): void
    {
        $this->withToken($this->token)
            ->patchJson('/api/v1/user/preferences', [
                'hideBalances' => true,
            ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/v1/user/preferences');

        $response->assertOk()
            ->assertJsonPath('data.hideBalances', true);
    }

    public function test_patch_preferences_validates_boolean_types(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson('/api/v1/user/preferences', [
                'hideBalances' => 'not-a-boolean',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    public function test_patch_preferences_ignores_unknown_fields(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson('/api/v1/user/preferences', [
                'unknownField' => 'value',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.code', 'NO_VALID_FIELDS');
    }

    public function test_patch_preferences_accepts_nested_preferences_key(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson('/api/v1/user/preferences', [
                'preferences' => [
                    'isPrivacyModeEnabled' => false,
                    'hideBalances'         => true,
                    'activeNetwork'        => 'ethereum',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.isPrivacyModeEnabled', false)
            ->assertJsonPath('data.hideBalances', true)
            ->assertJsonPath('data.activeNetwork', 'ethereum');
    }

    public function test_patch_preferences_accepts_mixed_flat_and_nested(): void
    {
        $response = $this->withToken($this->token)
            ->patchJson('/api/v1/user/preferences', [
                'hideBalances' => true,
                'preferences'  => [
                    'activeNetwork' => 'polygon',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.hideBalances', true)
            ->assertJsonPath('data.activeNetwork', 'polygon');
    }
}
