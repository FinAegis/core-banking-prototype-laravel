<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Applies an email change from the signed link sent to the new address
 * ({@see UpdateUserProfileInformation}). Opening the link proves the user
 * controls that address, so the new address counts as verified. The link
 * works only for the signed-in user it was made for, and only while the
 * account still has the email address it had when the link was sent.
 */
final class ConfirmEmailChangeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User || ! hash_equals((string) $user->getKey(), $this->queryString($request, 'id'))) {
            abort(403);
        }

        if ($user->privy_user_id !== null) {
            return $this->refuse(__('You sign in with this email address, so it cannot be changed here.'));
        }

        if (! hash_equals(UpdateUserProfileInformation::emailFingerprint((string) $user->email), $this->queryString($request, 'from'))) {
            return $this->refuse(__('This link is no longer valid. Change your email address again to get a new link.'));
        }

        try {
            $email = Crypt::decryptString($this->queryString($request, 'email'));
        } catch (DecryptException) {
            abort(403);
        }

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            abort(403);
        }

        if (User::query()->where('email', $email)->whereKeyNot($user->getKey())->exists()) {
            return $this->refuse(__('This email address is already used by another account.'));
        }

        $previous = (string) $user->email;

        try {
            $user->forceFill([
                'email'             => $email,
                'email_verified_at' => now(),
            ])->save();
        } catch (UniqueConstraintViolationException) {
            return $this->refuse(__('This email address is already used by another account.'));
        }

        Log::info('User email address changed after confirmation', [
            'user_id'        => $user->getKey(),
            'previous_email' => hash('sha256', mb_strtolower($previous)),
        ]);

        return redirect()->route('profile.show')
            ->with('flash.banner', __('Your email address has been changed.'))
            ->with('flash.bannerStyle', 'success');
    }

    private function queryString(Request $request, string $key): string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : '';
    }

    private function refuse(string $message): RedirectResponse
    {
        return redirect()->route('profile.show')
            ->with('flash.banner', $message)
            ->with('flash.bannerStyle', 'danger');
    }
}
