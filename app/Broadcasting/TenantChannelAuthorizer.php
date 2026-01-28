<?php

declare(strict_types=1);

namespace App\Broadcasting;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Authorizes user access to tenant-scoped broadcast channels.
 *
 * This class provides static methods for use in channel authorization
 * callbacks. It verifies that the authenticated user belongs to the
 * tenant (team) referenced in the channel name.
 *
 * Usage in routes/channels.php:
 * ```php
 * Broadcast::channel('tenant.{tenantId}', function ($user, string $tenantId) {
 *     return TenantChannelAuthorizer::authorizeUser($user, $tenantId);
 * });
 * ```
 */
class TenantChannelAuthorizer
{
    /**
     * Authorize a user to access a tenant channel.
     *
     * Returns user data array on success (for presence channels) or false on failure.
     *
     * @return array<string, mixed>|false
     */
    public static function authorizeUser(User $user, string $tenantId): array|false
    {
        if (! static::userBelongsToTenant($user, $tenantId)) {
            Log::warning('Channel authorization denied', [
                'user_id'   => $user->id,
                'tenant_id' => $tenantId,
                'channel'   => 'tenant.' . $tenantId,
            ]);

            return false;
        }

        return [
            'id'   => $user->id,
            'name' => $user->name,
        ];
    }

    /**
     * Authorize an admin user to access a tenant channel.
     *
     * Only team owners or users with admin roles can access admin channels.
     *
     * @return array<string, mixed>|false
     */
    public static function authorizeAdmin(User $user, string $tenantId): array|false
    {
        if (! static::userBelongsToTenant($user, $tenantId)) {
            return false;
        }

        if (! static::userIsTeamAdmin($user, $tenantId)) {
            Log::warning('Admin channel authorization denied', [
                'user_id'   => $user->id,
                'tenant_id' => $tenantId,
                'reason'    => 'not_admin',
            ]);

            return false;
        }

        return [
            'id'   => $user->id,
            'name' => $user->name,
            'role' => 'admin',
        ];
    }

    /**
     * Check if a user belongs to the specified tenant (team).
     */
    protected static function userBelongsToTenant(User $user, string $tenantId): bool
    {
        // Find the team associated with this tenant
        $team = Team::where('id', (int) $tenantId)->first();

        if (! $team) {
            return false;
        }

        // Check if user is the owner or a member of the team
        if ($team->user_id === $user->id) {
            return true;
        }

        return $team->hasUser($user);
    }

    /**
     * Check if a user is an admin of the specified tenant (team).
     */
    protected static function userIsTeamAdmin(User $user, string $tenantId): bool
    {
        $team = Team::where('id', (int) $tenantId)->first();

        if (! $team) {
            return false;
        }

        // Team owner is always admin
        if ($team->user_id === $user->id) {
            return true;
        }

        // Check for admin role via team membership
        return $user->hasTeamRole($team, 'admin');
    }
}
