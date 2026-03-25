<?php

declare(strict_types=1);

use App\Domain\SMS\Models\SmsMessage;
use App\Domain\X402\Models\X402MonetizedEndpoint;
use App\Domain\X402\Models\X402SpendingLimit;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed();
    (new DemoDataSeeder())->run();
});

it('seeds SMS demo data via artisan command', function () {
    $this->artisan('sms:setup-demo')
        ->assertSuccessful();

    expect(X402MonetizedEndpoint::where('path', '/api/v1/sms/send')->exists())->toBeTrue();
    expect(X402SpendingLimit::where('agent_id', 'demo-sms-agent')->exists())->toBeTrue();
    expect(SmsMessage::where('provider_id', 'like', 'demo-vtx-%')->count())->toBe(7);
});

it('seeds rewards when --with-rewards flag is passed', function () {
    $this->artisan('sms:setup-demo', ['--with-rewards' => true])
        ->assertSuccessful();

    expect(App\Domain\Rewards\Models\RewardQuest::where('slug', 'send-sms')->exists())->toBeTrue();
    expect(App\Domain\Rewards\Models\RewardShopItem::where('category', 'tiers')->count())->toBe(4);
});

it('auto-detects rewards domain and seeds profiles', function () {
    $this->artisan('sms:setup-demo')
        ->assertSuccessful();

    // Rewards domain exists in this project, so it should auto-seed
    expect(App\Domain\Rewards\Models\RewardQuest::where('slug', 'send-sms')->exists())->toBeTrue();
});

it('is safe to run multiple times', function () {
    $this->artisan('sms:setup-demo')->assertSuccessful();
    $this->artisan('sms:setup-demo')->assertSuccessful();

    expect(X402MonetizedEndpoint::where('path', '/api/v1/sms/send')->count())->toBe(1);
    expect(X402SpendingLimit::where('agent_id', 'demo-sms-agent')->count())->toBe(1);
});
