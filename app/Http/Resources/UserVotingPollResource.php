<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Domain\Governance\Strategies\AssetWeightedVotingStrategy;

class UserVotingPollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $votingPower = 0;
        $hasVoted = false;
        $userVote = null;

        if ($user) {
            // Calculate voting power
            $strategy = app($this->voting_power_strategy ?? AssetWeightedVotingStrategy::class);
            $votingPower = $strategy->calculatePower($user, $this->resource);

            // Check if user has voted
            $userVote = $this->votes()->where('user_uuid', $user->uuid)->first();
            $hasVoted = $userVote !== null;
        }

        return [
            'uuid' => $this->uuid,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'options' => $this->options,
            'start_date' => $this->start_date->toISOString(),
            'end_date' => $this->end_date->toISOString(),
            'required_participation' => $this->required_participation,
            'current_participation' => $this->calculateParticipation(),
            'user_context' => [
                'has_voted' => $hasVoted,
                'voting_power' => $votingPower,
                'can_vote' => !$hasVoted && $this->status->value === 'active' && $votingPower > 0,
                'vote' => $userVote ? [
                    'selected_options' => $userVote->selected_options,
                    'voted_at' => $userVote->created_at->toISOString(),
                ] : null,
            ],
            'metadata' => [
                'is_gcu_poll' => ($this->metadata['basket_code'] ?? null) === config('baskets.primary_code', 'GCU'),
                'voting_month' => $this->metadata['voting_month'] ?? null,
                'template' => $this->metadata['template'] ?? null,
            ],
            'results_visible' => $this->status->value === 'closed',
            'time_remaining' => $this->status->value === 'active' ? [
                'days' => now()->diffInDays($this->end_date, false),
                'hours' => now()->diffInHours($this->end_date, false) % 24,
                'human_readable' => now()->diffForHumans($this->end_date),
            ] : null,
        ];
    }

    /**
     * Calculate current participation percentage
     */
    private function calculateParticipation(): float
    {
        $totalVotingPower = $this->votes()->sum('voting_power');
        $potentialVotingPower = $this->estimatePotentialVotingPower();
        
        if ($potentialVotingPower === 0) {
            return 0;
        }
        
        return round(($totalVotingPower / $potentialVotingPower) * 100, 2);
    }

    /**
     * Estimate potential voting power (simplified for now)
     */
    private function estimatePotentialVotingPower(): int
    {
        // In production, this would calculate based on total GCU in circulation
        // For now, return a reasonable estimate
        return 1000000;
    }
}