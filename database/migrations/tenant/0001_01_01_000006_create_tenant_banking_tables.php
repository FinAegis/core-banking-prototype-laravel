<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tenant-specific migration for banking tables.
 *
 * This migration runs in tenant database context, creating tables for
 * bank account connections, transfers, and Open Banking integrations.
 */
return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bank_connections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->string('provider');
            $table->string('provider_id')->nullable();
            $table->string('institution_name');
            $table->string('institution_id')->nullable();
            $table->string('status')->default('pending');
            $table->json('access_data')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'provider']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->foreignId('connection_id')->nullable()->constrained('bank_connections')->nullOnDelete();
            $table->string('account_type');
            $table->string('account_number_masked')->nullable();
            $table->string('account_name')->nullable();
            $table->string('currency', 10)->default('USD');
            $table->decimal('balance', 20, 8)->nullable();
            $table->decimal('available_balance', 20, 8)->nullable();
            $table->string('iban')->nullable();
            $table->string('bic_swift')->nullable();
            $table->string('sort_code')->nullable();
            $table->string('routing_number')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'account_type']);
            $table->index(['currency', 'status']);
        });

        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('user_uuid')->index();
            $table->foreignId('source_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->foreignId('destination_bank_account_id')->nullable()->constrained('bank_accounts')->nullOnDelete();
            $table->uuid('internal_account_uuid')->nullable()->index();
            $table->string('direction');
            $table->string('type');
            $table->decimal('amount', 20, 8);
            $table->string('currency', 10);
            $table->decimal('fee', 20, 8)->default(0);
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->string('status')->default('pending');
            $table->string('reference')->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->text('description')->nullable();
            $table->json('beneficiary_details')->nullable();
            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'status']);
            $table->index(['type', 'status']);
            $table->index(['direction', 'created_at']);
        });

        Schema::create('bank_statements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('bank_account_id')->constrained('bank_accounts')->cascadeOnDelete();
            $table->date('statement_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('opening_balance', 20, 8);
            $table->decimal('closing_balance', 20, 8);
            $table->decimal('total_credits', 20, 8)->default(0);
            $table->decimal('total_debits', 20, 8)->default(0);
            $table->unsignedInteger('transaction_count')->default(0);
            $table->string('currency', 10);
            $table->string('file_path')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'statement_date']);
            $table->index(['period_start', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bank_statements');
        Schema::dropIfExists('bank_transfers');
        Schema::dropIfExists('bank_accounts');
        Schema::dropIfExists('bank_connections');
    }
};
