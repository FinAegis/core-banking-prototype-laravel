<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use App\Models\User;

/**
 * Decides whether a password may be used to sign in, under
 * auth.password_login_enabled (AUTH_PASSWORD_LOGIN_ENABLED).
 *
 * While the switch is off, only an active app-store reviewer account
 * (account_flags.is_review_account, not disabled, not expired) may still
 * sign in with its password. Reviewer accounts use .invalid addresses that
 * never receive the Privy email code. Password reset has no exception.
 */
final class PasswordSignInGate
{
    public const LOGIN_DISABLED_MESSAGE = 'Signing in with a password is turned off. Sign in with the code we send to your email.';

    public const RESET_DISABLED_MESSAGE = 'Password reset is turned off. Sign in with the code we send to your email.';

    public function __construct(
        private readonly AccountFlagsService $accountFlags,
    ) {
    }

    public function enabled(): bool
    {
        return (bool) config('auth.password_login_enabled', false);
    }

    /**
     * Whether a password sign-in for this email address may go ahead.
     */
    public function allowsEmail(mixed $email): bool
    {
        if ($this->enabled()) {
            return true;
        }

        if (! is_string($email) || trim($email) === '') {
            return false;
        }

        $matches = User::query()
            ->whereRaw('LOWER(email) = ?', [strtolower(trim($email))])
            ->limit(2)
            ->get();

        // Two accounts that differ only in letter case: allow neither.
        $first = $matches->first();
        if ($matches->count() !== 1 || ! $first instanceof User) {
            return false;
        }

        return $this->allowsUser($first);
    }

    /**
     * Whether a password sign-in for this user (the second step of a
     * two-factor sign-in) may go ahead.
     */
    public function allowsUser(User|int|null $user): bool
    {
        if ($this->enabled()) {
            return true;
        }

        if ($user === null || ! $this->accountFlags->isActiveReviewAccount($user)) {
            return false;
        }

        logger()->info('bypass.fired', [
            'user_id' => $user instanceof User ? $user->id : $user,
            'bypass'  => 'password_login',
            'reason'  => 'review_account',
        ]);

        return true;
    }
}
