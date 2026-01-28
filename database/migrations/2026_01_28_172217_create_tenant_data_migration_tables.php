<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates tables for tracking tenant data migrations and imports.
 *
 * These tables are in the CENTRAL database and track the history
 * of data migrations between central and tenant databases.
 */
return new class () extends Migration {
    public function up(): void
    {
        // Track data migrations from central to tenant databases
        Schema::create('tenant_data_migrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->integer('migrated_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->json('tables_migrated')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });

        // Track data imports from backup files to tenant databases
        Schema::create('tenant_data_imports', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('source_file', 500);
            $table->string('format', 20)->default('json');
            $table->integer('imported_count')->default(0);
            $table->integer('skipped_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->json('tables_imported')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });

        // Track data exports for audit purposes
        Schema::create('tenant_data_exports', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('output_file', 500);
            $table->string('format', 20)->default('json');
            $table->integer('record_count')->default(0);
            $table->json('tables_exported')->nullable();
            $table->string('status', 50)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')
                ->references('id')
                ->on('tenants')
                ->onDelete('cascade');

            $table->index(['tenant_id', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_data_exports');
        Schema::dropIfExists('tenant_data_imports');
        Schema::dropIfExists('tenant_data_migrations');
    }
};
