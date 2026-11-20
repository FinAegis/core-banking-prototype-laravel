<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\UserSignIns;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\ResetsUserPasswords;

class ResetUserPassword implements ResetsUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and reset the user's forgotten password.
     *
     * @param  array<string, string>  $input
     */
    public function reset(User $user, array $input): void
    {
        Validator::make(
            $input,
            [
                'password' => $this->passwordRules(),
            ]
        )->validate();

        $user->forceFill(
            [
                'password' => Hash::make($input['password']),
            ]
        )->save();

        // Whoever reset the password controls the account now: end every
        // other sign-in (API tokens, OAuth grants, remember-me, sessions,
        // mobile devices) and the API keys.
        UserSignIns::endAll($user);
    }
}
