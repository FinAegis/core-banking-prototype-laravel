<?php

namespace App\Actions\Fortify;

use App\Domain\User\Services\ApiRegistrationGate;
use App\Models\Team;
use App\Models\User;
use App\Rules\TeamName;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param  array<string, string>  $input
     * @param  bool  $isApiRegistration  When true, skip the Fortify web registration gate and apply the
     *                                   API registration switch instead (config/registration.php)
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \App\Domain\User\Exceptions\RegistrationNotAllowedException when API registration is
     *                                                                         closed or invite-only without a valid invitation
     *                                                                         (email and invitation_token)
     */
    public function create(array $input, bool $isApiRegistration = false): User
    {
        // Block Fortify web registration when disabled (REGISTRATION_ENABLED).
        if (! $isApiRegistration && ! config('fortify.registration_enabled', false)) {
            abort(403, 'Admin registration is disabled. Users register via the API or CLI.');
        }

        // API sign-ups follow REGISTRATION_API_ENABLED / REGISTRATION_API_INVITE_ONLY.
        // The email is not verified here, so invite-only also needs the token.
        $gate = $isApiRegistration ? app(ApiRegistrationGate::class) : null;
        $invitation = $gate?->admitWithToken(
            (string) ($input['email'] ?? ''),
            isset($input['invitation_token']) ? (string) $input['invitation_token'] : null,
        );

        Validator::make(
            $input,
            [
                'name'                 => ['required', 'string', 'max:255'],
                'email'                => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'is_business_customer' => ['boolean'],
                'referral_code'        => ['nullable', 'string', 'max:20'],
                'password'             => $this->passwordRules(),
                'terms'                => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
            ]
        )->validate();

        $user = User::create(
            [
                'name'     => $input['name'],
                'email'    => $input['email'],
                'password' => Hash::make($input['password']),
            ]
        );

        $gate?->accepted($invitation, $user);

        // Track referral if code provided
        if (! empty($input['referral_code'])) {
            $referrer = User::where('referral_code', $input['referral_code'])->first();
            if ($referrer && $referrer->id !== $user->id) {
                $user->update(['referred_by' => $referrer->id]);
                Log::info('Referral tracked', [
                    'user_id'     => $user->id,
                    'referrer_id' => $referrer->id,
                    'code'        => $input['referral_code'],
                ]);
            } elseif (! $referrer) {
                Log::warning('Invalid referral code used during registration', [
                    'user_id' => $user->id,
                    'code'    => $input['referral_code'],
                ]);
            }
        }

        $team = $this->createTeam($user);

        if (isset($input['is_business_customer']) && $input['is_business_customer']) {
            $user->assignRole('customer_business');

            // Convert personal team to business organization. Team roles are
            // organisation labels only; staff role names (StaffRoles) are
            // never offered here.
            $team->update(
                [
                    'is_business_organization' => true,
                    'organization_type'        => 'business',
                    'max_users'                => 10, // Default limit for business accounts
                    'allowed_roles'            => [
                        'accountant',
                        'operations_manager',
                        'customer_service',
                    ],
                ]
            );

            // Assign owner role in the team
            $team->assignUserRole($user, 'owner');
        } else {
            $user->assignRole('customer_private');
        }

        return $user;
    }

    /**
     * Create a personal team for the user.
     */
    protected function createTeam(User $user): Team
    {
        $team = $user->ownedTeams()->save(
            Team::forceCreate(
                [
                    'user_id'       => $user->id,
                    'name'          => TeamName::personalTeamName((string) $user->name),
                    'personal_team' => true,
                ]
            )
        );

        /** @var Team $team */
        return $team;
    }
}
