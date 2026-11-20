<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * Ends the ways a user is signed in. Used after a password reset or change,
 * so someone who knew the old password, or held a session or token from
 * before, loses access.
 */
final class UserSignIns
{
    /**
     * Revoke every API token and OAuth grant, replace the "remember me"
     * token and delete the stored sessions, except the session to keep.
     * Sessions in a store that cannot be listed by user (Redis, files,
     * cookies) end on their next request through the AuthenticateSession
     * middleware in the web group, because they carry the old password hash.
     */
    public static function endAllExcept(User $user, ?string $keepSessionId = null): void
    {
        $user->tokens()->delete();
        self::revokeOauthGrants($user);

        $user->setRememberToken(Str::random(60));
        $user->save();

        if (config('session.driver') === 'database') {
            $sessionConnection = config('session.connection');

            DB::connection(is_string($sessionConnection) ? $sessionConnection : null)
                ->table((string) config('session.table', 'sessions'))
                ->where('user_id', $user->getKey())
                ->when($keepSessionId !== null, fn ($query) => $query->where('id', '!=', $keepSessionId))
                ->delete();
        }
    }

    /**
     * OAuth access tokens (and their refresh tokens) that the user granted
     * to clients, such as MCP clients.
     */
    private static function revokeOauthGrants(User $user): void
    {
        $tokenIds = Passport::token()->newQuery()
            ->where('user_id', $user->getKey())
            ->where('revoked', false)
            ->pluck('id')
            ->all();

        if ($tokenIds === []) {
            return;
        }

        Passport::token()->newQuery()->whereIn('id', $tokenIds)->update(['revoked' => true]);
        Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokenIds)->update(['revoked' => true]);
    }
}
