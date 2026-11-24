<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Contracts\Session\Session;

/**
 * Remembers in the session when the user last proved who they are on the
 * website. A "remember me" cookie restores a session without that proof, so
 * only an interactive sign-in records the time. Risky actions, such as
 * deleting the account without a password, ask for a recent sign-in.
 */
final class RecentSignIn
{
    public const SESSION_KEY = 'auth.signed_in_at';

    public const WINDOW_MINUTES = 15;

    public static function record(?Session $session = null): void
    {
        self::session($session)->put(self::SESSION_KEY, now()->getTimestamp());
    }

    public static function isRecent(?Session $session = null): bool
    {
        $signedInAt = self::session($session)->get(self::SESSION_KEY);

        return is_int($signedInAt)
            && $signedInAt >= now()->subMinutes(self::WINDOW_MINUTES)->getTimestamp();
    }

    private static function session(?Session $session): Session
    {
        if ($session !== null) {
            return $session;
        }

        /** @var Session $store */
        $store = app('session.store');

        return $store;
    }
}
