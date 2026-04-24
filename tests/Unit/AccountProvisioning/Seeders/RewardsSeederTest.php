<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Seeders\RewardsSeeder;
use App\Domain\Rewards\Models\RewardProfile;
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

it('is idempotent when seeded twice for the same user', function (): void {
    $user = User::factory()->create();

    app(RewardsSeeder::class)->seed($user);
    app(RewardsSeeder::class)->seed($user);

    expect(RewardProfile::where('user_id', $user->id)->count())->toBe(1);

    $profile = RewardProfile::where('user_id', $user->id)->first();
    /** @var RewardProfile $profile */
    expect(RewardQuestCompletion::where('reward_profile_id', $profile->id)->count())->toBe(1);
});
