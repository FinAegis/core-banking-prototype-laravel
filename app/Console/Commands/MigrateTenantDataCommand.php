<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\MultiTenancy\TenantDataMigrationService;
use Exception;
use Illuminate\Console\Command;

/**
 * Artisan command to migrate data from central database to tenant databases.
 *
 * Usage:
 *   php artisan tenants:migrate-data                    # Migrate all tenants
 *   php artisan tenants:migrate-data --tenant=uuid      # Migrate specific tenant
 *   php artisan tenants:migrate-data --tables=accounts  # Migrate specific tables
 *   php artisan tenants:migrate-data --dry-run          # Preview without migrating
 */
class MigrateTenantDataCommand extends Command
{
    protected $signature = 'tenants:migrate-data
                            {--tenant= : Specific tenant UUID to migrate}
                            {--tables= : Comma-separated list of tables to migrate}
                            {--dry-run : Preview migration without executing}
                            {--batch-size=1000 : Number of records to process per batch}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Migrate data from central database to tenant databases';

    public function __construct(
        protected TenantDataMigrationService $migrationService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $tables = $this->option('tables')
            ? explode(',', $this->option('tables'))
            : null;
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        $this->migrationService->setBatchSize($batchSize);

        // Get tenants to migrate
        $tenants = $this->getTenantsToMigrate($tenantId);

        if ($tenants->isEmpty()) {
            $this->error('No tenants found to migrate.');

            return Command::FAILURE;
        }

        $this->info("Found {$tenants->count()} tenant(s) to migrate.");
        $this->newLine();

        // Show available tables
        $this->displayAvailableTables();

        if ($dryRun) {
            return $this->performDryRun($tenants, $tables);
        }

        // Confirm migration
        if (! $this->option('force') && ! $this->confirm('Do you want to proceed with the migration?')) {
            $this->info('Migration cancelled.');

            return Command::SUCCESS;
        }

        return $this->performMigration($tenants, $tables);
    }

    /**
     * Get tenants to migrate.
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    protected function getTenantsToMigrate(?string $tenantId): \Illuminate\Support\Collection
    {
        if ($tenantId) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::find($tenantId);

            return $tenant ? collect([$tenant]) : collect();
        }

        // Convert TenantCollection to Support\Collection
        /** @var array<int, Tenant> $tenants */
        $tenants = Tenant::all()->all();

        return collect($tenants);
    }

    /**
     * Display available tables for migration.
     */
    protected function displayAvailableTables(): void
    {
        $tables = $this->migrationService->getMigratableTables();

        $this->info('Available tables for migration:');
        $headers = ['Table', 'Source', 'Target', 'Key Column', 'Team Column'];
        $rows = [];

        foreach ($tables as $name => $config) {
            $rows[] = [
                $name,
                $config['source'],
                $config['target'],
                $config['key'],
                $config['team_column'] ?? '(indirect)',
            ];
        }

        $this->table($headers, $rows);
        $this->newLine();
    }

    /**
     * Perform a dry run to preview migration.
     *
     * @param \Illuminate\Support\Collection<int, Tenant> $tenants
     * @param array<string>|null $tables
     */
    protected function performDryRun($tenants, ?array $tables): int
    {
        $this->info('DRY RUN - No data will be migrated');
        $this->newLine();

        foreach ($tenants as $tenant) {
            $this->info("Tenant: {$tenant->name} ({$tenant->id})");

            $counts = $this->migrationService->getRecordCounts($tenant);
            $isMigrated = $this->migrationService->isMigrated($tenant);

            if ($isMigrated) {
                $this->warn('  Status: Already migrated');
            } else {
                $this->line('  Status: Not migrated');
            }

            $this->line('  Record counts:');

            $tablesToShow = $tables ?? array_keys($counts);
            foreach ($tablesToShow as $table) {
                $count = $counts[$table] ?? 0;
                $this->line("    - {$table}: {$count} records");
            }

            $this->newLine();
        }

        return Command::SUCCESS;
    }

    /**
     * Perform the actual migration.
     *
     * @param \Illuminate\Support\Collection<int, Tenant> $tenants
     * @param array<string>|null $tables
     */
    protected function performMigration($tenants, ?array $tables): int
    {
        $totalMigrated = 0;
        $totalErrors = 0;

        $progressBar = $this->output->createProgressBar($tenants->count());
        $progressBar->start();

        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("Migrating tenant: {$tenant->name} ({$tenant->id})");

            try {
                $result = $this->migrationService->migrateDataForTenant($tenant, $tables);

                $totalMigrated += $result['migrated'];
                $totalErrors += count($result['errors']);

                if (! empty($result['errors'])) {
                    $this->warn('  Completed with errors:');
                    foreach ($result['errors'] as $error) {
                        $this->error("    - {$error}");
                    }
                } else {
                    $this->line("  Migrated {$result['migrated']} records");
                }
            } catch (Exception $e) {
                $totalErrors++;
                $this->error("  Migration failed: {$e->getMessage()}");
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Migration Summary:');
        $this->line("  Total records migrated: {$totalMigrated}");
        $this->line("  Total errors: {$totalErrors}");

        return $totalErrors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
