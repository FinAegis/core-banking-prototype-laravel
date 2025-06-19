<?php

declare(strict_types=1);

namespace App\Domain\Governance\ValueObjects;

final readonly class PollResult
{
    public function __construct(
        public string $pollUuid,
        public int $totalVotes,
        public int $totalVotingPower,
        public array $optionResults, // ['option_id' => ['votes' => int, 'power' => int, 'percentage' => float]]
        public float $participationRate,
        public ?string $winningOption = null,
        public ?array $metadata = []
    ) {}

    public static function calculate(
        string $pollUuid,
        array $votes,
        array $options,
        int $totalEligibleVotingPower
    ): self {
        $totalVotes = count($votes);
        $totalVotingPower = (int) array_sum(array_column($votes, 'voting_power'));
        
        // Initialize results for all options
        $optionResults = [];
        foreach ($options as $option) {
            $optionResults[$option['id']] = [
                'votes' => 0,
                'power' => 0,
                'percentage' => 0.0,
            ];
        }
        
        // Calculate votes and power for each option
        foreach ($votes as $vote) {
            $selectedOptions = $vote['selected_options'];
            $votingPower = $vote['voting_power'];
            
            foreach ($selectedOptions as $optionId) {
                if (isset($optionResults[$optionId])) {
                    $optionResults[$optionId]['votes']++;
                    $optionResults[$optionId]['power'] += $votingPower;
                }
            }
        }
        
        // Calculate percentages and find winner
        $winningOption = null;
        $maxPower = 0;
        
        foreach ($optionResults as $optionId => &$result) {
            if ($totalVotingPower > 0) {
                $result['percentage'] = ($result['power'] / $totalVotingPower) * 100;
            }
            
            if ($result['power'] > $maxPower) {
                $maxPower = $result['power'];
                $winningOption = $optionId;
            }
        }
        
        $participationRate = $totalEligibleVotingPower > 0 
            ? ($totalVotingPower / $totalEligibleVotingPower) * 100 
            : 0;
        
        return new self(
            pollUuid: $pollUuid,
            totalVotes: $totalVotes,
            totalVotingPower: $totalVotingPower,
            optionResults: $optionResults,
            participationRate: $participationRate,
            winningOption: $winningOption
        );
    }

    public function toArray(): array
    {
        return [
            'poll_uuid' => $this->pollUuid,
            'total_votes' => $this->totalVotes,
            'total_voting_power' => $this->totalVotingPower,
            'option_results' => $this->optionResults,
            'participation_rate' => $this->participationRate,
            'winning_option' => $this->winningOption,
            'metadata' => $this->metadata,
        ];
    }

    public function getOptionResult(string $optionId): ?array
    {
        return $this->optionResults[$optionId] ?? null;
    }

    public function hasWinner(): bool
    {
        return $this->winningOption !== null;
    }

    public function meetsParticipationThreshold(float $threshold): bool
    {
        return $this->participationRate >= $threshold;
    }
}