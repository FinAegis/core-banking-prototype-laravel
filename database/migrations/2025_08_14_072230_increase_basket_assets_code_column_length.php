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
            // SQLite doesn't support ALTER COLUMN directly
            // We need to recreate the tables with the new column sizes
            // This is safe for testing environments

            // For basket_assets table
            if (Schema::hasTable('basket_assets')) {
                Schema::table('basket_assets', function ($table) {
                    // SQLite will handle this automatically during testing
                    // The column will be created with the correct size from the original migration
                });
            }

            // Note: In SQLite, VARCHAR(n) is just a hint and doesn't enforce length
            // So we don't need to do anything special for testing
            return;
        }

        // For MariaDB/MySQL
        if (in_array($driver, ['mysql', 'mariadb'])) {
            // First, drop the foreign key constraints using raw SQL
            if (Schema::hasTable('basket_values')) {
                try {
                    DB::statement('ALTER TABLE basket_values DROP FOREIGN KEY basket_values_basket_asset_code_foreign');
                } catch (Exception $e) {
                    // Foreign key might be named differently or not exist
                    // Try alternative naming convention
                    try {
                        DB::statement('ALTER TABLE basket_values DROP FOREIGN KEY finaegis/basket_values_basket_asset_code_foreign');
                    } catch (Exception $e2) {
                        // Ignore if foreign key doesn't exist
                    }
                }
            }

            if (Schema::hasTable('basket_performances')) {
                try {
                    DB::statement('ALTER TABLE basket_performances DROP FOREIGN KEY basket_performances_basket_asset_code_foreign');
                } catch (Exception $e) {
                    // Foreign key might be named differently
                    try {
                        DB::statement('ALTER TABLE basket_performances DROP FOREIGN KEY finaegis/basket_performances_basket_asset_code_foreign');
                    } catch (Exception $e2) {
                        // Try with backticks
                        try {
                            DB::statement('ALTER TABLE basket_performances DROP FOREIGN KEY `finaegis/basket_performances_basket_asset_code_foreign`');
                        } catch (Exception $e3) {
                            // Ignore if foreign key doesn't exist
                        }
                    }
                }
            }

            // Now modify column sizes
            DB::statement('ALTER TABLE basket_assets MODIFY code VARCHAR(50) NOT NULL');

            if (Schema::hasTable('basket_values')) {
                DB::statement('ALTER TABLE basket_values MODIFY basket_asset_code VARCHAR(50)');
            }

            if (Schema::hasTable('basket_performances')) {
                DB::statement('ALTER TABLE basket_performances MODIFY basket_asset_code VARCHAR(50)');
            }

            // Re-add foreign key constraints
            if (Schema::hasTable('basket_values')) {
                DB::statement('ALTER TABLE basket_values ADD CONSTRAINT basket_values_basket_asset_code_foreign FOREIGN KEY (basket_asset_code) REFERENCES basket_assets(code) ON DELETE CASCADE');
            }

            if (Schema::hasTable('basket_performances')) {
                DB::statement('ALTER TABLE basket_performances ADD CONSTRAINT basket_performances_basket_asset_code_foreign FOREIGN KEY (basket_asset_code) REFERENCES basket_assets(code) ON DELETE CASCADE');
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        // For SQLite (used in testing)
        if ($driver === 'sqlite') {
            // SQLite doesn't need reversal as column sizes are not enforced
            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'])) {
            // Drop foreign key constraints
            if (Schema::hasTable('basket_values')) {
                try {
                    DB::statement('ALTER TABLE basket_values DROP FOREIGN KEY basket_values_basket_asset_code_foreign');
                } catch (Exception $e) {
                    // Ignore
                }
            }

            if (Schema::hasTable('basket_performances')) {
                try {
                    DB::statement('ALTER TABLE basket_performances DROP FOREIGN KEY basket_performances_basket_asset_code_foreign');
                } catch (Exception $e) {
                    // Ignore
                }
            }

            // Revert column sizes
            DB::statement('ALTER TABLE basket_assets MODIFY code VARCHAR(20) NOT NULL');

            if (Schema::hasTable('basket_values')) {
                DB::statement('ALTER TABLE basket_values MODIFY basket_asset_code VARCHAR(20)');
            }

            if (Schema::hasTable('basket_performances')) {
                DB::statement('ALTER TABLE basket_performances MODIFY basket_asset_code VARCHAR(10)');
            }

            // Re-add foreign key constraints
            if (Schema::hasTable('basket_values')) {
                DB::statement('ALTER TABLE basket_values ADD CONSTRAINT basket_values_basket_asset_code_foreign FOREIGN KEY (basket_asset_code) REFERENCES basket_assets(code) ON DELETE CASCADE');
            }

            if (Schema::hasTable('basket_performances')) {
                DB::statement('ALTER TABLE basket_performances ADD CONSTRAINT basket_performances_basket_asset_code_foreign FOREIGN KEY (basket_asset_code) REFERENCES basket_assets(code) ON DELETE CASCADE');
            }
        }
    }
};
