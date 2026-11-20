<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Support\UserSignIns;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\UpdatesUserPasswords;

class UpdateUserPassword implements UpdatesUserPasswords
{
    use PasswordValidationRules;

    /**
     * Validate and update the user's password.
     *
     * @param  array<string, string>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make(
            $input,
            [
                'current_password' => ['required', 'string', 'current_password:web'],
                'password'         => $this->passwordRules(),
            ],
            [
                'current_password.current_password' => __('The provided password does not match your current password.'),
            ]
        )->validateWithBag('updatePassword');

        $user->forceFill(
            [
                'password' => Hash::make($input['password']),
            ]
        )->save();

        // End every other sign-in (mobile devices included; API keys stay),
        // but keep the browser that changed the password signed in:
        // AuthenticateSession compares this hash.
        $session = request()->hasSession() ? request()->session() : null;

        UserSignIns::endAllExcept($user, $session?->getId());

        $session?->put('password_hash_' . Auth::getDefaultDriver(), $user->getAuthPassword());
    }
}
