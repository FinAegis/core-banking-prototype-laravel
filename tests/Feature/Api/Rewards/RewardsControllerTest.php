<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Rewards;

use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardShopItem;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RewardsControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user, ['read', 'write']);
    }

    public function test_get_profile_creates_default_profile(): void
    {
        $response = $this->getJson('/api/v1/rewards/profile');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'xp',
                    'level',
                    'xp_for_next',
                    'xp_progress',
                    'current_streak',
                    'longest_streak',
                    'points_balance',
                    'quests_completed',
                ],
            ])
            ->assertJsonPath('data.xp', 0)
            ->assertJsonPath('data.level', 1)
            ->assertJsonPath('data.points_balance', 0);
    }

    public function test_get_profile_returns_existing_profile(): void
    {
        RewardProfile::create([
            'user_id'        => $this->user->id,
            'xp'             => 150,
            'level'          => 2,
            'current_streak' => 3,
            'longest_streak' => 7,
            'points_balance' => 500,
        ]);

        $response = $this->getJson('/api/v1/rewards/profile');

        $response->assertOk()
            ->assertJsonPath('data.xp', 150)
            ->assertJsonPath('data.level', 2)
            ->assertJsonPath('data.points_balance', 500);
    }

    public function test_get_quests_returns_active_quests(): void
    {
        RewardQuest::create([
            'slug'          => 'first-shield',
            'title'         => 'First Shield',
            'description'   => 'Shield tokens for the first time',
            'xp_reward'     => 50,
            'points_reward' => 100,
            'category'      => 'onboarding',
            'is_active'     => true,
            'sort_order'    => 1,
        ]);

        RewardQuest::create([
            'slug'          => 'inactive-quest',
            'title'         => 'Inactive',
            'description'   => 'Should not appear',
            'xp_reward'     => 10,
            'points_reward' => 10,
            'is_active'     => false,
            'sort_order'    => 2,
        ]);

        $response = $this->getJson('/api/v1/rewards/quests');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'first-shield')
            ->assertJsonPath('data.0.completed', false);
    }

    public function test_complete_quest_awards_xp_and_points(): void
    {
        $quest = RewardQuest::create([
            'slug'          => 'first-send',
            'title'         => 'First Send',
            'description'   => 'Send tokens for the first time',
            'xp_reward'     => 75,
            'points_reward' => 150,
            'category'      => 'onboarding',
            'is_active'     => true,
        ]);

        $response = $this->postJson("/api/v1/rewards/quests/{$quest->id}/complete");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.xp_earned', 75)
            ->assertJsonPath('data.points_earned', 150)
            ->assertJsonPath('data.new_points', 150);

        $this->assertDatabaseHas('reward_profiles', [
            'user_id'        => $this->user->id,
            'points_balance' => 150,
        ]);
    }

    public function test_complete_quest_triggers_level_up(): void
    {
        // Level 1 needs 100 XP to level up
        RewardProfile::create([
            'user_id'        => $this->user->id,
            'xp'             => 80,
            'level'          => 1,
            'points_balance' => 0,
        ]);

        $quest = RewardQuest::create([
            'slug'          => 'big-quest',
            'title'         => 'Big Quest',
            'description'   => 'Earn lots of XP',
            'xp_reward'     => 30,
            'points_reward' => 0,
            'is_active'     => true,
        ]);

        $response = $this->postJson("/api/v1/rewards/quests/{$quest->id}/complete");

        $response->assertOk()
            ->assertJsonPath('data.new_level', 2);
    }

    public function test_cannot_complete_non_repeatable_quest_twice(): void
    {
        $quest = RewardQuest::create([
            'slug'          => 'one-time',
            'title'         => 'One Time Only',
            'description'   => 'Can only be completed once',
            'xp_reward'     => 10,
            'points_reward' => 10,
            'is_repeatable' => false,
            'is_active'     => true,
        ]);

        $this->postJson("/api/v1/rewards/quests/{$quest->id}/complete")->assertOk();

        $response = $this->postJson("/api/v1/rewards/quests/{$quest->id}/complete");
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'QUEST_ERROR');
    }

    public function test_can_complete_repeatable_quest_multiple_times(): void
    {
        $quest = RewardQuest::create([
            'slug'          => 'daily-login',
            'title'         => 'Daily Login',
            'description'   => 'Log in daily',
            'xp_reward'     => 5,
            'points_reward' => 10,
            'is_repeatable' => true,
            'is_active'     => true,
        ]);

        $this->postJson("/api/v1/rewards/quests/{$quest->id}/complete")->assertOk();
        $this->postJson("/api/v1/rewards/quests/{$quest->id}/complete")->assertOk();

        $this->assertDatabaseHas('reward_profiles', [
            'user_id'        => $this->user->id,
            'points_balance' => 20,
        ]);
    }

    public function test_complete_nonexistent_quest_returns_422(): void
    {
        $response = $this->postJson('/api/v1/rewards/quests/nonexistent-id/complete');

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'QUEST_ERROR');
    }

    public function test_get_shop_items(): void
    {
        RewardShopItem::create([
            'slug'        => 'fee-waiver',
            'title'       => 'Fee Waiver',
            'description' => 'Waive one transaction fee',
            'points_cost' => 500,
            'category'    => 'perks',
            'is_active'   => true,
            'sort_order'  => 1,
        ]);

        $response = $this->getJson('/api/v1/rewards/shop');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'fee-waiver')
            ->assertJsonPath('data.0.points_cost', 500);
    }

    public function test_redeem_shop_item(): void
    {
        RewardProfile::create([
            'user_id'        => $this->user->id,
            'xp'             => 0,
            'level'          => 1,
            'points_balance' => 1000,
        ]);

        $item = RewardShopItem::create([
            'slug'        => 'badge',
            'title'       => 'Gold Badge',
            'description' => 'A shiny gold badge',
            'points_cost' => 300,
            'is_active'   => true,
        ]);

        $response = $this->postJson("/api/v1/rewards/shop/{$item->id}/redeem");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.points_spent', 300)
            ->assertJsonPath('data.points_balance', 700);

        $this->assertDatabaseHas('reward_profiles', [
            'user_id'        => $this->user->id,
            'points_balance' => 700,
        ]);
    }

    public function test_redeem_insufficient_points(): void
    {
        RewardProfile::create([
            'user_id'        => $this->user->id,
            'points_balance' => 100,
        ]);

        $item = RewardShopItem::create([
            'slug'        => 'expensive',
            'title'       => 'Expensive Item',
            'description' => 'Costs a lot',
            'points_cost' => 5000,
            'is_active'   => true,
        ]);

        $response = $this->postJson("/api/v1/rewards/shop/{$item->id}/redeem");

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'REDEMPTION_ERROR');
    }

    public function test_redeem_out_of_stock_item(): void
    {
        RewardProfile::create([
            'user_id'        => $this->user->id,
            'points_balance' => 1000,
        ]);

        $item = RewardShopItem::create([
            'slug'        => 'limited',
            'title'       => 'Limited Item',
            'description' => 'Very limited',
            'points_cost' => 100,
            'stock'       => 0,
            'is_active'   => true,
        ]);

        $response = $this->postJson("/api/v1/rewards/shop/{$item->id}/redeem");

        $response->assertStatus(422)
            ->assertJsonPath('error.message', 'Shop item is out of stock.');
    }

    public function test_profile_requires_auth(): void
    {
        // Reset auth
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/rewards/profile');
        $response->assertUnauthorized();
    }
}
