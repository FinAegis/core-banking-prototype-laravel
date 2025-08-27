<?php

namespace App\Domain\Stablecoin\Aggregates;

use App\Domain\Stablecoin\Events\ProposalCancelled;
use App\Domain\Stablecoin\Events\ProposalCreated;
use App\Domain\Stablecoin\Events\ProposalExecuted;
use App\Domain\Stablecoin\Events\ProposalFinalized;
use App\Domain\Stablecoin\Events\ProposalVoteCast;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use InvalidArgumentException;
use Spatie\EventSourcing\AggregateRoots\AggregateRoot;

class GovernanceProposal extends AggregateRoot
{
    protected string $proposalType;

    protected string $title;

    protected string $description;

    protected array $parameters;

    protected string $proposer;

    protected Carbon $startTime;

    protected Carbon $endTime;

    protected string $status = 'pending';

    protected array $votes = []; // voter => [choice, weight]

    protected array $votesSummary = [
        'for'     => '0',
        'against' => '0',
        'abstain' => '0',
    ];

    protected string $quorumRequired;

    protected string $approvalThreshold;

    protected ?array $executionResult = null;

    public static function create(
        string $proposalId,
        string $proposalType,
        string $title,
        string $description,
        array $parameters,
        string $proposer,
        Carbon $startTime,
        Carbon $endTime,
        string $quorumRequired = '0.1', // 10%
        string $approvalThreshold = '0.5' // 50%
    ): self {
        $proposal = static::retrieve($proposalId);

        $proposal->recordThat(
            new ProposalCreated(
                proposalId: $proposalId,
                proposalType: $proposalType,
                title: $title,
                description: $description,
                parameters: $parameters,
                proposer: $proposer,
                startTime: $startTime,
                endTime: $endTime,
                quorumRequired: $quorumRequired,
                approvalThreshold: $approvalThreshold
            )
        );

        return $proposal;
    }

    public function castVote(
        string $voter,
        string $choice,
        string $votingPower,
        string $reason = ''
    ): self {
        if ($this->status !== 'active') {
            throw new InvalidArgumentException('Proposal is not active for voting');
        }

        if (! in_array($choice, ['for', 'against', 'abstain'])) {
            throw new InvalidArgumentException('Invalid vote choice');
        }

        if (isset($this->votes[$voter])) {
            throw new InvalidArgumentException('Voter has already cast a vote');
        }

        if (now()->isBefore($this->startTime)) {
            throw new InvalidArgumentException('Voting has not started yet');
        }

        if (now()->isAfter($this->endTime)) {
            throw new InvalidArgumentException('Voting has ended');
        }

        $this->recordThat(
            new ProposalVoteCast(
                proposalId: $this->uuid(),
                voter: $voter,
                choice: $choice,
                votingPower: $votingPower,
                reason: $reason,
                timestamp: now()
            )
        );

        return $this;
    }

    public function finalize(): self
    {
        if ($this->status !== 'active') {
            throw new InvalidArgumentException('Proposal is not active');
        }

        if (now()->isBefore($this->endTime)) {
            throw new InvalidArgumentException('Voting period has not ended');
        }

        $result = $this->calculateResult();

        $this->recordThat(
            new ProposalFinalized(
                proposalId: $this->uuid(),
                result: $result['decision'],
                totalVotes: $result['total_votes'],
                votesSummary: $result['votes_summary'],
                quorumReached: $result['quorum_reached'],
                approvalRate: $result['approval_rate']
            )
        );

        return $this;
    }

    public function execute(array $executionData): self
    {
        if ($this->status !== 'passed') {
            throw new InvalidArgumentException('Only passed proposals can be executed');
        }

        $this->recordThat(
            new ProposalExecuted(
                proposalId: $this->uuid(),
                executedBy: $executionData['executed_by'],
                executionData: $executionData,
                timestamp: now()
            )
        );

        return $this;
    }

    public function cancel(string $reason, string $cancelledBy): self
    {
        if (in_array($this->status, ['executed', 'cancelled'])) {
            throw new InvalidArgumentException('Cannot cancel proposal in current status');
        }

        $this->recordThat(
            new ProposalCancelled(
                proposalId: $this->uuid(),
                reason: $reason,
                cancelledBy: $cancelledBy,
                timestamp: now()
            )
        );

        return $this;
    }

    protected function applyProposalCreated(ProposalCreated $event): void
    {
        $this->proposalType = $event->proposalType;
        $this->title = $event->title;
        $this->description = $event->description;
        $this->parameters = $event->parameters;
        $this->proposer = $event->proposer;
        $this->startTime = $event->startTime;
        $this->endTime = $event->endTime;
        $this->quorumRequired = $event->quorumRequired;
        $this->approvalThreshold = $event->approvalThreshold;
        $this->status = now()->isBefore($event->startTime) ? 'pending' : 'active';
    }

    protected function applyProposalVoteCast(ProposalVoteCast $event): void
    {
        $this->votes[$event->voter] = [
            'choice'    => $event->choice,
            'weight'    => $event->votingPower,
            'reason'    => $event->reason,
            'timestamp' => $event->timestamp,
        ];

        // Update vote summary
        $currentTotal = BigDecimal::of($this->votesSummary[$event->choice]);
        $this->votesSummary[$event->choice] = $currentTotal->plus($event->votingPower)->__toString();
    }

    protected function applyProposalFinalized(ProposalFinalized $event): void
    {
        $this->status = $event->result;
        $this->votesSummary = $event->votesSummary;
    }

    protected function applyProposalExecuted(ProposalExecuted $event): void
    {
        $this->status = 'executed';
        $this->executionResult = $event->executionData;
    }

    protected function applyProposalCancelled(ProposalCancelled $event): void
    {
        $this->status = 'cancelled';
    }

    private function calculateResult(): array
    {
        $totalVotes = BigDecimal::of('0');
        $forVotes = BigDecimal::of($this->votesSummary['for']);
        $againstVotes = BigDecimal::of($this->votesSummary['against']);
        $abstainVotes = BigDecimal::of($this->votesSummary['abstain']);

        $totalVotes = $forVotes->plus($againstVotes)->plus($abstainVotes);

        // Assume total voting power is 1000000 tokens for quorum calculation
        // In production, this would be fetched from governance token supply
        $totalVotingPower = BigDecimal::of('1000000');
        $quorumReached = $totalVotes->dividedBy($totalVotingPower, 4, RoundingMode::DOWN)
            ->isGreaterThanOrEqualTo($this->quorumRequired);

        $approvalRate = '0';
        if (! $forVotes->plus($againstVotes)->isZero()) {
            $approvalRate = $forVotes->dividedBy(
                $forVotes->plus($againstVotes),
                4,
                RoundingMode::DOWN
            )->__toString();
        }

        $decision = 'failed';
        if ($quorumReached && BigDecimal::of($approvalRate)->isGreaterThanOrEqualTo($this->approvalThreshold)) {
            $decision = 'passed';
        }

        return [
            'decision'       => $decision,
            'total_votes'    => $totalVotes->__toString(),
            'votes_summary'  => $this->votesSummary,
            'quorum_reached' => $quorumReached,
            'approval_rate'  => $approvalRate,
        ];
    }

    public function getStatus(): string
    {
        // Update status based on time if needed
        if ($this->status === 'pending' && now()->isAfter($this->startTime)) {
            $this->status = 'active';
        }

        if ($this->status === 'active' && now()->isAfter($this->endTime)) {
            $this->status = 'ended';
        }

        return $this->status;
    }

    public function getVotesSummary(): array
    {
        return $this->votesSummary;
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
