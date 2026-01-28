<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for transactions table.
 *
 * This migration runs in tenant database context, creating the transactions table
 * which stores all tenant-specific transaction records.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->uuid('account_uuid')->index();
            $table->string('type');
            $table->decimal('amount', 20, 8);
            $table->decimal('balance_before', 20, 8)->nullable();
            $table->decimal('balance_after', 20, 8)->nullable();
            $table->string('currency', 10)->default('USD');
            $table->string('status')->default('pending');
            $table->string('reference')->nullable()->index();
            $table->string('description')->nullable();

            // Fee tracking
            $table->decimal('fee_amount', 20, 8)->default(0);
            $table->string('fee_type')->nullable();

            // Counterparty info (for transfers)
            $table->string('counterparty_name')->nullable();
            $table->uuid('counterparty_account_uuid')->nullable()->index();

            // Value/Processing dates
            $table->date('value_date')->nullable()->index();
            $table->date('processing_date')->nullable();

            // Compliance
            $table->string('aml_status')->default('not_required');
            $table->timestamp('aml_screened_at')->nullable();
            $table->string('regulatory_reference')->nullable(); // For cross-border

            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->string('processed_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_uuid', 'type']);
            $table->index(['status', 'created_at']);
            $table->index(['type', 'processed_at']);
            $table->index(['value_date', 'status']);
        });

        Schema::create('transaction_snapshots', function (Blueprint $table) {
            $table->id();
            $table->uuid('aggregate_uuid')->index();
            $table->unsignedInteger('aggregate_version');
            $table->json('state');
            $table->timestamps();

            $table->unique(['aggregate_uuid', 'aggregate_version']);
        });

        Schema::create('transaction_projections', function (Blueprint $table) {
            $table->id();
            $table->uuid('transaction_uuid')->unique();
            $table->uuid('account_uuid')->index();
            $table->string('user_uuid')->index();
            $table->string('type');
            $table->decimal('amount', 20, 8);
            $table->string('currency', 10);
            $table->string('status');
            $table->string('reference')->nullable()->index();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['account_uuid', 'status']);
            $table->index(['user_uuid', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_projections');
        Schema::dropIfExists('transaction_snapshots');
        Schema::dropIfExists('transactions');
    }
};
