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
            // SECURITY: Use Laravel's encrypted cast in model for this field
            $table->text('access_data_encrypted')->nullable()->comment('Encrypted OAuth/API tokens');
            $table->timestamp('authorized_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->string('updated_by')->nullable();
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

            // SECURITY: Sensitive banking details - use encrypted cast in model
            $table->text('iban_encrypted')->nullable()->comment('Encrypted IBAN');
            $table->text('bic_swift_encrypted')->nullable()->comment('Encrypted BIC/SWIFT');
            $table->text('sort_code_encrypted')->nullable()->comment('Encrypted sort code');
            $table->text('routing_number_encrypted')->nullable()->comment('Encrypted routing number');

            // Masked versions for display (safe to show)
            $table->string('iban_masked')->nullable();
            $table->string('bic_swift')->nullable(); // BIC/SWIFT is not sensitive
            $table->string('sort_code_masked')->nullable();
            $table->string('routing_number_masked')->nullable();

            $table->string('status')->default('active');
            $table->boolean('is_primary')->default(false);
            $table->timestamp('last_synced_at')->nullable();

            // AML/Compliance
            $table->boolean('aml_verified')->default(false);
            $table->timestamp('aml_verified_at')->nullable();
            $table->string('verification_status')->default('pending');

            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('created_by')->nullable()->index();
            $table->string('verified_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'account_type']);
            $table->index(['currency', 'status']);
            $table->index(['verification_status', 'aml_verified']);
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
            $table->string('fee_type')->nullable();
            $table->decimal('exchange_rate', 20, 10)->nullable();
            $table->string('status')->default('pending');
            $table->string('reference')->nullable()->index();
            $table->string('provider_reference')->nullable()->index();
            $table->text('description')->nullable();
            $table->string('purpose')->nullable(); // Required by regulators

            // SECURITY: Sensitive beneficiary data - use encrypted cast in model
            $table->text('beneficiary_details_encrypted')->nullable()->comment('Encrypted beneficiary banking details');
            $table->string('beneficiary_name')->nullable(); // Safe to display

            // Value/Processing dates for settlement
            $table->date('value_date')->nullable()->index();
            $table->date('processing_date')->nullable();

            $table->timestamp('initiated_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('failure_reason')->nullable();

            // AML/Sanctions screening
            $table->string('aml_status')->default('pending');
            $table->timestamp('aml_screened_at')->nullable();
            $table->string('sanctions_status')->default('pending');
            $table->timestamp('sanctions_checked_at')->nullable();

            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('initiated_by')->nullable()->index();
            $table->string('authorized_by')->nullable();
            $table->timestamp('authorized_at')->nullable();
            $table->text('authorization_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_uuid', 'status']);
            $table->index(['type', 'status']);
            $table->index(['direction', 'created_at']);
            $table->index(['aml_status', 'sanctions_status']);
            $table->index(['source_bank_account_id', 'status']);
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

            // Reconciliation
            $table->string('reconciliation_status')->default('pending');
            $table->timestamp('reconciled_at')->nullable();
            $table->string('reconciled_by')->nullable();
            $table->decimal('discrepancy_amount', 20, 8)->nullable();
            $table->text('discrepancy_notes')->nullable();

            $table->json('metadata')->nullable();

            // Audit fields
            $table->string('imported_by')->nullable();
            $table->timestamps();

            $table->index(['bank_account_id', 'statement_date']);
            $table->index(['period_start', 'period_end']);
            $table->index(['reconciliation_status']);
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
