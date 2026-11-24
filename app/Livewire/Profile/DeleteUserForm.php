<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Domain\Compliance\Exceptions\AccountClosureBlockedException;
use App\Models\User;
use App\Support\RecentSignIn;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Jetstream\Contracts\DeletesUsers;
use Laravel\Jetstream\Http\Livewire\DeleteUserForm as JetstreamDeleteUserForm;
use Throwable;

/**
 * The profile page's account deletion form. Jetstream's form asks for the
 * password, but users who sign in to the website with an email code do not
 * know one. They confirm by typing DELETE instead, and they must have signed
 * in within the last 15 minutes. Users who sign in with a password still
 * confirm with it. Either way the account is erased through
 * AccountClosureService (via the DeleteUser action), like in the app.
 */
class DeleteUserForm extends JetstreamDeleteUserForm
{
    public const CONFIRMATION_WORD = 'DELETE';

    /**
     * The word a user without a password types to confirm.
     *
     * @var string
     */
    public $confirmation = '';

    public function confirmUserDeletion()
    {
        parent::confirmUserDeletion();

        $this->confirmation = '';
    }

    /**
     * @return Redirector|RedirectResponse
     */
    public function deleteUser(Request $request, DeletesUsers $deleter, StatefulGuard $auth)
    {
        $this->resetErrorBag();

        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        $field = $this->confirmsWithWord($user) ? 'confirmation' : 'password';

        if ($field === 'confirmation') {
            if (trim((string) $this->confirmation) !== self::CONFIRMATION_WORD) {
                throw ValidationException::withMessages([
                    'confirmation' => [__('Type DELETE to confirm.')],
                ]);
            }

            if (! RecentSignIn::isRecent()) {
                throw ValidationException::withMessages([
                    'confirmation' => [__('You signed in more than 15 minutes ago. Sign in again, then delete your account.')],
                ]);
            }
        } elseif (! Hash::check((string) $this->password, $user->password)) {
            throw ValidationException::withMessages([
                'password' => [__('This password does not match our records.')],
            ]);
        }

        try {
            $deleter->delete($user->fresh() ?? $user);
        } catch (AccountClosureBlockedException) {
            throw ValidationException::withMessages([
                $field => [__('Your account cannot be deleted yet. Please contact support.')],
            ]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages([
                $field => [__('We could not delete your account. You are still signed in, so you can try again.')],
            ]);
        }

        $auth->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect(config('fortify.redirects.logout') ?? '/');
    }

    /**
     * Signs the user out and sends them to the sign-in page. After signing
     * in they come back to the profile page with a recent sign-in.
     *
     * @return Redirector|RedirectResponse
     */
    public function signInAgain(Request $request, StatefulGuard $auth)
    {
        $auth->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        session()->put('url.intended', route('profile.show'));

        return redirect()->route('login');
    }

    /**
     * @return View
     */
    public function render()
    {
        $user = Auth::user();
        $confirmsWithWord = $user instanceof User && $this->confirmsWithWord($user);

        return view('profile.delete-user-form', [
            'confirmsWithWord' => $confirmsWithWord,
            'signedInRecently' => $confirmsWithWord && RecentSignIn::isRecent(),
        ]);
    }

    /**
     * The website signs users in with an email code when the Privy web
     * sign-in is on. Those users have no password they know.
     */
    private function confirmsWithWord(User $user): bool
    {
        return (bool) config('privy.web_login_enabled', false) && $user->privy_user_id !== null;
    }
}
