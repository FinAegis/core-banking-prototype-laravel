<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardQuestCompletion;
use App\Domain\Rewards\Models\RewardRedemption;
use App\Domain\Rewards\Models\RewardShopItem;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds mobile rewards demo data on top of the base RewardsSeeder:
 * - SMS-linked reward quests (send-sms, sms-century)
 * - Reward tier shop items (Bronze -> Platinum)
 * - Demo user reward profiles with XP / points / streaks
 * - Sample quest completions and shop redemptions
 *
 * Depends on: RewardsSeeder (base quests + shop items), DemoDataSeeder (demo users).
 *
 * @see app/Domain/Rewards/ — Gamification domain
 * @see database/seeders/RewardsSeeder.php
 */
class MobileRewardsDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSmsRewardQuests();
        $this->seedRewardTierItems();
        $this->seedDemoProfiles();
    }

    /**
     * Add SMS-specific quests that link rewards to SMS activity.
     */
    private function seedSmsRewardQuests(): void
    {
        $quests = [
            [
                'slug'          => 'send-sms',
                'title'         => 'Send Your First SMS',
                'description'   => 'Send an SMS via the VertexSMS integration',
                'xp_reward'     => 25,
                'points_reward' => 50,
                'category'      => 'onboarding',
                'icon'          => 'chatbubble',
                'is_repeatable' => false,
                'is_active'     => true,
                'sort_order'    => 7,
                'criteria'      => ['event' => 'sms.sent', 'count' => 1],
            ],
            [
                'slug'          => 'sms-century',
                'title'         => 'SMS Century',
                'description'   => 'Send 100 SMS messages — earn 50 bonus credits',
                'xp_reward'     => 200,
                'points_reward' => 500,
                'category'      => 'milestone',
                'icon'          => 'trophy',
                'is_repeatable' => false,
                'is_active'     => true,
                'sort_order'    => 8,
                'criteria'      => ['event' => 'sms.sent', 'count' => 100],
            ],
            [
                'slug'          => 'daily-sms',
                'title'         => 'Daily SMS',
                'description'   => 'Send at least one SMS today',
                'xp_reward'     => 10,
                'points_reward' => 20,
                'category'      => 'daily',
                'icon'          => 'chatbubble',
                'is_repeatable' => true,
                'is_active'     => true,
                'sort_order'    => 9,
                'criteria'      => ['event' => 'sms.sent', 'count' => 1, 'period' => 'daily'],
            ],
        ];

        foreach ($quests as $quest) {
            RewardQuest::firstOrCreate(
                ['slug' => $quest['slug']],
                $quest,
            );
        }
    }

    /**
     * Create tiered reward shop items (Bronze through Platinum).
     *
     * These represent loyalty tiers that users unlock by accumulating points.
     */
    private function seedRewardTierItems(): void
    {
        $tiers = [
            [
                'slug'        => 'tier-bronze',
                'title'       => 'Bronze Tier',
                'description' => 'Unlock 5 % fee discount on all SMS sends',
                'points_cost' => 250,
                'category'    => 'tiers',
                'icon'        => 'shield',
                'stock'       => null,
                'is_active'   => true,
                'sort_order'  => 10,
                'metadata'    => ['tier_level' => 1, 'fee_discount_pct' => 5],
            ],
            [
                'slug'        => 'tier-silver',
                'title'       => 'Silver Tier',
                'description' => 'Unlock 10 % fee discount + priority delivery',
                'points_cost' => 750,
                'category'    => 'tiers',
                'icon'        => 'shield',
                'stock'       => null,
                'is_active'   => true,
                'sort_order'  => 11,
                'metadata'    => ['tier_level' => 2, 'fee_discount_pct' => 10, 'priority_delivery' => true],
            ],
            [
                'slug'        => 'tier-gold',
                'title'       => 'Gold Tier',
                'description' => 'Unlock 20 % fee discount + priority + dedicated support',
                'points_cost' => 2000,
                'category'    => 'tiers',
                'icon'        => 'star',
                'stock'       => null,
                'is_active'   => true,
                'sort_order'  => 12,
                'metadata'    => ['tier_level' => 3, 'fee_discount_pct' => 20, 'priority_delivery' => true, 'dedicated_support' => true],
            ],
            [
                'slug'        => 'tier-platinum',
                'title'       => 'Platinum Tier',
                'description' => 'Unlock 30 % fee discount + all perks + early access',
                'points_cost' => 5000,
                'category'    => 'tiers',
                'icon'        => 'diamond',
                'stock'       => null,
                'is_active'   => true,
                'sort_order'  => 13,
                'metadata'    => ['tier_level' => 4, 'fee_discount_pct' => 30, 'priority_delivery' => true, 'dedicated_support' => true, 'early_access' => true],
            ],
        ];

        foreach ($tiers as $tier) {
            RewardShopItem::firstOrCreate(
                ['slug' => $tier['slug']],
                $tier,
            );
        }
    }

    /**
     * Create reward profiles for existing demo users so the
     * mobile rewards screen displays meaningful data immediately.
     */
    private function seedDemoProfiles(): void
    {
        $profiles = [
            // Power user — high XP, long streak, multiple completions
            'demo.nomad@gcu.global' => [
                'xp'                 => 475,
                'level'              => 5,
                'current_streak'     => 12,
                'longest_streak'     => 18,
                'last_activity_date' => now()->subDay(),
                'points_balance'     => 1350,
                'completions'        => ['first-payment', 'first-shield', 'complete-profile', 'first-card', 'send-sms'],
                'redemptions'        => ['fee-waiver'],
            ],
            // New user — just started
            'demo.user@gcu.global' => [
                'xp'                 => 55,
                'level'              => 1,
                'current_streak'     => 2,
                'longest_streak'     => 2,
                'last_activity_date' => now(),
                'points_balance'     => 110,
                'completions'        => ['first-payment'],
                'redemptions'        => [],
            ],
            // Business user — moderate activity
            'demo.business@gcu.global' => [
                'xp'                 => 260,
                'level'              => 3,
                'current_streak'     => 5,
                'longest_streak'     => 9,
                'last_activity_date' => now()->subDays(2),
                'points_balance'     => 820,
                'completions'        => ['first-payment', 'first-card', 'send-sms', 'complete-profile'],
                'redemptions'        => ['priority-processing'],
            ],
            // Investor — redeemed tier item
            'demo.investor@gcu.global' => [
                'xp'                 => 380,
                'level'              => 4,
                'current_streak'     => 0,
                'longest_streak'     => 14,
                'last_activity_date' => now()->subWeek(),
                'points_balance'     => 650,
                'completions'        => ['first-payment', 'first-shield', 'first-card', 'complete-profile'],
                'redemptions'        => ['tier-bronze', 'custom-badge'],
            ],
        ];

        foreach ($profiles as $email => $data) {
            $user = User::where('email', $email)->first();
            if ($user === null) {
                continue;
            }

            $profile = RewardProfile::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'xp'                 => $data['xp'],
                    'level'              => $data['level'],
                    'current_streak'     => $data['current_streak'],
                    'longest_streak'     => $data['longest_streak'],
                    'last_activity_date' => $data['last_activity_date'],
                    'points_balance'     => $data['points_balance'],
                ],
            );

            // Seed quest completions
            foreach ($data['completions'] as $slug) {
                $quest = RewardQuest::where('slug', $slug)->first();
                if ($quest === null) {
                    continue;
                }

                RewardQuestCompletion::firstOrCreate(
                    [
                        'reward_profile_id' => $profile->id,
                        'quest_id'          => $quest->id,
                    ],
                    [
                        'completed_at'  => now()->subDays(rand(1, 30)),
                        'xp_earned'     => $quest->xp_reward,
                        'points_earned' => $quest->points_reward,
                    ],
                );
            }

            // Seed shop redemptions
            foreach ($data['redemptions'] as $slug) {
                $item = RewardShopItem::where('slug', $slug)->first();
                if ($item === null) {
                    continue;
                }

                RewardRedemption::firstOrCreate(
                    [
                        'reward_profile_id' => $profile->id,
                        'shop_item_id'      => $item->id,
                    ],
                    [
                        'points_spent' => $item->points_cost,
                        'status'       => 'fulfilled',
                        'metadata'     => ['source' => 'demo-seeder'],
                    ],
                );
            }
        }
    }
}
