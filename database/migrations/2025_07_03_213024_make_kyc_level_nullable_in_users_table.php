<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE users MODIFY COLUMN kyc_level ENUM('none', 'basic', 'enhanced', 'full') DEFAULT NULL");
        } elseif ($driver === 'pgsql') {
            // First, remove the default constraint
            DB::statement("ALTER TABLE users ALTER COLUMN kyc_level DROP DEFAULT");
            
            // Add new value to the enum type
            DB::statement("ALTER TYPE users_kyc_level_enum ADD VALUE IF NOT EXISTS 'none'");
            
            // Make column nullable
            DB::statement("ALTER TABLE users ALTER COLUMN kyc_level DROP NOT NULL");
        } else {
            // For SQLite, we need to recreate the column
            // This is not ideal but necessary for testing
            
            // First drop the index if it exists
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropIndex(['kyc_level']);
                });
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            Schema::table('users', function (Blueprint $table) {
                // Drop the old column
                $table->dropColumn('kyc_level');
            });
            
            Schema::table('users', function (Blueprint $table) {
                // Add the new column with nullable and 'none' option
                $table->enum('kyc_level', ['none', 'basic', 'enhanced', 'full'])->nullable()->after('kyc_expires_at');
                $table->index('kyc_level');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement("ALTER TABLE users MODIFY COLUMN kyc_level ENUM('basic', 'enhanced', 'full') DEFAULT 'basic' NOT NULL");
        } elseif ($driver === 'pgsql') {
            // Set NOT NULL constraint back
            DB::statement("ALTER TABLE users ALTER COLUMN kyc_level SET NOT NULL");
            
            // Re-add the default
            DB::statement("ALTER TABLE users ALTER COLUMN kyc_level SET DEFAULT 'basic'");
        } else {
            // For SQLite
            try {
                Schema::table('users', function (Blueprint $table) {
                    $table->dropIndex(['kyc_level']);
                });
            } catch (\Exception $e) {
                // Index might not exist, ignore
            }
            
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('kyc_level');
            });
            
            Schema::table('users', function (Blueprint $table) {
                $table->enum('kyc_level', ['basic', 'enhanced', 'full'])->default('basic')->after('kyc_expires_at');
                $table->index('kyc_level');
            });
        }
    }
};