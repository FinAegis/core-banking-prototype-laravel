<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Banking\Models\UserBankPreference;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Seed the database with base data
    $this->seed();
});

it('can populate demo data', function () {
    $this->artisan('demo:populate')
        ->expectsOutput('🌍 GCU Platform Demo Data Population')
        ->expectsOutput('Running standard seeders...')
        ->expectsOutput('Creating demo users and data...')
        ->expectsOutput('✅ Demo data population completed!')
        ->assertSuccessful();

    // Verify demo users were created
    expect(User::where('email', 'like', 'demo.%')->count())->toBe(5);
    expect(User::where('email', 'demo.argentina@gcu.global')->exists())->toBeTrue();
    expect(User::where('email', 'demo.nomad@gcu.global')->exists())->toBeTrue();
    expect(User::where('email', 'demo.business@gcu.global')->exists())->toBeTrue();
    expect(User::where('email', 'demo.investor@gcu.global')->exists())->toBeTrue();
    expect(User::where('email', 'demo.user@gcu.global')->exists())->toBeTrue();
});

// Skip fresh database tests in SQLite environment
// These tests would work in MySQL/PostgreSQL but SQLite has issues with VACUUM in transactions

it('creates admin user when requested', function () {
    $this->artisan('demo:populate --with-admin')
        ->expectsOutput('Creating admin user...')
        ->assertSuccessful();

    // Note: We can't easily test Filament user creation in tests
    // but we can verify the command runs without errors
});

it('creates demo users with correct attributes', function () {
    Artisan::call('demo:populate');

    // Test Argentina user
    $argentina = User::where('email', 'demo.argentina@gcu.global')->first();
    expect($argentina)->not->toBeNull();
    expect($argentina->name)->toBe('Sofia Martinez');
    expect($argentina->email_verified_at)->not->toBeNull();

    // Test user has account
    expect($argentina->accounts()->count())->toBe(1);

    // Test user has bank preferences
    $banks = UserBankPreference::where('user_uuid', $argentina->uuid)->get();
    expect($banks)->toHaveCount(3);
    expect($banks->sum('allocation_percentage'))->toBe(100.0);
});

it('creates demo users with different bank allocations', function () {
    Artisan::call('demo:populate');

    // Test different bank setups
    $argentina = User::where('email', 'demo.argentina@gcu.global')->first();
    $nomad = User::where('email', 'demo.nomad@gcu.global')->first();
    $regular = User::where('email', 'demo.user@gcu.global')->first();

    expect(UserBankPreference::where('user_uuid', $argentina->uuid)->count())->toBe(3);
    expect(UserBankPreference::where('user_uuid', $nomad->uuid)->count())->toBe(3);
    expect(UserBankPreference::where('user_uuid', $regular->uuid)->count())->toBe(1);

    // Test primary bank is set
    expect(UserBankPreference::where('user_uuid', $argentina->uuid)->where('is_primary', true)->count())->toBe(1);
});

it('creates voting polls for demo', function () {
    Artisan::call('demo:populate');

    // Should have at least 3 polls (current active, next draft, last completed)
    expect(Poll::count())->toBeGreaterThanOrEqual(3);

    // Check active poll exists
    $activePoll = Poll::where('status', 'active')->first();
    expect($activePoll)->not->toBeNull();

    // Check executed poll exists with results
    $executedPoll = Poll::where('status', 'executed')->first();
    expect($executedPoll)->not->toBeNull();
    expect($executedPoll->metadata)->toHaveKey('results');
    expect($executedPoll->metadata['results'])->toHaveKeys(['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'XAU']);
});

it('creates demo votes for active polls', function () {
    Artisan::call('demo:populate');

    $activePoll = Poll::where('status', 'active')->first();
    expect($activePoll)->not->toBeNull();

    // Check votes were created
    $votes = Vote::where('poll_id', $activePoll->id)->get();
    expect($votes->count())->toBeGreaterThan(0);

    // Verify vote structure
    $vote = $votes->first();
    expect($vote->selected_options)->toHaveKey('basket_weights');
    expect($vote->voting_power)->toBeGreaterThan(0);
    expect($vote->signature)->not->toBeEmpty();
});

it('funds demo accounts with multiple assets', function () {
    Artisan::call('demo:populate');

    $argentina = User::where('email', 'demo.argentina@gcu.global')->first();
    $account = $argentina->accounts()->first();

    // Check USD balance
    expect($account->getBalance('USD'))->toBeGreaterThan(0);

    // Check account has multiple asset balances
    expect($account->balances()->count())->toBeGreaterThanOrEqual(2);

    // Check GCU balance exists
    $gcuBalance = $account->balances()->where('asset_code', 'GCU')->first();
    expect($gcuBalance)->not->toBeNull();
    expect($gcuBalance->balance)->toBeGreaterThan(0);
});

it('displays summary after population', function () {
    $this->artisan('demo:populate')
        ->expectsOutput('📊 Demo Data Summary')
        ->expectsOutputToContain('Demo Users:')
        ->expectsOutputToContain('Accounts:')
        ->expectsOutputToContain('Active Assets:')
        ->expectsOutput('🔐 Demo User Credentials')
        ->expectsTable(
            ['Email', 'Password', 'Persona'],
            [
                ['demo.argentina@gcu.global', 'demo123', 'High-inflation country user'],
                ['demo.nomad@gcu.global', 'demo123', 'Digital nomad'],
                ['demo.business@gcu.global', 'demo123', 'Business user'],
                ['demo.investor@gcu.global', 'demo123', 'Investor'],
                ['demo.user@gcu.global', 'demo123', 'Regular user'],
            ]
        )
        ->assertSuccessful();
});
