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
        
        // Initialize total voting power and weighted sums
        $totalVotingPower = 0;
        $weightedSums = [];
        
        // Get the basket voting options structure
        $basketOption = collect($poll->options)->firstWhere('id', 'basket_weights');
        if (!$basketOption) {
            throw new \Exception('Invalid poll structure - missing basket weights option');
        }
        
        // Initialize weighted sums for each currency
        foreach ($basketOption['currencies'] as $currency) {
            $weightedSums[$currency['code']] = 0;
        }
        
        // Calculate weighted averages from all votes
        foreach ($poll->votes as $vote) {
            $votingPower = $vote->voting_power;
            $totalVotingPower += $votingPower;
            
            // Extract the allocation from vote data
            $allocations = $vote->metadata['allocations'] ?? $vote->selected_options['allocations'] ?? [];
            
            foreach ($allocations as $currencyCode => $weight) {
                if (isset($weightedSums[$currencyCode])) {
                    $weightedSums[$currencyCode] += $weight * $votingPower;
                }
            }
        }
        
        // If no votes, use default composition
        if ($totalVotingPower === 0) {
            $composition = [];
            foreach ($basketOption['currencies'] as $currency) {
                $composition[$currency['code']] = $currency['default'];
            }
            return $composition;
        }
        
        // Calculate final weighted averages
        $composition = [];
        foreach ($weightedSums as $currencyCode => $weightedSum) {
            $composition[$currencyCode] = round($weightedSum / $totalVotingPower, 2);
        }
        
        // Ensure weights sum to 100% (handle rounding errors)
        $total = array_sum($composition);
        if ($total !== 100.0) {
            // Adjust the largest component
            $largestCurrency = array_keys($composition, max($composition))[0];
            $composition[$largestCurrency] += (100.0 - $total);
        }
        
        return $composition;
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