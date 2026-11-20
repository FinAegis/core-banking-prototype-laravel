<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use App\Models\Team;
use App\Models\User;
use App\Rules\TeamName;

/**
 * Provisions a Jetstream personal team for users created outside the
 * Fortify {@see \App\Actions\Fortify\CreateNewUser} flow — i.e. the Privy
 * email-OTP signup paths (web {@see \App\Http\Controllers\Web\PrivyWebAuthController}
 * and mobile {@see \App\Http\Controllers\Api\Auth\LoginController::privyLogin}).
 *
 * Team features are enabled (config/jetstream.php), so every team-aware
 * Blade view dereferences `Auth::user()->currentTeam` (e.g.
 * navigation-menu.blade.php → `currentTeam->name`). `HasTeams::currentTeam()`
 * only auto-switches to the personal team when one already EXISTS; a
 * teamless user yields a null `currentTeam` and the first team-aware page
 * 500s. Password signups get their team from `CreateNewUser::createTeam()`
 * — the Privy paths must mirror that or new users hit a 500 the moment
 * they land on the dashboard.
 *
 * {@see ensurePersonalTeam()} runs on every Privy login (not just signup):
 * it also heals users left teamless by an earlier code path — mobile users
 * created before team provisioning existed, and web users whose pre-fix
 * signup 500'd *after* the User row was already inserted (a retry lands on
 * the returning-user branch and would otherwise 500 forever).
 */
trait ProvisionsPersonalTeam
{
    /**
     * Return the user's personal team, creating one if it is missing.
     *
     * Idempotent — safe to call on every login.
     */
    protected function ensurePersonalTeam(User $user): Team
    {
        $existing = Team::where('user_id', $user->id)
            ->where('personal_team', true)
            ->first();

        if ($existing instanceof Team) {
            return $existing;
        }

        return $this->createPersonalTeam($user);
    }

    /**
     * Create and persist the user's personal team. Mirrors
     * {@see \App\Actions\Fortify\CreateNewUser::createTeam()}.
     */
    protected function createPersonalTeam(User $user): Team
    {
        /** @var Team $team */
        $team = $user->ownedTeams()->save(
            Team::forceCreate([
                'user_id'       => $user->id,
                'name'          => TeamName::personalTeamName((string) $user->name),
                'personal_team' => true,
            ])
        );

        return $team;
    }
}
