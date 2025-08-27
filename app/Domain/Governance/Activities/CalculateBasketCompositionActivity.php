<?php

namespace App\Domain\Governance\Activities;

use App\Models\Poll;
use Exception;
use Workflow\Activity;

class CalculateBasketCompositionActivity extends Activity
{
    /**
     * Execute basket composition calculation activity.
     */
    public function execute(string $pollUuid): array
    {
        /** @var \Illuminate\Database\Eloquent\Model|null $poll */
        $poll = Poll::where('uuid', $pollUuid)->with('votes')->first();

        if (! $poll) {
            throw new Exception('Poll not found');
        }

        // Initialize total voting power and weighted sums
        $totalVotingPower = 0;
        $weightedSums = [];

        // Get the basket voting options structure
        $basketOption = collect($poll->options)->firstWhere('id', 'basket_weights');
        if (! $basketOption) {
            throw new Exception('Invalid poll structure - missing basket weights option');
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
