<?php

use App\Domain\Governance\Services\VotingTemplateService;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Enums\PollStatus;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a system user for polls
    $this->systemUser = User::factory()->create([
        'name' => 'System',
        'email' => 'system@platform',
    ]);
});

test('can create monthly basket voting poll', function () {
    $service = new VotingTemplateService();
    $votingMonth = Carbon::parse('2025-07-01');
    
    $poll = $service->createMonthlyBasketVotingPoll($votingMonth);
    
    expect($poll)->toBeInstanceOf(Poll::class);
    expect($poll->title)->toBe('Currency Basket Composition - July 2025');
    expect($poll->type)->toBe(PollType::WEIGHTED_CHOICE);
    expect($poll->status)->toBe(PollStatus::DRAFT);
    expect($poll->metadata['basket_code'])->toBe('PRIMARY');
    expect($poll->metadata['auto_execute'])->toBeTrue();
    
    // Voting should start 7 days before the month
    expect($poll->start_date->format('Y-m-d'))->toBe('2025-06-24');
    expect($poll->end_date->format('Y-m-d'))->toBe('2025-06-30');
});

test('can create add currency poll', function () {
    $service = new VotingTemplateService();
    
    $poll = $service->createAddCurrencyPoll('CAD', 'Canadian Dollar');
    
    expect($poll->title)->toBe('Add Canadian Dollar (CAD) to Currency Basket?');
    expect($poll->type)->toBe(PollType::SINGLE_CHOICE);
    expect($poll->required_participation)->toBe(25);
    expect($poll->options)->toHaveCount(2);
    expect($poll->metadata['currency_code'])->toBe('CAD');
});

test('can create emergency rebalancing poll', function () {
    $service = new VotingTemplateService();
    
    $poll = $service->createEmergencyRebalancingPoll('Major currency crisis detected');
    
    expect($poll->title)->toBe('Emergency Basket Rebalancing Required');
    expect((int) $poll->start_date->diffInDays($poll->end_date))->toBe(3);
    expect($poll->required_participation)->toBe(30);
    expect($poll->metadata['urgent'])->toBeTrue();
});

test('can schedule yearly voting polls', function () {
    $service = new VotingTemplateService();
    
    $polls = $service->scheduleYearlyVotingPolls(2025);
    
    expect($polls)->toHaveCount(12);
    
    foreach ($polls as $index => $poll) {
        $month = $index + 1;
        expect($poll->metadata['voting_month'])->toBe("2025-" . str_pad($month, 2, '0', STR_PAD_LEFT));
    }
});

test('basket voting options have proper constraints', function () {
    $service = new VotingTemplateService();
    $poll = $service->createMonthlyBasketVotingPoll();
    
    $options = $poll->options[0];
    
    expect($options['type'])->toBe('allocation');
    expect($options['constraint'])->toBe('must_sum_to_100');
    expect($options['currencies'])->toHaveCount(6);
    
    // Check USD constraints
    $usd = collect($options['currencies'])->firstWhere('code', 'USD');
    expect($usd['min'])->toBe(20);
    expect($usd['max'])->toBe(50);
    expect($usd['default'])->toBe(40);
});