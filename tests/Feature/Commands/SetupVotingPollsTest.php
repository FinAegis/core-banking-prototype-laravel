<?php

use App\Domain\Governance\Models\Poll;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('setup voting command creates next month poll', function () {
    $this->artisan('voting:setup')
        ->expectsQuestion('Would you like to activate this poll now?', 'no')
        ->assertSuccessful();
    
    // Check poll was created
    $poll = Poll::where('metadata->template', 'monthly_basket')->first();
    expect($poll)->not->toBeNull();
    expect($poll->title)->toContain('Currency Basket Composition');
    // Poll might be auto-activated if within voting period
    expect($poll->status->value)->toBeIn(['draft', 'active']);
});

test('setup voting command creates specific month poll', function () {
    $this->artisan('voting:setup --month=2025-08')
        ->assertSuccessful();
    
    // Check poll was created for August 2025
    $poll = Poll::where('metadata->voting_month', '2025-08')->first();
    expect($poll)->not->toBeNull();
    expect($poll->title)->toBe('Currency Basket Composition - August 2025');
});

test('setup voting command creates yearly polls', function () {
    $this->artisan('voting:setup --year=2025')
        ->assertSuccessful();
    
    // Check 12 polls were created
    $polls = Poll::where('metadata->template', 'monthly_basket')
        ->where('metadata->voting_month', 'like', '2025-%')
        ->get();
    
    expect($polls)->toHaveCount(12);
    
    // Check months are sequential
    for ($month = 1; $month <= 12; $month++) {
        $monthStr = sprintf('2025-%02d', $month);
        $poll = $polls->firstWhere('metadata.voting_month', $monthStr);
        expect($poll)->not->toBeNull();
    }
});

test('setup voting command handles invalid month format', function () {
    $this->artisan('voting:setup --month=invalid-date')
        ->expectsOutput('Invalid month format. Please use YYYY-MM format.')
        ->assertSuccessful();
});

test('setup voting command can activate poll', function () {
    $this->artisan('voting:setup')
        ->expectsQuestion('Would you like to activate this poll now?', 'yes')
        ->expectsOutput('Poll activated successfully!')
        ->assertSuccessful();
    
    // Check poll is active
    $poll = Poll::where('metadata->template', 'monthly_basket')->first();
    expect($poll->status->value)->toBe('active');
});