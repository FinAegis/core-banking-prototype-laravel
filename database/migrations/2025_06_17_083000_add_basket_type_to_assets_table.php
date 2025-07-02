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
        // For SQLite, we need to recreate the table as it doesn't support ALTER COLUMN
        if (DB::getDriverName() === 'sqlite') {
            // First, create a temporary table with the new schema
            Schema::create('assets_temp', function (Blueprint $table) {
                $table->string('code', 10)->primary();
                $table->string('name', 100);
                $table->enum('type', ['fiat', 'crypto', 'commodity', 'custom', 'basket']);
                $table->unsignedTinyInteger('precision')->default(2);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_basket')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->index('type');
                $table->index('is_active');
                $table->index('is_basket');
            });
            
            // Copy data from old table to new table
            // Need to specify columns explicitly as the new table has additional columns
            DB::statement('INSERT INTO assets_temp (code, name, type, precision, is_active, metadata, created_at, updated_at) 
                          SELECT code, name, type, precision, is_active, metadata, created_at, updated_at FROM assets');
            
            // Drop the old table
            Schema::drop('assets');
            
            // Rename the temporary table
            Schema::rename('assets_temp', 'assets');
        } else {
            // For other databases that support ALTER COLUMN
            DB::statement("ALTER TABLE assets MODIFY COLUMN type ENUM('fiat', 'crypto', 'commodity', 'custom', 'basket')");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // For SQLite, we need to recreate the table
        if (DB::getDriverName() === 'sqlite') {
            // First, create a temporary table with the old schema
            Schema::create('assets_temp', function (Blueprint $table) {
                $table->string('code', 10)->primary();
                $table->string('name', 100);
                $table->enum('type', ['fiat', 'crypto', 'commodity', 'custom']);
                $table->unsignedTinyInteger('precision')->default(2);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_basket')->default(false);
                $table->json('metadata')->nullable();
                $table->timestamps();
                
                $table->index('type');
                $table->index('is_active');
                $table->index('is_basket');
            });
            
            // Copy data from old table to new table (excluding basket types)
            DB::statement("INSERT INTO assets_temp SELECT * FROM assets WHERE type != 'basket'");
            
            // Drop the old table
            Schema::drop('assets');
            
            // Rename the temporary table
            Schema::rename('assets_temp', 'assets');
        } else {
            // Delete any basket type assets first
            DB::table('assets')->where('type', 'basket')->delete();
            
            // For other databases that support ALTER COLUMN
            DB::statement("ALTER TABLE assets MODIFY COLUMN type ENUM('fiat', 'crypto', 'commodity', 'custom')");
        }
    }
};