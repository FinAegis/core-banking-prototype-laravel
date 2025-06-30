<?php

namespace App\Actions\Fortify;

use App\Events\BusinessUserCreated;
use App\Models\Team;
use App\Models\User;
use App\Values\UserRoles;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Laravel\Jetstream\Jetstream;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * Create a newly registered user.
     *
     * @param array<string, string> $input
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'is_business_customer' => ['boolean'],
            'password' => $this->passwordRules(),
            'terms' => Jetstream::hasTermsAndPrivacyPolicyFeature() ? ['accepted', 'required'] : '',
        ])->validate();

        $user = User::create([
            'name' => $input['name'],
            'email' => $input['email'],
            'password' => Hash::make($input['password']),
        ]);

        $team = $this->createTeam($user);

        if (isset($input['is_business_customer']) && $input['is_business_customer']) {
            $user->assignRole('customer_business');
            
            // Convert personal team to business organization
            $team->update([
                'is_business_organization' => true,
                'organization_type' => 'business',
                'max_users' => 10, // Default limit for business accounts
                'allowed_roles' => [
                    'compliance_officer',
                    'risk_manager',
                    'accountant',
                    'operations_manager',
                    'customer_service',
                ],
            ]);
            
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
        return $user->ownedTeams()->save(Team::forceCreate([
            'user_id' => $user->id,
            'name' => explode(' ', $user->name, 2)[0]."'s Team",
            'personal_team' => true,
        ]));
    }
}
