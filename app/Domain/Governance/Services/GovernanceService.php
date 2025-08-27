<?php

declare(strict_types=1);

namespace App\Domain\Governance\Services;

use App\Domain\Governance\Contracts\IVotingPowerStrategy;
use App\Domain\Governance\Enums\PollStatus;
use App\Domain\Governance\Enums\PollType;
use App\Domain\Governance\Models\Poll;
use App\Domain\Governance\Models\Vote;
use App\Domain\Governance\Strategies\AssetWeightedVoteStrategy;
use App\Domain\Governance\Strategies\OneUserOneVoteStrategy;
use App\Domain\Governance\ValueObjects\PollResult;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class GovernanceService
{
    private array $votingStrategies = [];

    public function __construct()
    {
        $this->registerVotingStrategies();
    }

    private function registerVotingStrategies(): void
    {
        $this->votingStrategies = [
            'one_user_one_vote'   => new OneUserOneVoteStrategy(),
            'asset_weighted_vote' => new AssetWeightedVoteStrategy(),
        ];
    }

    public function createPoll(array $data): Poll
    {
        DB::beginTransaction();

        try {
            $poll = Poll::create(
                [
                    'title'                  => $data['title'],
                    'description'            => $data['description'] ?? null,
                    'type'                   => PollType::from($data['type']),
                    'options'                => $data['options'],
                    'start_date'             => $data['start_date'],
                    'end_date'               => $data['end_date'],
                    'status'                 => PollStatus::from($data['status'] ?? 'draft'),
                    'required_participation' => $data['required_participation'] ?? null,
                    'voting_power_strategy'  => $data['voting_power_strategy'] ?? 'one_user_one_vote',
                    'execution_workflow'     => $data['execution_workflow'] ?? null,
                    'created_by'             => $data['created_by'],
                    'metadata'               => $data['metadata'] ?? [],
                ]
            );

            DB::commit();

            return $poll;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException('Failed to create poll: ' . $e->getMessage(), 0, $e);
        }
    }

    public function activatePoll(Poll $poll): bool
    {
        if ($poll->status !== PollStatus::DRAFT) {
            throw new InvalidArgumentException('Only draft polls can be activated');
        }

        if ($poll->start_date > now()) {
            throw new InvalidArgumentException('Poll cannot be activated before its start date');
        }

        if ($poll->end_date <= now()) {
            throw new InvalidArgumentException('Poll cannot be activated after its end date');
        }

        return $poll->update(['status' => PollStatus::ACTIVE]);
    }

    public function castVote(Poll $poll, User $user, array $selectedOptions): Vote
    {
        if (! $poll->canVote()) {
            throw new InvalidArgumentException('Poll is not available for voting');
        }

        if ($poll->hasUserVoted($user->uuid)) {
            throw new InvalidArgumentException('User has already voted in this poll');
        }

        $strategy = $this->getVotingStrategy($poll->voting_power_strategy);

        if (! $strategy->canVote($user, $poll)) {
            throw new InvalidArgumentException('User is not eligible to vote in this poll');
        }

        $votingPower = $strategy->calculatePower($user, $poll);

        if ($votingPower <= 0) {
            throw new InvalidArgumentException('User has no voting power for this poll');
        }

        $this->validateSelectedOptions($poll, $selectedOptions);

        DB::beginTransaction();

        try {
            $vote = Vote::create(
                [
                    'poll_id'          => $poll->id,
                    'user_uuid'        => $user->uuid,
                    'selected_options' => $selectedOptions,
                    'voting_power'     => $votingPower,
                    'voted_at'         => now(),
                    'metadata'         => [
                        'strategy_used' => $poll->voting_power_strategy,
                        'user_agent'    => request()->userAgent(),
                        'ip_address'    => request()->ip(),
                    ],
                ]
            );

            DB::commit();

            return $vote;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException('Failed to cast vote: ' . $e->getMessage(), 0, $e);
        }
    }

    public function completePoll(Poll $poll): PollResult
    {
        if ($poll->status !== PollStatus::ACTIVE) {
            throw new InvalidArgumentException('Only active polls can be completed');
        }

        if (! $poll->isExpired()) {
            throw new InvalidArgumentException('Poll has not expired yet');
        }

        DB::beginTransaction();

        try {
            $result = $poll->calculateResults();

            $poll->update(
                [
                    'status'   => PollStatus::CLOSED,
                    'metadata' => array_merge(
                        $poll->metadata ?? [],
                        [
                            'results'      => $result->toArray(),
                            'completed_at' => now()->toISOString(),
                        ]
                    ),
                ]
            );

            // Execute workflow if configured and requirements met
            if ($poll->execution_workflow && $this->shouldExecuteWorkflow($poll, $result)) {
                $this->executeWorkflow($poll, $result);
            }

            DB::commit();

            return $result;
        } catch (Exception $e) {
            DB::rollBack();
            throw new RuntimeException('Failed to complete poll: ' . $e->getMessage(), 0, $e);
        }
    }

    public function cancelPoll(Poll $poll, ?string $reason = null): bool
    {
        if ($poll->status === PollStatus::CLOSED) {
            throw new InvalidArgumentException('Cannot cancel a completed poll');
        }

        return $poll->update(
            [
                'status'   => PollStatus::CANCELLED,
                'metadata' => array_merge(
                    $poll->metadata ?? [],
                    [
                        'cancelled_at'        => now()->toISOString(),
                        'cancellation_reason' => $reason,
                    ]
                ),
            ]
        );
    }

    public function getActivePolls(): Collection
    {
        return Poll::active()->with(['creator', 'votes'])->get();
    }

    public function getUserVotingPower(User $user, Poll $poll): int
    {
        $strategy = $this->getVotingStrategy($poll->voting_power_strategy);

        return $strategy->calculatePower($user, $poll);
    }

    public function canUserVote(User $user, Poll $poll): bool
    {
        if (! $poll->canVote()) {
            return false;
        }

        if ($poll->hasUserVoted($user->uuid)) {
            return false;
        }

        $strategy = $this->getVotingStrategy($poll->voting_power_strategy);

        return $strategy->canVote($user, $poll);
    }

    public function getPollResults(Poll $poll): PollResult
    {
        return $poll->calculateResults();
    }

    public function getVotingStrategy(string $strategyName): IVotingPowerStrategy
    {
        if (! isset($this->votingStrategies[$strategyName])) {
            throw new InvalidArgumentException("Unknown voting strategy: {$strategyName}");
        }

        return $this->votingStrategies[$strategyName];
    }

    public function getAvailableVotingStrategies(): array
    {
        return array_values(
            array_map(
                fn (IVotingPowerStrategy $strategy) => [
                    'name'        => $strategy->getName(),
                    'description' => $strategy->getDescription(),
                ],
                $this->votingStrategies
            )
        );
    }

    private function validateSelectedOptions(Poll $poll, array $selectedOptions): void
    {
        if (empty($selectedOptions)) {
            throw new InvalidArgumentException('At least one option must be selected');
        }

        $validOptionIds = array_column($poll->options ?? [], 'id');

        foreach ($selectedOptions as $optionId) {
            if (! in_array($optionId, $validOptionIds, true)) {
                throw new InvalidArgumentException("Invalid option ID: {$optionId}");
            }
        }

        // Validate based on poll type
        match ($poll->type) {
            PollType::SINGLE_CHOICE, PollType::YES_NO => $this->validateSingleChoice($selectedOptions),
            PollType::MULTIPLE_CHOICE => $this->validateMultipleChoice($selectedOptions, count($validOptionIds)),
            PollType::WEIGHTED_CHOICE => $this->validateWeightedChoice($selectedOptions),
            PollType::RANKED_CHOICE   => $this->validateRankedChoice($selectedOptions, $validOptionIds),
        };
    }

    private function validateSingleChoice(array $selectedOptions): void
    {
        if (count($selectedOptions) !== 1) {
            throw new InvalidArgumentException('Exactly one option must be selected for single choice polls');
        }
    }

    private function validateMultipleChoice(array $selectedOptions, int $maxOptions): void
    {
        if (count($selectedOptions) > $maxOptions) {
            throw new InvalidArgumentException('Too many options selected');
        }

        if (count($selectedOptions) !== count(array_unique($selectedOptions))) {
            throw new InvalidArgumentException('Duplicate options are not allowed');
        }
    }

    private function validateWeightedChoice(array $selectedOptions): void
    {
        // For weighted choice, each option should have an associated weight
        // This implementation assumes simple selection for now
        $this->validateMultipleChoice($selectedOptions, count($selectedOptions));
    }

    private function validateRankedChoice(array $selectedOptions, array $validOptionIds): void
    {
        if (count($selectedOptions) < 2) {
            throw new InvalidArgumentException('At least two options must be ranked');
        }

        if (count($selectedOptions) !== count(array_unique($selectedOptions))) {
            throw new InvalidArgumentException('Each option can only be ranked once');
        }
    }

    private function shouldExecuteWorkflow(Poll $poll, PollResult $result): bool
    {
        // Check if minimum participation requirement is met
        if ($poll->required_participation) {
            if ($result->participationRate < $poll->required_participation) {
                return false;
            }
        }

        // Check if there's a clear winner
        return $result->hasWinner();
    }

    private function executeWorkflow(Poll $poll, PollResult $result): void
    {
        logger()->info(
            'Poll workflow execution requested',
            [
                'poll_uuid'          => $poll->uuid,
                'workflow'           => $poll->execution_workflow,
                'winning_option'     => $result->winningOption,
                'participation_rate' => $result->participationRate,
            ]
        );

        try {
            $workflowResult = match ($poll->execution_workflow) {
                'AddAssetWorkflow' => app(\App\Domain\Governance\Workflows\AddAssetWorkflow::class)
                    ->execute($poll, $result),
                'FeatureToggleWorkflow' => app(\App\Domain\Governance\Workflows\FeatureToggleWorkflow::class)
                    ->execute($poll, $result),
                'UpdateConfigurationWorkflow' => app(\App\Domain\Governance\Workflows\UpdateConfigurationWorkflow::class)
                    ->execute($poll, $result),
                default => [
                    'success'   => false,
                    'message'   => "Unknown workflow: {$poll->execution_workflow}",
                    'poll_uuid' => $poll->uuid,
                ]
            };

            // Update poll metadata with workflow execution result
            $poll->update(
                [
                    'metadata' => array_merge(
                        $poll->metadata ?? [],
                        [
                            'workflow_execution' => array_merge(
                                $workflowResult,
                                [
                                    'executed_at' => now()->toISOString(),
                                ]
                            ),
                        ]
                    ),
                ]
            );

            logger()->info(
                'Poll workflow execution completed',
                [
                    'poll_uuid' => $poll->uuid,
                    'workflow'  => $poll->execution_workflow,
                    'result'    => $workflowResult,
                ]
            );
        } catch (Exception $e) {
            logger()->error(
                'Poll workflow execution failed',
                [
                    'poll_uuid' => $poll->uuid,
                    'workflow'  => $poll->execution_workflow,
                    'error'     => $e->getMessage(),
                ]
            );

            // Update poll with failure information
            $poll->update(
                [
                    'metadata' => array_merge(
                        $poll->metadata ?? [],
                        [
                            'workflow_execution' => [
                                'success'     => false,
                                'message'     => 'Workflow execution failed: ' . $e->getMessage(),
                                'poll_uuid'   => $poll->uuid,
                                'executed_at' => now()->toISOString(),
                            ],
                        ]
                    ),
                ]
            );
        }
    }
}
