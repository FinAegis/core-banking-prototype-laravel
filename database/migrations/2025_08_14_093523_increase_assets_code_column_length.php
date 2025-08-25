<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        // For SQLite (used in testing)
        if ($driver === 'sqlite') {
            // SQLite doesn't enforce VARCHAR lengths, so we don't need to do anything
            // The tests will work fine with the existing column definitions
            return;
        }

        // For MariaDB/MySQL
        if (in_array($driver, ['mysql', 'mariadb'])) {
            // Get database name
            $db = DB::connection()->getDatabaseName();

            // Find all tables with foreign keys to assets.code
            $foreignKeys = DB::select("
                SELECT 
                    TABLE_NAME as table_name,
                    COLUMN_NAME as column_name,
                    CONSTRAINT_NAME as constraint_name
                FROM 
                    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                WHERE 
                    REFERENCED_TABLE_NAME = 'assets' 
                    AND REFERENCED_COLUMN_NAME = 'code'
                    AND TABLE_SCHEMA = ?
            ", [$db]);

            // Drop all foreign key constraints
            foreach ($foreignKeys as $fk) {
                try {
                    DB::statement("ALTER TABLE {$fk->table_name} DROP FOREIGN KEY {$fk->constraint_name}");
                } catch (Exception $e) {
                    // Ignore if foreign key doesn't exist or has different name
                }
            }

            // Check current column size and only modify if needed
            $result = DB::select("SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'assets' AND COLUMN_NAME = 'code' AND TABLE_SCHEMA = ?", [$db]);
            if (! empty($result) && $result[0]->CHARACTER_MAXIMUM_LENGTH < 50) {
                // Modify assets.code column
                DB::statement('ALTER TABLE assets MODIFY code VARCHAR(50) NOT NULL');
            }

            // Update all referencing columns
            foreach ($foreignKeys as $fk) {
                if (Schema::hasTable($fk->table_name)) {
                    // Check current column size
                    $result = DB::select('SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ? AND TABLE_SCHEMA = ?', [$fk->table_name, $fk->column_name, $db]);
                    if (! empty($result) && $result[0]->CHARACTER_MAXIMUM_LENGTH < 50) {
                        DB::statement("ALTER TABLE {$fk->table_name} MODIFY {$fk->column_name} VARCHAR(50)");
                    }
                }
            }

            // Also update any other columns that might not have foreign keys but should match
            $additionalTables = [
                ['table' => 'basket_components', 'column' => 'asset_code'],
                ['table' => 'exchange_orders', 'column' => 'base_asset_code'],
                ['table' => 'exchange_orders', 'column' => 'quote_asset_code'],
                ['table' => 'exchange_trades', 'column' => 'base_asset_code'],
                ['table' => 'exchange_trades', 'column' => 'quote_asset_code'],
                ['table' => 'exchange_rates', 'column' => 'from_asset_code'],
                ['table' => 'exchange_rates', 'column' => 'to_asset_code'],
            ];

            foreach ($additionalTables as $ref) {
                if (Schema::hasTable($ref['table'])) {
                    $result = DB::select('SELECT CHARACTER_MAXIMUM_LENGTH FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = ? AND COLUMN_NAME = ? AND TABLE_SCHEMA = ?', [$ref['table'], $ref['column'], $db]);
                    if (! empty($result) && $result[0]->CHARACTER_MAXIMUM_LENGTH < 50) {
                        DB::statement("ALTER TABLE {$ref['table']} MODIFY {$ref['column']} VARCHAR(50)");
                    }
                }
            }

            // Re-add foreign key constraints
            foreach ($foreignKeys as $fk) {
                if (Schema::hasTable($fk->table_name)) {
                    try {
                        // Determine cascade behavior based on table
                        $onDelete = in_array($fk->table_name, ['account_balances']) ? 'RESTRICT' : 'CASCADE';
                        DB::statement("ALTER TABLE {$fk->table_name} ADD CONSTRAINT {$fk->constraint_name} FOREIGN KEY ({$fk->column_name}) REFERENCES assets(code) ON DELETE {$onDelete}");
                    } catch (Exception $e) {
                        // Constraint might already exist
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite (used in testing)
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            // SQLite doesn't need reversal as column sizes are not enforced
            return;
        }

        // This migration is considered a fix and should not be reversed
        // The original column sizes were too small for practical use
    }
};
