<?php

declare(strict_types=1);

namespace App\Support;

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
     * Staff who work compliance alerts and cases and fraud cases.
     */
    public const COMPLIANCE = ['admin', 'super_admin', 'bank_admin', 'compliance_officer', 'risk_manager'];
}
