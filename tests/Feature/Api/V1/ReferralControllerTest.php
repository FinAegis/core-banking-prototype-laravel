<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReferralControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        config(['relayer.sponsorship.enabled' => true, 'relayer.sponsorship.default_free_tx' => 5]);
    }

    public function test_get_my_code_requires_auth(): void
    {
        $this->getJson('/api/v1/referrals/my-code')
            ->assertStatus(401);
    }

    public function test_get_my_code_generates_code(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $response = $this->getJson('/api/v1/referrals/my-code')
            ->assertOk()
            ->assertJsonStructure(['data' => ['code', 'share_link', 'share_text', 'uses_count', 'max_uses', 'active', 'created_at']]);

        $code = $response->json('data.code');
        $this->assertEquals(8, strlen($code));
        $this->assertTrue($response->json('data.active'));
        $this->assertStringContainsString($code, $response->json('data.share_text'));
    }

    public function test_get_my_code_returns_existing(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $existing = ReferralCode::create([
            'user_id'  => $this->user->id,
            'code'     => 'TEST1234',
            'max_uses' => 50,
            'active'   => true,
        ]);

        $this->getJson('/api/v1/referrals/my-code')
            ->assertOk()
            ->assertJsonPath('data.code', 'TEST1234');
    }

    public function test_apply_code_creates_referral(): void
    {
        $referrer = User::factory()->create();
        $referralCode = ReferralCode::create([
            'user_id'  => $referrer->id,
            'code'     => 'REF12345',
            'max_uses' => 50,
        ]);

        Sanctum::actingAs($this->user, ['read', 'write']);

        $this->postJson('/api/v1/referrals/apply', ['code' => 'REF12345'])
            ->assertOk()
            ->assertJsonPath('message', 'Referral code applied successfully');

        $this->assertDatabaseHas('referrals', [
            'referrer_id' => $referrer->id,
            'referee_id'  => $this->user->id,
            'status'      => 'pending',
        ]);

        $this->user->refresh();
        $this->assertEquals($referrer->id, $this->user->referred_by);
    }

    public function test_apply_own_code_fails(): void
    {
        $referralCode = ReferralCode::create([
            'user_id'  => $this->user->id,
            'code'     => 'MYOWN123',
            'max_uses' => 50,
        ]);

        Sanctum::actingAs($this->user, ['read', 'write']);

        $this->postJson('/api/v1/referrals/apply', ['code' => 'MYOWN123'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'You cannot use your own referral code.');
    }

    public function test_apply_invalid_code_fails(): void
    {
        Sanctum::actingAs($this->user, ['read', 'write']);

        $this->postJson('/api/v1/referrals/apply', ['code' => 'INVALID1'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'Invalid referral code.');
    }

    public function test_apply_code_twice_fails(): void
    {
        $referrer = User::factory()->create();
        ReferralCode::create([
            'user_id'  => $referrer->id,
            'code'     => 'FIRST123',
            'max_uses' => 50,
        ]);

        $referrer2 = User::factory()->create();
        ReferralCode::create([
            'user_id'  => $referrer2->id,
            'code'     => 'SECND123',
            'max_uses' => 50,
        ]);

        Sanctum::actingAs($this->user, ['read', 'write']);

        $this->postJson('/api/v1/referrals/apply', ['code' => 'FIRST123'])
            ->assertOk();

        $this->postJson('/api/v1/referrals/apply', ['code' => 'SECND123'])
            ->assertStatus(422)
            ->assertJsonPath('error.message', 'You have already used a referral code.');
    }

    public function test_list_referrals(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $referee = User::factory()->create();
        $code = ReferralCode::create([
            'user_id'  => $this->user->id,
            'code'     => 'LIST1234',
            'max_uses' => 50,
        ]);
        Referral::create([
            'referrer_id'      => $this->user->id,
            'referee_id'       => $referee->id,
            'referral_code_id' => $code->id,
            'status'           => 'pending',
        ]);

        $this->getJson('/api/v1/referrals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonStructure(['data' => ['*' => ['id', 'status', 'reward_amount', 'created_at']]]);
    }

    public function test_get_stats(): void
    {
        Sanctum::actingAs($this->user, ['read']);

        $code = ReferralCode::create([
            'user_id'  => $this->user->id,
            'code'     => 'STAT1234',
            'max_uses' => 50,
        ]);

        // Create 2 referrals (1 rewarded, 1 pending)
        $referee1 = User::factory()->create();
        Referral::create([
            'referrer_id'      => $this->user->id,
            'referee_id'       => $referee1->id,
            'referral_code_id' => $code->id,
            'status'           => 'rewarded',
            'completed_at'     => now(),
        ]);
        $referee2 = User::factory()->create();
        Referral::create([
            'referrer_id'      => $this->user->id,
            'referee_id'       => $referee2->id,
            'referral_code_id' => $code->id,
            'status'           => 'pending',
        ]);

        $this->getJson('/api/v1/referrals/stats')
            ->assertOk()
            ->assertJsonPath('data.total_referred', 2)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.rewards_earned', 5);
    }
}
