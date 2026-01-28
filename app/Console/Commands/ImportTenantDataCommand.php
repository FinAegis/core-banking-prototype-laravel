<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use ZipArchive;

/**
 * Artisan command to import tenant data from backup files.
 *
 * Usage:
 *   php artisan tenants:import-data --tenant=uuid --file=path/to/export.json
 *   php artisan tenants:import-data --tenant=uuid --file=path/to/export.zip
 *   php artisan tenants:import-data --tenant=uuid --file=path/to/export --format=csv
 */
class ImportTenantDataCommand extends Command
{
    protected $signature = 'tenants:import-data
                            {--tenant= : Tenant UUID to import into (required)}
                            {--file= : Path to import file or directory (required)}
                            {--format= : Import format (json, csv, sql) - auto-detected if not specified}
                            {--truncate : Truncate existing data before import}
                            {--dry-run : Preview import without executing}
                            {--force : Skip confirmation prompts}';

    protected $description = 'Import tenant data from backup files';

    /**
     * Tables available for import.
     *
     * @var array<string>
     */
    protected array $importableTables = [
        'accounts',
        'account_balances',
        'transactions',
        'transfers',
        'ledger_entries',
    ];

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $filePath = $this->option('file');

        if (! $tenantId) {
            $this->error('Tenant UUID is required. Use --tenant=uuid');

            return Command::FAILURE;
        }

        if (! $filePath) {
            $this->error('Import file path is required. Use --file=path');

            return Command::FAILURE;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant not found: {$tenantId}");

            return Command::FAILURE;
        }

        // Handle compressed files
        if (str_ends_with($filePath, '.zip')) {
            $filePath = $this->extractZip($filePath);
        }

        if (! File::exists($filePath)) {
            $this->error("File or directory not found: {$filePath}");

            return Command::FAILURE;
        }

        $format = $this->option('format') ?? $this->detectFormat($filePath);
        $dryRun = $this->option('dry-run');
        $truncate = $this->option('truncate');

        $this->info("Importing data for tenant: {$tenant->name} ({$tenant->id})");
        $this->info("Format: {$format}");
        $this->info("Source: {$filePath}");
        $this->newLine();

        if ($dryRun) {
            $this->info('DRY RUN - No data will be imported');
            $this->newLine();
        }

        // Confirm import
        if (! $dryRun && ! $this->option('force')) {
            if ($truncate) {
                $this->warn('WARNING: This will truncate existing data before import!');
            }

            if (! $this->confirm('Do you want to proceed with the import?')) {
                $this->info('Import cancelled.');

                return Command::SUCCESS;
            }
        }

        try {
            // Initialize tenant context
            tenancy()->initialize($tenant);

            $result = $this->importData($tenant, $filePath, $format, $truncate, $dryRun);

            tenancy()->end();

            $this->newLine();
            $this->info('Import completed successfully!');
            $this->info("Records imported: {$result['imported']}");
            $this->info("Records skipped: {$result['skipped']}");

            if (! empty($result['errors'])) {
                $this->warn('Errors encountered: ' . count($result['errors']));
                foreach ($result['errors'] as $error) {
                    $this->error("  - {$error}");
                }
            }

            return empty($result['errors']) ? Command::SUCCESS : Command::FAILURE;
        } catch (Exception $e) {
            $this->error("Import failed: {$e->getMessage()}");
            Log::error('Tenant data import failed', [
                'tenant_id' => $tenant->id,
                'file'      => $filePath,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Detect import format from file path.
     */
    protected function detectFormat(string $filePath): string
    {
        if (is_dir($filePath)) {
            // Check if it's a CSV export directory
            if (File::exists("{$filePath}/metadata.json")) {
                return 'csv';
            }

            throw new RuntimeException("Cannot detect format for directory: {$filePath}");
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);

        return match ($extension) {
            'json'  => 'json',
            'sql'   => 'sql',
            'csv'   => 'csv',
            default => throw new RuntimeException("Unknown file extension: {$extension}"),
        };
    }

    /**
     * Extract a zip file and return the extracted path.
     */
    protected function extractZip(string $zipPath): string
    {
        if (! File::exists($zipPath)) {
            throw new RuntimeException("Zip file not found: {$zipPath}");
        }

        $extractPath = dirname($zipPath) . '/' . pathinfo($zipPath, PATHINFO_FILENAME);

        $zip = new ZipArchive();

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open zip file: {$zipPath}");
        }

        $zip->extractTo($extractPath);
        $zip->close();

        $this->info("Extracted zip to: {$extractPath}");

        // Check if there's a single file or directory inside
        $contents = File::files($extractPath);
        $directories = File::directories($extractPath);

        if (count($contents) === 1 && empty($directories)) {
            return $contents[0]->getPathname();
        }

        if (count($directories) === 1 && empty($contents)) {
            return $directories[0];
        }

        return $extractPath;
    }

    /**
     * Import data based on format.
     *
     * @return array{imported: int, skipped: int, errors: array<string>}
     */
    protected function importData(Tenant $tenant, string $filePath, string $format, bool $truncate, bool $dryRun): array
    {
        return match ($format) {
            'json'  => $this->importFromJson($tenant, $filePath, $truncate, $dryRun),
            'csv'   => $this->importFromCsv($tenant, $filePath, $truncate, $dryRun),
            'sql'   => $this->importFromSql($tenant, $filePath, $truncate, $dryRun),
            default => throw new RuntimeException("Unknown format: {$format}"),
        };
    }

    /**
     * Import data from JSON file.
     *
     * @return array{imported: int, skipped: int, errors: array<string>}
     */
    protected function importFromJson(Tenant $tenant, string $filePath, bool $truncate, bool $dryRun): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        $content = File::get($filePath);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON: ' . json_last_error_msg());
        }

        if (! isset($data['metadata']) || ! isset($data['data'])) {
            throw new RuntimeException('Invalid export format: missing metadata or data sections');
        }

        $this->info('Export metadata:');
        $this->line("  Tenant: {$data['metadata']['tenant_name']} ({$data['metadata']['tenant_id']})");
        $this->line("  Exported: {$data['metadata']['exported_at']}");
        $this->line('  Tables: ' . implode(', ', $data['metadata']['tables']));
        $this->newLine();

        foreach ($data['data'] as $table => $records) {
            if (! in_array($table, $this->importableTables)) {
                $result['errors'][] = "Table {$table} is not importable, skipping.";
                $this->warn("  Skipping table {$table} (not importable)");

                continue;
            }

            $this->line("  Importing {$table}...");

            if ($dryRun) {
                $count = count($records);
                $this->line("    Would import {$count} records");
                $result['imported'] += $count;

                continue;
            }

            if ($truncate) {
                DB::connection('tenant')->table($table)->truncate();
                $this->line('    Truncated existing data');
            }

            $imported = 0;
            $skipped = 0;

            foreach ($records as $record) {
                try {
                    $this->importRecord($table, (array) $record);
                    $imported++;
                } catch (Exception $e) {
                    $skipped++;
                    // Log but don't fail on individual record errors
                }
            }

            $this->line("    Imported {$imported} records, skipped {$skipped}");
            $result['imported'] += $imported;
            $result['skipped'] += $skipped;
        }

        return $result;
    }

    /**
     * Import data from CSV directory.
     *
     * @return array{imported: int, skipped: int, errors: array<string>}
     */
    protected function importFromCsv(Tenant $tenant, string $dirPath, bool $truncate, bool $dryRun): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        if (! is_dir($dirPath)) {
            throw new RuntimeException('CSV import requires a directory, not a file');
        }

        $metadataPath = "{$dirPath}/metadata.json";

        if (File::exists($metadataPath)) {
            $metadata = json_decode(File::get($metadataPath), true);
            $this->info('Export metadata:');
            $this->line("  Tenant: {$metadata['tenant_name']} ({$metadata['tenant_id']})");
            $this->line("  Exported: {$metadata['exported_at']}");
            $this->newLine();
        }

        $csvFiles = File::glob("{$dirPath}/*.csv");

        foreach ($csvFiles as $csvFile) {
            $table = pathinfo($csvFile, PATHINFO_FILENAME);

            if (! in_array($table, $this->importableTables)) {
                $result['errors'][] = "Table {$table} is not importable, skipping.";
                $this->warn("  Skipping table {$table} (not importable)");

                continue;
            }

            $this->line("  Importing {$table}...");

            if ($dryRun) {
                $fileLines = file($csvFile);
                $lineCount = $fileLines !== false ? count($fileLines) - 1 : 0; // Exclude header
                $this->line("    Would import {$lineCount} records");
                $result['imported'] += $lineCount;

                continue;
            }

            if ($truncate) {
                DB::connection('tenant')->table($table)->truncate();
                $this->line('    Truncated existing data');
            }

            $imported = 0;
            $skipped = 0;
            $handle = fopen($csvFile, 'r');

            if ($handle === false) {
                $result['errors'][] = "Could not open file: {$csvFile}";

                continue;
            }

            $headers = fgetcsv($handle);

            if ($headers === false) {
                $result['errors'][] = "Could not read headers from: {$csvFile}";
                fclose($handle);

                continue;
            }

            // Filter out any null values from headers
            $headers = array_map(fn ($h) => (string) $h, $headers);

            while (($row = fgetcsv($handle)) !== false) {
                try {
                    // Ensure row has same count as headers
                    if (count($row) !== count($headers)) {
                        $skipped++;

                        continue;
                    }

                    /** @var array<string, string|null> $record */
                    $record = array_combine($headers, $row);

                    $this->importRecord($table, $record);
                    $imported++;
                } catch (Exception $e) {
                    $skipped++;
                }
            }

            fclose($handle);
            $this->line("    Imported {$imported} records, skipped {$skipped}");
            $result['imported'] += $imported;
            $result['skipped'] += $skipped;
        }

        return $result;
    }

    /**
     * Import data from SQL file.
     *
     * @return array{imported: int, skipped: int, errors: array<string>}
     */
    protected function importFromSql(Tenant $tenant, string $filePath, bool $truncate, bool $dryRun): array
    {
        $result = ['imported' => 0, 'skipped' => 0, 'errors' => []];

        $content = File::get($filePath);
        $statements = array_filter(
            array_map('trim', explode(';', $content)),
            fn ($s) => ! empty($s) && ! str_starts_with($s, '--')
        );

        $this->info('Found ' . count($statements) . ' SQL statements');

        if ($dryRun) {
            $insertCount = count(array_filter($statements, fn ($s) => str_starts_with(strtoupper($s), 'INSERT')));
            $this->line("  Would execute {$insertCount} INSERT statements");
            $result['imported'] = $insertCount;

            return $result;
        }

        if ($truncate) {
            $this->line('  Truncating tables...');
            DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=0');

            foreach ($this->importableTables as $table) {
                if (DB::connection('tenant')->getSchemaBuilder()->hasTable($table)) {
                    DB::connection('tenant')->table($table)->truncate();
                }
            }

            DB::connection('tenant')->statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $progressBar = $this->output->createProgressBar(count($statements));

        foreach ($statements as $statement) {
            try {
                // Skip comments and SET statements for dry display
                if (str_starts_with($statement, '--') || str_starts_with(strtoupper($statement), 'SET')) {
                    $progressBar->advance();

                    continue;
                }

                DB::connection('tenant')->statement($statement);

                if (str_starts_with(strtoupper($statement), 'INSERT')) {
                    $result['imported']++;
                }
            } catch (Exception $e) {
                $result['skipped']++;
                $result['errors'][] = 'SQL error: ' . $e->getMessage();
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        return $result;
    }

    /**
     * Import a single record into a table.
     *
     * @param array<string, mixed> $record
     */
    protected function importRecord(string $table, array $record): void
    {
        // Convert null strings to actual null
        $record = array_map(function ($value) {
            if ($value === 'NULL' || $value === '') {
                return null;
            }

            return $value;
        }, $record);

        // Determine the key column for upsert
        $keyColumn = match ($table) {
            'accounts', 'transactions', 'transfers' => 'uuid',
            default => 'id',
        };

        if (isset($record[$keyColumn])) {
            // Check if record exists
            $exists = DB::connection('tenant')
                ->table($table)
                ->where($keyColumn, $record[$keyColumn])
                ->exists();

            if ($exists) {
                // Update existing record
                DB::connection('tenant')
                    ->table($table)
                    ->where($keyColumn, $record[$keyColumn])
                    ->update($record);
            } else {
                // Insert new record
                DB::connection('tenant')->table($table)->insert($record);
            }
        } else {
            // No key column, just insert
            DB::connection('tenant')->table($table)->insert($record);
        }
    }

    /**
     * Log the import operation.
     *
     * @param array{imported: int, skipped: int, errors: array<string>} $result
     */
    protected function logImport(Tenant $tenant, string $filePath, array $result): void
    {
        DB::table('tenant_data_imports')->insert([
            'tenant_id'      => $tenant->id,
            'source_file'    => $filePath,
            'imported_count' => $result['imported'],
            'skipped_count'  => $result['skipped'],
            'error_count'    => count($result['errors']),
            'errors'         => json_encode($result['errors']),
            'status'         => empty($result['errors']) ? 'completed' : 'completed_with_errors',
            'completed_at'   => Carbon::now(),
            'created_at'     => Carbon::now(),
            'updated_at'     => Carbon::now(),
        ]);
    }
}
