<?php

declare(strict_types=1);

use App\Domain\Rewards\Models\RewardProfile;
use App\Domain\Rewards\Models\RewardQuest;
use App\Domain\Rewards\Models\RewardQuestCompletion;
use App\Domain\Rewards\Models\RewardRedemption;
use App\Domain\Rewards\Models\RewardShopItem;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\MobileRewardsDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Base seeders (includes RewardsSeeder for quests + shop items)
    $this->seed();

    // Demo users are needed for reward profiles
    (new DemoDataSeeder())->run();
});

it('creates SMS-specific reward quests', function () {
    (new MobileRewardsDemoSeeder())->run();

    /** @var RewardQuest $sendSms */
    $sendSms = RewardQuest::where('slug', 'send-sms')->firstOrFail();
    expect($sendSms->xp_reward)->toBe(25);
    expect($sendSms->points_reward)->toBe(50);
    expect($sendSms->category)->toBe('onboarding');

    /** @var RewardQuest $smsCentury */
    $smsCentury = RewardQuest::where('slug', 'sms-century')->firstOrFail();
    expect($smsCentury->points_reward)->toBe(500);
    expect($smsCentury->category)->toBe('milestone');
    expect($smsCentury->criteria)->toBe(['event' => 'sms.sent', 'count' => 100]);

    /** @var RewardQuest $dailySms */
    $dailySms = RewardQuest::where('slug', 'daily-sms')->firstOrFail();
    expect($dailySms->is_repeatable)->toBeTrue();
});

it('creates reward tier shop items from Bronze to Platinum', function () {
    (new MobileRewardsDemoSeeder())->run();

    $tiers = RewardShopItem::where('category', 'tiers')
        ->orderBy('sort_order')
        ->get();

    expect($tiers)->toHaveCount(4);
    expect($tiers->pluck('slug')->toArray())->toBe([
        'tier-bronze',
        'tier-silver',
        'tier-gold',
        'tier-platinum',
    ]);

    // Verify ascending cost
    $costs = $tiers->pluck('points_cost')->toArray();
    expect($costs)->toBe([250, 750, 2000, 5000]);

    // Verify metadata on Gold tier
    /** @var RewardShopItem $gold */
    $gold = $tiers->firstWhere('slug', 'tier-gold');
    $goldMeta = $gold->metadata ?? [];
    expect($goldMeta['fee_discount_pct'])->toBe(20);
    expect($goldMeta['dedicated_support'])->toBeTrue();
});

it('creates reward profiles for demo users', function () {
    (new MobileRewardsDemoSeeder())->run();

    // Power user
    /** @var User $nomad */
    $nomad = User::where('email', 'demo.nomad@gcu.global')->firstOrFail();
    /** @var RewardProfile $nomadProfile */
    $nomadProfile = RewardProfile::where('user_id', $nomad->id)->firstOrFail();
    expect($nomadProfile->level)->toBe(5);
    expect($nomadProfile->points_balance)->toBe(1350);
    expect($nomadProfile->current_streak)->toBe(12);

    // New user
    /** @var User $regular */
    $regular = User::where('email', 'demo.user@gcu.global')->firstOrFail();
    /** @var RewardProfile $regularProfile */
    $regularProfile = RewardProfile::where('user_id', $regular->id)->firstOrFail();
    expect($regularProfile->level)->toBe(1);
    expect($regularProfile->points_balance)->toBe(110);
});

it('creates quest completions for demo profiles', function () {
    (new MobileRewardsDemoSeeder())->run();

    /** @var User $nomad */
    $nomad = User::where('email', 'demo.nomad@gcu.global')->firstOrFail();
    /** @var RewardProfile $nomadProfile */
    $nomadProfile = RewardProfile::where('user_id', $nomad->id)->firstOrFail();

    $completions = RewardQuestCompletion::where('reward_profile_id', $nomadProfile->id)->get();
    expect($completions->count())->toBe(5);

    // Check one specific completion
    /** @var RewardQuest $sendSmsQuest */
    $sendSmsQuest = RewardQuest::where('slug', 'send-sms')->firstOrFail();
    $smsCompletion = $completions->firstWhere('quest_id', $sendSmsQuest->id);
    expect($smsCompletion)->not->toBeNull();
    expect($smsCompletion->xp_earned)->toBe(25);
    expect($smsCompletion->points_earned)->toBe(50);
});

it('creates shop redemptions for demo profiles', function () {
    (new MobileRewardsDemoSeeder())->run();

    /** @var User $investor */
    $investor = User::where('email', 'demo.investor@gcu.global')->firstOrFail();
    /** @var RewardProfile $investorProfile */
    $investorProfile = RewardProfile::where('user_id', $investor->id)->firstOrFail();

    $redemptions = RewardRedemption::where('reward_profile_id', $investorProfile->id)->get();
    expect($redemptions->count())->toBe(2);

    $tierRedemption = $redemptions->first(function ($r) {
        /** @var RewardRedemption $r */
        $item = RewardShopItem::find($r->shop_item_id);

        return $item !== null && $item->slug === 'tier-bronze';
    });
    expect($tierRedemption)->not->toBeNull();
    expect($tierRedemption->status)->toBe('fulfilled');
});

it('is idempotent on repeated runs', function () {
    $seeder = new MobileRewardsDemoSeeder();
    $seeder->run();
    $seeder->run();

    $smsQuests = RewardQuest::where('slug', 'like', '%sms%')->count();
    expect($smsQuests)->toBe(3);

    $tierItems = RewardShopItem::where('category', 'tiers')->count();
    expect($tierItems)->toBe(4);

    /** @var User $nomad */
    $nomad = User::where('email', 'demo.nomad@gcu.global')->firstOrFail();
    $profileCount = RewardProfile::where('user_id', $nomad->id)->count();
    expect($profileCount)->toBe(1);
});
