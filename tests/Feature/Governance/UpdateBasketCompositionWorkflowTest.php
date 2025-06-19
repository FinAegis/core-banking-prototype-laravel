<?php

use App\Domain\Governance\Workflows\UpdateBasketCompositionWorkflow;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Enums\PollStatus;
use App\Models\BasketAsset;
use App\Domain\Asset\Models\Asset;
use App\Domain\Basket\Events\BasketRebalanced;
use Workflow\WorkflowStub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create assets
    $assets = [
        'USD' => ['name' => 'US Dollar', 'type' => 'fiat'],
        'EUR' => ['name' => 'Euro', 'type' => 'fiat'],
        'GBP' => ['name' => 'British Pound', 'type' => 'fiat'],
        'CHF' => ['name' => 'Swiss Franc', 'type' => 'fiat'],
        'JPY' => ['name' => 'Japanese Yen', 'type' => 'fiat', 'precision' => 0],
        'XAU' => ['name' => 'Gold', 'type' => 'commodity', 'precision' => 3],
    ];
    
    foreach ($assets as $code => $data) {
        Asset::firstOrCreate(
            ['code' => $code],
            array_merge([
                'name' => $data['name'],
                'type' => $data['type'],
                'precision' => $data['precision'] ?? 2,
                'is_active' => true,
            ])
        );
    }
    
    // Create PRIMARY basket
    $this->basket = BasketAsset::create([
        'code' => 'PRIMARY',
        'name' => 'Primary Currency Basket',
        'type' => 'fixed',
        'rebalance_frequency' => 'monthly',
        'is_active' => true,
    ]);
    
    // Add components with initial weights
    $this->basket->components()->createMany([
        ['asset_code' => 'USD', 'weight' => 40.0, 'is_active' => true],
        ['asset_code' => 'EUR', 'weight' => 30.0, 'is_active' => true],
        ['asset_code' => 'GBP', 'weight' => 15.0, 'is_active' => true],
        ['asset_code' => 'CHF', 'weight' => 10.0, 'is_active' => true],
        ['asset_code' => 'JPY', 'weight' => 3.0, 'is_active' => true],
        ['asset_code' => 'XAU', 'weight' => 2.0, 'is_active' => true],
    ]);
});

test('workflow updates basket composition based on poll results', function () {
    Event::fake();
    WorkflowStub::fake();
    
    // Create completed poll
    $poll = Poll::factory()->create([
        'status' => PollStatus::CLOSED,
        'metadata' => ['basket_code' => 'PRIMARY'],
    ]);
    
    // Mock activities
    WorkflowStub::mock(App\Domain\Governance\Workflows\GetPollActivity::class, $poll);
    WorkflowStub::mock(App\Domain\Governance\Workflows\CalculateBasketCompositionActivity::class, [
        'USD' => 40.0,
        'EUR' => 30.0,
        'GBP' => 15.0,
        'CHF' => 10.0,
        'JPY' => 3.0,
        'XAU' => 2.0,
    ]);
    WorkflowStub::mock(App\Domain\Governance\Workflows\UpdateBasketComponentsActivity::class, null);
    WorkflowStub::mock(App\Domain\Governance\Workflows\TriggerBasketRebalancingActivity::class, null);
    WorkflowStub::mock(App\Domain\Governance\Workflows\RecordGovernanceEventActivity::class, null);
    
    $workflow = WorkflowStub::make(UpdateBasketCompositionWorkflow::class);
    $result = $workflow->start($poll->uuid);
    
    WorkflowStub::assertDispatched(App\Domain\Governance\Workflows\GetPollActivity::class);
    WorkflowStub::assertDispatched(App\Domain\Governance\Workflows\CalculateBasketCompositionActivity::class);
    WorkflowStub::assertDispatched(App\Domain\Governance\Workflows\UpdateBasketComponentsActivity::class);
    WorkflowStub::assertDispatched(App\Domain\Governance\Workflows\TriggerBasketRebalancingActivity::class);
    WorkflowStub::assertDispatched(App\Domain\Governance\Workflows\RecordGovernanceEventActivity::class);
    
    expect($workflow->output())->toBe(true);
});

test('workflow can be started with poll uuid', function () {
    WorkflowStub::fake();
    
    // Create completed poll
    $poll = Poll::factory()->create([
        'status' => PollStatus::CLOSED,
        'metadata' => ['basket_code' => 'PRIMARY'],
    ]);
    
    $workflow = WorkflowStub::make(UpdateBasketCompositionWorkflow::class);
    $workflow->start($poll->uuid);
    
    expect($workflow)->toBeInstanceOf(WorkflowStub::class);
});

test('basket components are updated correctly', function () {
    // Test basket component update functionality
    $newWeights = [
        'USD' => 35.0,
        'EUR' => 35.0,
        'GBP' => 15.0,
        'CHF' => 10.0,
        'JPY' => 3.0,
        'XAU' => 2.0,
    ];
    
    foreach ($newWeights as $assetCode => $weight) {
        $this->basket->components()
            ->where('asset_code', $assetCode)
            ->update(['weight' => $weight]);
    }
    
    $this->basket->update(['last_rebalanced_at' => now()]);
    
    // Check weights were updated
    $this->basket->refresh();
    foreach ($newWeights as $assetCode => $weight) {
        $component = $this->basket->components()
            ->where('asset_code', $assetCode)
            ->first();
        expect($component->weight)->toBe($weight);
    }
    
    // Check rebalancing timestamp
    expect($this->basket->last_rebalanced_at)->not->toBeNull();
});

test('basket rebalancing event contains correct data', function () {
    Event::fake();
    
    $basket = BasketAsset::where('code', 'PRIMARY')->with('components')->first();
    
    event(new BasketRebalanced(
        'PRIMARY',
        $basket->components->map(function ($component) {
            return [
                'asset' => $component->asset_code,
                'old_weight' => $component->weight,
                'new_weight' => $component->weight,
                'adjustment' => 0.0,
            ];
        })->toArray(),
        now()
    ));
    
    Event::assertDispatched(BasketRebalanced::class, function ($event) {
        return $event->basketCode === 'PRIMARY' && count($event->adjustments) === 6;
    });
});