<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for transfers table.
 *
 * This migration runs in tenant database context, creating the transfers table
 * which stores all tenant-specific fund transfer records.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('from_account_uuid')->index();
            $table->uuid('to_account_uuid')->index();
            $table->decimal('amount', 20, 8);
            $table->string('currency', 10)->default('USD');
            $table->string('status')->default('pending');
            $table->string('type')->default('internal');
            $table->string('reference')->nullable()->index();
            $table->string('description')->nullable();
            $table->decimal('fee', 20, 8)->default(0);
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'created_at']);
            $table->index(['from_account_uuid', 'to_account_uuid']);
            $table->index(['type', 'status']);
        });

        Schema::create('transfer_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_snapshots');
        Schema::dropIfExists('transfers');
    }
};
