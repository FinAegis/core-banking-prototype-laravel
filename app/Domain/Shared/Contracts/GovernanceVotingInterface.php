<?php

declare(strict_types=1);

namespace App\Domain\Shared\Contracts;

/**
 * Interface for governance voting operations used by external domains.
 *
 * This interface enables domain decoupling by allowing domains like
 * Basket, Stablecoin, CGO, etc. to integrate with governance voting
 * without directly depending on the Governance domain implementation.
 *
 * @see \App\Domain\Governance\Services\GovernanceService for implementation
 */
interface GovernanceVotingInterface
{
    /**
     * Proposal status constants.
     */
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PASSED = 'passed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXECUTED = 'executed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Create a new governance proposal.
     *
     * @param array{
     *     type: string,
     *     title: string,
     *     description: string,
     *     data: array<string, mixed>,
     *     voting_period_days?: int,
     *     quorum?: float,
     *     approval_threshold?: float,
     *     created_by: string
     * } $proposal Proposal details
     * @return array{
     *     id: string,
     *     uuid: string,
     *     status: string,
     *     voting_starts_at: string,
     *     voting_ends_at: string
     * }
     */
    public function createProposal(array $proposal): array;

    /**
     * Cast a vote on a proposal.
     *
     * @param string $proposalId Proposal UUID
     * @param string $voterId Voter UUID (user or agent)
     * @param bool $approve True for approve, false for reject
     * @param string|null $reason Optional reason for the vote
     * @return array{
     *     vote_id: string,
     *     vote_weight: string,
     *     recorded_at: string
     * }
     *
     * @throws \App\Domain\Governance\Exceptions\VotingClosedException
     * @throws \App\Domain\Governance\Exceptions\AlreadyVotedException
     */
    public function castVote(
        string $proposalId,
        string $voterId,
        bool $approve,
        ?string $reason = null
    ): array;

    /**
     * Get the current status of a proposal.
     *
     * @param string $proposalId Proposal UUID
     * @return array{
     *     id: string,
     *     status: string,
     *     votes_for: string,
     *     votes_against: string,
     *     total_votes: int,
     *     quorum_reached: bool,
     *     threshold_reached: bool,
     *     voting_ends_at: string
     * }|null Proposal status or null if not found
     */
    public function getProposalStatus(string $proposalId): ?array;

    /**
     * Check if a proposal has been approved.
     *
     * @param string $proposalId Proposal UUID
     * @return bool True if approved (quorum and threshold met)
     */
    public function isProposalApproved(string $proposalId): bool;

    /**
     * Get the voting power for a user/entity.
     *
     * @param string $voterId Voter UUID
     * @param string|null $context Optional context for weighted voting
     * @return array{
     *     voting_power: string,
     *     weight_type: string,
     *     eligible: bool
     * }
     */
    public function getVotingPower(string $voterId, ?string $context = null): array;

    /**
     * Execute an approved proposal.
     *
     * @param string $proposalId Proposal UUID
     * @return array{
     *     executed: bool,
     *     executed_at: string,
     *     result: array<string, mixed>
     * }
     *
     * @throws \App\Domain\Governance\Exceptions\ProposalNotApprovedException
     * @throws \App\Domain\Governance\Exceptions\AlreadyExecutedException
     */
    public function executeProposal(string $proposalId): array;

    /**
     * Get proposals by type.
     *
     * @param string $type Proposal type (e.g., 'basket_composition')
     * @param string|null $status Optional status filter
     * @return array<array{
     *     id: string,
     *     title: string,
     *     status: string,
     *     created_at: string,
     *     voting_ends_at: string
     * }>
     */
    public function getProposalsByType(string $type, ?string $status = null): array;
}
