<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Role groups for staff-only operations (spatie/laravel-permission role
 * names, see the add_banking_roles_and_permissions migration).
 */
final class StaffRoles
{
    /**
     * Platform operators: the same roles the account freeze/unfreeze
     * endpoints accept.
     */
    public const OPERATORS = ['admin', 'super_admin', 'bank_admin'];

    /**
     * Platform administrators (plugin management).
     */
    public const ADMINS = ['admin', 'super_admin'];

    /**
     * Operators plus the stablecoin_operator role: stablecoin configuration
     * (create, update, deactivate, reactivate, stability mechanism).
     */
    public const STABLECOIN_OPERATORS = ['admin', 'super_admin', 'bank_admin', 'stablecoin_operator'];

    /**
     * Staff who work compliance alerts and cases and fraud cases.
     */
    public const COMPLIANCE = ['admin', 'super_admin', 'bank_admin', 'compliance_officer', 'risk_manager'];

    /**
     * Whether the user holds at least one of the roles.
     *
     * @param  list<string>  $roles
     */
    public static function userHasAny(?Authenticatable $user, array $roles): bool
    {
        return $user instanceof User && $user->hasRole($roles);
    }

    /**
     * Every staff role name in the groups above. Customer-controlled flows
     * (business team roles) never grant or accept these names.
     */
    public const ALL = [...self::OPERATORS, ...self::ADMINS, ...self::STABLECOIN_OPERATORS, ...self::COMPLIANCE];
}
