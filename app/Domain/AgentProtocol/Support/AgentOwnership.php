<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\Support;

use App\Models\Agent;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Who may manage an Agent Protocol agent through the user (Sanctum)
 * endpoints: its API keys, its sessions and the A2A tasks sent from it.
 *
 * The owner is the user recorded in agents.owner_user_id when the agent was
 * registered. Platform operators (StaffRoles::OPERATORS) may manage every
 * agent, including platform agents that have no owner. Nobody else may,
 * and the endpoints answer as if the agent did not exist.
 */
final class AgentOwnership
{
    /**
     * Scopes a non-staff user may not put on an agent API key (the config
     * marks the admin scopes as restricted; `*` includes them).
     */
    public const RESTRICTED_SCOPES = ['*', 'admin:*', 'admin:read', 'admin:manage'];

    public static function isStaff(?Authenticatable $user): bool
    {
        return StaffRoles::userHasAny($user, StaffRoles::OPERATORS);
    }

    /**
     * Whether the user may manage the agent (an App\Models\Agent or
     * App\Domain\AgentProtocol\Models\Agent row).
     */
    public static function canManage(?Authenticatable $user, ?Model $agent): bool
    {
        if (! $user instanceof User || $agent === null) {
            return false;
        }

        if (self::isStaff($user)) {
            return true;
        }

        $ownerId = $agent->getAttribute('owner_user_id');

        return $ownerId !== null && (int) $ownerId === (int) $user->getKey();
    }

    /**
     * Whether the user may manage the agent with this DID. False when no
     * agent has the DID.
     */
    public static function canManageDid(?Authenticatable $user, string $did): bool
    {
        if (! $user instanceof User || $did === '') {
            return false;
        }

        return self::canManage($user, Agent::query()->where('did', $did)->first());
    }
}
