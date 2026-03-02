<?php

declare(strict_types=1);

namespace App\Domain\Rewards\Services;

use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardQuestCompletion;
use App\Domain\Rewards\Models\RewardRedemption;
use App\Domain\Rewards\Models\RewardShopItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RewardsService
{
    private const MAX_LEVEL = 999;

    /**
     * Get or create the user's rewards profile.
     */
    public function getProfile(User $user): RewardProfile
    {
        return RewardProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'xp'             => 0,
                'level'          => 1,
                'current_streak' => 0,
                'longest_streak' => 0,
                'points_balance' => 0,
            ],
        );
    }

    /**
     * Get the profile as API response data.
     *
     * Streak is computed for display only — not persisted on read.
     *
     * @return array<string, mixed>
     */
    public function getProfileData(User $user): array
    {
        $profile = $this->getProfile($user);
        $displayStreak = $this->computeDisplayStreak($profile);

        return [
            'xp'               => $profile->xp,
            'level'            => $profile->level,
            'xp_for_next'      => $profile->xpForNextLevel(),
            'xp_progress'      => round($profile->xpProgress(), 2),
            'current_streak'   => $displayStreak['current'],
            'longest_streak'   => $displayStreak['longest'],
            'points_balance'   => $profile->points_balance,
            'quests_completed' => $profile->questCompletions()->count(),
        ];
    }

    /**
     * Get active quests with user's completion status.
     *
     * @return Collection<int, mixed>
     */
    public function getQuests(User $user): Collection
    {
        $profile = $this->getProfile($user);
        $completedQuestIds = $profile->questCompletions()
            ->pluck('quest_id')
            ->toArray();

        return RewardQuest::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RewardQuest $quest) => [
                'id'            => $quest->id,
                'slug'          => $quest->slug,
                'title'         => $quest->title,
                'description'   => $quest->description,
                'xp_reward'     => $quest->xp_reward,
                'points_reward' => $quest->points_reward,
                'category'      => $quest->category,
                'icon'          => $quest->icon,
                'is_repeatable' => $quest->is_repeatable,
                'completed'     => in_array($quest->id, $completedQuestIds, true),
            ]);
    }

    /**
     * Complete a quest for the user.
     *
     * All checks and mutations happen inside a serialized transaction
     * with pessimistic locking to prevent race conditions.
     *
     * @return array<string, mixed>
     */
    public function completeQuest(User $user, string $questId): array
    {
        $quest = RewardQuest::where('id', $questId)
            ->where('is_active', true)
            ->first();

        if (! $quest) {
            throw new RuntimeException('Quest not found or inactive.');
        }

        return DB::transaction(function () use ($user, $quest) {
            // Lock the profile row to serialize concurrent completions
            $profile = RewardProfile::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                $profile = $this->getProfile($user);
                // Re-acquire with lock
                $profile = RewardProfile::where('id', $profile->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            // Duplicate check INSIDE the transaction, after acquiring lock
            if (! $quest->is_repeatable) {
                $alreadyCompleted = RewardQuestCompletion::where('reward_profile_id', $profile->id)
                    ->where('quest_id', $quest->id)
                    ->exists();

                if ($alreadyCompleted) {
                    throw new RuntimeException('Quest already completed.');
                }
            }

            $completion = RewardQuestCompletion::create([
                'reward_profile_id' => $profile->id,
                'quest_id'          => $quest->id,
                'completed_at'      => now(),
                'xp_earned'         => $quest->xp_reward,
                'points_earned'     => $quest->points_reward,
            ]);

            $profile->xp += $quest->xp_reward;
            $profile->points_balance += $quest->points_reward;

            // Level up check with cap
            while ($profile->xp >= $profile->xpForNextLevel() && $profile->level < self::MAX_LEVEL) {
                $profile->xp -= $profile->xpForNextLevel();
                $profile->level++;
            }

            // Update streak (persisted since this is a real activity)
            $this->updateStreak($profile);

            $profile->save();

            return [
                'quest_id'      => $quest->id,
                'xp_earned'     => $quest->xp_reward,
                'points_earned' => $quest->points_reward,
                'new_xp'        => $profile->xp,
                'new_total_xp'  => $profile->xp,
                'new_level'     => $profile->level,
                'new_points'    => $profile->points_balance,
                'level_up'      => $profile->wasChanged('level'),
                'completed_at'  => $completion->completed_at->toIso8601String(),
            ];
        });
    }

    /**
     * Get active shop items.
     *
     * @return Collection<int, mixed>
     */
    public function getShopItems(): Collection
    {
        return RewardShopItem::where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (RewardShopItem $item) => [
                'id'          => $item->id,
                'slug'        => $item->slug,
                'title'       => $item->title,
                'description' => $item->description,
                'points_cost' => $item->points_cost,
                'category'    => $item->category,
                'icon'        => $item->icon,
                'available'   => $item->isAvailable(),
                'stock'       => $item->stock,
            ]);
    }

    /**
     * Redeem a shop item.
     *
     * All checks (balance, stock, availability) happen inside a serialized
     * transaction with pessimistic locking to prevent double-spend.
     *
     * The shop uses **points** (not XP). Points are earned from quests and
     * spent in the shop. XP is separate and determines the user's level.
     *
     * @return array<string, mixed>
     */
    public function redeemItem(User $user, string $itemId): array
    {
        return DB::transaction(function () use ($user, $itemId) {
            // Lock profile to prevent concurrent balance mutations
            $profile = RewardProfile::where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                $profile = $this->getProfile($user);
                $profile = RewardProfile::where('id', $profile->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            // Lock shop item to prevent stock race condition
            $item = RewardShopItem::where('id', $itemId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $item) {
                throw new RuntimeException('Shop item not found or unavailable.');
            }

            if (! $item->isAvailable()) {
                throw new RuntimeException('Shop item is out of stock.');
            }

            if ($profile->points_balance < $item->points_cost) {
                throw new RuntimeException('Insufficient points balance.');
            }

            $profile->points_balance -= $item->points_cost;
            $profile->save();

            if ($item->stock !== null) {
                $item->decrement('stock');
            }

            $redemption = RewardRedemption::create([
                'reward_profile_id' => $profile->id,
                'shop_item_id'      => $item->id,
                'points_spent'      => $item->points_cost,
                'status'            => 'completed',
            ]);

            return [
                'redemption_id'  => $redemption->id,
                'item_id'        => $item->id,
                'item_title'     => $item->title,
                'points_spent'   => $item->points_cost,
                'points_balance' => $profile->points_balance,
                'new_total_xp'   => $profile->xp,
                'status'         => 'completed',
                'redeemed_at'    => $redemption->created_at?->toIso8601String(),
            ];
        });
    }

    /**
     * Compute display streak without persisting.
     *
     * Used by getProfileData to show current streak state without
     * mutating on read.
     *
     * @return array{current: int, longest: int}
     */
    private function computeDisplayStreak(RewardProfile $profile): array
    {
        $current = $profile->current_streak;
        $longest = $profile->longest_streak;
        $lastActivity = $profile->last_activity_date;

        if ($lastActivity === null) {
            return ['current' => $current, 'longest' => $longest];
        }

        $daysSince = (int) $lastActivity->diffInDays(Carbon::today());

        if ($daysSince > 1) {
            // Streak is broken but don't persist the reset on a read
            $current = 0;
        }

        return ['current' => $current, 'longest' => $longest];
    }

    /**
     * Update and persist the streak based on last activity date.
     *
     * Only called during actual activity (quest completion), never on reads.
     */
    private function updateStreak(RewardProfile $profile): void
    {
        $today = Carbon::today();
        $lastActivity = $profile->last_activity_date;

        if ($lastActivity === null) {
            // First activity ever — start the streak
            $profile->current_streak = 1;
            $profile->longest_streak = max($profile->longest_streak, 1);
            $profile->last_activity_date = $today;

            return;
        }

        $daysSince = (int) $lastActivity->diffInDays($today);

        if ($daysSince === 0) {
            // Same day — streak unchanged
            return;
        }

        if ($daysSince === 1) {
            // Consecutive day — extend streak
            $profile->current_streak++;
            if ($profile->current_streak > $profile->longest_streak) {
                $profile->longest_streak = $profile->current_streak;
            }
        } else {
            // Streak broken, restart at 1 (since this IS an activity)
            $profile->current_streak = 1;
        }

        $profile->last_activity_date = $today;
    }
}
