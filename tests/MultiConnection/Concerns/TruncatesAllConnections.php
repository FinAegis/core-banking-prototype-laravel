<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Concerns;

use Illuminate\Support\Facades\DB;

trait TruncatesAllConnections
{
    /**
     * Tables preserved across tests in this suite.
     *
     * - migrations: required for schema state
     * - permission/role tables: created once via createRoles() pattern; truncating
     *   them forces every test to re-seed roles, which is wasteful
     */
    private const PRESERVED_TABLES = [
        'migrations',
        'roles',
        'permissions',
        'model_has_roles',
        'model_has_permissions',
        'role_has_permissions',
    ];

    /**
     * Truncate every non-preserved, non-empty table on both default and tenant
     * connections.
     *
     * MultiConnection tests cannot use RefreshDatabase (which wraps the default
     * connection in a single transaction the tenant connection cannot see).
     * We truncate instead, and since both connections target the same physical
     * database, we only need to truncate once via the default connection.
     *
     * We only TRUNCATE tables that have rows. Empty tables are no-ops anyway,
     * and the project has 200+ tables — issuing TRUNCATE on each of them every
     * test setUp/tearDown serializes through MySQL's redo log writer and trips
     * the per-statement timeout. Skipping empties keeps the cleanup proportional
     * to what each test actually wrote.
     */
    protected function truncateAllConnections(): void
    {
        $tables = $this->resolveTablesToTruncate();

        if ($tables === []) {
            return;
        }

        DB::connection()->statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($tables as $table) {
                DB::connection()->table($table)->truncate();
            }
        } finally {
            DB::connection()->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveTablesToTruncate(): array
    {
        $all = array_map(
            fn (object $row) => array_values((array) $row)[0],
            DB::connection()->select('SHOW TABLES')
        );

        $candidates = array_filter(
            $all,
            fn (string $table) => ! in_array($table, self::PRESERVED_TABLES, true)
        );

        return array_values(array_filter(
            $candidates,
            fn (string $table) => DB::connection()->table($table)->limit(1)->exists()
        ));
    }
}
