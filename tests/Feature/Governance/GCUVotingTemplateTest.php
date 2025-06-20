<?php

use App\Domain\Governance\Services\VotingTemplateService;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Enums\PollStatus;
use App\Models\User;
use Carbon\Carbon;

beforeEach(function () {
    // Set GCU configuration
    config([
        'baskets.primary' => 'GCU',
        'baskets.primary_code' => 'GCU',
        'baskets.primary_name' => 'Global Currency Unit',
        'baskets.primary_symbol' => 'Ǥ',
    ]);
    
    $this->service = app(VotingTemplateService::class);
});

it('creates monthly GCU basket voting poll', function () {
    $votingMonth = Carbon::now()->addMonth()->startOfMonth();
    $poll = $this->service->createMonthlyBasketVotingPoll($votingMonth);
    
    expect($poll)->toBeInstanceOf(Poll::class);
    expect($poll->title)->toContain('Currency Basket Composition');
    expect($poll->title)->toContain($votingMonth->format('F Y'));
    expect($poll->type)->toBe(PollType::WEIGHTED_CHOICE);
    expect($poll->status)->toBe(PollStatus::DRAFT);
    expect($poll->metadata['basket_code'])->toBe('GCU');
    expect($poll->metadata['template'])->toBe('monthly_basket');
    expect($poll->options[0]['label'])->toBe('Currency Basket Weights');
});

it('creates GCU currency addition poll', function () {
    $poll = $this->service->createAddCurrencyPoll('CAD', 'Canadian Dollar');
    
    expect($poll)->toBeInstanceOf(Poll::class);
    expect($poll->title)->toContain('Add Canadian Dollar (CAD) to Currency Basket?');
    expect($poll->description)->toContain('currency basket');
    expect($poll->type)->toBe(PollType::SINGLE_CHOICE);
    expect($poll->options)->toHaveCount(2);
    expect($poll->options[0]['label'])->toContain('add CAD to the basket');
});

it('creates emergency GCU rebalancing poll', function () {
    $reason = 'Major market volatility detected';
    $poll = $this->service->createEmergencyRebalancingPoll($reason);
    
    expect($poll)->toBeInstanceOf(Poll::class);
    expect($poll->title)->toBe('Emergency Basket Rebalancing Required');
    expect($poll->description)->toContain($reason);
    expect($poll->description)->toContain('currency basket');
    expect($poll->metadata['urgent'])->toBeTrue();
    expect($poll->end_date->diffInDays(now()))->toBeLessThanOrEqual(3);
});

it('uses correct GCU basket options structure', function () {
    $poll = $this->service->createMonthlyBasketVotingPoll();
    $options = $poll->options[0];
    
    expect($options['id'])->toBe('basket_weights');
    expect($options['label'])->toBe('Currency Basket Weights');
    expect($options['type'])->toBe('allocation');
    expect($options['currencies'])->toHaveCount(6);
    
    // Check currency codes
    $currencyCodes = collect($options['currencies'])->pluck('code')->toArray();
    expect($currencyCodes)->toBe(['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'XAU']);
    
    // Check default weights sum to 100
    $totalWeight = collect($options['currencies'])->sum('default');
    expect($totalWeight)->toBe(100);
});

it('creates yearly GCU voting polls', function () {
    $year = 2025;
    $polls = $this->service->scheduleYearlyVotingPolls($year);
    
    expect($polls)->toHaveCount(12);
    
    foreach ($polls as $index => $poll) {
        $expectedMonth = Carbon::create($year, $index + 1, 1);
        expect($poll->metadata['voting_month'])->toBe($expectedMonth->format('Y-m'));
        expect($poll->title)->toContain('Currency Basket Composition');
    }
});

it('sets correct voting periods for GCU polls', function () {
    $votingMonth = Carbon::create(2025, 7, 1); // July 2025
    $poll = $this->service->createMonthlyBasketVotingPoll($votingMonth);
    
    // Voting should start 7 days before the month
    expect($poll->start_date->format('Y-m-d'))->toBe('2025-06-24');
    
    // Voting should end on the last day of the previous month
    expect($poll->end_date->format('Y-m-d H:i:s'))->toBe('2025-06-30 23:59:59');
});

it('creates system user for GCU polls', function () {
    $poll = $this->service->createMonthlyBasketVotingPoll();
    
    $systemUser = User::where('email', 'system@platform')->first();
    expect($systemUser)->not->toBeNull();
    expect($poll->created_by)->toBe($systemUser->uuid);
});

it('includes GCU voting power strategy', function () {
    $poll = $this->service->createMonthlyBasketVotingPoll();
    
    expect($poll->voting_power_strategy)->toBe(\App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class);
});

it('marks GCU polls for auto execution', function () {
    $poll = $this->service->createMonthlyBasketVotingPoll();
    
    expect($poll->metadata['auto_execute'])->toBeTrue();
    expect($poll->execution_workflow)->toBe(\App\Domain\Governance\Workflows\UpdateBasketCompositionWorkflow::class);
});

it('validates GCU basket voting description mentions voting power', function () {
    $poll = $this->service->createMonthlyBasketVotingPoll();
    
    expect($poll->description)->toContain('asset holdings');
    expect($poll->description)->toContain('weighted average');
});

it('sets proper participation thresholds for different GCU poll types', function () {
    $monthlyPoll = $this->service->createMonthlyBasketVotingPoll();
    $addCurrencyPoll = $this->service->createAddCurrencyPoll('CAD', 'Canadian Dollar');
    $emergencyPoll = $this->service->createEmergencyRebalancingPoll('Crisis');
    
    expect($monthlyPoll->required_participation)->toBe(10);
    expect($addCurrencyPoll->required_participation)->toBe(25);
    expect($emergencyPoll->required_participation)->toBe(30);
});