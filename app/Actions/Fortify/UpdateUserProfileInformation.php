<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Profile updates from the web (Fortify PUT /user/profile-information and the
 * Jetstream profile form).
 *
 * A new email address never replaces the old one straight away. Otherwise
 * anyone could take an address they do not control, block its owner's
 * sign-up and look like an allowlisted operator. For an account linked to
 * the sign-in provider (Privy), the email address is the sign-in identity,
 * so it cannot be changed here at all. For other accounts a confirmation
 * link goes to the new address, and the change is applied when the link is
 * opened ({@see \App\Http\Controllers\Web\ConfirmEmailChangeController}).
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /** How long the confirmation link works. */
    public const CONFIRMATION_MINUTES = 60;

    /** Confirmation emails a user may trigger per day. */
    public const DAILY_EMAIL_CHANGE_LIMIT = 3;

    /**
     * Validate and update the given user's profile information.
     *
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make(
            $input,
            [
                'name'  => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
                'photo' => ['nullable', 'mimes:jpg,jpeg,png', 'max:1024'],
            ]
        )->validateWithBag('updateProfileInformation');

        $newEmail = (string) $input['email'];
        $emailChanged = $newEmail !== $user->email;

        if ($emailChanged && $user->privy_user_id !== null) {
            throw ValidationException::withMessages([
                'email' => __('You sign in with this email address, so it cannot be changed here.'),
            ])->errorBag('updateProfileInformation');
        }

        if ($emailChanged) {
            $this->throttleEmailChange($user);
        }

        if (isset($input['photo'])) {
            $user->updateProfilePhoto($input['photo']);
        }

        $user->forceFill(['name' => $input['name']])->save();

        if ($emailChanged) {
            $this->sendConfirmationLink($user, $newEmail);
        }
    }

    /**
     * A fingerprint of the account's current email address. The confirmation
     * link carries it, so a link stops working once the address changes.
     */
    public static function emailFingerprint(string $email): string
    {
        return hash_hmac('sha256', mb_strtolower($email), (string) config('app.key'));
    }

    /**
     * Each confirmation is an email to an address the user typed, so cap them
     * per user and day.
     *
     * @throws ValidationException
     */
    protected function throttleEmailChange(User $user): void
    {
        $key = 'profile_email_change:' . $user->getKey() . ':day';
        Cache::add($key, 0, 86400);

        if ((int) Cache::increment($key) > self::DAILY_EMAIL_CHANGE_LIMIT) {
            throw ValidationException::withMessages([
                'email' => __('You have asked to change your email address too many times today. Try again tomorrow.'),
            ])->errorBag('updateProfileInformation');
        }
    }

    protected function sendConfirmationLink(User $user, string $newEmail): void
    {
        $url = URL::temporarySignedRoute(
            'user.email-change.confirm',
            now()->addMinutes(self::CONFIRMATION_MINUTES),
            [
                'id'    => $user->getKey(),
                'from'  => self::emailFingerprint((string) $user->email),
                'email' => Crypt::encryptString($newEmail),
            ],
        );

        Notification::route('mail', $newEmail)
            ->notify(new ConfirmEmailChange($url, self::CONFIRMATION_MINUTES));
    }
}
