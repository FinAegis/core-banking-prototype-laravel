<?php

declare(strict_types=1);

use App\Domain\Wallet\Models\WalletSendRecord;
use App\Domain\Wallet\Services\SponsorshipCostTracker;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
});

it('runs with no recorded spend and reports an unconfigured budget', function (): void {
    config(['wallet.sponsorship.daily_budget_usd' => null]);

    $this->artisan('wallet:sponsorship-spend')
        ->expectsOutputToContain('No confirmed sends with a recorded sponsored fee today.')
        ->expectsOutputToContain('Daily USD budget: not configured')
        ->assertExitCode(0);
});

it('aggregates today\'s confirmed sponsored fees per network', function (): void {
    $user = User::factory()->create();

    foreach ([['solana', '5000', 'SOL'], ['solana', '7000', 'SOL'], ['polygon', '200000000000', 'MATIC']] as $i => [$network, $fee, $asset]) {
        WalletSendRecord::create([
            'public_id'           => 'pi_send_spendcmd_' . $i,
            'user_id'             => $user->id,
            'network'             => $network,
            'asset'               => 'USDC',
            'amount'              => '1.00000000',
            'sender_address'      => 'sender-address-x',
            'recipient_address'   => 'recipient-address-x',
            'status'              => 'confirmed',
            'confirmed_at'        => now(),
            'sponsored_fee_raw'   => $fee,
            'sponsored_fee_asset' => $asset,
        ]);
    }

    // A failed send and an unsponsored confirmed send must not be counted.
    WalletSendRecord::create([
        'public_id'         => 'pi_send_spendcmd_skip',
        'user_id'           => $user->id,
        'network'           => 'solana',
        'asset'             => 'USDC',
        'amount'            => '1.00000000',
        'sender_address'    => 'sender-address-x',
        'recipient_address' => 'recipient-address-x',
        'status'            => 'confirmed',
        'confirmed_at'      => now(),
    ]);

    $this->artisan('wallet:sponsorship-spend')
        ->expectsOutputToContain('12000') // 5000 + 7000 lamports
        ->expectsOutputToContain('200000000000')
        ->assertExitCode(0);
});

it('outputs machine-readable JSON with --json', function (): void {
    config(['wallet.sponsorship.daily_budget_usd' => '25']);

    $key = SponsorshipCostTracker::budgetCacheKey();
    Cache::add($key, 0, now()->addDay());
    Cache::increment($key, 1_500_000); // $1.50 spent

    $exitCode = Illuminate\Support\Facades\Artisan::call('wallet:sponsorship-spend', ['--json' => true]);
    expect($exitCode)->toBe(0);

    $output = Illuminate\Support\Facades\Artisan::output();
    $decoded = json_decode($output, true);

    expect($decoded)->toBeArray()
        ->and($decoded['budget_spent_usd'])->toBe('1.500000')
        ->and($decoded['budget_configured_usd'])->toBe('25.000000')
        ->and($decoded['budget_exhausted'])->toBeFalse()
        ->and($decoded['networks'])->toBeArray();
});
