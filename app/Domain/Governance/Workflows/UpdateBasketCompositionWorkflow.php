<?php

declare(strict_types=1);

namespace App\Domain\Governance\Workflows;

use App\Domain\Governance\Models\Poll;
use App\Models\BasketAsset;
use Workflow\Workflow;
use Workflow\ActivityStub;

class UpdateBasketCompositionWorkflow extends Workflow
{
    /**
     * Execute the workflow to update basket composition based on poll results
     */
    public function execute(string $pollUuid): \Generator
    {
        // Get the poll
        $poll = yield ActivityStub::make(GetPollActivity::class, $pollUuid);
        
        if (!$poll || $poll->status->value !== 'closed') {
            throw new \Exception('Poll not found or not completed');
        }
        
        // Get the basket code from metadata
        $basketCode = $poll->metadata['basket_code'] ?? config('baskets.primary', 'PRIMARY');
        
        // Calculate weighted average of votes
        $newComposition = yield ActivityStub::make(CalculateBasketCompositionActivity::class, $pollUuid);
        
        // Update the basket composition
        yield ActivityStub::make(UpdateBasketComponentsActivity::class, $basketCode, $newComposition);
        
        // Trigger basket rebalancing if needed
        yield ActivityStub::make(TriggerBasketRebalancingActivity::class, $basketCode);
        
        // Record the governance event
        yield ActivityStub::make(RecordGovernanceEventActivity::class, [
            'type' => 'basket_composition_updated',
            'poll_uuid' => $pollUuid,
            'basket_code' => $basketCode,
            'new_composition' => $newComposition,
        ]);
        
        return true;
    }
}

class GetPollActivity extends \Workflow\Activity
{
    public function execute(string $pollUuid): ?Poll
    {
        return Poll::where('uuid', $pollUuid)->first();
    }
}

class CalculateBasketCompositionActivity extends \Workflow\Activity
{
    public function execute(string $pollUuid): array
    {
        $poll = Poll::where('uuid', $pollUuid)->with('votes')->first();
        
        if (!$poll) {
            throw new \Exception('Poll not found');
        }
        
        // For now, return the default composition
        // In production, this would calculate weighted averages from votes
        return [
            'USD' => 40.0,
            'EUR' => 30.0,
            'GBP' => 15.0,
            'CHF' => 10.0,
            'JPY' => 3.0,
            'XAU' => 2.0,
        ];
    }
}

class UpdateBasketComponentsActivity extends \Workflow\Activity
{
    public function execute(string $basketCode, array $composition): void
    {
        $basket = BasketAsset::where('code', $basketCode)->first();
        
        if (!$basket) {
            throw new \Exception("Basket {$basketCode} not found");
        }
        
        foreach ($composition as $assetCode => $weight) {
            $basket->components()
                ->where('asset_code', $assetCode)
                ->update(['weight' => $weight]);
        }
        
        $basket->update(['last_rebalanced_at' => now()]);
    }
}

class TriggerBasketRebalancingActivity extends \Workflow\Activity
{
    public function execute(string $basketCode): void
    {
        $basket = BasketAsset::where('code', $basketCode)->with('components')->first();
        
        if (!$basket) {
            return;
        }
        
        // Trigger rebalancing event
        event(new \App\Domain\Basket\Events\BasketRebalanced(
            $basketCode,
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
    }
}

class RecordGovernanceEventActivity extends \Workflow\Activity
{
    public function execute(array $eventData): void
    {
        // Log the governance event
        \Log::info('Governance event recorded', $eventData);
    }
}