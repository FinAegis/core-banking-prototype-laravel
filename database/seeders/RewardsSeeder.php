<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardShopItem;
use Illuminate\Database\Seeder;

/**
 * Seeds reward quests and shop items for the mobile rewards screen.
 *
 * Required by mobile app v1.2.0+ (PR #265).
 *
 * @see docs/BACKEND_PRODUCTION_HANDOVER.md (finaegis-mobile)
 */
class RewardsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedQuests();
        $this->seedShopItems();
    }

    private function seedQuests(): void
    {
        $quests = [
            [
                'slug'          => 'first-payment',
                'title'         => 'Make Your First Payment',
                'description'   => 'Send tokens to any address',
                'xp_reward'     => 50,
                'points_reward' => 100,
                'category'      => 'onboarding',
                'icon'          => 'flash',
                'is_repeatable' => false,
                'is_active'     => true,
                'sort_order'    => 1,
            ],
            [
                'slug'          => 'first-shield',
                'title'         => 'Shield a Transaction',
                'description'   => 'Shield tokens for privacy',
                'xp_reward'     => 75,
                'points_reward' => 150,
                'category'      => 'onboarding',
                'icon'          => 'shield',
                'is_repeatable' => false,
                'is_active'     => true,
                'sort_order'    => 2,
            ],
            [
                'slug'          => 'complete-profile',
                'title'         => 'Complete Your Profile',
                'description'   => 'Fill in all profile fields',
                'xp_reward'     => 100,
                'points_reward' => 200,
                'category'      => 'onboarding',
                'icon'          => 'person',
                'is_repeatable' => false,
                'is_active'     => true,
                'sort_order'    => 3,
            ],
            [
                'slug'          => 'first-card',
                'title'         => 'Create a Virtual Card',
                'description'   => 'Issue your first virtual card',
                'xp_reward'     => 50,
                'points_reward' => 100,
                'category'      => 'onboarding',
                'icon'          => 'card',
                'is_repeatable' => false,
                'is_active'     => true,
                'sort_order'    => 4,
            ],
            [
                'slug'          => 'daily-login',
                'title'         => 'Daily Login',
                'description'   => 'Log in to Zelta',
                'xp_reward'     => 5,
                'points_reward' => 10,
                'category'      => 'daily',
                'icon'          => 'calendar',
                'is_repeatable' => true,
                'is_active'     => true,
                'sort_order'    => 5,
            ],
            [
                'slug'          => 'daily-transaction',
                'title'         => 'Daily Transaction',
                'description'   => 'Make any transaction today',
                'xp_reward'     => 10,
                'points_reward' => 20,
                'category'      => 'daily',
                'icon'          => 'flash',
                'is_repeatable' => true,
                'is_active'     => true,
                'sort_order'    => 6,
            ],
        ];

        foreach ($quests as $quest) {
            RewardQuest::firstOrCreate(
                ['slug' => $quest['slug']],
                $quest,
            );
        }
    }

    private function seedShopItems(): void
    {
        $items = [
            [
                'slug'        => 'fee-waiver',
                'title'       => 'Fee-Free Transfer',
                'description' => 'Waive one transaction fee',
                'points_cost' => 500,
                'category'    => 'perks',
                'icon'        => 'flash',
                'stock'       => null,
                'is_active'   => true,
                'sort_order'  => 1,
            ],
            [
                'slug'        => 'priority-processing',
                'title'       => 'Priority Processing',
                'description' => 'Faster confirmation times',
                'points_cost' => 750,
                'category'    => 'perks',
                'icon'        => 'rocket',
                'stock'       => null,
                'is_active'   => true,
                'sort_order'  => 2,
            ],
            [
                'slug'        => 'custom-badge',
                'title'       => 'Custom Badge',
                'description' => 'Exclusive profile badge',
                'points_cost' => 1000,
                'category'    => 'badges',
                'icon'        => 'star',
                'stock'       => 50,
                'is_active'   => true,
                'sort_order'  => 3,
            ],
        ];

        foreach ($items as $item) {
            RewardShopItem::firstOrCreate(
                ['slug' => $item['slug']],
                $item,
            );
        }
    }
}
