<?php

namespace App\Actions\Jetstream;

use App\Models\Team;
use App\Models\User;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Jetstream\Contracts\InvitesTeamMembers;
use Laravel\Jetstream\Events\InvitingTeamMember;
use Laravel\Jetstream\Jetstream;
use Laravel\Jetstream\Mail\TeamInvitation;
use Laravel\Jetstream\Rules\Role;

class InviteTeamMember implements InvitesTeamMembers
{
    /**
     * Invite a new team member to the given team.
     */
    public function invite(User $user, Team $team, string $email, ?string $role = null): void
    {
        Gate::forUser($user)->authorize('addTeamMember', $team);

        $this->validate($team, $email, $role);

        $this->throttle($user, $team, $email);

        InvitingTeamMember::dispatch($team, $email, $role);

        /** @var \Laravel\Jetstream\TeamInvitation $invitation */
        $invitation = $team->teamInvitations()->create(
            [
                'email' => $email,
                'role'  => $role,
            ]
        );

        Mail::to($email)->send(new TeamInvitation($invitation));
    }

    /**
     * Every invitation is an email from the platform's own domain to an
     * address the inviter chooses: cap them per inviter (across all of the
     * inviter's teams), per recipient address within a team and
     * platform-wide (config jetstream.invitation_limits). The recipient
     * count is per team, so invitations from one team never block another
     * team from inviting the same address. Counted when the mail is about
     * to go out, so cancelling and re-inviting counts again.
     *
     * New inviters (a young account or an unverified email address) get a
     * smaller daily share and draw on their own platform-wide pool, so
     * throwaway accounts cannot use up the pool established users rely on.
     *
     * @throws ValidationException
     */
    protected function throttle(User $user, Team $team, string $email): void
    {
        $limits = (array) config('jetstream.invitation_limits', []);
        $inviter = (string) $user->getKey();
        $teamKey = (string) $team->getKey();
        $recipient = hash('sha256', mb_strtolower(trim($email)));
        $perInviterDaily = (int) ($limits['per_inviter_daily'] ?? 50);

        if ($this->isNewInviter($user, (int) ($limits['new_inviter_days'] ?? 7))) {
            $perInviterDaily = min($perInviterDaily, (int) ($limits['per_new_inviter_daily'] ?? 20));
            $globalBucket = ['team_invitations:global:new_inviters:day', (int) ($limits['new_inviters_global_daily'] ?? 200), 86400];
        } else {
            $globalBucket = ['team_invitations:global:day', (int) ($limits['global_daily'] ?? 1000), 86400];
        }

        $buckets = [
            ["team_invitations:inviter:{$inviter}:hour", (int) ($limits['per_inviter_hourly'] ?? 10), 3600],
            ["team_invitations:inviter:{$inviter}:day", $perInviterDaily, 86400],
            ["team_invitations:team:{$teamKey}:recipient:{$recipient}:day", (int) ($limits['per_recipient_daily'] ?? 3), 86400],
            $globalBucket,
        ];

        foreach ($buckets as [$key, $limit, $ttl]) {
            Cache::add($key, 0, $ttl);

            if ((int) Cache::increment($key) > $limit) {
                throw ValidationException::withMessages(
                    ['email' => __('Too many invitations have been sent. Please try again later.')]
                )->errorBag('addTeamMember');
            }
        }
    }

    /**
     * An inviter whose account is younger than the given number of days, or
     * whose email address is not verified.
     */
    protected function isNewInviter(User $user, int $days): bool
    {
        if ($user->email_verified_at === null || $user->created_at === null) {
            return true;
        }

        return $user->created_at->gt(now()->subDays(max(0, $days)));
    }

    /**
     * Validate the invite member operation.
     */
    protected function validate(Team $team, string $email, ?string $role): void
    {
        Validator::make(
            [
                'email' => $email,
                'role'  => $role,
            ],
            $this->rules($team),
            [
                'email.unique' => __('This user has already been invited to the team.'),
            ]
        )->after(
            $this->ensureUserIsNotAlreadyOnTeam($team, $email)
        )->validateWithBag('addTeamMember');
    }

    /**
     * Get the validation rules for inviting a team member.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    protected function rules(Team $team): array
    {
        return array_filter(
            [
                'email' => [
                    'required', 'email',
                    Rule::unique(Jetstream::teamInvitationModel())->where(
                        function (Builder $query) use ($team) {
                            $query->where('team_id', $team->id);
                        }
                    ),
                ],
                'role' => Jetstream::hasRoles()
                                ? ['required', 'string', new Role()]
                                : null,
            ]
        );
    }

    /**
     * Ensure that the user is not already on the team.
     */
    protected function ensureUserIsNotAlreadyOnTeam(Team $team, string $email): Closure
    {
        return function ($validator) use ($team, $email) {
            $validator->errors()->addIf(
                $team->hasUserWithEmail($email),
                'email',
                __('This user already belongs to the team.')
            );
        };
    }
}
