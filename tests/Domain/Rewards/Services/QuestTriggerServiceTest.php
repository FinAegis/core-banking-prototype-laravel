<?php

declare(strict_types=1);

use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Services\QuestTriggerService;
use App\Domain\Rewards\Services\RewardsService;
use App\Models\User;

describe('QuestTriggerService', function (): void {
    beforeEach(function (): void {
        $this->user = User::factory()->create();
        $this->service = app(QuestTriggerService::class);
    });

    it('triggers a quest by slug', function (): void {
        $quest = RewardQuest::create([
            'slug'          => 'test-trigger',
            'title'         => 'Test Trigger',
            'description'   => 'A test quest',
            'xp_reward'     => 10,
            'points_reward' => 20,
            'category'      => 'test',
            'is_repeatable' => false,
            'is_active'     => true,
        ]);

        $this->service->trigger($this->user, 'test-trigger');

        $profile = app(RewardsService::class)->getProfile($this->user);
        expect($profile->xp)->toBe(10);
        expect($profile->points_balance)->toBe(20);
    });

    it('silently ignores non-existent quest slugs', function (): void {
        $this->service->trigger($this->user, 'non-existent-quest');

        $profile = app(RewardsService::class)->getProfile($this->user);
        expect($profile->xp)->toBe(0);
    });

    it('silently ignores already completed non-repeatable quests', function (): void {
        $quest = RewardQuest::create([
            'slug'          => 'one-time',
            'title'         => 'One Time',
            'description'   => 'Only once',
            'xp_reward'     => 50,
            'points_reward' => 100,
            'category'      => 'test',
            'is_repeatable' => false,
            'is_active'     => true,
        ]);

        $this->service->trigger($this->user, 'one-time');
        $this->service->trigger($this->user, 'one-time'); // Should not throw

        $profile = app(RewardsService::class)->getProfile($this->user);
        expect($profile->xp)->toBe(50); // Only awarded once
    });

    it('triggers multiple quests at once', function (): void {
        RewardQuest::create([
            'slug'          => 'multi-a', 'title' => 'A', 'description' => 'A',
            'xp_reward'     => 10, 'points_reward' => 10, 'category' => 'test',
            'is_repeatable' => false, 'is_active' => true,
        ]);
        RewardQuest::create([
            'slug'          => 'multi-b', 'title' => 'B', 'description' => 'B',
            'xp_reward'     => 20, 'points_reward' => 20, 'category' => 'test',
            'is_repeatable' => false, 'is_active' => true,
        ]);

        $this->service->triggerMultiple($this->user, ['multi-a', 'multi-b', 'non-existent']);

        $profile = app(RewardsService::class)->getProfile($this->user);
        expect($profile->xp)->toBe(30);
        expect($profile->points_balance)->toBe(30);
    });

    it('skips inactive quests', function (): void {
        RewardQuest::create([
            'slug'          => 'inactive-quest', 'title' => 'Inactive', 'description' => 'Off',
            'xp_reward'     => 100, 'points_reward' => 100, 'category' => 'test',
            'is_repeatable' => false, 'is_active' => false,
        ]);

        $this->service->trigger($this->user, 'inactive-quest');

        $profile = app(RewardsService::class)->getProfile($this->user);
        expect($profile->xp)->toBe(0);
    });
});
