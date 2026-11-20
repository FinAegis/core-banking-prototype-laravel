<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services\Certification;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use stdClass;

/**
 * SOC 2 Access Review Service.
 *
 * Provides user access reviews, privileged account auditing, permission matrix
 * generation, and detection of stale accounts and dormant API tokens for
 * SOC 2 Trust Services Criteria (CC6.1, CC6.2, CC6.3).
 */
class AccessReviewService
{
    /**
     * Run a full access review and return the report.
     *
     * @return array<string, mixed>
     */
    public function runAccessReview(): array
    {
        if (config('compliance-certification.soc2.demo_mode', true)) {
            return $this->getDemoAccessReview();
        }

        Log::info('SOC 2 access review initiated');

        $report = $this->generateReviewReport();

        Log::info('SOC 2 access review completed', [
            'privileged_users' => count($report['privileged_users']),
            'stale_accounts'   => count($report['stale_accounts']),
            'dormant_tokens'   => count($report['dormant_tokens']),
        ]);

        return $report;
    }

    /**
     * Get all users assigned to privileged roles.
     *
     * @return Collection<int, User>
     */
    public function getPrivilegedUsers(): Collection
    {
        $privilegedRoles = config('compliance-certification.soc2.privileged_roles', [
            'admin',
            'super_admin',
            'compliance_officer',
            'compliance_manager',
        ]);

        return User::role($privilegedRoles)
            ->with('roles.permissions')
            ->get();
    }

    /**
     * Build a matrix mapping each role to its granted permissions.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getPermissionMatrix(): array
    {
        $roles = DB::table('roles')->get();
        $matrix = [];

        foreach ($roles as $role) {
            $permissions = DB::table('role_has_permissions')
                ->join('permissions', 'role_has_permissions.permission_id', '=', 'permissions.id')
                ->where('role_has_permissions.role_id', $role->id)
                ->pluck('permissions.name')
                ->toArray();

            $userCount = DB::table('model_has_roles')
                ->where('role_id', $role->id)
                ->count();

            $matrix[$role->name] = [
                'permissions'      => $permissions,
                'permission_count' => count($permissions),
                'user_count'       => $userCount,
                'guard_name'       => $role->guard_name ?? 'web',
            ];
        }

        return $matrix;
    }

    /**
     * Find user accounts that have not logged in for the specified number of days.
     *
     * @param int $daysInactive Inactivity threshold in days
     *
     * @return Collection<int, User>
     */
    public function findStaleAccounts(int $daysInactive = 90): Collection
    {
        $cutoff = now()->subDays($daysInactive);

        return User::where(function ($query) use ($cutoff) {
            $query->where('last_login_at', '<', $cutoff)
                ->orWhereNull('last_login_at');
        })
            ->with('roles')
            ->get();
    }

    /**
     * Find Sanctum personal access tokens that have not been used recently.
     *
     * @param int $daysUnused Number of days since last use to be considered dormant
     *
     * @return Collection<int, stdClass>
     */
    public function findDormantTokens(int $daysUnused = 30): Collection
    {
        $cutoff = now()->subDays($daysUnused);

        return DB::table('personal_access_tokens')
            ->where(function ($query) use ($cutoff) {
                $query->where('last_used_at', '<', $cutoff)
                    ->orWhereNull('last_used_at');
            })
            ->select([
                'id',
                'tokenable_type',
                'tokenable_id',
                'name',
                'last_used_at',
                'created_at',
                'expires_at',
            ])
            ->orderBy('last_used_at', 'asc')
            ->get();
    }

    /**
     * Generate a comprehensive access review report combining all review data.
     *
     * @return array<string, mixed>
     */
    public function generateReviewReport(): array
    {
        $privilegedUsers = $this->getPrivilegedUsers();
        $permissionMatrix = $this->getPermissionMatrix();
        $staleAccounts = $this->findStaleAccounts();
        $dormantTokens = $this->findDormantTokens();

        $totalUsers = User::count();
        $activeUsers = User::where('last_login_at', '>=', now()->subDays(30))->count();

        return [
            'review_date'   => now()->toIso8601String(),
            'review_period' => config('compliance-certification.soc2.review_period', 'quarterly'),
            'summary'       => [
                'total_users'           => $totalUsers,
                'active_users_30d'      => $activeUsers,
                'privileged_user_count' => $privilegedUsers->count(),
                'stale_account_count'   => $staleAccounts->count(),
                'dormant_token_count'   => $dormantTokens->count(),
                'total_roles'           => count($permissionMatrix),
            ],
            'privileged_users' => $privilegedUsers->map(function (User $user) {
                return [
                    'uuid'          => $user->uuid,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'roles'         => $user->roles->pluck('name')->toArray(),
                    'permissions'   => $user->getAllPermissions()->pluck('name')->toArray(),
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                ];
            })->toArray(),
            'permission_matrix' => $permissionMatrix,
            'stale_accounts'    => $staleAccounts->map(function (User $user) {
                return [
                    'uuid'          => $user->uuid,
                    'name'          => $user->name,
                    'email'         => $user->email,
                    'roles'         => $user->roles->pluck('name')->toArray(),
                    'last_login_at' => $user->last_login_at?->toIso8601String(),
                    'created_at'    => $user->created_at?->toIso8601String(),
                ];
            })->toArray(),
            'dormant_tokens' => $dormantTokens->map(function ($token) {
                return [
                    'id'             => $token->id,
                    'tokenable_type' => $token->tokenable_type,
                    'tokenable_id'   => $token->tokenable_id,
                    'name'           => $token->name,
                    'last_used_at'   => $token->last_used_at,
                    'created_at'     => $token->created_at,
                    'expires_at'     => $token->expires_at ?? null,
                ];
            })->toArray(),
            'recommendations' => $this->generateRecommendations(
                $privilegedUsers,
                $staleAccounts,
                $dormantTokens
            ),
        ];
    }

    /**
     * Generate review recommendations based on findings.
     *
     * @param Collection<int, User>     $privilegedUsers
     * @param Collection<int, User>     $staleAccounts
     * @param Collection<int, stdClass> $dormantTokens
     *
     * @return array<int, string>
     */
    private function generateRecommendations(
        Collection $privilegedUsers,
        Collection $staleAccounts,
        Collection $dormantTokens
    ): array {
        $recommendations = [];

        if ($staleAccounts->count() > 0) {
            $recommendations[] = "Review and disable {$staleAccounts->count()} stale user account(s) that have not logged in for over 90 days.";
        }

        if ($dormantTokens->count() > 0) {
            $recommendations[] = "Revoke {$dormantTokens->count()} dormant API token(s) that have not been used in over 30 days.";
        }

        $privilegedWithNoRecentLogin = $privilegedUsers->filter(function (User $user) {
            return $user->last_login_at === null || $user->last_login_at->lt(now()->subDays(30));
        });

        if ($privilegedWithNoRecentLogin->count() > 0) {
            $recommendations[] = "Investigate {$privilegedWithNoRecentLogin->count()} privileged user(s) with no recent login activity.";
        }

        if ($privilegedUsers->count() > 10) {
            $recommendations[] = 'Consider reducing the number of privileged users. Current count exceeds recommended threshold of 10.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'No immediate action items identified. Access controls appear healthy.';
        }

        return $recommendations;
    }

    /**
     * Return realistic simulated access review data for demo mode.
     *
     * @return array<string, mixed>
     */
    private function getDemoAccessReview(): array
    {
        return [
            'review_date'   => now()->toIso8601String(),
            'review_period' => 'quarterly',
            'summary'       => [
                'total_users'           => 47,
                'active_users_30d'      => 38,
                'privileged_user_count' => 5,
                'stale_account_count'   => 3,
                'dormant_token_count'   => 7,
                'total_roles'           => 6,
            ],
            'privileged_users' => [
                [
                    'uuid'          => 'demo-admin-001',
                    'name'          => 'System Administrator',
                    'email'         => 'admin@example.com',
                    'roles'         => ['admin', 'compliance_officer'],
                    'permissions'   => ['manage-users', 'manage-roles', 'view-audit-logs', 'manage-compliance'],
                    'last_login_at' => now()->subHours(2)->toIso8601String(),
                ],
                [
                    'uuid'          => 'demo-admin-002',
                    'name'          => 'Compliance Manager',
                    'email'         => 'compliance@example.com',
                    'roles'         => ['compliance_manager'],
                    'permissions'   => ['view-audit-logs', 'manage-compliance', 'export-reports'],
                    'last_login_at' => now()->subDays(1)->toIso8601String(),
                ],
            ],
            'permission_matrix' => [
                'admin' => [
                    'permissions'      => ['manage-users', 'manage-roles', 'view-audit-logs', 'manage-settings'],
                    'permission_count' => 4,
                    'user_count'       => 2,
                    'guard_name'       => 'web',
                ],
                'compliance_officer' => [
                    'permissions'      => ['view-audit-logs', 'manage-compliance', 'export-reports'],
                    'permission_count' => 3,
                    'user_count'       => 3,
                    'guard_name'       => 'web',
                ],
                'analyst' => [
                    'permissions'      => ['view-reports', 'view-transactions'],
                    'permission_count' => 2,
                    'user_count'       => 12,
                    'guard_name'       => 'web',
                ],
                'operator' => [
                    'permissions'      => ['view-transactions', 'process-transactions'],
                    'permission_count' => 2,
                    'user_count'       => 18,
                    'guard_name'       => 'web',
                ],
            ],
            'stale_accounts' => [
                [
                    'uuid'          => 'demo-stale-001',
                    'name'          => 'Former Analyst',
                    'email'         => 'former.analyst@example.com',
                    'roles'         => ['analyst'],
                    'last_login_at' => now()->subDays(120)->toIso8601String(),
                    'created_at'    => now()->subYear()->toIso8601String(),
                ],
                [
                    'uuid'          => 'demo-stale-002',
                    'name'          => 'Contractor Account',
                    'email'         => 'contractor@example.com',
                    'roles'         => ['operator'],
                    'last_login_at' => now()->subDays(95)->toIso8601String(),
                    'created_at'    => now()->subMonths(8)->toIso8601String(),
                ],
            ],
            'dormant_tokens' => [
                [
                    'id'             => 1,
                    'tokenable_type' => 'App\\Models\\User',
                    'tokenable_id'   => 'demo-stale-001',
                    'name'           => 'api-token-analytics',
                    'last_used_at'   => now()->subDays(60)->toIso8601String(),
                    'created_at'     => now()->subMonths(6)->toIso8601String(),
                    'expires_at'     => null,
                ],
            ],
            'recommendations' => [
                'Review and disable 3 stale user account(s) that have not logged in for over 90 days.',
                'Revoke 7 dormant API token(s) that have not been used in over 30 days.',
                'Investigate 1 privileged user(s) with no recent login activity.',
            ],
        ];
    }
}
