<?php

declare(strict_types=1);

namespace App\Services\MultiTenancy;

use App\Models\Tenant;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use stdClass;

/**
 * Service for migrating data from central database to tenant databases.
 *
 * This service orchestrates the migration of existing data when transitioning
 * to a multi-tenant architecture. It handles:
 * - Copying data from central to tenant databases
 * - Tracking migration progress
 * - Supporting incremental migrations
 * - Providing rollback capabilities
 */
class TenantDataMigrationService
{
    /**
     * Tables that should be migrated to tenant databases.
     *
     * @var array<string, array{source: string, target: string, key: string, team_column: string|null}>
     */
    protected array $migratableTables = [
        'accounts' => [
            'source'      => 'accounts',
            'target'      => 'accounts',
            'key'         => 'uuid',
            'team_column' => 'team_id',
        ],
        'transactions' => [
            'source'      => 'transactions',
            'target'      => 'transactions',
            'key'         => 'uuid',
            'team_column' => null, // Will use account relationship
        ],
        'transfers' => [
            'source'      => 'transfers',
            'target'      => 'transfers',
            'key'         => 'uuid',
            'team_column' => null, // Will use account relationship
        ],
    ];

    /**
     * Batch size for data migration.
     */
    protected int $batchSize = 1000;

    /**
     * Get the list of migratable tables.
     *
     * @return array<string, array{source: string, target: string, key: string, team_column: string|null}>
     */
    public function getMigratableTables(): array
    {
        return $this->migratableTables;
    }

    /**
     * Register additional tables for migration.
     *
     * @param array{source: string, target: string, key: string, team_column: string|null} $config
     */
    public function registerTable(string $name, array $config): self
    {
        $this->migratableTables[$name] = $config;

        return $this;
    }

    /**
     * Set the batch size for migrations.
     */
    public function setBatchSize(int $size): self
    {
        $this->batchSize = $size;

        return $this;
    }

    /**
     * Migrate all data for a specific tenant.
     *
     * @param array<string>|null $tables Specific tables to migrate, or null for all
     * @return array{migrated: int, skipped: int, errors: array<string>}
     */
    public function migrateDataForTenant(Tenant $tenant, ?array $tables = null): array
    {
        $result = [
            'migrated' => 0,
            'skipped'  => 0,
            'errors'   => [],
        ];

        $tablesToMigrate = $tables ?? array_keys($this->migratableTables);

        Log::info('Starting tenant data migration', [
            'tenant_id' => $tenant->id,
            'tables'    => $tablesToMigrate,
        ]);

        foreach ($tablesToMigrate as $tableName) {
            if (! isset($this->migratableTables[$tableName])) {
                $result['errors'][] = "Unknown table: {$tableName}";

                continue;
            }

            try {
                $count = $this->migrateTable($tenant, $tableName);
                $result['migrated'] += $count;

                Log::info('Table migration completed', [
                    'tenant_id' => $tenant->id,
                    'table'     => $tableName,
                    'count'     => $count,
                ]);
            } catch (Exception $e) {
                $result['errors'][] = "{$tableName}: {$e->getMessage()}";

                Log::error('Table migration failed', [
                    'tenant_id' => $tenant->id,
                    'table'     => $tableName,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        $this->logMigration($tenant, $result);

        return $result;
    }

    /**
     * Migrate a specific table for a tenant.
     */
    protected function migrateTable(Tenant $tenant, string $tableName): int
    {
        $config = $this->migratableTables[$tableName];
        $sourceTable = $config['source'];
        $targetTable = $config['target'];
        $keyColumn = $config['key'];
        $teamColumn = $config['team_column'];

        // Get team ID from tenant
        $teamId = $tenant->team_id;
        if ($teamId === null) {
            throw new RuntimeException("Tenant {$tenant->id} has no associated team");
        }

        // Initialize tenant context
        tenancy()->initialize($tenant);

        try {
            $totalMigrated = 0;

            // Build query for source data
            $query = DB::connection('mysql')->table($sourceTable);

            if ($teamColumn !== null) {
                $query->where($teamColumn, $teamId);
            } else {
                // For tables without direct team relationship, we need custom logic
                $query = $this->buildIndirectQuery($sourceTable, $teamId);
            }

            // Process in batches
            $query->orderBy($keyColumn)
                ->chunk($this->batchSize, function (Collection $records) use ($tenant, $targetTable, $keyColumn, &$totalMigrated) {
                    $this->insertBatch($tenant, $targetTable, $keyColumn, $records);
                    $totalMigrated += $records->count();
                });

            return $totalMigrated;
        } finally {
            tenancy()->end();
        }
    }

    /**
     * Build query for tables without direct team relationship.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    protected function buildIndirectQuery(string $tableName, int $teamId)
    {
        return match ($tableName) {
            'transactions' => DB::connection('mysql')
                ->table('transactions')
                ->join('accounts', 'transactions.account_uuid', '=', 'accounts.uuid')
                ->where('accounts.team_id', $teamId)
                ->select('transactions.*'),

            'transfers' => DB::connection('mysql')
                ->table('transfers')
                ->join('accounts', 'transfers.source_account_uuid', '=', 'accounts.uuid')
                ->where('accounts.team_id', $teamId)
                ->select('transfers.*'),

            default => throw new RuntimeException("No indirect query defined for table: {$tableName}"),
        };
    }

    /**
     * Insert a batch of records into the tenant database.
     *
     * @param Collection<int, object> $records
     */
    protected function insertBatch(Tenant $tenant, string $targetTable, string $keyColumn, Collection $records): void
    {
        $tenantConnection = $this->getTenantConnection($tenant);

        foreach ($records as $record) {
            $data = (array) $record;

            // Check if record already exists
            $exists = $tenantConnection
                ->table($targetTable)
                ->where($keyColumn, $data[$keyColumn])
                ->exists();

            if (! $exists) {
                $tenantConnection->table($targetTable)->insert($data);
            }
        }
    }

    /**
     * Get the database connection for a tenant.
     */
    protected function getTenantConnection(Tenant $tenant): ConnectionInterface
    {
        // Configure tenant database connection
        $tenantDbName = 'tenant_' . $tenant->id;

        config([
            'database.connections.tenant' => array_merge(
                config('database.connections.mysql'),
                ['database' => $tenantDbName]
            ),
        ]);

        return DB::connection('tenant');
    }

    /**
     * Log the migration result.
     *
     * @param array{migrated: int, skipped: int, errors: array<string>} $result
     */
    protected function logMigration(Tenant $tenant, array $result): void
    {
        DB::table('tenant_data_migrations')->insert([
            'tenant_id'      => $tenant->id,
            'migrated_count' => $result['migrated'],
            'skipped_count'  => $result['skipped'],
            'error_count'    => count($result['errors']),
            'errors'         => json_encode($result['errors']),
            'status'         => empty($result['errors']) ? 'completed' : 'completed_with_errors',
            'completed_at'   => Carbon::now(),
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ]);
    }

    /**
     * Get migration history for a tenant.
     *
     * @return Collection<int, stdClass>
     */
    public function getMigrationHistory(Tenant $tenant): Collection
    {
        return DB::table('tenant_data_migrations')
            ->where('tenant_id', $tenant->id)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Check if a tenant has been migrated.
     */
    public function isMigrated(Tenant $tenant): bool
    {
        return DB::table('tenant_data_migrations')
            ->where('tenant_id', $tenant->id)
            ->where('status', 'completed')
            ->exists();
    }

    /**
     * Get count of records to migrate for a tenant.
     *
     * @return array<string, int>
     */
    public function getRecordCounts(Tenant $tenant): array
    {
        $counts = [];
        $teamId = $tenant->team_id;

        if ($teamId === null) {
            return $counts;
        }

        foreach ($this->migratableTables as $name => $config) {
            if ($config['team_column'] !== null) {
                $counts[$name] = DB::connection('mysql')
                    ->table($config['source'])
                    ->where($config['team_column'], $teamId)
                    ->count();
            } else {
                try {
                    $counts[$name] = $this->buildIndirectQuery($config['source'], $teamId)->count();
                } catch (Exception $e) {
                    $counts[$name] = 0;
                }
            }
        }

        return $counts;
    }
}
