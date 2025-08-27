<?php

declare(strict_types=1);

namespace App\Domain\Governance\Workflows;

use App\Domain\Governance\Activities\CalculateBasketCompositionActivity;
use App\Domain\Governance\Activities\GetPollActivity;
use App\Domain\Governance\Activities\RecordGovernanceEventActivity;
use App\Domain\Governance\Activities\TriggerBasketRebalancingActivity;
use App\Domain\Governance\Activities\UpdateBasketComponentsActivity;
use App\Domain\Governance\Models\Poll;
use Exception;
use Generator;
use Workflow\ActivityStub;
use Workflow\Workflow;

class UpdateBasketCompositionWorkflow extends Workflow
{
    /**
     * Execute the workflow to update basket composition based on poll results.
     */
    public function execute(string $pollUuid): Generator
    {
        // Get the poll
        $poll = yield ActivityStub::make(GetPollActivity::class, $pollUuid);

        if (! $poll || $poll->status->value !== 'closed') {
            throw new Exception('Poll not found or not completed');
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
        yield ActivityStub::make(
            RecordGovernanceEventActivity::class,
            [
                'type'            => 'basket_composition_updated',
                'poll_uuid'       => $pollUuid,
                'basket_code'     => $basketCode,
                'new_composition' => $newComposition,
            ]
        );

        return true;
    }
}
