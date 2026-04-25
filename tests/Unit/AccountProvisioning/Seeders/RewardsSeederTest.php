<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Seeders\RewardsSeeder;
use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardQuestCompletion;
use App\Models\User;

uses(Tests\TestCase::class, Illuminate\Foundation\Testing\RefreshDatabase::class);

it('creates a reward profile with XP and exactly one quest completion', function (): void {
    $user = User::factory()->create();

    app(RewardsSeeder::class)->seed($user);

    $profile = RewardProfile::where('user_id', $user->id)->first();
    expect($profile)->not->toBeNull();
    /** @var RewardProfile $profile */
    expect($profile->xp)->toBeGreaterThan(0);

    expect(RewardQuestCompletion::where('reward_profile_id', $profile->id)->count())->toBe(1);
});

it('keys completion to the welcome slug even when other active quests exist', function (): void {
    // Pre-seed an unrelated active quest with sort_order=0 — without
    // determinism this would win the lookup over a missing 'welcome' quest.
    RewardQuest::create([
        'slug'          => 'unrelated-active',
        'title'         => 'Unrelated',
        'description'   => 'Should not be selected.',
        'xp_reward'     => 999,
        'points_reward' => 999,
        'category'      => 'misc',
        'is_repeatable' => false,
        'is_active'     => true,
        'sort_order'    => 0,
        'criteria'      => ['event' => 'misc'],
    ]);

    $user = User::factory()->create();
    app(RewardsSeeder::class)->seed($user);

    $profile = RewardProfile::where('user_id', $user->id)->firstOrFail();
    $completion = RewardQuestCompletion::where('reward_profile_id', $profile->id)->firstOrFail();
    $quest = RewardQuest::where('id', $completion->quest_id)->firstOrFail();

    expect($quest->slug)->toBe('welcome');
});

it('is idempotent when seeded twice for the same user', function (): void {
    $user = User::factory()->create();

    app(RewardsSeeder::class)->seed($user);
    app(RewardsSeeder::class)->seed($user);

    expect(RewardProfile::where('user_id', $user->id)->count())->toBe(1);

    $profile = RewardProfile::where('user_id', $user->id)->first();
    /** @var RewardProfile $profile */
    expect(RewardQuestCompletion::where('reward_profile_id', $profile->id)->count())->toBe(1);
});
