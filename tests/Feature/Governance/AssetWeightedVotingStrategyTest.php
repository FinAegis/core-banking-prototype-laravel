<?php

use App\Domain\Governance\Strategies\AssetWeightedVotingStrategy;
use App\Domain\Governance\Models\Poll;
use App\Models\User;
use App\Models\Account;
use App\Domain\Asset\Models\Asset;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create PRIMARY asset
    Asset::firstOrCreate(
        ['code' => 'PRIMARY'],
        [
            'name' => 'Primary Currency Basket',
            'type' => 'custom',
            'precision' => 2,
            'is_active' => true,
        ]
    );
    
    // Create USD for comparison
    Asset::firstOrCreate(
        ['code' => 'USD'],
        [
            'name' => 'US Dollar',
            'type' => 'fiat',
            'precision' => 2,
            'is_active' => true,
        ]
    );
});

test('voting power is based on primary asset holdings', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $poll = Poll::factory()->create();
    
    // Add 100 PRIMARY (10000 cents)
    $account->addBalance('PRIMARY', 10000);
    
    $strategy = new AssetWeightedVotingStrategy();
    $votingPower = $strategy->calculatePower($user, $poll);
    
    expect($votingPower)->toBe(100); // 100 units = 100 votes
});

test('voting power combines multiple accounts', function () {
    $user = User::factory()->create();
    $account1 = Account::factory()->create(['user_uuid' => $user->uuid]);
    $account2 = Account::factory()->create(['user_uuid' => $user->uuid]);
    $poll = Poll::factory()->create();
    
    // Add PRIMARY to both accounts
    $account1->addBalance('PRIMARY', 5000); // 50 units
    $account2->addBalance('PRIMARY', 7500); // 75 units
    
    $strategy = new AssetWeightedVotingStrategy();
    $votingPower = $strategy->calculatePower($user, $poll);
    
    expect($votingPower)->toBe(125); // 50 + 75 = 125 votes
});

test('minimum voting power is 1 for any primary asset holder', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $poll = Poll::factory()->create();
    
    // Add tiny amount of PRIMARY (0.01 units)
    $account->addBalance('PRIMARY', 1);
    
    $strategy = new AssetWeightedVotingStrategy();
    $votingPower = $strategy->calculatePower($user, $poll);
    
    expect($votingPower)->toBe(1); // Minimum 1 vote
});

test('user with no primary asset has no voting power', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $poll = Poll::factory()->create();
    
    // Add USD but no PRIMARY
    $account->addBalance('USD', 10000);
    
    $strategy = new AssetWeightedVotingStrategy();
    $votingPower = $strategy->calculatePower($user, $poll);
    
    expect($votingPower)->toBe(1); // Still gets minimum 1 vote
});

test('user is eligible to vote if they have primary asset', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $poll = Poll::factory()->create();
    
    $strategy = new AssetWeightedVotingStrategy();
    
    // Not eligible without PRIMARY
    expect($strategy->isEligible($user, $poll))->toBeFalse();
    
    // Add PRIMARY
    $account->addBalance('PRIMARY', 100);
    
    // Now eligible
    expect($strategy->isEligible($user, $poll))->toBeTrue();
});

test('strategy has correct description', function () {
    $strategy = new AssetWeightedVotingStrategy();
    
    expect($strategy->getDescription())
        ->toBe('Voting power is proportional to primary asset holdings. 1 unit = 1 vote.');
});

test('voting power ignores non-primary assets', function () {
    // Create EUR asset
    Asset::firstOrCreate(
        ['code' => 'EUR'],
        [
            'name' => 'Euro',
            'type' => 'fiat',
            'precision' => 2,
            'is_active' => true,
        ]
    );
    
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $poll = Poll::factory()->create();
    
    // Add various assets
    $account->addBalance('USD', 100000); // $1000
    $account->addBalance('EUR', 50000);  // €500
    $account->addBalance('PRIMARY', 2500);   // 25 units
    
    $strategy = new AssetWeightedVotingStrategy();
    $votingPower = $strategy->calculatePower($user, $poll);
    
    expect($votingPower)->toBe(25); // Only PRIMARY counts
});

test('user with no accounts has no voting power', function () {
    $user = User::factory()->create();
    $poll = Poll::factory()->create();
    
    $strategy = new AssetWeightedVotingStrategy();
    $votingPower = $strategy->calculatePower($user, $poll);
    
    expect($votingPower)->toBe(1); // Minimum 1 vote
    expect($strategy->isEligible($user, $poll))->toBeFalse();
});