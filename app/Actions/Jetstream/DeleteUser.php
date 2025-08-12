<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use App\Traits\HandlesNestedTransactions;
use Laravel\Jetstream\Contracts\DeletesTeams;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    use HandlesNestedTransactions;

    /**
     * Create a new action instance.
     */
    public function __construct(protected DeletesTeams $deletesTeams)
    {
    }

    /**
     * Delete the given user.
     */
    public function delete(User $user): void
    {
        $this->executeInTransaction(
            function () use ($user) {
                $this->deleteTeams($user);
                $user->deleteProfilePhoto();
                $user->tokens->each->delete();
                $user->delete();
            }
        );
    }

    /**
     * Delete the teams and team associations attached to the user.
     */
    protected function deleteTeams(User $user): void
    {
        $user->teams()->detach();

        /** @var \Illuminate\Support\Collection<int, Team> $ownedTeams */
        $ownedTeams = $user->ownedTeams;
        $ownedTeams->each(
            function (Team $team) {
                $this->deletesTeams->delete($team);
            }
        );
    }
}
