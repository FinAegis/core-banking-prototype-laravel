<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardQuestCompletion;
use App\Models\User;

/**
 * Seeds a RewardProfile (with modest XP) + exactly one completed quest for a
 * review/demo account.
 *
 * Idempotent via firstOrCreate on (user_id) and (reward_profile_id, quest_id).
 * If no RewardQuest exists in the DB, one is created so the seeder is
 * deterministic regardless of surrounding seed order.
 */
class RewardsSeeder
{
    private const DEFAULT_QUEST_SLUG = 'welcome';

    private const DEFAULT_XP = 250;

    public function seed(User $user): void
    {
        /** @var RewardProfile $profile */
        $profile = RewardProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'xp'             => self::DEFAULT_XP,
                'level'          => 3,
                'current_streak' => 1,
                'longest_streak' => 1,
                'points_balance' => 100,
            ]
        );

        $quest = $this->ensureQuest();

        RewardQuestCompletion::firstOrCreate(
            [
                'reward_profile_id' => $profile->id,
                'quest_id'          => $quest->id,
            ],
            [
                'completed_at'  => now(),
                'xp_earned'     => $quest->xp_reward,
                'points_earned' => $quest->points_reward,
            ]
        );
    }

    private function ensureQuest(): RewardQuest
    {
        /** @var RewardQuest|null $existing */
        $existing = RewardQuest::where('slug', self::DEFAULT_QUEST_SLUG)->first();

        if ($existing instanceof RewardQuest) {
            return $existing;
        }

        return RewardQuest::create([
            'slug'          => self::DEFAULT_QUEST_SLUG,
            'title'         => 'Welcome',
            'description'   => 'Complete your review-account onboarding.',
            'xp_reward'     => 50,
            'points_reward' => 100,
            'category'      => 'onboarding',
            'is_repeatable' => false,
            'is_active'     => true,
            'sort_order'    => 0,
            'criteria'      => ['event' => 'account.provisioned'],
        ]);
    }
}
