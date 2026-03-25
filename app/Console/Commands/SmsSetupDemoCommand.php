<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * One-shot command that bootstraps all SMS + mobile rewards demo data.
 *
 * Safe to run multiple times — every seeder uses firstOrCreate.
 *
 * Usage:
 *   php artisan sms:setup-demo
 *   php artisan sms:setup-demo --with-rewards
 */
class SmsSetupDemoCommand extends Command
{
    /** @var string */
    protected $signature = 'sms:setup-demo
                            {--with-rewards : Also seed mobile rewards demo data}';

    /** @var string */
    protected $description = 'Create x402 monetized endpoint, spending limits, and sample SMS messages for demo';

    public function handle(): int
    {
        $this->info('SMS Demo Setup');
        $this->line('==============');

        // 1. Core SMS demo data (endpoint + limits + messages)
        $this->info('Seeding SMS demo data...');
        Artisan::call('db:seed', ['--class' => 'SmsDemoSeeder'], $this->output);

        // 2. Rewards (opt-in, or auto-detected)
        if ($this->option('with-rewards') || $this->rewardsDomainExists()) {
            $this->info('Seeding mobile rewards demo data...');
            Artisan::call('db:seed', ['--class' => 'MobileRewardsDemoSeeder'], $this->output);
        } else {
            $this->warn('Rewards domain not detected — skipping mobile rewards. Pass --with-rewards to force.');
        }

        // 3. Summary
        $this->displaySummary();

        $this->newLine();
        $this->info('SMS demo setup completed!');

        return Command::SUCCESS;
    }

    /**
     * Auto-detect whether the Rewards domain module is present.
     */
    private function rewardsDomainExists(): bool
    {
        return class_exists(\App\Domain\Rewards\Models\RewardQuest::class);
    }

    private function displaySummary(): void
    {
        $this->newLine();
        $this->info('Summary');
        $this->line('-------');

        $endpointCount = \App\Domain\X402\Models\X402MonetizedEndpoint::where('path', 'like', '%sms%')->count();
        $this->line("SMS monetized endpoints: {$endpointCount}");

        $limitCount = \App\Domain\X402\Models\X402SpendingLimit::where('agent_type', 'sms')->count();
        $this->line("SMS agent spending limits: {$limitCount}");

        $msgCount = \App\Domain\SMS\Models\SmsMessage::where('test_mode', true)->count();
        $this->line("Sample SMS messages: {$msgCount}");

        if (class_exists(\App\Domain\Rewards\Models\RewardQuest::class)) {
            $questCount = \App\Domain\Rewards\Models\RewardQuest::where('slug', 'like', '%sms%')->count();
            $this->line("SMS reward quests: {$questCount}");

            $profileCount = \App\Domain\Rewards\Models\RewardProfile::count();
            $this->line("Reward profiles: {$profileCount}");
        }

        $this->newLine();
        $this->info('Test endpoints');
        $this->line('--------------');
        $this->line('GET  /api/v1/sms/info   — service status');
        $this->line('GET  /api/v1/sms/rates  — country pricing');
        $this->line('POST /api/v1/sms/send   — send SMS (x402 gated)');
    }
}
