<?php

namespace App\Domain\Governance\Policies;

use App\Domain\Governance\Models\GcuVotingProposal;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\Access\HandlesAuthorization;

class GcuVotingProposalPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can view proposals
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(?User $user, GcuVotingProposal $proposal): bool
    {
        return true; // Anyone can view proposals
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only admins can create proposals
        return StaffRoles::userHasAny($user, StaffRoles::ADMINS);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GcuVotingProposal $proposal): bool
    {
        // Only admins can update proposals
        return StaffRoles::userHasAny($user, StaffRoles::ADMINS);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GcuVotingProposal $proposal): bool
    {
        // Only admins can delete proposals
        return StaffRoles::userHasAny($user, StaffRoles::ADMINS);
    }

    /**
     * Determine whether the user can vote on the proposal.
     */
    public function vote(User $user, GcuVotingProposal $proposal): bool
    {
        // User must have GCU holdings to vote
        /** @var \Illuminate\Database\Eloquent\Model|null $account */
        $account = $user->accounts()->first();
        if (! $account) {
            return false;
        }

        $gcuBalance = $account->balances()
            ->where('asset_code', 'GCU')
            ->first()?->balance ?? 0;

        return $gcuBalance > 0 && $proposal->isVotingActive();
    }
}
