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
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use ZipArchive;

/**
 * Artisan command to export tenant data for backup or transfer.
 *
 * Usage:
 *   php artisan tenants:export-data --tenant=uuid                    # Export specific tenant
 *   php artisan tenants:export-data --tenant=uuid --tables=accounts  # Export specific tables
 *   php artisan tenants:export-data --tenant=uuid --format=sql       # Export as SQL
 *   php artisan tenants:export-data --tenant=uuid --format=json      # Export as JSON
 */
class ExportTenantDataCommand extends Command
{
    protected $signature = 'tenants:export-data
                            {--tenant= : Tenant UUID to export (required)}
                            {--tables= : Comma-separated list of tables to export}
                            {--format=json : Export format (json, csv, sql)}
                            {--output= : Output directory (default: storage/exports)}
                            {--compress : Compress the output file}';

    protected $description = 'Export tenant data for backup or transfer';

    /**
     * Tables available for export.
     *
     * @var array<string>
     */
    protected array $exportableTables = [
        'accounts',
        'account_balances',
        'transactions',
        'transfers',
        'ledger_entries',
    ];

    public function handle(): int
    {
        $tenantId = $this->option('tenant');

        if (! $tenantId) {
            $this->error('Tenant UUID is required. Use --tenant=uuid');

            return Command::FAILURE;
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            $this->error("Tenant not found: {$tenantId}");

            return Command::FAILURE;
        }

        $tables = $this->option('tables')
            ? explode(',', $this->option('tables'))
            : $this->exportableTables;

        /** @var string $format */
        $format = $this->option('format') ?? 'json';
        /** @var string $outputDir */
        $outputDir = $this->option('output') ?? storage_path('exports');
        $compress = $this->option('compress');

        // Ensure output directory exists
        if (! File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $this->info("Exporting data for tenant: {$tenant->name} ({$tenant->id})");
        $this->info("Format: {$format}");
        $this->info('Tables: ' . implode(', ', $tables));
        $this->newLine();

        try {
            // Initialize tenant context
            tenancy()->initialize($tenant);

            $exportFile = $this->exportData($tenant, $tables, $format, $outputDir);

            if ($compress) {
                $exportFile = $this->compressFile($exportFile);
            }

            tenancy()->end();

            $this->info('Export completed successfully!');
            $this->info("Output file: {$exportFile}");

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->error("Export failed: {$e->getMessage()}");
            Log::error('Tenant data export failed', [
                'tenant_id' => $tenant->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Export data in the specified format.
     *
     * @param array<string> $tables
     */
    protected function exportData(Tenant $tenant, array $tables, string $format, string $outputDir): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_His');
        $filename = "tenant_{$tenant->id}_{$timestamp}";

        return match ($format) {
            'json'  => $this->exportAsJson($tenant, $tables, $outputDir, $filename),
            'csv'   => $this->exportAsCsv($tenant, $tables, $outputDir, $filename),
            'sql'   => $this->exportAsSql($tenant, $tables, $outputDir, $filename),
            default => throw new RuntimeException("Unknown format: {$format}"),
        };
    }

    /**
     * Export data as JSON.
     *
     * @param array<string> $tables
     */
    protected function exportAsJson(Tenant $tenant, array $tables, string $outputDir, string $filename): string
    {
        $exportData = [
            'metadata' => [
                'tenant_id'   => $tenant->id,
                'tenant_name' => $tenant->name,
                'exported_at' => Carbon::now()->toIso8601String(),
                'tables'      => $tables,
            ],
            'data' => [],
        ];

        $progressBar = $this->output->createProgressBar(count($tables));

        foreach ($tables as $table) {
            $this->line("  Exporting {$table}...");

            if (! $this->tableExists($table)) {
                $this->warn("    Table {$table} does not exist, skipping.");
                $progressBar->advance();

                continue;
            }

            $records = DB::connection('tenant')->table($table)->get();
            $exportData['data'][$table] = $records->toArray();

            $this->line("    Exported {$records->count()} records");
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();

        $filePath = "{$outputDir}/{$filename}.json";
        $jsonContent = json_encode($exportData, JSON_PRETTY_PRINT);

        if ($jsonContent === false) {
            throw new RuntimeException('Failed to encode export data to JSON: ' . json_last_error_msg());
        }

        File::put($filePath, $jsonContent);

        return $filePath;
    }

    /**
     * Export data as CSV (one file per table).
     *
     * @param array<string> $tables
     */
    protected function exportAsCsv(Tenant $tenant, array $tables, string $outputDir, string $filename): string
    {
        $exportDir = "{$outputDir}/{$filename}";
        File::makeDirectory($exportDir, 0755, true);

        foreach ($tables as $table) {
            $this->line("  Exporting {$table}...");

            if (! $this->tableExists($table)) {
                $this->warn("    Table {$table} does not exist, skipping.");

                continue;
            }

            $records = DB::connection('tenant')->table($table)->get();

            if ($records->isEmpty()) {
                $this->line('    No records to export');

                continue;
            }

            $csvFile = "{$exportDir}/{$table}.csv";
            $handle = fopen($csvFile, 'w');

            if ($handle === false) {
                throw new RuntimeException("Could not open file: {$csvFile}");
            }

            // Write headers
            $firstRecord = (array) $records->first();
            fputcsv($handle, array_keys($firstRecord));

            // Write data
            foreach ($records as $record) {
                fputcsv($handle, (array) $record);
            }

            fclose($handle);
            $this->line("    Exported {$records->count()} records to {$table}.csv");
        }

        // Write metadata
        $metadataFile = "{$exportDir}/metadata.json";
        $metadataJson = json_encode([
            'tenant_id'   => $tenant->id,
            'tenant_name' => $tenant->name,
            'exported_at' => Carbon::now()->toIso8601String(),
            'tables'      => $tables,
        ], JSON_PRETTY_PRINT);

        if ($metadataJson === false) {
            throw new RuntimeException('Failed to encode metadata to JSON: ' . json_last_error_msg());
        }

        File::put($metadataFile, $metadataJson);

        return $exportDir;
    }

    /**
     * Export data as SQL statements.
     *
     * @param array<string> $tables
     */
    protected function exportAsSql(Tenant $tenant, array $tables, string $outputDir, string $filename): string
    {
        $filePath = "{$outputDir}/{$filename}.sql";
        $handle = fopen($filePath, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not open file: {$filePath}");
        }

        // Write header
        fwrite($handle, "-- Tenant Data Export\n");
        fwrite($handle, "-- Tenant ID: {$tenant->id}\n");
        fwrite($handle, "-- Tenant Name: {$tenant->name}\n");
        fwrite($handle, '-- Exported: ' . Carbon::now()->toIso8601String() . "\n");
        fwrite($handle, '-- Tables: ' . implode(', ', $tables) . "\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        foreach ($tables as $table) {
            $this->line("  Exporting {$table}...");

            if (! $this->tableExists($table)) {
                $this->warn("    Table {$table} does not exist, skipping.");

                continue;
            }

            $records = DB::connection('tenant')->table($table)->get();

            if ($records->isEmpty()) {
                $this->line('    No records to export');

                continue;
            }

            fwrite($handle, "-- Table: {$table}\n");
            fwrite($handle, "-- Records: {$records->count()}\n");

            foreach ($records as $record) {
                $data = (array) $record;
                $columns = implode('`, `', array_keys($data));
                $values = implode("', '", array_map(function ($value) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return addslashes((string) $value);
                }, array_values($data)));

                fwrite($handle, "INSERT INTO `{$table}` (`{$columns}`) VALUES ('{$values}');\n");
            }

            fwrite($handle, "\n");
            $this->line("    Exported {$records->count()} records");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($handle);

        return $filePath;
    }

    /**
     * Check if a table exists in the tenant database.
     */
    protected function tableExists(string $table): bool
    {
        return DB::connection('tenant')
            ->getSchemaBuilder()
            ->hasTable($table);
    }

    /**
     * Compress the export file.
     */
    protected function compressFile(string $filePath): string
    {
        $zipPath = $filePath . '.zip';

        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            throw new RuntimeException("Could not create zip file: {$zipPath}");
        }

        if (is_dir($filePath)) {
            $this->addDirectoryToZip($zip, $filePath, basename($filePath));
            $zip->close();
            File::deleteDirectory($filePath);
        } else {
            $zip->addFile($filePath, basename($filePath));
            $zip->close();
            File::delete($filePath);
        }

        return $zipPath;
    }

    /**
     * Add a directory recursively to a zip archive.
     */
    protected function addDirectoryToZip(ZipArchive $zip, string $dir, string $basePath): void
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (! $file->isDir()) {
                $filePath = $file->getRealPath();
                $relativePath = $basePath . '/' . substr($filePath, strlen($dir) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
}
