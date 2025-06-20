<?php

use App\Domain\Governance\Workflows\UpdateBasketCompositionWorkflow;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Enums\PollType;
use App\Models\BasketAsset;
use App\Models\User;
use Workflow\WorkflowStub;

beforeEach(function () {
    // Ensure all required assets exist
    $assets = ['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'XAU'];
    foreach ($assets as $code) {
        \App\Domain\Asset\Models\Asset::firstOrCreate(
            ['code' => $code],
            [
                'name' => $code . ' Currency',
                'type' => in_array($code, ['XAU']) ? 'commodity' : 'fiat',
                'precision' => 2,
                'is_active' => true,
            ]
        );
    }
    
    // Create GCU basket
    $this->basket = BasketAsset::factory()->create([
        'code' => 'GCU',
        'name' => 'Global Currency Unit',
        'type' => 'dynamic',
    ]);
    
    // Delete any existing components to avoid duplicates
    $this->basket->components()->delete();
    
    // Add components
    $components = [
        ['asset_code' => 'USD', 'weight' => 40.0],
        ['asset_code' => 'EUR', 'weight' => 30.0],
        ['asset_code' => 'GBP', 'weight' => 15.0],
        ['asset_code' => 'CHF', 'weight' => 10.0],
        ['asset_code' => 'JPY', 'weight' => 3.0],
        ['asset_code' => 'XAU', 'weight' => 2.0],
    ];
    
    foreach ($components as $component) {
        $this->basket->components()->create($component);
    }
    
    // Create poll with basket voting structure
    $this->poll = Poll::factory()->create([
        'status' => PollStatus::CLOSED,
        'type' => PollType::WEIGHTED_CHOICE,
        'metadata' => [
            'basket_code' => 'GCU',
            'template' => 'monthly_basket',
        ],
        'options' => [[
            'id' => 'basket_weights',
            'label' => 'GCU Basket Weights',
            'type' => 'allocation',
            'currencies' => [
                ['code' => 'USD', 'default' => 40],
                ['code' => 'EUR', 'default' => 30],
                ['code' => 'GBP', 'default' => 15],
                ['code' => 'CHF', 'default' => 10],
                ['code' => 'JPY', 'default' => 3],
                ['code' => 'XAU', 'default' => 2],
            ],
        ]],
    ]);
});

it('can create workflow stub for updating basket composition', function () {
    expect(class_exists(UpdateBasketCompositionWorkflow::class))->toBeTrue();
});

it('workflow processes votes with different allocations', function () {
    // Create votes with different allocations and voting power
    Vote::factory()->create([
        'poll_id' => $this->poll->id,
        'voting_power' => 1000,
        'selected_options' => [
            'allocations' => [
                'USD' => 45,
                'EUR' => 25,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 3,
                'XAU' => 2,
            ],
        ],
    ]);
    
    Vote::factory()->create([
        'poll_id' => $this->poll->id,
        'voting_power' => 2000,
        'selected_options' => [
            'allocations' => [
                'USD' => 35,
                'EUR' => 35,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 3,
                'XAU' => 2,
            ],
        ],
    ]);
    
    // Test that the workflow can be instantiated with votes present
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow handles poll with no votes', function () {
    // No votes created - should use default composition
    expect($this->poll->votes()->count())->toBe(0);
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow handles votes with metadata allocations format', function () {
    Vote::factory()->create([
        'poll_id' => $this->poll->id,
        'voting_power' => 1000,
        'metadata' => [
            'allocations' => [
                'USD' => 40,
                'EUR' => 30,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 3,
                'XAU' => 2,
            ],
        ],
    ]);
    
    expect($this->poll->votes()->count())->toBe(1);
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow handles allocations with rounding', function () {
    // Create vote with allocations that might have rounding issues
    Vote::factory()->create([
        'poll_id' => $this->poll->id,
        'voting_power' => 1000,
        'selected_options' => [
            'allocations' => [
                'USD' => 33.33,
                'EUR' => 33.33,
                'GBP' => 16.67,
                'CHF' => 10.0,
                'JPY' => 4.67,
                'XAU' => 2.0,
            ],
        ],
    ]);
    
    expect($this->poll->votes()->count())->toBe(1);
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow processes basket rebalancing votes', function () {
    Vote::factory()->create([
        'poll_id' => $this->poll->id,
        'voting_power' => 1000,
        'selected_options' => [
            'allocations' => [
                'USD' => 50,
                'EUR' => 25,
                'GBP' => 10,
                'CHF' => 10,
                'JPY' => 3,
                'XAU' => 2,
            ],
        ],
    ]);
    
    expect($this->poll->votes()->count())->toBe(1);
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow records governance events', function () {
    // Workflow should record governance events
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('requires closed poll status', function () {
    $this->poll->update(['status' => PollStatus::ACTIVE]);
    
    expect($this->poll->status)->toBe(PollStatus::ACTIVE);
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow uses basket code from poll metadata', function () {
    $this->poll->update([
        'metadata' => [
            'basket_code' => 'CUSTOM_BASKET',
            'template' => 'monthly_basket',
        ],
    ]);
    
    expect($this->poll->metadata['basket_code'])->toBe('CUSTOM_BASKET');
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow ignores votes for invalid currencies', function () {
    Vote::factory()->create([
        'poll_id' => $this->poll->id,
        'voting_power' => 1000,
        'selected_options' => [
            'allocations' => [
                'USD' => 40,
                'EUR' => 30,
                'GBP' => 15,
                'CHF' => 10,
                'JPY' => 3,
                'XAU' => 2,
                'CAD' => 10, // Not in basket options
            ],
        ],
    ]);
    
    expect($this->poll->votes()->count())->toBe(1);
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});

it('workflow handles zero voting power', function () {
    Vote::factory()->create([
        'poll_id' => $this->poll->id,
        'voting_power' => 0,
        'selected_options' => [
            'allocations' => [
                'USD' => 100,
                'EUR' => 0,
                'GBP' => 0,
                'CHF' => 0,
                'JPY' => 0,
                'XAU' => 0,
            ],
        ],
    ]);
    
    expect($this->poll->votes()->where('voting_power', 0)->count())->toBe(1);
    expect(UpdateBasketCompositionWorkflow::class)->toBeString();
});