<?php

declare(strict_types=1);

namespace App\Domain\Microfinance\Support;

use App\Domain\Microfinance\Enums\MemberRole;
use App\Domain\Microfinance\Models\GroupMember;
use App\Domain\Microfinance\Models\ShareAccount;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Who may change microfinance records, shared by the REST controller and
 * the GraphQL mutations:
 *  - groups are created, activated and closed by platform operators
 *    (StaffRoles::OPERATORS);
 *  - members and meetings are managed by an active leader of the group or
 *    an operator, and only operators assign a role other than `member`;
 *  - shares are bought and redeemed by the share account's holder or an
 *    operator.
 */
final class MicrofinanceAccess
{
    public static function isOperator(?Authenticatable $user): bool
    {
        return StaffRoles::userHasAny($user, StaffRoles::OPERATORS);
    }

    /**
     * An active leader of the group, or an operator.
     */
    public static function mayManageGroup(?Authenticatable $user, string $groupId): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        if (self::isOperator($user)) {
            return true;
        }

        return GroupMember::query()
            ->where('group_id', $groupId)
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->where('role', MemberRole::LEADER->value)
            ->exists();
    }

    /**
     * Whether the user may give a new member this role: `member` for anyone
     * who may manage the group, any other role for operators only.
     */
    public static function mayAssignRole(?Authenticatable $user, string $role): bool
    {
        return $role === MemberRole::MEMBER->value || self::isOperator($user);
    }

    /**
     * The share account's holder, or an operator.
     */
    public static function mayUseShareAccount(?Authenticatable $user, ShareAccount $account): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        return (string) $account->user_id === (string) $user->id || self::isOperator($user);
    }
}
