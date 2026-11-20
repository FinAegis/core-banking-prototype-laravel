<?php

declare(strict_types=1);

namespace App\Support;

use App\Domain\Mobile\Models\MobileDeviceSession;
use App\Domain\Mobile\Services\MobileDeviceService;
use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;

/**
 * Ends the ways a user is signed in. Used after a password reset or change,
 * and when a Privy sign-in links an account whose email nobody had proved,
 * so someone who knew the old password, or held a session, token or device
 * from before, loses access.
 */
final class UserSignIns
{
    /**
     * After a password reset, or a link of an account nobody had proved:
     * whoever did it controls the account now, so every earlier way in ends,
     * API keys included.
     */
    public static function endAll(User $user): void
    {
        self::endAllExcept($user);

        ApiKey::query()
            ->where('user_uuid', $user->uuid)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    /**
     * After a password change: revoke every API token and OAuth grant,
     * replace the "remember me" token, delete the stored sessions except the
     * session to keep, and remove the mobile devices with their passkey and
     * biometric keys, device sessions and push tokens. API keys stay: the
     * account holder proved the current password and the keys do not
     * depend on it.
     *
     * No request can be tied to a device row (the X-Device-ID header is
     * only a client claim), so no device is kept. The app registers its
     * device again after the next sign-in.
     *
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

        self::removeDevices($user);
    }

    /**
     * Mobile devices hold passkey and biometric keys that sign in on their
     * own, and push tokens that receive the account's notifications.
     */
    private static function removeDevices(User $user): void
    {
        MobileDeviceSession::query()->where('user_id', $user->getKey())->delete();

        app(MobileDeviceService::class)->removeAllDevices($user);
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
